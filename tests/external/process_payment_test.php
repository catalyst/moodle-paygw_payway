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

        process_payment::execute('enrol_fee', 'fee', 1, 'fake-token');
    }
}
