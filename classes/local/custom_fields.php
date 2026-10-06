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

/**
 * Maps PayWay field names to explicitly allowed user profile fields.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_fields {
    /**
     * Create an immutable set of parsed mappings.
     *
     * @param array $mappings Exact PayWay field name => source key (string keys and values).
     */
    private function __construct(
        /** @var array<string, string> Exact PayWay field name => source key. */
        public readonly array $mappings,
    ) {
    }

    /**
     * Return available sources. Custom fields use IDs so renames preserve mappings.
     *
     * @return array Source key => display label.
     */
    public static function get_sources(): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $sources = [];
        foreach (self::get_standard_fields() as $field) {
            $sources['user:' . $field] = get_string('customfieldusersource', 'paygw_payway', $field);
        }
        foreach (profile_get_custom_fields() as $field) {
            $sources['profile:' . $field->id] = get_string(
                'customfieldprofilesource',
                'paygw_payway',
                format_string($field->name)
            );
        }
        return $sources;
    }

    /**
     * Get Moodle's displayable fields that are stored in the user table.
     *
     * Exclude calculated and structured entries such as fullname, roles and
     * customfields. Do not expose arbitrary database columns such as password.
     *
     * @return array Standard user column names.
     */
    private static function get_standard_fields(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        return array_values(array_intersect(user_get_default_fields(), array_keys($DB->get_columns('user'))));
    }

    /**
     * Parse mappings at the storage boundary, without checking external state.
     *
     * Deleted sources are deliberately retained so the form can display them.
     *
     * @param object $config Gateway configuration.
     * @return result<custom_fields> Parsed mappings, or a configuration error.
     */
    public static function validate_and_parse_stored_config(object $config): result {
        $mappings = [];
        $records = $config->customfieldmappings ?? [];
        if ((!is_array($records) && !is_object($records)) || count((array)$records) > 4) {
            return result::err(get_string('customfieldmappinginvalid', 'paygw_payway'));
        }
        foreach ((array)$records as $key => $record) {
            if (!is_array($record) && !is_object($record)) {
                return result::err(get_string('customfieldmappinginvalid', 'paygw_payway'));
            }
            $record = (object)$record;
            $name = $record->name ?? null;
            $source = $record->source ?? null;
            if (!is_string($name) || $name === '' || $key !== self::get_field_key($name) || !is_string($source)) {
                return result::err(get_string('customfieldmappinginvalid', 'paygw_payway'));
            }
            if ($source !== '') {
                $mappings[$name] = $source;
            }
        }
        return result::ok(new self($mappings));
    }

    /**
     * Stable HTML-safe identifier for a field - use in mForms.
     *
     * @param string $name Exact PayWay field name.
     * @return string
     */
    public static function get_field_key(string $name): string {
        return hash('sha256', $name);
    }

    /**
     * Form element storing the source for a named PayWay field.
     *
     * @param string $name Exact PayWay field name.
     * @return string
     */
    public static function get_element_name(string $name): string {
        return 'customfieldmappings[' . self::get_field_key($name) . '][source]';
    }

    /**
     * If there are any mappings
     * @return bool Whether any named mappings exist.
     */
    public function has_mappings(): bool {
        return !empty($this->mappings);
    }

    /**
     * Build mappings from stored configuration, throwing if malformed.
     *
     * @param object $config Gateway configuration.
     * @return custom_fields
     */
    public static function from_stored_config(object $config): self {
        return self::validate_and_parse_stored_config($config)->unwrap();
    }

    /**
     * Validate only configured mappings; unmapped transaction fields are optional.
     *
     * @param array $definitions PayWay definitions indexed by slot ID.
     * @return array Form element name => error message.
     */
    public function validate(array $definitions): array {
        $sources = self::get_sources();
        $errors = [];
        foreach ($this->mappings as $fieldname => $source) {
            $fieldname = (string)$fieldname;
            $name = self::get_element_name($fieldname);
            $matches = array_filter($definitions, fn($field) => $field['fieldName'] === $fieldname);
            if (!$matches) {
                $errors[$name] = get_string('customfieldremoved', 'paygw_payway', s($fieldname));
            } else if (count($matches) !== 1) {
                $errors[$name] = get_string('customfieldambiguous', 'paygw_payway', s($fieldname));
            } else if (!isset($sources[$source])) {
                $errors[$name] = get_string('customfieldsourceinvalid', 'paygw_payway', s($fieldname));
            }
        }
        return $errors;
    }

    /**
     * Format the data into the format expected for sending in a transaction
     *
     * Important: When creating a transaction, PayWay expects an array with keys of fieldIds - 
     * these fieldIds are simply the numbers 1,2,3,4 and are not actual ids but are instead better thought of as "slots".
     * We do not ever store the PayWay fieldId as they may be reordered in the PayWay frontend which would cause the data
     * to be linked to the wrong field on the Moodle side.
     * Therefore, we store $this->mappings as PayWay field names -> Moodle field names,
     * and then map these at transaction time to their relevant slot (fieldId) (this is what $definitions is).
     *
     * @param int $userid Paying user's ID.
     * @param array $definitions Current PayWay definitions indexed by slot ID.
     * @return result<array> Transaction parameters, or a safe error.
     */
    public function get_transaction_values(int $userid, array $definitions): result {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $errors = $this->validate($definitions);
        if ($errors) {
            return result::err(implode(' ', $errors));
        }
        if (!$this->mappings) {
            return result::ok([]);
        }
        $sources = self::get_sources();
        $user = \core_user::get_user($userid, implode(',', self::get_standard_fields()), MUST_EXIST);
        $profilevalues = [];
        foreach (profile_get_user_fields_with_data($userid) as $profilefield) {
            $profilevalues[$profilefield->fieldid] = $profilefield->data;
        }
        $values = [];
        foreach ($this->mappings as $name => $source) {
            $name = (string)$name;
            $matches = array_filter($definitions, fn($field) => $field['fieldName'] === $name);
            $id = array_key_first($matches);
            if (!isset($sources[$source])) {
                return result::err(get_string('customfieldsourceinvalid', 'paygw_payway', s($name)));
            }
            [$type, $field] = explode(':', $source, 2);
            if ($type === 'user') {
                $value = $user->{$field};
            } else {
                $value = $profilevalues[(int)$field] ?? '';
            }
            $value = (string)$value;
            if ($value === '') {
                continue;
            }
            // Match any byte outside printable ASCII: space (0x20) through tilde (0x7E).
            // This rejects control characters and non-ASCII text that PayWay does not accept.
            if (preg_match('/[^\x20-\x7E]/', $value)) {
                return result::err(get_string('customfieldvalueinvalid', 'paygw_payway', s($name)));
            }
            $values['customField' . $id] = substr($value, 0, 60);
        }
        return result::ok($values);
    }
}
