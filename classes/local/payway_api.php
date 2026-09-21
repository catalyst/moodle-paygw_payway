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

namespace paygw_payway\local;

use curl;

/**
 * PayWay API interaction class - manages the setup, calling, etc.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payway_api {
    /**
     * @var string base payway API url
     */
    public const API_BASE_URL = 'https://api.payway.com.au/rest/v1';

    /**
     * @var int default timeout (in seconds) for API requests
     */
    public const DEFAULT_TIMEOUT = 5;

    /**
     * Create API class
     * @param api_credential $credential api credentials
     */
    public function __construct(
        /** @var api_credential $credential api credentials */
        protected readonly api_credential $credential,
    ) {
    }

    /**
     * Create new instance of payway api.
     * @param api_credential $credential the credentials
     * @return payway_api
     */
    public static function new(api_credential $credential): payway_api {
        return new payway_api($credential);
    }

    /**
     * Send a test request to confirm credential is valid.
     * @param int $timeout request timeout in seconds
     * @return int http status code returned from a test query
     */
    public function test_secret_key(int $timeout = self::DEFAULT_TIMEOUT): int {
        // PayWay docs specify to test API token, do a GET request on the base url.
        $info = $this->secret_authorized_get(self::API_BASE_URL, $timeout);
        return $info['http_code'] ?? 500;
    }

    /**
     * Do a authorized curl using the secret key
     * @param string $url
     * @param int $timeout request timeout in seconds
     * @return array curl response info
     */
    protected function secret_authorized_get(string $url, int $timeout = self::DEFAULT_TIMEOUT): array {
        $curl = new curl();
        $curl->setopt($this->prepare_secret_authorization_curl_options() + $this->prepare_timeout_curl_options($timeout));
        $curl->get($url);
        return $curl->get_info();
    }

    /**
     * Prepare array of curl options to authorization with the secret key
     * @return array
     */
    protected function prepare_secret_authorization_curl_options(): array {
        return [
            // PayWay uses HTTP basic auth,
            // See https://www.payway.com.au/docs/rest.html?#basic-authentication .
            'CURLOPT_HTTPAUTH' => CURLAUTH_BASIC,
            // Key is username, password (after colon) is blank.
            'CURLOPT_USERPWD' => $this->credential->secretkey . ':',
        ];
    }

    /**
     * Prepare array of curl options to enforce a request timeout
     * @param int $timeout request timeout in seconds
     * @return array
     */
    protected function prepare_timeout_curl_options(int $timeout): array {
        return [
            // Avoid the request hanging indefinitely if the API is unresponsive.
            'CURLOPT_CONNECTTIMEOUT' => $timeout,
            'CURLOPT_TIMEOUT' => $timeout,
        ];
    }
}
