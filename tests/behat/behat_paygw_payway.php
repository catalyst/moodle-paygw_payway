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
use Behat\Mink\Exception\ExpectationException;

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
    /** @var \core\lock\lock|null Lock held for the current scenario. */
    private $configurationlock;

    /**
     * Open the PayWay gateway settings page for a payment account.
     *
     * @Given /^I am on the PayWay configuration page for payment account "(?P<account_name>(?:[^"]|\\")*)"$/
     * @param string $accountname
     */
    public function i_am_on_payway_configuration_page(string $accountname): void {
        global $DB;
        $accountid = $DB->get_field('payment_accounts', 'id', ['name' => $accountname], MUST_EXIST);
        $gatewayid = $DB->get_field('payment_gateways', 'id', [
            'accountid' => $accountid,
            'gateway' => 'payway',
        ], MUST_EXIST);
        $url = new moodle_url('/payment/manage_gateway.php', ['id' => $gatewayid]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Hold the configuration lock while the browser submits the settings form.
     *
     * @Given /^the PayWay configuration lock is held for payment account "(?P<account_name>(?:[^"]|\\")*)"$/
     * @param string $accountname
     */
    public function the_payway_configuration_lock_is_held(string $accountname): void {
        global $DB;
        $accountid = $DB->get_field('payment_accounts', 'id', ['name' => $accountname], MUST_EXIST);
        $gatewayid = $DB->get_field('payment_gateways', 'id', [
            'accountid' => $accountid,
            'gateway' => 'payway',
        ], MUST_EXIST);
        $this->configurationlock = \paygw_payway\gateway::get_configuration_lock($gatewayid);
        if (!$this->configurationlock) {
            throw new ExpectationException('Could not acquire the PayWay configuration lock.', $this->getSession());
        }
    }

    /**
     * Release the configuration lock held by the current scenario.
     *
     * @Given /^the PayWay configuration lock is released$/
     */
    public function the_payway_configuration_lock_is_released(): void {
        if ($this->configurationlock) {
            $this->configurationlock->release();
            $this->configurationlock = null;
        }
    }

    /**
     * Verify that the PayWay configuration lock is exclusive and released correctly.
     *
     * @Given /^the PayWay configuration lock can be acquired and released$/
     */
    public function the_payway_configuration_lock_can_be_acquired_and_released(): void {
        $lock = \paygw_payway\gateway::get_configuration_lock(12345);
        if (!$lock) {
            throw new ExpectationException('Could not acquire the PayWay configuration lock.', $this->getSession());
        }
        $lock->release();

        $lock = \paygw_payway\gateway::get_configuration_lock(12345);
        if (!$lock) {
            throw new ExpectationException('The PayWay configuration lock was not released.', $this->getSession());
        }
        $lock->release();
    }

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
                'merchantid' => 'TEST',
                'notificationemail' => 'notifications@example.com',
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
                            tokenCallback(null, {singleUseTokenId: '2bcec36f-7b02-43db-b3ec-bfb65acfe272'});
                        },
                        destroy: function() {},
                    });
                },
            };
        JS);
    }

    /**
     * Enter sandbox card details into PayWay's trusted frame.
     *
     * PayWay's test-card table uses # for the expiry year's decade. The
     * symbolic forms below preserve each card's documented final year digit
     * while calculating a suitable future decade for the current date.
     * See https://www.payway.com.au/docs/rest.html#reference-test-card-numbers
     *
     * @Given /^I enter PayWay card number "(\d+)" expiry "(\d{2})\/(\d{2}|future[05-9])" CVV "(\d+)" name "([^"]+)"$/
     * @param string $number
     * @param string $month
     * @param string $year
     * @param string $securitycode
     * @param string $name
     */
    public function i_enter_payway_card_details(
        string $number,
        string $month,
        string $year,
        string $securitycode,
        string $name
    ): void {
        if (str_starts_with($year, 'future')) {
            $finaldigit = substr($year, -1);
            $decade = intdiv((int) date('Y'), 10) % 10 + 1;
            $year = $decade . $finaldigit;
        }

        $iframe = $this->spin(function () {
            return $this->getSession()->getPage()->find('css', '#payway-credit-card iframe.payway-credit-card-iframe');
        }, false, false, new ExpectationException('The PayWay credit card iframe was not found.', $this->getSession()));

        // The PayWay iframe has no name, which Moodle's Selenium driver requires to switch frames.
        $this->execute_js_on_node($iframe, "{{ELEMENT}}.name = 'behat-payway-card';");
        $this->getSession()->switchToIFrame('behat-payway-card');
        try {
            $cardinput = $this->spin(function () {
                $field = $this->getSession()->getPage()->find('css', 'input.payway-number-formatted');
                return $field && $field->isVisible() ? $field : false;
            }, false, false, new ExpectationException('The PayWay card number input was not shown.', $this->getSession()));

            $cardinput->setValue($number);
            $this->find('css', 'select[name="expiryDateMonth"]')->selectOption($month);
            $this->find('css', 'select[name="expiryDateYear"]')->selectOption($year);
            $this->find('css', '#cvn')->setValue($securitycode);
            $this->find('css', '#cardholderName')->setValue($name);
        } finally {
            $this->getSession()->switchToIFrame();
        }
    }

    /**
     * Simulates a payment webservice failure without delivering the order.
     *
     * @Given /^PayWay payment processing is forced to fail$/
     */
    public function payway_payment_processing_is_forced_to_fail(): void {
        set_config('behat_force_payment_error', 1, 'paygw_payway');
    }

    /**
     * Configure a BEHAT-only sequence of simulated PayWay API outcomes.
     * Supported values include approved, approved*, pending, declined, voided,
     * suspended, retry, invalid, servererror, networkerror, and responseerror.
     *
     * @Given /^PayWay API response sequence is "([^"]+)"$/
     * @param string $sequence comma-separated response names
     */
    public function payway_api_response_sequence_is(string $sequence): void {
        $responses = array_values(array_filter(array_map('trim', explode(',', $sequence))));
        set_config('behat_mock_payment_response_sequence', json_encode($responses), 'paygw_payway');
        set_config('behat_payment_attempts', '[]', 'paygw_payway');
    }

    /**
     * Verify that retrying the same request keeps its key and PayWay token.
     *
     * @Then /^PayWay should have retried the same payment request$/
     */
    public function payway_should_have_retried_the_same_payment_request(): void {
        $attempts = json_decode(get_config('paygw_payway', 'behat_payment_attempts') ?: '[]', true);
        if (count($attempts) !== 2 || $attempts[0] !== $attempts[1]) {
            throw new ExpectationException('PayWay did not retry the same token and key.', $this->getSession());
        }
    }

    /**
     * Verify that a subsequent manual submission uses a new idempotency key.
     *
     * @Then /^PayWay should have used distinct payment keys$/
     */
    public function payway_should_have_used_distinct_payment_keys(): void {
        $attempts = json_decode(get_config('paygw_payway', 'behat_payment_attempts') ?: '[]', true);
        if (count($attempts) !== 2 || $attempts[0]['idempotencykey'] === $attempts[1]['idempotencykey']) {
            throw new ExpectationException('PayWay did not use a fresh key for the new submission.', $this->getSession());
        }
    }

    /**
     * Set the countdown used by the BEHAT-only retry response.
     *
     * @Given /^PayWay retry delay is "(\d+)" seconds?$/
     * @param string $seconds
     */
    public function payway_retry_delay_is_seconds(string $seconds): void {
        set_config('behat_retry_after_seconds', (int) $seconds, 'paygw_payway');
    }

    /**
     * Wait for PayWay to complete a payment, allowing for its retry countdown.
     *
     * @Then /^I wait for PayWay payment success$/
     */
    public function i_wait_for_payway_payment_success(): void {
        $this->spin(
            function (): bool {
                return (bool) $this->getSession()->getPage()->find('css', '.modal.show .paygw-payway-success');
            },
            false,
            60,
            new ExpectationException('PayWay did not complete the payment within 60 seconds.', $this->getSession()),
            true
        );
    }

    /**
     * Forces an artificial delay in the paygw_payway_get_public_config_for_js webservice, so the loading
     * placeholder can reliably be observed before the credit card form replaces it.
     *
     * @Given /^PayWay config lookup is delayed by "(?P<seconds>\d+)" seconds?$/
     * @param string $seconds
     */
    public function payway_config_lookup_is_delayed_by_seconds(string $seconds): void {
        set_config('behat_forced_delay_seconds', (int) $seconds, 'paygw_payway');
    }

    /**
     * Assert the transient placeholder before Moodle waits for pending AJAX at the end of this step.
     *
     * @When /^I proceed with PayWay and see its loading placeholder$/
     */
    public function i_proceed_with_payway_and_see_its_loading_placeholder(): void {
        $button = $this->getSession()->getPage()->find('css', '.modal.show [data-action="proceed"]');
        if (!$button) {
            throw new ExpectationException('The PayWay proceed button was not found.', $this->getSession());
        }
        $button->click();

        $this->spin(function (): bool {
            $placeholder = $this->getSession()->getPage()->find('css', '.modal.show .bg-pulse-grey');
            return $placeholder && $placeholder->isVisible();
        }, false, 3, new ExpectationException('The PayWay loading placeholder was not shown.', $this->getSession()), true);

        $this->spin(function (): bool {
            $closebutton = $this->getSession()->getPage()->find('css', '.modal.show button[data-action="hide"]');
            return $closebutton && $closebutton->isVisible();
        }, false, 3, new ExpectationException('The PayWay modal close button was not shown.', $this->getSession()), true);
    }

    /**
     * Wait for the PayWay modal to stop covering the gateway selector before clicking its buttons.
     *
     * @Given /^I wait until only the gateway selector modal is open$/
     */
    public function i_wait_until_only_the_gateway_selector_modal_is_open(): void {
        try {
            $this->spin(function (): bool {
                $modals = $this->getSession()->getPage()->findAll('css', '.modal.show');
                if (count($modals) !== 1) {
                    return false;
                }

                $title = $modals[0]->find('css', '[data-region="title"]');
                return $title && trim($title->getText()) === get_string('selectpaymenttype', 'core_payment');
            }, false, false, new ExpectationException(
                'The gateway selector was not the only open modal.',
                $this->getSession()
            ));
        } catch (ExpectationException $e) {
            $modals = $this->getSession()->getPage()->findAll('css', '.modal');
            $details = [];
            foreach ($modals as $modal) {
                $title = $modal->find('css', '[data-region="title"]');
                $body = $modal->find('css', '[data-region="body"]');
                $details[] = sprintf(
                    '%s (class: %s; aria-hidden: %s; body: %s)',
                    $title ? trim($title->getText()) : 'untitled',
                    $modal->getAttribute('class'),
                    $modal->getAttribute('aria-hidden'),
                    $body ? trim($body->getText()) : 'no body'
                );
            }

            throw new ExpectationException(
                'Expected only the gateway selector modal; found: ' . implode(', ', $details),
                $this->getSession(),
                $e
            );
        }
    }

    /**
     * Sets up "Enrolment on payment" for a course using an existing payment account, without
     * going through the admin UI, so scenarios can focus on testing the payment flow itself.
     *
     * @Given /^fee enrolment for "([^"]+)" uses "([^"]+)" at "([^"]+)" "([^"]+)"$/
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
