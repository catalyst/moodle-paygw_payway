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

namespace paygw_payway;

use advanced_testcase;
use paygw_payway\local\payment_processing_error_email;

/**
 * Tests payment processing error notifications.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\local\payment_processing_error_email
 */
final class payment_processing_error_email_test extends advanced_testcase {
    /**
     * The notification includes the warning and payment context.
     */
    public function test_network_error_notification_contents(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'PayWay',
            'lastname' => 'Customer',
            'email' => 'customer@example.com',
        ]);
        $sink = $this->redirectEmails();
        $intro = get_string('email:networkerror:intro', 'paygw_payway');

        $this->assertTrue(payment_processing_error_email::send(
            'notifications@example.com',
            $intro,
            $user,
            'enrol_fee',
            'fee',
            42,
            ['HTTP status' => 0, 'cURL error' => 28, 'Idempotency key' => 'key'],
        ));

        $this->assertSame(1, $sink->count());
        $mail = $sink->get_messages()[0];
        $this->assertSame('notifications@example.com', $mail->to);
        $this->assertSame(get_string('email:paymentprocessingerror:subject', 'paygw_payway'), $mail->subject);
        $body = quoted_printable_decode($mail->body);
        $this->assertStringContainsString($intro, $body);
        $this->assertStringContainsString(fullname($user), $body);
        $this->assertStringContainsString('enrol_fee / fee / 42', $body);
        $this->assertStringContainsString('cURL error: 28', $body);
    }
}
