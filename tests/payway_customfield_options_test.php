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
use core_payment\account_gateway;
use paygw_payway\local\custom_fields;
use paygw_payway\local\environment;

/**
 * Tests the custom-field section inside the real persistent mform.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\form\payway_customfield_options
 */
final class payway_customfield_options_test extends advanced_testcase {
    /**
     * Build the real persistent form using saved name-based mappings.
     *
     * @param string $response Mock response.
     * @return \core_payment\form\account_gateway
     */
    private function get_form(string $response): \core_payment\form\account_gateway {
        /** @var \core_payment_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $account = $generator->create_payment_account();
        $persistent = new account_gateway(0, (object)[
            'accountid' => $account->get('id'), 'gateway' => 'payway',
            'config' => json_encode([
                'environment' => environment::Sandbox->value, 'merchantid' => 'TEST',
                'secretkey' => 'APPLICATION_SEC_xyzabc', 'publishablekey' => 'APPLICATION_PUB_uvwxyz',
                'customfieldmappings' => [
                    custom_fields::get_field_key('Membership') => ['name' => 'Membership', 'source' => 'profile:999999'],
                    custom_fields::get_field_key('Email') => ['name' => 'Email', 'source' => 'user:email'],
                ],
            ]),
        ]);
        \curl::mock_response($response);
        return new \core_payment\form\account_gateway(null, ['persistent' => $persistent]);
    }

    /**
     * Reordered fields retain their source selection by name, with no empty slot controls.
     */
    public function test_reordered_fields_preserve_named_selections(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $form = $this->get_form('{"data":[{"customFieldId":1,"fieldName":"Email"},'
            . '{"customFieldId":4,"fieldName":"Membership"}]}');
        $mform = $form->get_mform();
        $membership = custom_fields::get_element_name('Membership');
        $email = custom_fields::get_element_name('Email');
        $this->assertSame(['profile:999999'], $mform->getElement($membership)->getValue());
        $this->assertSame(['user:email'], $mform->getElement($email)->getValue());
        $this->assertSame('Membership', $mform->getElement($membership)->getLabel());
        $this->assertStringContainsString(
            get_string('customfieldmissingsource', 'paygw_payway'),
            $mform->getElement($membership)->toHtml()
        );
        $this->assertFalse($mform->elementExists('customfieldmapping2'));
        $this->assertFalse($mform->elementExists('customfieldmapping3status'));
        $parsed = custom_fields::from_stored_config((object)$mform->exportValues());
        $this->assertSame(['Email' => 'user:email', 'Membership' => 'profile:999999'], $parsed->mappings);
    }

    /**
     * Confirmed removed fields are not displayed and are removed from submitted config.
     */
    public function test_removed_fields_are_not_shown(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $mform = $this->get_form('{"data":[]}')->get_mform();
        $this->assertFalse($mform->elementExists(custom_fields::get_element_name('Membership')));
        $this->assertFalse($mform->elementExists(custom_fields::get_element_name('Email')));
        $this->assertStringContainsString('Membership', $mform->getElement('customfieldsnotice')->toHtml());
        $this->assertSame([], custom_fields::from_stored_config((object)$mform->exportValues())->mappings);
    }

    /**
     * An outage retains saved mappings in hidden fields, not unverified dropdowns.
     */
    public function test_failed_discovery_preserves_named_mappings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $mform = $this->get_form('invalid response')->get_mform();
        $this->assertSame('hidden', $mform->getElement(custom_fields::get_element_name('Membership'))->getType());
        $this->assertSame('hidden', $mform->getElement(custom_fields::get_element_name('Email'))->getType());
        $this->assertSame(
            ['Membership' => 'profile:999999', 'Email' => 'user:email'],
            custom_fields::from_stored_config((object)$mform->exportValues())->mappings
        );
    }

    /**
     * A renamed field starts unmapped, rather than inheriting a different name's source.
     */
    public function test_renamed_field_starts_unmapped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $mform = $this->get_form('{"data":[{"customFieldId":1,"fieldName":"New membership"}]}')->get_mform();
        $this->assertFalse($mform->elementExists(custom_fields::get_element_name('Membership')));
        $this->assertSame([''], $mform->getElement(custom_fields::get_element_name('New membership'))->getValue());
        $this->assertSame([], custom_fields::from_stored_config((object)$mform->exportValues())->mappings);
    }
}
