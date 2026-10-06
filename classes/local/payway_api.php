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
     * @param api_configuration $credential api credentials
     */
    public function __construct(
        /** @var api_configuration $credential api credentials */
        protected readonly api_configuration $credential,
    ) {
    }

    /**
     * Create new instance of payway api.
     * @param api_configuration $credential the credentials
     * @return payway_api
     */
    public static function new(api_configuration $credential): payway_api {
        return new payway_api($credential);
    }

    /**
     * Send a request to the PayWay API base URL.
     *
     * @param int $timeout Request timeout in seconds.
     * @return api_response HTTP response details.
     */
    public function request_base_url_get(int $timeout = self::DEFAULT_TIMEOUT): api_response {
        return $this->secret_authorized_request('GET', self::API_BASE_URL, [], $timeout);
    }

    /**
     * Check whether the configured secret key is valid.
     *
     * PayWay documents a GET request to the API base URL as the credential check.
     * Only HTTP 200 with no cURL error is considered valid.
     *
     * @param int $timeout Request timeout in seconds.
     * @return result<bool> True on success, otherwise a descriptive error.
     */
    public function test_is_secret_key_valid(int $timeout = self::DEFAULT_TIMEOUT): result {
        $response = $this->request_base_url_get($timeout);
        if ($response->curlerrno === 0 && $response->httpcode === 200) {
            return result::ok(true);
        }

        return result::err(
            'Secret API key validation failed. ' .
                "HTTP status: {$response->httpcode}; cURL error code: {$response->curlerrno}."
        );
    }

    /**
     * Request the latest API key from PayWay
     * @param int $timeout request timeout in seconds
     * @return api_response api response.
     */
    public function get_latest_api_key(int $timeout = self::DEFAULT_TIMEOUT): api_response {
        return $this->secret_authorized_request('GET', self::API_BASE_URL . '/api-keys/latest', [], $timeout);
    }

    /**
     * Call the PayWay API to process a payment for the configured credentials, and price.
     *
     * @param string $singleusetoken Token returned by payway.js identifying the card being charged
     * @param string $idempotencykey UUID used to avoid duplicate charges
     * @param float $price the amount to charge the user. Note this is always in AUD.
     * @param int $customernumber
     * @return api_response
     */
    public function process_payment(
        string $singleusetoken,
        string $idempotencykey,
        float $price,
        int $customernumber
    ): api_response {
        // See PayWay's transaction request fields and duplicate-payment guidance.
        // See https://www.payway.com.au/docs/rest.html#resources-transactions.

        $params = [
            'singleUseTokenId' => $singleusetoken,
            'customerNumber' => $customernumber,
            'transactionType' => 'payment',
            'principalAmount' => $price,
            'currency' => 'aud',
            'merchantId' => $this->credential->merchantid,
            // 3DSecure fraud detection is not supported by this plugin.
            'threeDS2' => false,
        ];

        $remoteip = getremoteaddr(null);
        // PayWay recommends customerIpAddress for cardholder-initiated payments.
        // See https://www.payway.com.au/docs/customer-ip-address.html .

        if (!empty($remoteip)) {
            $params['customerIpAddress'] = $remoteip;
        }

        // Idempotency-Key is a request header, not a form parameter. PayWay's
        // retry guidance is documented at the URL below.
        // See https://www.payway.com.au/docs/rest.html#basics-sending-requests .

        return $this->secret_authorized_request(
            'POST',
            self::API_BASE_URL . '/transactions',
            $params,
            self::DEFAULT_TIMEOUT,
            ['Idempotency-Key: ' . $idempotencykey],
        );
    }

    /**
     * Make an authorized HTTP request using the secret key.
     *
     * @param string $method HTTP method
     * @param string $url
     * @param array $params form parameters
     * @param int $timeout request timeout in seconds
     * @param array $headers additional request headers
     * @return api_response
     */
    protected function secret_authorized_request(
        string $method,
        string $url,
        array $params = [],
        int $timeout = self::DEFAULT_TIMEOUT,
        array $headers = []
    ): api_response {
        $curl = new curl();
        $options = $this->prepare_secret_authorization_curl_options() + $this->prepare_timeout_curl_options($timeout);
        if (!empty($headers)) {
            $options['CURLOPT_HTTPHEADER'] = $headers;
        }
        $curl->setopt($options);
        $body = strtoupper($method) === 'GET' ? $curl->get($url) : $curl->post($url, $params);
        $info = $curl->get_info();
        return new api_response(
            httpcode: (int) ($info['http_code'] ?? 0),
            body: (string) $body,
            curlerrno: (int) $curl->get_errno(),
        );
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
