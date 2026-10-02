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

namespace paygw_payway\local;

/**
 * Represents the latest API key response from PayWay.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api_key_response {
    /**
     * Store a parsed PayWay API key response.
     *
     * @param string $key Latest secret API key.
     */
    public function __construct(
        /** @var string Latest secret API key. */
        public readonly string $key,
    ) {
    }

    /**
     * Parse a PayWay latest API key JSON response.
     *
     * Error messages intentionally do not include response values because the response contains a secret key.
     *
     * @param string $json Response body.
     * @return result<api_key_response> Parsed key response or a descriptive, secret-safe error.
     */
    public static function try_parse(string $json): result {
        try {
            $decoded = json_decode($json, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return result::err('Could not decode JSON response for PayWay API key.');
        }

        if (!is_object($decoded)) {
            return result::err('Expected a JSON object in PayWay API key response.');
        }

        if (!isset($decoded->key)) {
            return result::err('PayWay API key response is missing the required key field.');
        }

        if (!is_string($decoded->key) || trim($decoded->key) === '') {
            return result::err('PayWay API key response contains an invalid key field.');
        }

        return result::ok(new api_key_response($decoded->key));
    }
}
