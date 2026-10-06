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

namespace paygw_payway\form;

use core_payment\form\account_gateway;
use paygw_payway\local\api_configuration;
use paygw_payway\local\custom_fields;
use paygw_payway\local\payway_api;
use paygw_payway\local\result;

/**
 * Reusable custom-field configuration section for Moodle's gateway mform.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payway_customfield_options {
    /**
     * Add fields to form.
     *
     * @param account_gateway $form Gateway form.
     */
    public static function add_to_form(account_gateway $form): void {
        // This is a bit bleh, but necessary,
        // we need to extract the gateway from the form, to lookup the credentials, in order to query the customfields in PayWay.
        $mform = $form->get_mform();
        $config = json_decode($form->get_gateway_persistent()->get('config') ?: '{}');

        // Parse customfield config.
        $mappingresult = custom_fields::validate_and_parse_stored_config($config ?? (object)[]);
        $customfields = $mappingresult->is_ok() ? $mappingresult->unwrap() : custom_fields::from_stored_config(new \stdClass());
        $mappings = $customfields->mappings;
        $mform->addElement('header', 'customfieldsheader', get_string('customfields', 'paygw_payway'));

        // Get fields from API.
        $discovery = self::discover_fields($config);

        // Error getting from API - just show config but not editable.
        if ($discovery->is_err()) {
            $mform->addElement('static', 'customfieldsnotice', '', $discovery->error);
            // Preserve named mappings without pretending these are current API fields.
            foreach ($mappings as $fieldname => $source) {
                $key = custom_fields::get_field_key((string)$fieldname);
                $name = 'customfieldmappings[' . $key . '][name]';
                $mform->addElement('hidden', $name, (string)$fieldname);
                $mform->setType($name, PARAM_RAW);
                $mform->setConstant($name, (string)$fieldname);
                $name = custom_fields::get_element_name((string)$fieldname);
                $mform->addElement('hidden', $name, $source);
                $mform->setType($name, PARAM_RAW_TRIMMED);
                $mform->setConstant($name, $source);
            }
            return;
        }

        // Else was able to get from API, add the fields as editable fields.
        $definitions = $discovery->unwrap();
        $removed = array_diff(array_keys($mappings), array_column($definitions, 'fieldName'));
        $notice = $removed
            ? get_string('customfieldremovedmappings', 'paygw_payway', s(implode(', ', $removed)))
            : '';
        $mform->addElement('static', 'customfieldsnotice', '', $notice);
        $sources = ['' => get_string('customfieldnotmapped', 'paygw_payway')] + custom_fields::get_sources();
        foreach ($definitions as $id => $definition) {
            $fieldname = $definition['fieldName'];
            $key = custom_fields::get_field_key($fieldname);
            $hiddenname = 'customfieldmappings[' . $key . '][name]';
            $mform->addElement('hidden', $hiddenname, $fieldname);
            $mform->setType($hiddenname, PARAM_RAW);
            $mform->setConstant($hiddenname, $fieldname);
            $name = custom_fields::get_element_name($fieldname);
            $label = s($fieldname);
            $options = $sources;
            $saved = $mappings[$fieldname] ?? '';
            if ($saved !== '' && !isset($options[$saved])) {
                $options[$saved] = get_string('customfieldmissingsource', 'paygw_payway');
            }
            $mform->addElement('select', $name, $label, $options);
            $mform->setType($name, PARAM_RAW_TRIMMED);
            $mform->setDefault($name, $saved);
            $mform->addHelpButton($name, 'customfieldmapping', 'paygw_payway');
            if (!empty($definition['help']) && is_string($definition['help'])) {
                $mform->addElement('static', 'customfieldhelp' . $key, '', s($definition['help']));
            }
        }
    }

    /**
     * Discover fields, keeping credential and API failures out of the rendering flow.
     *
     * @param object $config Stored gateway configuration.
     * @return result Field definitions or a notice explaining why discovery is unavailable.
     */
    private static function discover_fields(object $config): result {
        $credentials = api_configuration::validate_and_parse_stored_config($config);
        if ($credentials->is_err()) {
            return result::err(get_string('customfieldssavefirst', 'paygw_payway'));
        }
        return payway_api::new($credentials->unwrap())->get_custom_fields();
    }
}
