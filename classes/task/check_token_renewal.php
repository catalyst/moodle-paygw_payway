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

use core\task\manager;
use core\task\scheduled_task;

/**
 * Spawns adhoc tasks to check token renewal.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_token_renewal extends scheduled_task {
    /**
     * Task name
     * @return string
     */
    public function get_name() {
        return get_string('task:checktokenrenewal', 'paygw_payway');
    }

    /**
     * Execute
     */
    public function execute() {
        global $DB;

        // For each enabled payway paygw, spawn an adhoc task to check their corresponding token.
        $gateways = $DB->get_records('payment_gateways', ['gateway' => 'payway', 'enabled' => true], 'id');
        foreach ($gateways as $gateway) {
            $task = new check_token_renewal_adhoc();
            $task->set_custom_data(['gatewayid' => $gateway->id]);
            manager::queue_adhoc_task($task, checkforexisting: true);
            mtrace("Queued adhoc task to check token for gateway " . $gateway->id);
        }
    }
}
