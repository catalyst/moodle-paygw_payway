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

namespace paygw_payway\event;

use core\event\base;
use paygw_payway\local\payment;

/**
 * Event triggered when PayWay returns a valid transaction response.
 *
 * This reports the status returned by PayWay, not whether Moodle successfully
 * delivered the associated order.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payway_payment extends base {
    /**
     * Initialise event parameters.
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Create an event from a parsed PayWay transaction response.
     *
     * @param payment $payment PayWay response.
     * @param int $userid User who initiated the payment.
     * @param string $component Payment component.
     * @param string $paymentarea Payment area.
     * @param int $itemid Item being paid for.
     * @return self
     */
    public static function create_from_payment(
        payment $payment,
        int $userid,
        string $component,
        string $paymentarea,
        int $itemid,
    ): self {
        return self::create([
            'context' => \context_system::instance(),
            'userid' => $userid,
            'other' => [
                'status' => $payment->status->value,
                'transactionid' => $payment->transactionid,
                'receiptnumber' => $payment->receiptnumber,
                'component' => $component,
                'paymentarea' => $paymentarea,
                'itemid' => $itemid,
            ],
        ]);
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventpaywaypayment', 'paygw_payway');
    }

    /**
     * Returns a description suitable for the event log.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' received PayWay status '{$this->other['status']}' " .
            "for transaction '{$this->other['transactionid']}' (receipt '{$this->other['receiptnumber']}').";
    }
}
