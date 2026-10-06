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
use paygw_payway\local\custom_fields;
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
     * Build mappings using the exact PayWay names and nested storage records.
     *
     * @param array $mappings Field names and sources (string keys and values).
     * @return custom_fields
     */
    private function get_named_mappings(array $mappings): custom_fields {
        $records = [];
        foreach ($mappings as $name => $source) {
            $records[hash('sha256', $name)] = ['name' => $name, 'source' => $source];
        }
        return custom_fields::from_stored_config((object)[
            'customfieldmappings' => (object)$records,
        ]);
    }

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
            $this->get_named_mappings([]),
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
    public function test_request_base_url_get_returns_full_api_response(): void {
        $api = $this->getMockBuilder(payway_api::class)
            ->setConstructorArgs([
                new api_configuration(
                    'APPLICATION_PUBLISHABLE_abcdefg',
                    'APPLICATION_SECRET_uvwxyz',
                    environment::Sandbox,
                    'TEST',
                    null,
                    $this->get_named_mappings([]),
                ),
            ])
            ->onlyMethods(['secret_authorized_request'])
            ->getMock();

        $api->method('secret_authorized_request')->willReturn(new api_response(201, ''));

        $response = $api->request_base_url_get();
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
                    $this->get_named_mappings([]),
                ),
            ])
            ->onlyMethods(['request_base_url_get'])
            ->getMock();

        $api->method('request_base_url_get')->willReturn($response);
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
        $customfields = ['customField1' => 'Alice', 'customField4' => '0'];
        $api = $this->getMockBuilder(payway_api::class)
            ->setConstructorArgs([
                new api_configuration(
                    'APPLICATION_PUBLISHABLE_abcdefg',
                    'APPLICATION_SECRET_uvwxyz',
                    environment::Sandbox,
                    'TEST',
                    null,
                    $this->get_named_mappings([]),
                ),
            ])
            ->onlyMethods(['secret_authorized_request', 'get_custom_fields'])
            ->getMock();

        $api->expects($this->never())->method('get_custom_fields');
        $api->expects($this->once())
            ->method('secret_authorized_request')
            ->with(
                'POST',
                payway_api::API_BASE_URL . '/transactions',
                $this->callback(function (array $params): bool {
                    return $params['singleUseTokenId'] === 'single-use-token'
                        && $params['customerNumber'] === 999999
                        && $params['principalAmount'] === 10.5
                        && $params['merchantId'] === 'TEST'
                        && $params['customField1'] === 'Alice'
                        && $params['customField4'] === '0'
                        && !isset($params['customField2'])
                        && !isset($params['customField3'])
                        && !isset($params['idempotencyKey']);
                }),
                payway_api::DEFAULT_TIMEOUT,
                ['Idempotency-Key: payment-attempt-key'],
            )
            ->willReturn(new api_response(201, '{}'));

        $response = $api->process_payment('single-use-token', 'payment-attempt-key', 10.5, 999999, $customfields);

        $this->assertSame(201, $response->httpcode);
    }

    /**
     * API discovery fails closed on transport errors and malformed responses.
     *
     * @dataProvider custom_fields_response_provider
     * @param api_response $response Response.
     * @param bool $valid Whether discovery succeeds.
     */
    public function test_custom_field_discovery(api_response $response, bool $valid): void {
        $api = $this->getMockBuilder(payway_api::class)
            ->setConstructorArgs([new api_configuration(
                'APPLICATION_PUB_abcdef',
                'APPLICATION_SEC_abcdef',
                environment::Sandbox,
                'TEST',
                null,
                $this->get_named_mappings([]),
            )])
            ->onlyMethods(['secret_authorized_request'])
            ->getMock();
        $api->expects($this->once())->method('secret_authorized_request')
            ->with('GET', payway_api::API_BASE_URL . '/custom-fields')
            ->willReturn($response);
        $result = $api->get_custom_fields();
        $this->assertSame($valid, $result->is_ok());
        if ($valid) {
            foreach ($result->unwrap() as $id => $field) {
                $this->assertSame($id, $field['customFieldId']);
            }
        }
    }

    /**
     * Provide custom-field discovery response cases.
     *
     * @return array Discovery response cases.
     */
    public static function custom_fields_response_provider(): array {
        return [
            'fields' => [new api_response(200, '{"data":[{"customFieldId":2,"fieldName":"Membership"}]}'), true],
            'none' => [new api_response(200, '{"data":[]}'), true],
            'unauthorised' => [new api_response(401, '{}'), false],
            'network error' => [new api_response(200, '{"data":[]}', 7), false],
            'invalid json' => [new api_response(200, 'not json'), false],
            'missing data' => [new api_response(200, '{}'), false],
            'wrong shape' => [new api_response(200, '{"data":[null]}'), false],
            'invalid slot' => [new api_response(200, '{"data":[{"customFieldId":5,"fieldName":"Bad"}]}'), false],
            'duplicate slot' => [new api_response(
                200,
                '{"data":[{"customFieldId":1,"fieldName":"A"},{"customFieldId":1,"fieldName":"B"}]}'
            ), false],
        ];
    }

    /**
        * Custom data must never override fixed payment parameters.
     */
    public function test_custom_fields_cannot_override_payment_parameters(): void {
        $api = $this->getMockBuilder(payway_api::class)
            ->setConstructorArgs([new api_configuration(
                'APPLICATION_PUB_abcdef',
                'APPLICATION_SEC_abcdef',
                environment::Sandbox,
                'TEST',
                null,
                $this->get_named_mappings([]),
            )])
            ->onlyMethods(['secret_authorized_request', 'get_custom_fields'])
            ->getMock();
        $api->expects($this->never())->method('get_custom_fields');
        $api->expects($this->once())->method('secret_authorized_request')
            ->with('POST', payway_api::API_BASE_URL . '/transactions', $this->callback(function (array $params): bool {
                return $params['singleUseTokenId'] === 'token'
                    && $params['customerNumber'] === 999999
                    && $params['transactionType'] === 'payment'
                    && $params['principalAmount'] === 10.0
                    && $params['currency'] === 'aud'
                    && $params['merchantId'] === 'TEST'
                    && $params['threeDS2'] === false
                    && $params['customField1'] === 'Alice';
            }))
            ->willReturn(new api_response(201, '{}'));
        $response = $api->process_payment('token', 'key', 10, 999999, [
            'singleUseTokenId' => 'other-token',
            'customerNumber' => 1,
            'transactionType' => 'refund',
            'principalAmount' => 0,
            'currency' => 'usd',
            'merchantId' => 'OTHER',
            'threeDS2' => true,
            'customField1' => 'Alice',
        ]);
        $this->assertSame(201, $response->httpcode);
    }

    /**
        * Empty resolved values produce no additional payload fields or discovery requests.
     */
    public function test_payment_without_custom_field_mappings(): void {
        $api = $this->getMockBuilder(payway_api::class)
            ->setConstructorArgs([new api_configuration(
                'APPLICATION_PUB_abcdef',
                'APPLICATION_SEC_abcdef',
                environment::Sandbox,
                'TEST',
                null,
                $this->get_named_mappings([]),
            )])
            ->onlyMethods(['secret_authorized_request', 'get_custom_fields'])
            ->getMock();
        $api->expects($this->never())->method('get_custom_fields');
        $api->expects($this->once())->method('secret_authorized_request')
            ->with('POST', payway_api::API_BASE_URL . '/transactions', $this->callback(function (array $params): bool {
                return $params['principalAmount'] === 10.0
                    && !isset($params['customField1'])
                    && !isset($params['customField2'])
                    && !isset($params['customField3'])
                    && !isset($params['customField4']);
            }))
            ->willReturn(new api_response(201, '{}'));
        $response = $api->process_payment('token', 'key', 10, 999999, []);
        $this->assertSame(201, $response->httpcode);
    }

    /**
     * All four resolved custom-field values are passed through unchanged.
     */
    public function test_payment_passes_through_all_resolved_custom_field_slots(): void {
        $customfields = [
            'customField1' => 'MEMBER-123',
            'customField2' => 'Alice',
            'customField3' => 'Department',
            'customField4' => '0',
        ];
        $api = $this->getMockBuilder(payway_api::class)
            ->setConstructorArgs([new api_configuration(
                'APPLICATION_PUB_abcdef',
                'APPLICATION_SEC_abcdef',
                environment::Sandbox,
                'TEST',
                null,
                $this->get_named_mappings([]),
            )])
            ->onlyMethods(['secret_authorized_request', 'get_custom_fields'])
            ->getMock();
        $api->expects($this->never())->method('get_custom_fields');
        $api->expects($this->once())->method('secret_authorized_request')
            ->with('POST', payway_api::API_BASE_URL . '/transactions', $this->callback(function (array $params): bool {
                return $params['customField1'] === 'MEMBER-123'
                    && $params['customField2'] === 'Alice'
                    && $params['customField3'] === 'Department'
                    && $params['customField4'] === '0';
            }))
            ->willReturn(new api_response(201, '{}'));
        $response = $api->process_payment('token', 'key', 10, 999999, $customfields);
        $this->assertSame(201, $response->httpcode);
    }
}
