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

namespace paygw_payway\local;

/**
 * Sends notifications when a payment outcome cannot be completed or verified.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class payment_processing_error_email {
    /**
     * Send a payment processing error notification.
     *
     * @param string $notificationemail
     * @param string $intro
     * @param \stdClass $user
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param array $details
     * @return bool
     */
    public static function send(
        string $notificationemail,
        string $intro,
        \stdClass $user,
        string $component,
        string $paymentarea,
        int $itemid,
        array $details,
    ): bool {
        $context = [
            'intro' => $intro,
            'user' => fullname($user),
            'useremail' => $user->email,
            'userid' => (int) $user->id,
            'item' => "$component / $paymentarea / $itemid",
            'details' => array_map(
                fn(string $label, mixed $value): array => [
                    'label' => $label,
                    'value' => is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR),
                ],
                array_keys($details),
                array_values($details),
            ),
        ];
        global $OUTPUT;
        $messagehtml = $OUTPUT->render_from_template('paygw_payway/payment_processing_error', $context);
        $messagetext = html_to_text($messagehtml);
        $subject = get_string('email:paymentprocessingerror:subject', 'paygw_payway');

        $recipient = \core_user::get_noreply_user();
        $recipient->email = $notificationemail;
        $recipient->mailformat = FORMAT_HTML;

        return email_to_user($recipient, \core_user::get_noreply_user(), $subject, $messagetext, $messagehtml);
    }
}
