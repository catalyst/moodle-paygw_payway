# moodle-paygw_payway
Moodle payment gateway plugin for Westpac PayWay

# Testing
This plugin is covered by both phpunit and behat tests.

Note, there are some Behat tests that only run if you give it a real PayWay sandbox API token:

```
PAYGW_PAYWAY_TEST_PUBLISHABLE_KEY=xxxx \
PAYGW_PAYWAY_TEST_SECRET_KEY=yyyy \
php admin/tool/behat/cli/run.php --tags="@paygw_payway" --profile=chrome
```
