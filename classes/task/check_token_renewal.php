<?php

namespace paygw_payway\task;

use core\task\manager;
use core\task\scheduled_task;

class check_token_renewal extends scheduled_task {
    public function get_name() {
        return get_string('task:checktokenrenewal', 'paygw_payway');
    }

    public function execute() {
        global $DB;

        // For each paygw, spawn an adhoc task to check their corresponding token.
        $gateways = $DB->get_records('payment_gateways', ['gateway' => 'payway'], 'id');
        foreach ($gateways as $gateway) {
            $task = new check_token_renewal_adhoc();
            $task->set_custom_data(['gatewayid' => $gateway->id]);
            manager::queue_adhoc_task($task, checkforexisting: true);
        }
    }
}
