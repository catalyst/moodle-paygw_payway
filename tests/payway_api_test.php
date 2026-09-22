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
use paygw_payway\local\api_credential;
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
        $api = new payway_api(new api_credential(
            'APPLICATION_PUBLISHABLE_abcdefg',
            'APPLICATION_SECRET_uvwxyz',
            environment::Sandbox,
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
    public function test_test_secret_key_returns_status_code_from_api(): void {
        $api = $this->getMockBuilder(payway_api::class)
            ->setConstructorArgs([
                new api_credential(
                    'APPLICATION_PUBLISHABLE_abcdefg',
                    'APPLICATION_SECRET_uvwxyz',
                    environment::Sandbox,
                ),
            ])
            ->onlyMethods(['secret_authorized_get'])
            ->getMock();

        $api->method('secret_authorized_get')->willReturn(['http_code' => 201]);

        $this->assertSame(201, $api->test_secret_key());
    }
}
