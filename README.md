# moodle-paygw_payway
Moodle payment gateway plugin for Westpac PayWay

**This plugin is currently under construction!**

# Feature support
## Supported
- Credit/Debit card payments
- Automated token renewal
- Transaction custom fields

## Not Supported
- 3DSecure
- Refunds
- Bank account payments

# Important
This plugin processes credit/debit card payments in conjunction with Westpac PayWay. It is important to note what this plugin will do when certain statuses are returned. [See the full list of statuses in the PayWay API Documentation](https://www.payway.com.au/docs/rest.html#transaction-status).

While most credit/debit card payments will return either `approved` or `declined`, it is technically possible to return other statuses. Most of these statuses usually occur when processing bank account payments (which are not supported), however, in the case of any of these statuses are encountered, the plugin will handle them as follows:

| Status      | Explanation                                                                    | Will the order be delivered    | Will a follow up notification be sent |
|-------------|--------------------------------------------------------------------------------|--------------------------------|---------------------------------------|
| `approved`  | A successful transaction.                                                      | Yes                            | No                                    |
| `approved*` | A successful transaction during period when it may be declined or dishonoured. | Yes                            | Yes                                   |
| `pending`   | Currently processing.                                                          | Yes                            | Yes                                   |
| `declined`  | An unsuccessful transaction.                                                   | No                             | No                                    |
| `voided`    | Originally approved, but then cancelled prior to settlement.                   | No                             | Yes                                   |
| `suspended` | An unusual payment for you to review.                                          | No                             | Yes                                   |

Unusual statuses will notify the configured gateway's `notificationemail` email for manual review. **This plugin only checks the status at the moment of payment** - meaning asynchronous statuses such as `approved*` and `pending` are not able to be automatically handled - this is a limitation of the Moodle payment gateway API. The intention is that you will manually reconcile these after receiving a notification from the plugin.

# Testing
This plugin is covered by both phpunit and behat tests.

Note, there are some Behat tests that only run if you give it a real PayWay sandbox API token:

```
PAYGW_PAYWAY_TEST_PUBLISHABLE_KEY=xxxx \
PAYGW_PAYWAY_TEST_SECRET_KEY=yyyy \
php admin/tool/behat/cli/run.php --tags="@paygw_payway" --profile=chrome
```
