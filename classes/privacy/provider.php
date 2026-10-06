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

namespace paygw_payway\privacy;

use core_payment\privacy\paygw_provider;

/**
 * Privacy Provider
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\metadata\null_provider,
    paygw_provider {
    /**
     * Explain why the gateway has no locally stored user data to export or delete.
     *
     * External transfers are still described by get_metadata(). Core payment
     * handles the payment records, while this gateway stores no additional data.
     *
     * @return string Language string identifier.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }

    /**
     * Describe personal data sent to the payment provider.
     *
     * @param \core_privacy\local\metadata\collection $collection Metadata collection.
     * @return \core_privacy\local\metadata\collection
     */
    public static function get_metadata(
        \core_privacy\local\metadata\collection $collection
    ): \core_privacy\local\metadata\collection {
        $collection->add_external_location_link('payway', [
            'userid' => 'privacy:metadata:payway:userid',
            'ipaddress' => 'privacy:metadata:payway:ipaddress',
            'customfields' => 'privacy:metadata:payway:customfields',
        ], 'privacy:metadata:payway');
        return $collection;
    }

    /**
     * Export user data stored by this payment gateway for a payment.
     *
     * PayWay does not store any gateway-specific payment data.
     *
     * @param \context $context Context
     * @param array $subcontext The location within the current context that the payment data belongs
     * @param \stdClass $payment The payment record
     */
    public static function export_payment_data(\context $context, array $subcontext, \stdClass $payment): void {
    }

    /**
     * Delete user data stored by this payment gateway for the given payments.
     *
     * PayWay does not store any gateway-specific payment data.
     *
     * @param string $paymentsql SQL query that selects payment.id field for the payments
     * @param array $paymentparams Array of parameters for $paymentsql
     */
    public static function delete_data_for_payment_sql(string $paymentsql, array $paymentparams): void {
    }
}
