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
use core_payment\form\account_gateway;
use paygw_payway\local\environment;

/**
 * Gateway class test
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\gateway
 */
final class gateway_test extends advanced_testcase {
    /**
     * Get a stub form instance - validate_gateway_form does not use it directly, only its type.
     *
     * @return account_gateway
     */
    private function get_stub_form(): account_gateway {
        return new class extends account_gateway {
            public function __construct() {
            }
        };
    }

    /**
     * Test that valid form data produces no errors.
     *
     * @return void
     */
    public function test_validate_gateway_form_accepts_valid_data(): void {
        $data = (object) [
            'environment' => environment::Sandbox->value,
            'secretkey' => 'APPLICATION_SEC_xyzabc',
            'publishablekey' => 'APPLICATION_PUB_uvwxyz',
        ];
        $errors = [];

        gateway::validate_gateway_form($this->get_stub_form(), $data, [], $errors);

        $this->assertSame([], $errors);
    }

    /**
     * Test that an invalid environment value produces an error.
     *
     * @return void
     */
    public function test_validate_gateway_form_rejects_invalid_environment(): void {
        $data = (object) [
            'environment' => 'not-a-real-environment',
            'secretkey' => 'APPLICATION_SEC_xyzabc',
            'publishablekey' => 'APPLICATION_PUB_uvwxyz',
        ];
        $errors = [];

        gateway::validate_gateway_form($this->get_stub_form(), $data, [], $errors);

        $this->assertArrayHasKey('environment', $errors);
    }

    /**
     * Test that a malformed secret key produces an error, without affecting the publishable key.
     *
     * @return void
     */
    public function test_validate_gateway_form_rejects_invalid_secretkey_format(): void {
        $data = (object) [
            'environment' => environment::Sandbox->value,
            'secretkey' => 'notvalid',
            'publishablekey' => 'APPLICATION_PUB_uvwxyz',
        ];
        $errors = [];

        gateway::validate_gateway_form($this->get_stub_form(), $data, [], $errors);

        $this->assertArrayHasKey('secretkey', $errors);
        $this->assertArrayNotHasKey('publishablekey', $errors);
    }

    /**
     * Test that a malformed publishable key produces an error, without affecting the secret key.
     *
     * @return void
     */
    public function test_validate_gateway_form_rejects_invalid_publishablekey_format(): void {
        $data = (object) [
            'environment' => environment::Sandbox->value,
            'secretkey' => 'APPLICATION_SEC_xyzabc',
            'publishablekey' => 'notvalid',
        ];
        $errors = [];

        gateway::validate_gateway_form($this->get_stub_form(), $data, [], $errors);

        $this->assertArrayHasKey('publishablekey', $errors);
        $this->assertArrayNotHasKey('secretkey', $errors);
    }

    /**
     * Test that swapping the secret and publishable keys is rejected, since their types no longer match.
     *
     * @return void
     */
    public function test_validate_gateway_form_rejects_swapped_key_types(): void {
        $data = (object) [
            'environment' => environment::Sandbox->value,
            'secretkey' => 'APPLICATION_PUB_uvwxyz',
            'publishablekey' => 'APPLICATION_SEC_xyzabc',
        ];
        $errors = [];

        gateway::validate_gateway_form($this->get_stub_form(), $data, [], $errors);

        $this->assertArrayHasKey('secretkey', $errors);
        $this->assertArrayHasKey('publishablekey', $errors);
    }
}
