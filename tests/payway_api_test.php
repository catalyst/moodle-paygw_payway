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
use paygw_payway\local\api_configuration;
use paygw_payway\local\api_response;
use paygw_payway\local\environment;
use paygw_payway\local\payway_api;
use ReflectionMethod;

/**
 * PayWay API class test
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\local\payway_api
 */
final class payway_api_test extends advanced_testcase {
    /**
     * Test that basic HTTP auth credentials are prepared correctly for PayWay API requests.
     *
     * @return void
     */
    public function test_prepare_secret_authorization_curl_options_uses_basic_auth(): void {
        $api = new payway_api(new api_configuration(
            'APPLICATION_PUBLISHABLE_abcdefg',
            'APPLICATION_SECRET_uvwxyz',
            environment::Sandbox,
            'TEST',
            null,
        ));

        $method = new ReflectionMethod(payway_api::class, 'prepare_secret_authorization_curl_options');
        $method->setAccessible(true);
        $options = $method->invoke($api);

        $this->assertSame(CURLAUTH_BASIC, $options['CURLOPT_HTTPAUTH']);
        $this->assertSame('APPLICATION_SECRET_uvwxyz:', $options['CURLOPT_USERPWD']);
    }

    /**
     * Test that the PayWay API helper returns the HTTP status code from the upstream API response.
     *
     * @return void
     */
    public function test_get_base_url_returns_full_api_response(): void {
        $api = $this->getMockBuilder(payway_api::class)
            ->setConstructorArgs([
                new api_configuration(
                    'APPLICATION_PUBLISHABLE_abcdefg',
                    'APPLICATION_SECRET_uvwxyz',
                    environment::Sandbox,
                    'TEST',
                    null,
                ),
            ])
            ->onlyMethods(['secret_authorized_request'])
            ->getMock();

        $api->method('secret_authorized_request')->willReturn(new api_response(201, ''));

        $response = $api->get_base_url();
        $this->assertSame(201, $response->httpcode);
    }

    /**
     * Test secret-key validation accepts only HTTP 200 without transport errors.
     *
     * @dataProvider secret_key_validation_response_provider
     * @param api_response $response API response to validate.
     * @param bool $isvalid Whether the validation should succeed.
     */
    public function test_secret_key_validation(api_response $response, bool $isvalid): void {
        $api = $this->getMockBuilder(payway_api::class)
            ->setConstructorArgs([
                new api_configuration(
                    'APPLICATION_PUBLISHABLE_abcdefg',
                    'APPLICATION_SECRET_uvwxyz',
                    environment::Sandbox,
                    'TEST',
                    null,
                ),
            ])
            ->onlyMethods(['get_base_url'])
            ->getMock();

        $api->method('get_base_url')->willReturn($response);
        $result = $api->test_is_secret_key_valid();

        if ($isvalid) {
            $this->assertTrue($result->is_ok());
            $this->assertTrue($result->unwrap());
        } else {
            $this->assertTrue($result->is_err());
            $this->assertNotEmpty($result->error);
        }
    }

    /**
     * Responses to check when validating a secret key.
     *
     * @return array<string, array{api_response, bool}>
     */
    public static function secret_key_validation_response_provider(): array {
        return [
            'HTTP 200' => [new api_response(200, ''), true],
            'other success status' => [new api_response(201, ''), false],
            'HTTP failure' => [new api_response(401, ''), false],
            'network failure' => [new api_response(0, '', 7), false],
        ];
    }

    public function test_process_payment_sends_idempotency_key_as_header(): void {
        $api = $this->getMockBuilder(payway_api::class)
            ->setConstructorArgs([
                new api_configuration(
                    'APPLICATION_PUBLISHABLE_abcdefg',
                    'APPLICATION_SECRET_uvwxyz',
                    environment::Sandbox,
                    'TEST',
                    null,
                ),
            ])
            ->onlyMethods(['secret_authorized_request'])
            ->getMock();

        $api->expects($this->once())
            ->method('secret_authorized_request')
            ->with(
                'POST',
                payway_api::API_BASE_URL . '/transactions',
                $this->callback(function (array $params): bool {
                    return $params['singleUseTokenId'] === 'single-use-token'
                        && $params['customerNumber'] === 96
                        && $params['principalAmount'] === 10.5
                        && $params['merchantId'] === 'TEST';
                }),
                payway_api::DEFAULT_TIMEOUT,
                ['Idempotency-Key: payment-attempt-key'],
            )
            ->willReturn(new api_response(201, '{}'));

        $response = $api->process_payment('single-use-token', 'payment-attempt-key', 10.5, 96);

        $this->assertSame(201, $response->httpcode);
    }
}
