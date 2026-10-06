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
$string['configurationlocked'] = 'The configuration is currently locked by another task (most likely secret key auto rotation). Please try again shortly.';
$string['connectiontest'] = 'HTTP {$a->status} response returned from PayWay API using secret key {$a->keyname}';
$string['connectiontestcannotparse'] = 'Unexpected key format ';
$string['connectiontestunknown'] = 'Unknown error during connection test: {$a}';
$string['customfieldambiguous'] = 'More than one PayWay custom field is named {$a}. Give each field a unique name in PayWay.';
$string['customfieldmapping'] = 'User profile mapping';
$string['customfieldmapping_help'] = 'Must be printable ASCII text. Values longer than 60 characters will be truncated to 60 characters. Empty values are not sent.';
$string['customfieldmappingcleared'] = 'The previous mapping will be cleared when you save.';
$string['customfieldmappinginvalid'] = 'The custom field mapping configuration is invalid. Reconfigure the gateway custom fields.';
$string['customfieldmissingsource'] = 'Previously selected user profile field (removed)';
$string['customfieldnotavailable'] = 'Not available in PayWay';
$string['customfieldnotconfigured'] = 'No field configured in PayWay';
$string['customfieldnotmapped'] = 'Do not send';
$string['customfieldprofilesource'] = 'Custom user field: {$a}';
$string['customfieldremoved'] = 'PayWay custom field {$a} no longer exists. Clear this mapping or restore the field in PayWay.';
$string['customfieldremovedmappings'] = 'PayWay fields no longer configured: {$a}. Their previous mappings will be cleared when you save.';
$string['customfields'] = 'PayWay custom fields';
$string['customfieldslot'] = 'Custom field {$a}';
$string['customfieldsourceinvalid'] = 'The source for custom field {$a} is no longer available. Choose another user profile field or clear this mapping.';
$string['customfieldssavefirst'] = 'Save your API credentials, then reopen this form to load the field names from PayWay.';
$string['customfieldsunavailable'] = 'Unable to load custom fields from PayWay. Existing mappings have been retained. Check your API credentials and connection, then try again.';
$string['customfieldusersource'] = 'User profile: {$a}';
$string['customfieldvalueinvalid'] = 'The profile value for PayWay custom field {$a} must contain only printable ASCII characters. Update your profile or contact the site administrator.';
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
$string['error:customfieldconfiguration'] = 'The PayWay custom field configuration is unavailable or out of date. Contact the site administrator. No payment has been attempted.';
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
$string['privacy:metadata:payway'] = 'Personal data is sent to Westpac PayWay to process payments. No additional payment data is stored locally by this gateway.';
$string['privacy:metadata:payway:customfields'] = 'Administrator-selected standard and custom user profile values are sent as transaction custom fields.';
$string['privacy:metadata:payway:ipaddress'] = 'The paying user\'s IP address is sent for fraud detection.';
$string['privacy:metadata:payway:userid'] = 'The paying user\'s Moodle ID is sent as the customer reference.';
$string['publishablekey'] = 'Publishable API key';
$string['publishablekey_help'] = 'Your publishable API key is used by payway.js to send credit card details directly from the browser to PayWay. It is usually in the form TXXXXX_PUB_xxxx';
$string['publishablekeystatuscheck'] = 'Note - There is currently no way to validate the publishable key. It does not expire.';
$string['sandboxhint'] = 'SANDBOX: <a href="https://www.payway.com.au/docs/net.html#test-card-numbers" target="_blank" rel="noopener noreferrer">Test card numbers</a>.';
$string['sandboxmerchantidmustbetest'] = 'When in sandbox environment, the merchant id must be "TEST", was {$a}';
$string['secretkey'] = 'Secret API key';
$string['secretkey_help'] = 'Your secret API key allows your server to process payments and provides full access to the API. It is usually in the form TXXXXX_SEC_xxxx';
$string['secretkeyrenewalnotice'] = 'Secret key is automatically checked for renewal daily.';
$string['task:checktokenrenewal'] = 'Check for new secret REST API token';
