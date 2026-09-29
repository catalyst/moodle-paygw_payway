<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace paygw_payway\check;

use action_link;
use core\check\check;
use core\check\result;
use invalid_parameter_exception;
use moodle_url;
use paygw_payway\local\api_configuration;
use paygw_payway\local\payway_api;
use Throwable;

/**
 * Gateway config + Secret key api token check.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends check {
    /**
     * Create check
     * @param int $gatewayid payment_gateway db record id
     * @param bool $gatewayenabled if the gateway is enabled
     * @param string $config the stored configuration for this gateway
     */
    public function __construct(
        /** @var int $gatewayid payment_gateway db record id */
        protected readonly int $gatewayid,
        /** @var bool $gatewayenabled if the gateway is enabled */
        protected readonly bool $gatewayenabled,
        /** @var array $config the stored configuration for this gateway */
        protected readonly string $config
    ) {
    }

    /**
     * Action link
     * @return action_link|null
     */
    public function get_action_link(): ?action_link {
        return new action_link(
            new moodle_url('/payment/manage_gateway.php', ['id' => $this->gatewayid]),
            get_string('managegateway', 'paygw_payway')
        );
    }

    /**
     * Get check result
     * @return result
     */
    public function get_result(): result {
        try {
            $credentialdata = json_decode($this->config, flags: JSON_THROW_ON_ERROR);

            // First parse/validate credentials.
            $credentialresult = api_configuration::validate_and_parse_stored_config($credentialdata);
            if ($credentialresult->is_err()) {
                $statuscode = $this->gatewayenabled ? result::ERROR : result::WARNING;
                $message = $credentialresult->error;
                return new result($statuscode, $message);
            } else {
                // Now test via API.
                $credential = $credentialresult->unwrap();
                $api = payway_api::new($credential);
                $status = $api->test_secret_key();
                $keyname = api_configuration::parse_and_validate_key_name(
                    $credential->secretkey,
                    api_configuration::KEY_TYPE_SECRET
                )->unwrap();

                // Ok if 200, else warning if not enabled or error if is enabled.
                $statuscode = $status == 200 ? result::OK : ($this->gatewayenabled ? result::ERROR : result::WARNING);
                $message = get_string('connectiontest', 'paygw_payway', ['status' => $status, 'keyname' => $keyname]);
                return new result($statuscode, $message);
            }
        } catch (Throwable $e) {
            // Catch-all to not blow up check api status page, in case of any exception.
            $statuscode = result::UNKNOWN;
            $message = get_string('connectiontestunknown', 'paygw_payway', $e->getMessage());
            return new result($statuscode, $message);
        }
    }
}
