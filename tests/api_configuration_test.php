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
use coding_exception;
use paygw_payway\local\api_configuration;
use paygw_payway\local\environment;

/**
 * api credential class test
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\local\api_configuration
 */
final class api_configuration_test extends advanced_testcase {
    /**
     * Test that a valid key string is normalised to the expected display format.
     *
     * @return void
     */
    public function test_parse_and_validate_key_name_accepts_valid_key(): void {
        $result = api_configuration::parse_and_validate_key_name('APPLICATION_SEC_xyzabc', api_configuration::KEY_TYPE_SECRET);

        $this->assertTrue($result->is_ok());
        $this->assertSame('APPLICATION_SEC...abc', $result->unwrap());
    }

    /**
     * Test that malformed key names with too few sections are rejected.
     *
     * @return void
     */
    public function test_parse_and_validate_key_name_rejects_missing_sections(): void {
        $result = api_configuration::parse_and_validate_key_name('APPLICATION_SEC', api_configuration::KEY_TYPE_SECRET);

        $this->assertTrue($result->is_err());
        $this->assertSame('Unexpected key format, expected 3 parts separated by underscores', $result->error);
    }

    /**
     * Test that key names with a short random suffix are rejected.
     *
     * @return void
     */
    public function test_parse_and_validate_key_name_rejects_short_suffix(): void {
        $result = api_configuration::parse_and_validate_key_name('APPLICATION_SEC_abc', api_configuration::KEY_TYPE_SECRET);

        $this->assertTrue($result->is_err());
        $this->assertSame('Unexpected length of random part of key, expected > 3 chars', $result->error);
    }

    /**
     * Test that a key not matching the expected type segment is rejected.
     *
     * @return void
     */
    public function test_parse_and_validate_key_name_rejects_mismatched_type(): void {
        $result = api_configuration::parse_and_validate_key_name('APPLICATION_PUB_xyzabc', api_configuration::KEY_TYPE_SECRET);

        $this->assertTrue($result->is_err());
        $this->assertSame('Unexpected key type, expected SEC but got PUB', $result->error);
    }

    /**
     * Test that a key matching the expected type segment is accepted.
     *
     * @return void
     */
    public function test_parse_and_validate_key_name_accepts_matching_type(): void {
        $result = api_configuration::parse_and_validate_key_name('APPLICATION_SEC_xyzabc', api_configuration::KEY_TYPE_SECRET);

        $this->assertTrue($result->is_ok());
        $this->assertSame('APPLICATION_SEC...abc', $result->unwrap());
    }

    /**
     * Test that JSON configuration data is converted into an api_configuration object correctly.
     *
     * @return void
     */
    public function test_from_stored_config_builds_credential_from_json(): void {
        $credential = api_configuration::from_stored_config((object) [
            'publishablekey' => 'APPLICATION_PUB_abcdefg',
            'secretkey' => 'APPLICATION_SEC_uvwxyz',
            'environment' => environment::Sandbox->value,
            'merchantid' => 'TEST',
        ]);

        $this->assertSame('APPLICATION_PUB_abcdefg', $credential->publishablekey);
        $this->assertSame('APPLICATION_SEC_uvwxyz', $credential->secretkey);
        $this->assertSame(environment::Sandbox, $credential->environment);
        $this->assertSame('TEST', $credential->merchantid);
        $this->assertSame([], $credential->customfields->mappings);
    }

    /**
     * Parsed configuration includes immutable custom-field mappings.
     */
    public function test_from_stored_config_parses_custom_fields(): void {
        $stored = (object)[
            'publishablekey' => 'APPLICATION_PUB_abcdefg',
            'secretkey' => 'APPLICATION_SEC_uvwxyz',
            'environment' => environment::Sandbox->value,
            'merchantid' => 'TEST',
            'customfieldmappings' => (object)[
                hash('sha256', 'Email') => (object)['name' => 'Email', 'source' => 'user:email'],
                hash('sha256', 'Department') => (object)['name' => 'Department', 'source' => ''],
                hash('sha256', 'Membership') => (object)['name' => 'Membership', 'source' => 'profile:999999'],
            ],
        ];
        $configuration = api_configuration::from_stored_config($stored);
        // Keep stale sources for subsequent validation and editing.
        $this->assertSame(['Email' => 'user:email', 'Membership' => 'profile:999999'], $configuration->customfields->mappings);
        $stored->customfieldmappings->{hash('sha256', 'Email')}->source = 'user:firstname';
        $this->assertSame('user:email', $configuration->customfields->mappings['Email']);

        $stored->customfieldmappings->{hash('sha256', 'Membership')}->source = ['profile:999999'];
        $result = api_configuration::validate_and_parse_stored_config($stored);
        $this->assertTrue($result->is_err());
        $this->assertSame(get_string('customfieldmappinginvalid', 'paygw_payway'), $result->error);
    }

    /**
     * The record key must hash the exact name, not a normalised variant.
     */
    public function test_from_stored_config_rejects_mismatched_name_hash(): void {
        $stored = (object)[
            'publishablekey' => 'APPLICATION_PUB_abcdefg',
            'secretkey' => 'APPLICATION_SEC_uvwxyz',
            'environment' => environment::Sandbox->value,
            'merchantid' => 'TEST',
            'customfieldmappings' => (object)[
                hash('sha256', 'Membership') => ['name' => 'membership', 'source' => 'user:idnumber'],
            ],
        ];
        $result = api_configuration::validate_and_parse_stored_config($stored);
        $this->assertTrue($result->is_err());
        $this->assertSame(get_string('customfieldmappinginvalid', 'paygw_payway'), $result->error);
    }

    /**
     * Test that missing required configuration values are rejected.
     *
     * @return void
     */
    public function test_from_stored_config_rejects_missing_values(): void {
        $this->expectException(coding_exception::class);
        api_configuration::from_stored_config((object) [
            'publishablekey' => 'APPLICATION_PUB_abcdefg',
            'secretkey' => 'APPLICATION_SEC_uvwxyz',
            'environment' => null,
            'merchantid' => 'TEST',
        ]);
    }
}
