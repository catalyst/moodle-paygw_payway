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

/**
 * Lib functions
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Note - defined('MOODLE_INTERNAL') || die(); is not required for this file.

use paygw_payway\check\gateway;

/**
 * Return check API status checks
 * @return array
 */
function paygw_payway_status_checks(): array {
    global $DB;

    // There are likely to be only a handful of gateways on a given site,
    // so its ok to query them all at once here.
    $gateways = $DB->get_records('payment_gateways', ['gateway' => 'payway'], 'id', 'id, enabled, config');
    $checks = array_map(function ($gateway) {
        return new gateway($gateway->id, $gateway->enabled, $gateway->config);
    }, $gateways);

    return array_values(array_filter($checks));
}
