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

namespace paygw_payway\external;

use advanced_testcase;
use core_payment\helper as payment_helper;
use invalid_parameter_exception;
use moodle_exception;
use paygw_payway\event\payway_payment as payway_payment_event;
use paygw_payway\local\status;

/**
 * Tests for the PayWay payment processing web service.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\external\process_payment
 */
final class process_payment_test extends advanced_testcase {
    /**
     * A parsed PayWay response triggers one event carrying the returned status and payment context.
     *
     * @dataProvider payway_payment_event_provider
     * @param status $status PayWay transaction status.
     * @param bool $accepted Whether Moodle should deliver the order for this status.
     */
    public function test_payway_payment_event(status $status, bool $accepted): void {
        global $DB;

        $this->resetAfterTest();
        \core\plugininfo\paygw::enable_plugin('payway', 1);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $account = payment_helper::save_payment_account((object) [
            'name' => 'PayWay event test account',
            'idnumber' => '',
        ]);
        payment_helper::save_payment_gateway((object) [
            'accountid' => $account->get('id'),
            'gateway' => 'payway',
            'enabled' => 1,
            'config' => json_encode([
                'publishablekey' => 'APPLICATION_PUB_test',
                'secretkey' => 'APPLICATION_SEC_test',
                'environment' => 'sandbox',
                'merchantid' => 'TEST',
                'notificationemail' => '',
            ], JSON_THROW_ON_ERROR),
        ]);

        $course = $this->getDataGenerator()->create_course();
        $feeplugin = enrol_get_plugin('fee');
        $enrolid = $feeplugin->add_instance($course, [
            'courseid' => $course->id,
            'customint1' => $account->get('id'),
            'cost' => 10,
            'currency' => 'AUD',
            'roleid' => $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST),
        ]);

        \curl::mock_response(json_encode([
            'transactionId' => 123456,
            'receiptNumber' => 987654,
            'status' => $status->value,
            'responseCode' => '08',
            'responseText' => 'Test response',
        ], JSON_THROW_ON_ERROR));

        $sink = $this->redirectEvents();
        $result = process_payment::execute(
            'enrol_fee',
            'fee',
            $enrolid,
            '2bcec36f-7b02-43db-b3ec-bfb65acfe272',
            '2bcec36f-7b02-43db-b3ec-bfb65acfe273',
        );
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($event): bool => $event instanceof payway_payment_event,
        ));
        $sink->close();

        $this->assertSame($accepted ? 'ok' : 'error', $result['status']);
        $this->assertCount(1, $events);
        $event = reset($events);
        // The event sink reads these IDs back from the database as strings.
        $this->assertEquals($user->id, $event->userid);
        $this->assertEquals(\context_system::instance()->id, $event->contextid);
        $this->assertSame($status->value, $event->other['status']);
        $this->assertSame(123456, $event->other['transactionid']);
        $this->assertSame(987654, $event->other['receiptnumber']);
        $this->assertSame('enrol_fee', $event->other['component']);
        $this->assertSame('fee', $event->other['paymentarea']);
        $this->assertSame($enrolid, $event->other['itemid']);
    }

    /**
     * Provide provider statuses covering successful, pending, declined, and unusual responses.
     *
     * @return array<string, array{status, bool}>
     */
    public static function payway_payment_event_provider(): array {
        return [
            'approved' => [status::Approved, true],
            'approved with warning' => [status::ApprovedAsterisk, true],
            'pending' => [status::Pending, true],
            'declined' => [status::Declined, false],
            'voided' => [status::Voided, false],
            'suspended' => [status::Suspended, false],
        ];
    }

    /**
     * Guests must not be able to submit a payment or receive an order.
     */
    public function test_guest_cannot_process_payment(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('guestsarenotallowed', 'error'));

        process_payment::execute('enrol_fee', 'fee', 1, 'fake-token', 'fake-key');
    }

    /**
     * Reject invalid payment identifiers before contacting PayWay.
     *
     * @dataProvider invalid_payment_identifiers_provider
     * @param string $token PayWay single-use token
     * @param string $idempotencykey Idempotency key
     */
    public function test_invalid_payment_identifiers_are_rejected(string $token, string $idempotencykey): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(invalid_parameter_exception::class);
        process_payment::execute('enrol_fee', 'fee', 1, $token, $idempotencykey);
    }

    /**
     * Provide invalid token and idempotency key combinations.
     *
     * @return array<string, array{string, string}>
     */
    public static function invalid_payment_identifiers_provider(): array {
        $valid = '2bcec36f-7b02-43db-b3ec-bfb65acfe272';
        return [
            'empty token' => ['', $valid],
            'malformed token' => ['not-a-uuid', $valid],
            'empty key' => [$valid, ''],
            'malformed key' => [$valid, 'not-a-uuid'],
            'header injection' => [$valid, $valid . "\r\nX-Injected: true"],
            'trailing newline' => [$valid, $valid . "\n"],
            'html token' => ['<script>alert(1)</script>', $valid],
        ];
    }
}
