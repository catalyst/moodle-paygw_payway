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
use paygw_payway\local\custom_fields;

/**
 * Custom profile mapping tests.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\local\custom_fields
 */
final class custom_fields_test extends advanced_testcase {
    /**
     * Build stored mappings for test cases.
     *
     * @param array $mappings Exact PayWay name => source.
     * @return custom_fields
     */
    private function get_named_mappings(array $mappings): custom_fields {
        $records = [];
        foreach ($mappings as $name => $source) {
            $records[custom_fields::get_field_key((string)$name)] = ['name' => (string)$name, 'source' => $source];
        }
        return custom_fields::from_stored_config((object)['customfieldmappings' => $records]);
    }

    /**
     * Test absent mappings are backwards compatible.
     */
    public function test_no_mappings(): void {
        $config = custom_fields::from_stored_config(new \stdClass());
        $this->assertSame([], $config->mappings);
        $this->assertSame([], $config->get_transaction_values(0, [])->unwrap());
    }

    /**
     * Moodle displayable fields are available without exposing sensitive or calculated columns.
     */
    public function test_sources_use_moodle_profile_functions(): void {
        $this->resetAfterTest();
        $field = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'reference', 'name' => 'Reference',
        ]);
        $sources = custom_fields::get_sources();
        $this->assertArrayHasKey('user:email', $sources);
        $this->assertArrayHasKey('user:timezone', $sources);
        $this->assertArrayHasKey('profile:' . $field->id, $sources);
        $this->assertArrayNotHasKey('user:password', $sources);
        $this->assertArrayNotHasKey('user:secret', $sources);
        $this->assertArrayNotHasKey('user:fullname', $sources);
        $this->assertArrayNotHasKey('user:customfields', $sources);
        $this->assertArrayNotHasKey('user:roles', $sources);
    }

    /**
     * Malformed storage is rejected before a typed configuration is constructed.
     */
    public function test_parse_rejects_malformed_mapping(): void {
        $result = custom_fields::validate_and_parse_stored_config((object)[
            'customfieldmappings' => [custom_fields::get_field_key('Email') => ['name' => 'Email', 'source' => ['user:email']]],
        ]);
        $this->assertTrue($result->is_err());
        $this->assertSame(get_string('customfieldmappinginvalid', 'paygw_payway'), $result->error);
        $this->expectException(\coding_exception::class);
        custom_fields::from_stored_config((object)['customfieldmappings' => false]);
    }

    /**
     * Test removed remote/local fields and unsafe user columns are rejected.
     */
    public function test_validate_stale_and_unsafe_mappings(): void {
        $this->resetAfterTest();
        $config = $this->get_named_mappings([
            'Email' => 'user:email', 'Member' => 'profile:999999',
            'Password' => 'user:password', 'Unknown' => 'user:unknown',
        ]);
        $definitions = [2 => ['fieldName' => 'Member'], 3 => ['fieldName' => 'Password'], 4 => ['fieldName' => 'Unknown']];
        $errors = $config->validate($definitions);
        $this->assertCount(4, $errors);
        $this->assertSame(
            get_string('customfieldremoved', 'paygw_payway', 'Email'),
            $errors[custom_fields::get_element_name('Email')]
        );
        $this->assertSame([], $this->get_named_mappings(['Email' => ''])->validate([]));
    }

    /**
     * Values are read from the database; empty values are omitted and zero is kept.
     */
    public function test_resolve_standard_and_custom_fields(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'department' => '']);
        $field = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'membership', 'name' => 'Membership', 'defaultdata' => 'Default',
        ]);
        $DB->insert_record('user_info_data', (object)[
            'userid' => $user->id, 'fieldid' => $field->id, 'data' => '0', 'dataformat' => 0,
        ]);
        $config = $this->get_named_mappings([
            'Name' => 'user:firstname', 'Membership' => 'profile:' . $field->id, 'Department' => 'user:department',
        ]);
        $definitions = [1 => ['fieldName' => 'Name'], 2 => ['fieldName' => 'Membership'], 3 => ['fieldName' => 'Department']];
        // Ensure values do not come from a stale session object.
        $user->firstname = 'Not the stored name';
        $this->assertSame(
            ['customField1' => 'Alice', 'customField2' => '0'],
            $config->get_transaction_values($user->id, $definitions)->unwrap()
        );

        $DB->set_field('user_info_field', 'name', 'Renamed membership', ['id' => $field->id]);
        $this->assertSame([], $config->validate($definitions));
        $DB->delete_records('user_info_data', ['fieldid' => $field->id]);
        $this->assertSame('Default', $config->get_transaction_values($user->id, $definitions)->unwrap()['customField2']);
        $DB->delete_records('user_info_field', ['id' => $field->id]);
        $this->assertTrue($config->get_transaction_values($user->id, $definitions)->is_err());
    }

    /**
     * Boundary and encoding validation.
     *
     * @param string $value Profile value.
     * @param bool $valid Whether it can be sent.
     * @dataProvider value_provider
     */
    public function test_value_constraints(string $value, bool $valid): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['department' => $value]);
        $config = $this->get_named_mappings(['Department' => 'user:department']);
        $result = $config->get_transaction_values($user->id, [1 => ['fieldName' => 'Department']]);
        $this->assertSame($valid, $result->is_ok());
        if ($valid) {
            $this->assertSame($value === '' ? [] : ['customField1' => substr($value, 0, 60)], $result->unwrap());
        }
    }

    /**
     * Reordering changes only the transaction slot, never the mapped source.
     */
    public function test_reordering_fields_follows_names(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'idnumber' => 'MEMBER-123']);
        $config = $this->get_named_mappings(['Name' => 'user:firstname', 'Membership' => 'user:idnumber']);
        $this->assertSame(
            ['customField2' => 'Alice', 'customField1' => 'MEMBER-123'],
            $config->get_transaction_values($user->id, [1 => ['fieldName' => 'Membership'], 2 => ['fieldName' => 'Name']])
                ->unwrap()
        );
    }

    /**
     * Duplicate and renamed fields must never silently receive another field's value.
     */
    public function test_ambiguous_or_renamed_fields_fail(): void {
        $this->resetAfterTest();
        $config = $this->get_named_mappings(['Membership' => 'user:idnumber']);
        $this->assertTrue($config->get_transaction_values(999999, [1 => ['fieldName' => 'Renamed']])->is_err());
        $result = $config->get_transaction_values(
            999999,
            [1 => ['fieldName' => 'Membership'], 2 => ['fieldName' => 'Membership']]
        );
        $this->assertTrue($result->is_err());
        $this->assertSame(get_string('customfieldambiguous', 'paygw_payway', 'Membership'), $result->error);
    }

    /**
     * Provides field values for tests
     * @return array Value validation cases.
     */
    public static function value_provider(): array {
        return [
            'maximum length' => [str_repeat('a', 60), true],
            'truncated' => [str_repeat('a', 61), true],
            'long value retains prefix' => [str_repeat('a', 59) . 'bcdef', true],
            'unicode' => ['Café', false],
            'control character' => ["Line\nbreak", false],
            'zero' => ['0', true],
            'empty' => ['', true],
        ];
    }
}
