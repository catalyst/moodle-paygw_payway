<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace paygw_payway\task;

use coding_exception;
use core\task\adhoc_task;
use core_payment\helper;
use Override;
use paygw_payway\local\api_key_response;
use paygw_payway\local\api_configuration;
use paygw_payway\local\payway_api;
use paygw_payway\gateway as payway_gateway;

/**
 * Checks and rolls PayWay secret API token if new one exists.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_token_renewal_adhoc extends adhoc_task {
    /**
     * Create a PayWay API client for the supplied credentials.
     *
     * Kept as an instance method so task tests can provide deterministic API responses.
     *
     * @param api_configuration $configuration
     * @return payway_api
     */
    protected function create_api(api_configuration $configuration): payway_api {
        return payway_api::new($configuration);
    }

    /**
     * Acquire the lock used to update gateway configuration.
     *
     * @param int $gatewayid
     * @return \core\lock\lock|false
     */
    protected function acquire_configuration_lock(int $gatewayid): \core\lock\lock|false {
        return payway_gateway::get_configuration_lock($gatewayid, timeout: 60, lifetime: 60);
    }

    /**
     * If this task should retry until success.
     * @return bool
     */
    public function retry_until_success(): bool {
        // Because we queue this daily w/ the scheduled task, we don't want to retry on failures at the adhoc task level.
        // Doing so would open a pandoras box of edge cases w.r.t. retries, attemptsavailable, etc.
        return false;
    }

    /**
     * Execute
     */
    public function execute() {
        global $DB;
        $gatewayid = $this->get_custom_data()->gatewayid ?? null;
        if (empty($gatewayid)) {
            mtrace("No gatewayid in customdata. Ignoring.");
            return;
        }

        // Hold the configuration lock for the entire operation so the stored config cannot be changed
        // between reading the current key, checking the replacement, and saving it.
        $lock = $this->acquire_configuration_lock($gatewayid);
        if (!$lock) {
            mtrace("Gateway configuration is locked. Ignoring.");
            return;
        }

        try {
            $gateway = $DB->get_record('payment_gateways', ['id' => $gatewayid], 'id,enabled,config', IGNORE_MISSING);

            // Gateway may have been deleted, just exit.
            if (empty($gateway)) {
                mtrace("Gateway does not exist, may have been deleted. Ignoring.");
                return;
            }
            // May have been disabled.
            if (!$gateway->enabled) {
                mtrace("Gateway not enabled. Ignoring");
                return;
            }

            // Implements https://www.payway.com.au/docs/rest.html#automate-secret-api-key-renewal .
            $storedconfig = json_decode($gateway->config, flags: JSON_THROW_ON_ERROR);
            $configuration = api_configuration::from_stored_config($storedconfig);
            $api = $this->create_api($configuration);
            $latestsecretkeyres = $api->get_latest_api_key();

            // Network error!
            if ($latestsecretkeyres->curlerrno != 0 || !$latestsecretkeyres->is_success()) {
                $errormessage = 'Could not check for a new token from PayWay. ' .
                    "HTTP status: {$latestsecretkeyres->httpcode}; cURL error code: {$latestsecretkeyres->curlerrno}.";
                $responsebody = json_decode($latestsecretkeyres->body);
                if (isset($responsebody->message) && is_string($responsebody->message)) {
                    // Do not risk exposing the configured credential if PayWay echoes it in an error message.
                    $message = str_replace($configuration->secretkey, '[redacted]', $responsebody->message);
                    $errormessage .= ' PayWay message: ' . $message;
                }
                throw new coding_exception($errormessage);
            }

            $keyresult = api_key_response::try_parse($latestsecretkeyres->body);
            if ($keyresult->is_err()) {
                // Do not include the response body in this exception; it contains a secret API key.
                throw new coding_exception($keyresult->error);
            }
            $latestsecretkey = $keyresult->unwrap()->key;

            // If key matches, its currently valid, so no-op.
            if ($latestsecretkey === $configuration->secretkey) {
                mtrace("Stored API key is same as returned from PayWay API, nothing to do.");
                return;
            }

            // We've got the new key now, lets give it a quick test as a sanity check.
            $newconfig = new api_configuration(
                publishablekey: $configuration->publishablekey,
                secretkey: $latestsecretkey,
                environment: $configuration->environment,
                merchantid: $configuration->merchantid,
                notificationemail: null, // Purposely skip email for this test.
                customfields: $configuration->customfields,
            );
            $newapi = $this->create_api($newconfig);
            $keyvalidation = $newapi->test_is_secret_key_valid();
            if ($keyvalidation->is_err()) {
                throw new coding_exception(
                    'Received new secret key from PayWay API but it failed testing: ' . $keyvalidation->error
                );
            }

            // The config cannot have changed while the lock has been held, so update the copy read above.
            $storedconfig->secretkey = $latestsecretkey;
            helper::save_payment_gateway((object) [
                'id' => $gateway->id,
                'config' => json_encode($storedconfig, JSON_THROW_ON_ERROR),
            ]);

            // Else good (assume key is valid so just unwrap to get key name).
            mtrace("Secret key refreshed successfully - new key "
                . api_configuration::parse_and_validate_key_name(
                    $newconfig->secretkey,
                    api_configuration::KEY_TYPE_SECRET
                )->unwrap());
        } finally {
            $lock->release();
        }
    }
}
