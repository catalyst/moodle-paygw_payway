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

declare(strict_types=1);

namespace paygw_payway\local;

/**
 * Email sent to the gateway notification address when an unexpected payment status is encountered.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unexpected_status_email {
    /**
     * Sends the email, if a valid notification address is configured.
     *
     * @param string $notificationemail
     * @param payment $payment
     * @param \stdClass $user the user who made the payment
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return bool true if an email was sent
     */
    public static function send(
        string $notificationemail,
        payment $payment,
        \stdClass $user,
        string $component,
        string $paymentarea,
        int $itemid,
    ): bool {
        global $OUTPUT;

        $context = [
            'transactionid' => $payment->transactionid,
            'receiptnumber' => $payment->receiptnumber,
            'status' => $payment->status->value,
            'responsecode' => $payment->responsecode,
            'responsetext' => $payment->responsetext,
            'userid' => (int) $user->id,
            'userfullname' => fullname($user),
            'useremail' => $user->email,
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'delivered' => $payment->is_accepted(),
        ];

        $messagehtml = $OUTPUT->render_from_template('paygw_payway/email_unexpected_status', $context);
        $messagetext = html_to_text($messagehtml);
        $subject = get_string('email:unexpectedstatus:subject', 'paygw_payway', $payment->transactionid);

        // Notification address is not a Moodle user, so build a pseudo recipient.
        $recipient = \core_user::get_noreply_user();
        $recipient->email = $notificationemail;
        $recipient->mailformat = FORMAT_HTML;

        return email_to_user($recipient, \core_user::get_noreply_user(), $subject, $messagetext, $messagehtml);
    }
}
