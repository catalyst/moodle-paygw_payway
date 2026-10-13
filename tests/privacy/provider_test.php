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

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;

/**
 * Tests for the PayWay privacy provider metadata.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\privacy\provider
 */
final class provider_test extends \advanced_testcase {
    /**
     * Notification email transfers are declared with the personal data they contain.
     */
    public function test_notification_email_metadata(): void {
        $collection = new collection('paygw_payway');
        $collection = provider::get_metadata($collection);
        $locations = $collection->get_collection();

        $this->assertCount(2, $locations);
        $notificationlocation = $locations[1];
        $this->assertSame('notificationemail', $notificationlocation->get_name());
        $this->assertSame('privacy:metadata:notificationemail', $notificationlocation->get_summary());
        $this->assertEqualsCanonicalizing(
            ['userdetails', 'paymentdetails', 'itemdetails'],
            array_keys($notificationlocation->get_privacy_fields()),
        );
    }
}