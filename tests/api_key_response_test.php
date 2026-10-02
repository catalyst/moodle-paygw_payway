<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace paygw_payway;

use advanced_testcase;
use paygw_payway\local\api_key_response;

/**
 * Tests parsing of PayWay latest API key responses.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\local\api_key_response
 */
final class api_key_response_test extends advanced_testcase {
    /**
     * Parse and retain the returned API key.
     */
    public function test_valid_response_is_parsed(): void {
        $result = api_key_response::try_parse('{"key":"APPLICATION_SEC_replacement"}');

        $this->assertTrue($result->is_ok());
        $this->assertSame('APPLICATION_SEC_replacement', $result->unwrap()->key);
    }

    /**
     * Reject malformed, incomplete, and invalid responses with descriptive secret-safe errors.
     *
     * @dataProvider invalid_response_provider
     * @param string $json Response body.
     * @param string $expectederror Expected safe parser error.
     * @param string $secret Secret value that must not be included in the error.
     */
    public function test_invalid_response_returns_safe_error(string $json, string $expectederror, string $secret): void {
        $result = api_key_response::try_parse($json);

        $this->assertTrue($result->is_err());
        $this->assertSame($expectederror, $result->error);
        $this->assertStringNotContainsString($secret, $result->error);
    }

    /**
     * Provide invalid API key response bodies.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function invalid_response_provider(): array {
        $secret = 'APPLICATION_SEC_sensitive';
        return [
            'invalid JSON' => [
                '{"key":"' . $secret,
                'Could not decode JSON response for PayWay API key.',
                $secret,
            ],
            'top-level array' => [
                '["' . $secret . '"]',
                'Expected a JSON object in PayWay API key response.',
                $secret,
            ],
            'missing key' => [
                '{"other":"' . $secret . '"}',
                'PayWay API key response is missing the required key field.',
                $secret,
            ],
            'non-string key' => [
                '{"key":{"value":"' . $secret . '"}}',
                'PayWay API key response contains an invalid key field.',
                $secret,
            ],
            'empty key' => [
                '{"key":""}',
                'PayWay API key response contains an invalid key field.',
                $secret,
            ],
        ];
    }
}
