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
use paygw_payway\local\api_credential;
use paygw_payway\local\environment;

/**
 * api credential class test
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\local\api_credential
 */
final class api_credential_test extends advanced_testcase {
    /**
     * Test that a valid key string is normalised to the expected display format.
     *
     * @return void
     */
    public function test_parse_and_validate_key_name_accepts_valid_key(): void {
        $result = api_credential::parse_and_validate_key_name('APPLICATION_SECRET_xyzabc');

        $this->assertTrue($result->is_ok());
        $this->assertSame('APPLICATION_SECRET...abc', $result->value);
    }

    /**
     * Test that malformed key names with too few sections are rejected.
     *
     * @return void
     */
    public function test_parse_and_validate_key_name_rejects_missing_sections(): void {
        $result = api_credential::parse_and_validate_key_name('APPLICATION_SECRET');

        $this->assertTrue($result->is_err());
        $this->assertSame('Unexpected key format, expected 3 parts separated by underscores', $result->error);
    }

    /**
     * Test that key names with a short random suffix are rejected.
     *
     * @return void
     */
    public function test_parse_and_validate_key_name_rejects_short_suffix(): void {
        $result = api_credential::parse_and_validate_key_name('APPLICATION_SECRET_abc');

        $this->assertTrue($result->is_err());
        $this->assertSame('Unexpected length of random part of key, expected > 3 chars', $result->error);
    }

    /**
     * Test that a key not matching the expected type segment is rejected.
     *
     * @return void
     */
    public function test_parse_and_validate_key_name_rejects_mismatched_type(): void {
        $result = api_credential::parse_and_validate_key_name('APPLICATION_PUB_xyzabc', api_credential::TYPE_SECRET);

        $this->assertTrue($result->is_err());
        $this->assertSame('Unexpected key type, expected SEC but got PUB', $result->error);
    }

    /**
     * Test that a key matching the expected type segment is accepted.
     *
     * @return void
     */
    public function test_parse_and_validate_key_name_accepts_matching_type(): void {
        $result = api_credential::parse_and_validate_key_name('APPLICATION_SEC_xyzabc', api_credential::TYPE_SECRET);

        $this->assertTrue($result->is_ok());
        $this->assertSame('APPLICATION_SEC...abc', $result->value);
    }

    /**
     * Test that JSON configuration data is converted into an api_credential object correctly.
     *
     * @return void
     */
    public function test_from_stored_config_builds_credential_from_json(): void {
        $credential = api_credential::from_stored_config(json_encode([
            'publishablekey' => 'APPLICATION_PUBLISHABLE_abcdefg',
            'secretkey' => 'APPLICATION_SECRET_uvwxyz',
            'environment' => 'sandbox',
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('APPLICATION_PUBLISHABLE_abcdefg', $credential->publishablekey);
        $this->assertSame('APPLICATION_SECRET_uvwxyz', $credential->secretkey);
        $this->assertSame(environment::Sandbox, $credential->environment);
    }

    /**
     * Test that missing required configuration values are rejected.
     *
     * @return void
     */
    public function test_from_stored_config_rejects_missing_values(): void {
        $this->expectException(coding_exception::class);
        api_credential::from_stored_config(json_encode([
            'publishablekey' => 'APPLICATION_PUBLISHABLE_abcdefg',
            'secretkey' => 'APPLICATION_SECRET_uvwxyz',
            'environment' => null,
        ], JSON_THROW_ON_ERROR));
    }
}
