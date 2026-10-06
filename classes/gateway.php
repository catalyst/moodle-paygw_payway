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

use paygw_payway\local\api_configuration;
use paygw_payway\local\environment;
use coding_exception;
use core\url;
use html_writer;
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
     * Acquire the lock used while a gateway configuration is being changed.
     *
     * @param int $gatewayid gateway id, or account id when creating a gateway
     * @param int $timeout lock acquisition timeout in seconds
     * @param int $lifetime lock lifetime
     * @return \core\lock\lock|false
     */
    public static function get_configuration_lock(int $gatewayid, int $timeout, int $lifetime) {
        $lockfactory = \core\lock\lock_config::get_lock_factory('paygw_payway');
        return $lockfactory->get_lock('gateway_config:' . $gatewayid, $timeout, $lifetime);
    }

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

        $mform->addElement('text', 'publishablekey', get_string('publishablekey', 'paygw_payway'), ['size' => 40]);
        $mform->setType('publishablekey', PARAM_TEXT);
        $mform->addHelpButton('publishablekey', 'publishablekey', 'paygw_payway');
        $mform->addRule('publishablekey', null, 'required');

        $mform->addElement('static', 'publishablekeystatuscheck', '', get_string('publishablekeystatuscheck', 'paygw_payway'));

        $mform->addElement('passwordunmask', 'secretkey', get_string('secretkey', 'paygw_payway'));
        $mform->setType('secretkey', PARAM_TEXT);
        $mform->addHelpButton('secretkey', 'secretkey', 'paygw_payway');
        $mform->addRule('secretkey', null, 'required');

        $mform->addElement(
            'static',
            'secretkeyrenewalnotice',
            '',
            html_writer::link(
                new url('/admin/tasklogs.php', ['filter' => 'paygw_payway\task\check_token_renewal_adhoc']),
                get_string('secretkeyrenewalnotice', 'paygw_payway')
            )
        );

        $environments = array_column(environment::cases(), 'value');
        $environmentlabels = array_map(fn($env) => get_string('environment:' . $env, 'paygw_payway'), $environments);
        $options = array_combine($environments, $environmentlabels);
        $mform->addElement('select', 'environment', get_string('environment', 'paygw_payway'), $options);
        $mform->addHelpButton('environment', 'environment', 'paygw_payway');
        $mform->addRule('environment', null, 'required');

        $mform->addElement('text', 'merchantid', get_string('merchantid', 'paygw_payway'));
        $mform->setType('merchantid', PARAM_TEXT);
        $mform->addHelpButton('merchantid', 'merchantid', 'paygw_payway');
        $mform->addRule('merchantid', null, 'required');

        $mform->addElement('static', 'gatewaystatuscheck', '', get_string('gatewaystatuscheck', 'paygw_payway'));

        $mform->addElement('text', 'notificationemail', get_string('notificationemail', 'paygw_payway'));
        $mform->setType('notificationemail', PARAM_EMAIL);
        $mform->addHelpButton('notificationemail', 'notificationemail', 'paygw_payway');
        $mform->addRule('notificationemail', null, 'email', null, 'client');

        \paygw_payway\form\payway_customfield_options::add_to_form($form);
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
        // Here, we offload all validation to the api_configuration class to keep
        // the validation in sync with elsewhere in the app.

        $envresult = api_configuration::validate_and_parse_environment($data->environment);
        if ($envresult->is_err()) {
            $errors['environment'] = $envresult->error;
        } else {
            $environment = $envresult->unwrap();
        }

        if (isset($environment)) {
            $merchantresult = api_configuration::validate_merchantid($data->merchantid, $environment);
            if ($merchantresult->is_err()) {
                $errors['merchantid'] = $merchantresult->error;
            }
        }

        $secretkeyvalidation = api_configuration::parse_and_validate_key_name(
            $data->secretkey,
            api_configuration::KEY_TYPE_SECRET
        );
        if ($secretkeyvalidation->is_err()) {
            $errors['secretkey'] = $secretkeyvalidation->error;
        }

        $publishablekeyverification = api_configuration::parse_and_validate_key_name(
            $data->publishablekey,
            api_configuration::KEY_TYPE_PUBLISHABLE
        );
        if ($publishablekeyverification->is_err()) {
            $errors['publishablekey'] = $publishablekeyverification->error;
        }

        // Validate against the submitted credentials, which may differ from those
        // used to discover the fields when the form was first opened.
        $mappingresult = \paygw_payway\local\custom_fields::validate_and_parse_stored_config($data);
        if ($mappingresult->is_err()) {
            $errors['customfieldsnotice'] = $mappingresult->error;
        }
        if (!$errors && $mappingresult->unwrap()->has_mappings()) {
            $credentials = api_configuration::validate_and_parse_stored_config($data);
            if ($credentials->is_ok()) {
                $configuration = $credentials->unwrap();
                $fields = \paygw_payway\local\payway_api::new($configuration)->get_custom_fields();
                if ($fields->is_err()) {
                    $errors['customfieldsnotice'] = $fields->error;
                } else {
                    $mappingerrors = $configuration->customfields->validate($fields->unwrap());
                    if ($mappingerrors) {
                        // Includes removed fields that no longer have a visible selector.
                        $errors['customfieldsnotice'] = implode(' ', $mappingerrors);
                    }
                }
            }
        }

        // This is a bit hacky, but we also check if the configuration is locked atm,
        // e.g. the automated secret renewal might be running at the same time.
        // so try take out a lock and add a form error if we can't.
        // It won't catch all edge cases but should catch most.
        if (!empty($data->id)) {
            // Timeout = 0 seconds (if locked don't wait - just exit early and show form error),
            // Lifetime = 5 seconds (not really applicable because we release it immediately, but just in case this explodes).
            $lock = self::get_configuration_lock((int)$data->id, timeout: 0, lifetime: 5);
            if (!$lock) {
                $errors['secretkey'] = get_string('configurationlocked', 'paygw_payway');
            } else {
                $lock->release();
            }
        }
    }
}
