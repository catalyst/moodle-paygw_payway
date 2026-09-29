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
use invalid_parameter_exception;
use moodle_exception;

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
