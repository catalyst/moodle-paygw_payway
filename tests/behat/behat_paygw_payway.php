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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Moodle\BehatExtension\Exception\SkippedException;

/**
 * Behat steps definitions for the PayWay payment gateway.
 *
 * Most scenarios mock payway.js and force the outcome of the process_payment webservice, since real
 * PayWay credentials are not available in most test environments.
 *
 * A small number of scenarios use real credentials, which can be provided by defining
 * PAYGW_PAYWAY_TEST_PUBLISHABLE_KEY and PAYGW_PAYWAY_TEST_SECRET_KEY in config.php.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_paygw_payway extends behat_base {
    /**
     * Configure the PayWay gateway for a payment account with placeholder credentials.
     * Since processing is mocked/forced in these scenarios, the credentials themselves are never used.
     *
     * @Given /^PayWay is configured for payment account "(?P<account_name>(?:[^"]|\\")*)"$/
     * @param string $accountname
     */
    public function payway_is_configured_for_payment_account(string $accountname): void {
        $this->save_payway_gateway_config(
            $accountname,
            'APPLICATION_PUB_placeholder',
            'APPLICATION_SEC_placeholder'
        );
    }

    /**
     * Configure the PayWay gateway for a payment account using real sandbox credentials, so that
     * payway.js and the PayWay REST API can genuinely be exercised.
     *
     * Skips the scenario if PAYGW_PAYWAY_TEST_PUBLISHABLE_KEY and PAYGW_PAYWAY_TEST_SECRET_KEY are
     * not defined in config.php.
     *
     * @Given /^PayWay is configured for payment account "(?P<account_name>(?:[^"]|\\")*)" with real sandbox credentials$/
     * @param string $accountname
     */
    public function payway_is_configured_with_real_sandbox_credentials(string $accountname): void {
        $pub = getenv('PAYGW_PAYWAY_TEST_PUBLISHABLE_KEY');
        $sec = getenv('PAYGW_PAYWAY_TEST_SECRET_KEY');

        if (empty($pub) || empty($sec)) {
            throw new SkippedException(
                'To run PayWay tests with real sandbox credentials you must define ' .
                'PAYGW_PAYWAY_TEST_PUBLISHABLE_KEY and PAYGW_PAYWAY_TEST_SECRET_KEY as environment variables.'
            );
        }

        $this->save_payway_gateway_config(
            $accountname,
            $pub,
            $sec
        );
    }

    /**
     * Saves sandbox PayWay gateway configuration onto an existing payment account.
     *
     * @param string $accountname
     * @param string $publishablekey
     * @param string $secretkey
     */
    private function save_payway_gateway_config(string $accountname, string $publishablekey, string $secretkey): void {
        global $DB;
        \core\plugininfo\paygw::enable_plugin('payway', 1);

        $accountid = $DB->get_field('payment_accounts', 'id', ['name' => $accountname], MUST_EXIST);

        \core_payment\helper::save_payment_gateway((object) [
            'accountid' => $accountid,
            'gateway' => 'payway',
            'enabled' => 1,
            'config' => json_encode([
                'publishablekey' => $publishablekey,
                'secretkey' => $secretkey,
                'environment' => 'sandbox',
            ]),
        ]);
    }

    /**
     * Replaces window.payway with a fake implementation, so the trusted credit card frame can be
     * created and a token obtained without contacting the real PayWay servers.
     *
     * Must be run on a page before the PayWay payment modal is opened.
     *
     * @Given /^the PayWay JS library is mocked$/
     */
    public function the_payway_js_library_is_mocked(): void {
        $this->execute_script(<<<JS
            window.payway = {
                createCreditCardFrame: function(options, createdCallback) {
                    if (options.onValid) {
                        options.onValid();
                    }
                    createdCallback(null, {
                        getToken: function(tokenCallback) {
                            tokenCallback(null, {singleUseTokenId: 'behat-fake-single-use-token'});
                        },
                        destroy: function() {},
                    });
                },
            };
        JS);
    }

    /**
     * Forces the outcome of the paygw_payway_process_payment webservice, since real PayWay
     * processing is not yet implemented.
     *
     * @Given /^PayWay payment processing is forced to (succeed|fail)$/
     * @param string $outcome
     */
    public function payway_payment_processing_is_forced_to(string $outcome): void {
        set_config('behat_forced_payment_status', $outcome === 'succeed' ? 'ok' : 'exception', 'paygw_payway');
    }

    /**
     * Forces an artificial delay in the paygw_payway_get_config_for_js webservice, so the loading
     * placeholder can reliably be observed before the credit card form replaces it.
     *
     * @Given /^PayWay config lookup is delayed by "(?P<seconds>\d+)" seconds?$/
     * @param string $seconds
     */
    public function payway_config_lookup_is_delayed_by_seconds(string $seconds): void {
        set_config('behat_forced_delay_seconds', (int) $seconds, 'paygw_payway');
    }

    /**
     * Sets up "Enrolment on payment" for a course using an existing payment account, without
     * going through the admin UI, so scenarios can focus on testing the payment flow itself.
     *
     * @Given /^enrolment on payment is set up for course "(?P<shortname>(?:[^"]|\\")*)" using payment account "(?P<account_name>(?:[^"]|\\")*)" costing "(?P<cost>[^"]*)" "(?P<currency>[^"]*)"$/
     * @param string $shortname
     * @param string $accountname
     * @param string $cost
     * @param string $currency
     */
    public function enrolment_on_payment_is_set_up_for_course(
        string $shortname,
        string $accountname,
        string $cost,
        string $currency
    ): void {
        global $DB;

        \core\plugininfo\enrol::enable_plugin('fee', 1);

        $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $accountid = $DB->get_field('payment_accounts', 'id', ['name' => $accountname], MUST_EXIST);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        enrol_get_plugin('fee')->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint1' => $accountid,
            'cost' => $cost,
            'currency' => $currency,
            'roleid' => $studentroleid,
        ]);
    }
}
