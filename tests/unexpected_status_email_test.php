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
use paygw_payway\local\payment;
use paygw_payway\local\status;
use paygw_payway\local\unexpected_status_email;

/**
 * Tests notification emails for unexpected PayWay payment statuses.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\local\unexpected_status_email
 */
final class unexpected_status_email_test extends advanced_testcase {
    /**
     * The notification reaches the configured address with the payment and delivery details.
     *
     * @dataProvider notifiable_status_provider
     * @param status $status Payment status
     * @param bool $delivered Whether the item was delivered
     */
    public function test_notification_contents(status $status, bool $delivered): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'PayWay',
            'lastname' => 'Customer',
            'email' => 'customer@example.com',
        ]);
        $payment = new payment(123456, 987654, $status, '08', 'Manual review needed');
        $sink = $this->redirectEmails();

        $this->assertTrue(unexpected_status_email::send(
            'notifications@example.com',
            $payment,
            $user,
            'enrol_fee',
            'fee',
            42,
        ));

        $this->assertSame(1, $sink->count());
        $mail = $sink->get_messages()[0];
        $this->assertSame('notifications@example.com', $mail->to);
        $this->assertSame(\core_user::get_noreply_user()->email, $mail->from);
        $this->assertSame(get_string('email:unexpectedstatus:subject', 'paygw_payway', 123456), $mail->subject);

        $body = quoted_printable_decode($mail->body);
        $this->assertStringContainsString(get_string('email:unexpectedstatus:intro', 'paygw_payway'), $body);
        $this->assertStringContainsString('123456', $body);
        $this->assertStringContainsString('987654', $body);
        $this->assertStringContainsString($status->value, $body);
        $this->assertStringContainsString('08 - Manual review needed', $body);
        $this->assertStringContainsString(fullname($user), $body);
        $this->assertStringContainsString('customer@example.com', $body);
        $this->assertStringContainsString('id ' . $user->id, $body);
        $this->assertStringContainsString('enrol_fee / fee / 42', $body);
        $this->assertStringContainsString(
            get_string('email:delivered', 'paygw_payway') . ': ' . get_string($delivered ? 'yes' : 'no'),
            strip_tags($body),
        );
    }

    /**
     * Provide statuses that require notification, with their expected delivery outcome.
     *
     * @return array<string, array{status, bool}>
     */
    public static function notifiable_status_provider(): array {
        return [
            'approved with warning' => [status::ApprovedAsterisk, true],
            'suspended' => [status::Suspended, false],
        ];
    }
}
