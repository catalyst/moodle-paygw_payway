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

use paygw_payway\local\api_credential;
use paygw_payway\local\environment;
use coding_exception;
use ValueError;

/**
 * Contains class for PayWay payment gateway.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    /**
     * Supported currencies list
     * @return array
     */
    public static function get_supported_currencies(): array {
        // Only AUD is supported,
        // see https://www.payway.com.au/docs/rest.html#process-token-payment
        // under "currency".
        return ['AUD'];
    }

    /**
     * Configuration form for the gateway instance
     *
     * Use $form->get_mform() to access the \MoodleQuickForm instance
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'publishablekey', get_string('publishablekey', 'paygw_payway'));
        $mform->setType('publishablekey', PARAM_TEXT);
        $mform->addHelpButton('publishablekey', 'publishablekey', 'paygw_payway');
        $mform->addRule('publishablekey', null, 'required');

        $mform->addElement('static', 'publishablekeystatuscheck', '', get_string('publishablekeystatuscheck', 'paygw_payway'));

        $mform->addElement('text', 'secretkey', get_string('secretkey', 'paygw_payway'));
        $mform->setType('secretkey', PARAM_TEXT);
        $mform->addHelpButton('secretkey', 'secretkey', 'paygw_payway');
        $mform->addRule('secretkey', null, 'required');

        $mform->addElement('static', 'secretkeystatuscheck', '', get_string('secretkeystatuscheck', 'paygw_payway'));

        $environments = array_column(environment::cases(), 'value');
        $environmentlabels = array_map(fn($env) => get_string('environment:' . $env, 'paygw_payway'), $environments);
        $options = array_combine($environments, $environmentlabels);
        $mform->addElement('select', 'environment', get_string('environment', 'paygw_payway'), $options);
        $mform->addHelpButton('environment', 'environment', 'paygw_payway');
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors form errors (passed by reference)
     */
    public static function validate_gateway_form(
        \core_payment\form\account_gateway $form,
        \stdClass $data,
        array $files,
        array &$errors
    ): void {
        // Check environment is one the enums values.
        try {
            environment::from($data->environment);
        } catch (ValueError $e) {
            $errors['environment'] = get_string('error:environment', 'paygw_payway');
        }

        // Check key formatting is good for both keys.
        try {
            api_credential::parse_and_validate_key_name($data->secretkey, api_credential::TYPE_SECRET);
        } catch (coding_exception $e) {
            // Use $e->a (the raw hint) rather than getMessage(), which has a "coding error" prefix.
            $errors['secretkey'] = get_string('error:invalidkey', 'paygw_payway', $e->a);
        }
        try {
            api_credential::parse_and_validate_key_name($data->publishablekey, api_credential::TYPE_PUBLISHABLE);
        } catch (coding_exception $e) {
            $errors['publishablekey'] = get_string('error:invalidkey', 'paygw_payway', $e->a);
        }
    }
}
