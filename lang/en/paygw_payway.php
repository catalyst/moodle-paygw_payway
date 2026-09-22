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
 * Language strings
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['checksecret_key'] = 'Secret key check';
$string['connectiontest'] = 'HTTP {$a->status} response returned from PayWay API using secret key {$a->keyname}';
$string['connectiontestcannotparse'] = 'Unexpected key format ';
$string['connectiontestunknown'] = 'Unknown error during connection test: {$a}';
$string['environment'] = 'Environment';
$string['environment:live'] = 'Live';
$string['environment:sandbox'] = 'Sandbox';
$string['environment_help'] = 'Sandbox credentials can be used in the Sandbox environment for testing purposes.';
$string['error:environment'] = 'Environment value was not valid';
$string['error:invalidkey'] = 'Invalid key format: {$a}';
$string['managegateway'] = 'Edit gateway settings';
$string['pluginname'] = 'PayWay';
$string['privacy:metadata'] = 'No user data is stored';
$string['publishablekey'] = 'Publishable API key';
$string['publishablekey_help'] = 'Your publishable API key is used by payway.js to send credit card details directly from the browser to PayWay. It is usually in the form TXXXXX_PUB_xxxx';
$string['publishablekeystatuscheck'] = 'Note - There is currently no way to validate the publishable key.';
$string['secretkey'] = 'Secret API key';
$string['secretkey_help'] = 'Your secret API key allows your server to process payments and provides full access to the API. It is usually in the form TXXXXX_SEC_xxxx';
$string['secretkeystatuscheck'] = '<a href="/report/status/index.php?detail=paygw_payway_secret_key">Check secret key validation.</a>';
