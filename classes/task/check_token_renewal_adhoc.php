<?php

namespace paygw_payway\task;

use coding_exception;
use core\task\adhoc_task;
use core_payment\helper;
use paygw_payway\local\api_configuration;
use paygw_payway\local\payway_api;
use paygw_payway\gateway as payway_gateway;

class check_token_renewal_adhoc extends adhoc_task {
    public function execute() {
        global $DB;
        $gatewayid = $this->get_custom_data()->gatewayid ?? null;
        if (empty($gatewayid)) {
            mtrace("No gatewayid in customdata. Ignoring.");
            return;
        }

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

        // Implements https://www.payway.com.au/docs/rest.html#automate-secret-api-key-renewal
        $storedconfig = json_decode($gateway->config, flags: JSON_THROW_ON_ERROR);
        $configuration = api_configuration::from_stored_config($storedconfig);
        $api = payway_api::new($configuration);
        $latestsecretkeyres = $api->get_latest_api_key();

        // Network error!
        if ($latestsecretkeyres->curlerrno != 0 || !$latestsecretkeyres->is_success()) {
            // TODO better way to handle?
            throw new coding_exception("Could not check for new token");
        }

        // There is always a single key here.
        $latestsecretkey = json_decode($latestsecretkeyres->body, flags: JSON_THROW_ON_ERROR)->key ?? null;

        if (empty($latestsecretkey)) {
            throw new coding_exception("PayWay key response returned no key");
        }

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
        );
        $newapi = payway_api::new($newconfig);
        if (!$newapi->test_secret_key()) { // TODO this is incorrect it returns a HTTP code. but we will likely refactor into a result<>
            throw new coding_exception("Received new secret key from PayWay API but it failed testing");
        }

        // Take out a lock to edit the config, just for the slim chance someone is editing this at the exact same time
        // from the UI, we dont want to cause a race condition when submitting.
        $lock = payway_gateway::get_configuration_lock($gatewayid, timeout: 60);
        if (!$lock) {
            mtrace("Gateway configuration is locked. Ignoring.");
            return;
        }

        try {
            // TODO maybe take out lock earlier to avoid re-querying...
            $currentgateway = $DB->get_record('payment_gateways', ['id' => $gatewayid], 'id,enabled,config', IGNORE_MISSING);
            if (empty($currentgateway) || !$currentgateway->enabled) {
                mtrace("Gateway was deleted or disabled while renewing. Ignoring.");
                return;
            }

            $currentconfig = json_decode($currentgateway->config, flags: JSON_THROW_ON_ERROR);
            if ($currentconfig->secretkey !== $configuration->secretkey) {
                mtrace("Gateway secret key changed while renewing. Ignoring.");
                return;
            }

            // Use the latest configuration so unrelated frontend edits are preserved.
            $currentconfig->secretkey = $latestsecretkey;
            helper::save_payment_gateway((object) [
                'id' => $currentgateway->id,
                'config' => json_encode($currentconfig, JSON_THROW_ON_ERROR),
            ]);

            // Else good!
            mtrace("Secret key refreshed successfully");
        } finally {
            $lock->release();
        }
    }
}
