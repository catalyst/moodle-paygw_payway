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

/**
 * Language strings
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['checkgateway'] = 'Gateway key check';
$string['connectiontest'] = 'HTTP {$a->status} response returned from PayWay API using secret key {$a->keyname}';
$string['connectiontestcannotparse'] = 'Unexpected key format ';
$string['connectiontestunknown'] = 'Unknown error during connection test: {$a}';
$string['email:delivered'] = 'Product delivered to user';
$string['email:item'] = 'Item (component / area / item id)';
$string['email:networkerror:intro'] = 'Network issues were detected during payment processing. A potential duplicate payment may have occurred.';
$string['email:paymentprocessingerror:subject'] = 'PayWay: payment processing requires review';
$string['email:receiptnumber'] = 'Receipt number';
$string['email:response'] = 'Response';
$string['email:responseerror:intro'] = 'PayWay accepted or may have accepted a payment, but Moodle could not complete or verify the payment. Manual review is required.';
$string['email:status'] = 'Status';
$string['email:transactionid'] = 'Transaction ID';
$string['email:unexpectedstatus:intro'] = 'An unexpected PayWay payment status was encountered and may require manual review.';
$string['email:unexpectedstatus:subject'] = 'PayWay: unexpected payment status (transaction {$a})';
$string['email:user'] = 'User';
$string['entercourse'] = 'Enter course';
$string['environment'] = 'Environment';
$string['environment:live'] = 'Live';
$string['environment:sandbox'] = 'Sandbox';
$string['environment_help'] = 'Sandbox credentials can be used in the Sandbox environment for testing purposes.';
$string['error:environment'] = 'Environment value was not valid';
$string['error:invalidkey'] = 'Invalid key format: {$a}';
$string['error:paymentsetupfailed'] = 'Unable to load the credit card payment form. Please try again.';
$string['failedpayment'] = 'Payment could not be completed. Please check your details or try another card.';
$string['gatewaydescription'] = 'Pay using your credit card using Westpac PayWay';
$string['gatewayname'] = 'PayWay';
$string['gatewaystatuscheck'] = '<a href="/report/status/index.php?detail=paygw_payway_gateway">Check gateway validation.</a>';
$string['invalidenvironment'] = 'Invalid environment value: {$a}';
$string['livemerchantidinvalidformat'] = 'When in live environment, the merchant id must be 8 numeric digits, was {$a}';
$string['managegateway'] = 'Edit gateway settings';
$string['merchantid'] = 'Merchant ID';
$string['merchantid_help'] = 'A Merchant Id is required in order to process credit card payments. It is a 8 digit number supplied by Westpac. When in sandbox, this is always "TEST"';
$string['misconfiguration'] = 'Misconfiguration detected: {$a}';
$string['missingrequiredfield'] = 'Missing required configuration for: {$a}';
$string['notificationemail'] = 'Notification email';
$string['notificationemail_help'] = 'Notified when an attempted payment encounters an unexpected state (such as a network error) or status. Some payment statuses will trigger a notification for further manual review. See the plugins README for more information';
$string['orderdetails'] = 'Order details:';
$string['pay'] = 'Pay';
$string['paymentalreadyprocessing'] = 'This payment is already being processed. Please wait and try again.';
$string['paymentdetailsinvalid'] = 'The payment details could not be processed. Please check them and try again.';
$string['paymentfailed'] = 'The payment provider could not process this payment.';
$string['paymentretryfailed'] = 'The payment service is still unavailable. Please try again later.';
$string['paymentretrying'] = 'The payment service is temporarily busy. Retrying shortly.';
$string['paymentretryingcountdown'] = 'The payment service is temporarily busy. Retrying in {$a} seconds.';
$string['paymentsuccessful'] = 'Payment successful';
$string['paytitle'] = 'Pay using Westpac PayWay';
$string['pluginname'] = 'PayWay';
$string['privacy:metadata'] = 'No user data is stored';
$string['publishablekey'] = 'Publishable API key';
$string['publishablekey_help'] = 'Your publishable API key is used by payway.js to send credit card details directly from the browser to PayWay. It is usually in the form TXXXXX_PUB_xxxx';
$string['publishablekeystatuscheck'] = 'Note - There is currently no way to validate the publishable key.';
$string['sandboxhint'] = 'SANDBOX: <a href="https://www.payway.com.au/docs/net.html#test-card-numbers" target="_blank" rel="noopener noreferrer">Test card numbers</a>.';
$string['sandboxmerchantidmustbetest'] = 'When in sandbox environment, the merchant id must be "TEST", was {$a}';
$string['secretkey'] = 'Secret API key';
$string['secretkey_help'] = 'Your secret API key allows your server to process payments and provides full access to the API. It is usually in the form TXXXXX_SEC_xxxx';
