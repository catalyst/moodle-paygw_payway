<?php

namespace paygw_payway\task;

use core\task\adhoc_task;

class check_token_renewal_adhoc extends adhoc_task {
    public function execute() {
        global $DB;
        $gatewayid = $this->get_custom_data()['gatewayid'] ?? null;
        $gateway = $DB->get_record('payment_gateways', ['id' => $gatewayid], '*', IGNORE_MISSING);

        // Gateway may have been deleted, just exit.
        if (empty($gateway)) {
            return;
        }

        // Implements https://www.payway.com.au/docs/rest.html#automate-secret-api-key-renewal
        
        // TODO implement per

        
        throw new \Exception('Not implemented');
    }
}
