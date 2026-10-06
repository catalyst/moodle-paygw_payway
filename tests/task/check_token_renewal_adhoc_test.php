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

namespace paygw_payway\task;

use advanced_testcase;
use coding_exception;
use core_payment\helper;
use paygw_payway\local\api_configuration;
use paygw_payway\local\api_response;
use paygw_payway\local\payway_api;
use paygw_payway\local\result;

/**
 * Tests for automated PayWay secret API token renewal.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\task\check_token_renewal_adhoc
 */
final class check_token_renewal_adhoc_test extends advanced_testcase {
    /** @var int Gateway record used by each test. */
    private int $gatewayid;

    /** @var int Account record used by each test. */
    private int $accountid;

    /** @var array<string, mixed> Initial gateway configuration. */
    private array $initialconfig = [
        'publishablekey' => 'APPLICATION_PUB_original',
        'secretkey' => 'APPLICATION_SEC_original',
        'environment' => 'sandbox',
        'merchantid' => 'TEST',
        'notificationemail' => 'notify@example.com',
    ];

    /**
     * Create a configured PayWay gateway for a test.
     */
    private function create_gateway(): void {
        $this->resetAfterTest();
        \core\plugininfo\paygw::enable_plugin('payway', 1);
        $account = helper::save_payment_account((object) [
            'name' => 'PayWay renewal test account',
            'idnumber' => '',
        ]);
        $this->accountid = (int) $account->get('id');
        $gateway = helper::save_payment_gateway((object) [
            'accountid' => $this->accountid,
            'gateway' => 'payway',
            'enabled' => 1,
            'config' => json_encode($this->initialconfig, JSON_THROW_ON_ERROR),
        ]);
        $this->gatewayid = (int) $gateway->get('id');
    }

    /**
     * Get current saved gateway configuration.
     *
     * @return object
     */
    private function get_config(): object {
        global $DB;
        $record = $DB->get_record('payment_gateways', ['id' => $this->gatewayid], 'config', MUST_EXIST);
        return json_decode($record->config, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Run a renewal task against deterministic PayWay API responses.
     *
     * @param api_response $latestresponse response from the latest-key endpoint
     * @param int $verificationstatus HTTP code from checking the replacement key
     * @param callable|null $duringverification optional callback during replacement-key verification
     * @param bool $lockavailable whether the lock can be acquired
     * @return int number of API clients created
     */
    private function run_mocked_task(
        api_response $latestresponse,
        int $verificationstatus = 200,
        ?callable $duringverification = null,
        bool $lockavailable = true
    ): int {
        $apicalls = 0;
        $configurationlockacquired = false;
        $lockfactory = $this->createMock(\core\lock\lock_factory::class);
        $lock = null;
        if ($lockavailable) {
            $lockfactory->expects($this->once())
                ->method('release_lock')
                ->willReturn(true);
            $lock = new \core\lock\lock('payway-renewal-test-lock', $lockfactory);
        }
        $apifactory = function (api_configuration $configuration) use (
            $latestresponse,
            $verificationstatus,
            $duringverification,
            &$configurationlockacquired,
            &$apicalls
        ): payway_api {
            $apicalls++;
            return new class (
                $configuration,
                $latestresponse,
                $verificationstatus,
                $duringverification,
                $configurationlockacquired,
            ) extends payway_api {
                /** @var api_response */
                private api_response $latestresponse;

                /** @var int */
                private int $verificationstatus;

                /** @var callable|null */
                private $duringverification;

                /** @var bool */
                private bool $configurationlockacquired;

                /**
                 * Construct the mocked PayWay API client.
                 *
                 * @param api_configuration $configuration API credentials.
                 * @param api_response $latestresponse Latest-key endpoint response.
                 * @param int $verificationstatus Replacement-key verification status.
                 * @param callable|null $duringverification Callback run during key verification.
                 * @param bool $configurationlockacquired Whether the task acquired the config lock first.
                 */
                public function __construct(
                    api_configuration $configuration,
                    api_response $latestresponse,
                    int $verificationstatus,
                    ?callable $duringverification,
                    bool $configurationlockacquired
                ) {
                    parent::__construct($configuration);
                    $this->latestresponse = $latestresponse;
                    $this->verificationstatus = $verificationstatus;
                    $this->duringverification = $duringverification;
                    $this->configurationlockacquired = $configurationlockacquired;
                }

                /**
                 * Return the predetermined latest-key response.
                 *
                 * @param int $timeout Request timeout.
                 * @return api_response
                 */
                public function get_latest_api_key(int $timeout = self::DEFAULT_TIMEOUT): api_response {
                    return $this->latestresponse;
                }

                /**
                 * Return the predetermined replacement-key verification status.
                 *
                 * @param int $timeout Request timeout.
                 * @return int HTTP status code.
                 */
                public function test_is_secret_key_valid(int $timeout = self::DEFAULT_TIMEOUT): result {
                    if ($this->duringverification) {
                        ($this->duringverification)($this->configurationlockacquired);
                    }
                    if ($this->verificationstatus === 200) {
                        return result::ok(true);
                    }
                    return result::err(
                        "Secret API key validation failed. HTTP status: {$this->verificationstatus}; cURL error code: 0."
                    );
                }
            };
        };

        $task = new class ($apifactory, $lockavailable, $lock, $configurationlockacquired) extends check_token_renewal_adhoc {
            /** @var callable */
            private $apifactory;

            /** @var bool */
            private bool $lockavailable;

            /** @var \core\lock\lock|null */
            private ?\core\lock\lock $configurationlock;

            /** @var bool */
            private bool $configurationlockacquired;

            /**
             * Construct the task with its API mock and lock behavior.
             *
             * @param callable $apifactory API client factory.
             * @param bool $lockavailable Whether lock acquisition succeeds.
             * @param \core\lock\lock|null $configurationlock Configuration lock returned by the mock.
             * @param bool $configurationlockacquired Whether the task acquired the lock, updated by reference.
             */
            public function __construct(
                callable $apifactory,
                bool $lockavailable,
                ?\core\lock\lock $configurationlock,
                bool &$configurationlockacquired
            ) {
                $this->apifactory = $apifactory;
                $this->lockavailable = $lockavailable;
                $this->configurationlock = $configurationlock;
                $this->configurationlockacquired = &$configurationlockacquired;
            }

            /**
             * Use the deterministic API client factory.
             *
             * @param api_configuration $configuration API credentials.
             * @return payway_api
             */
            protected function create_api(api_configuration $configuration): payway_api {
                return ($this->apifactory)($configuration);
            }

            /**
             * Simulate lock contention or use the real lock factory.
             *
             * @param int $gatewayid Gateway instance ID.
             * @return \core\lock\lock|false
             */
            protected function acquire_configuration_lock(int $gatewayid): \core\lock\lock|false {
                if (!$this->lockavailable) {
                    return false;
                }
                $this->configurationlockacquired = true;
                return $this->configurationlock;
            }
        };
        $task->set_custom_data(['gatewayid' => $this->gatewayid]);
        // The task writes operational progress through mtrace(), which PHPUnit treats as risky output.
        ob_start();
        try {
            $task->execute();
        } finally {
            ob_end_clean();
        }

        return $apicalls;
    }

    /**
     * A replacement key is saved without modifying other configuration fields.
     */
    public function test_new_token_is_saved_and_other_configuration_is_unchanged(): void {
        $this->create_gateway();
        $latestresponse = new api_response(200, json_encode(['key' => 'APPLICATION_SEC_replacement']));

        $apicalls = $this->run_mocked_task($latestresponse);

        $config = $this->get_config();
        $this->assertSame('APPLICATION_SEC_replacement', $config->secretkey);
        $this->assertSame('APPLICATION_PUB_original', $config->publishablekey);
        $this->assertSame('sandbox', $config->environment);
        $this->assertSame('TEST', $config->merchantid);
        $this->assertSame('notify@example.com', $config->notificationemail);
        $this->assertSame(2, $apicalls, 'Expected one API client for lookup and one for verification.');

        // The task must release the lock after saving.
        $lock = \paygw_payway\gateway::get_configuration_lock($this->gatewayid, timeout: 0, lifetime: 60);
        $this->assertNotFalse($lock);
        $lock->release();
    }

    /**
     * The already-current key is a no-op and must not be tested or saved again.
     */
    public function test_same_token_is_a_noop(): void {
        $this->create_gateway();
        $response = new api_response(200, json_encode(['key' => $this->initialconfig['secretkey']]));

        $this->assertSame(1, $this->run_mocked_task($response));
        $this->assertSame($this->initialconfig['secretkey'], $this->get_config()->secretkey);
    }

    /**
     * A successful response without a key fails safely and leaves stored config unchanged.
     */
    public function test_response_without_token_throws_and_does_not_update(): void {
        $this->create_gateway();
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('PayWay API key response is missing the required key field.');

        try {
            $this->run_mocked_task(new api_response(200, '{}'));
        } finally {
            $this->assertSame($this->initialconfig['secretkey'], $this->get_config()->secretkey);
        }
    }

    /**
     * Invalid JSON from PayWay fails safely without changing the stored token.
     */
    public function test_malformed_latest_key_response_does_not_update_token(): void {
        $this->create_gateway();
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Could not decode JSON response for PayWay API key.');

        try {
            $this->run_mocked_task(new api_response(200, 'not-json'));
        } finally {
            $this->assertSame($this->initialconfig['secretkey'], $this->get_config()->secretkey);
        }
    }

    /**
     * Network and HTTP failures do not change the configured token.
     *
     * @dataProvider latest_key_failure_provider
     * @param int $httpcode
     * @param int $curlerrno
     * @param string $body
     * @param string $expectedmessage
     */
    public function test_latest_key_request_failure_does_not_update_token(
        int $httpcode,
        int $curlerrno,
        string $body,
        string $expectedmessage
    ): void {
        $this->create_gateway();
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage($expectedmessage);

        try {
            $this->run_mocked_task(new api_response($httpcode, $body, $curlerrno));
        } finally {
            $this->assertSame($this->initialconfig['secretkey'], $this->get_config()->secretkey);
        }
    }

    /**
     * Provide network and unsuccessful HTTP responses, including credential-redaction coverage.
     *
     * @return array<string, array{int, int, string, string}>
     */
    public static function latest_key_failure_provider(): array {
        return [
            'network failure' => [0, 7, '', 'cURL error code: 7'],
            'HTTP failure with message' => [503, 0, '{"message":"service unavailable"}', 'PayWay message: service unavailable'],
            'response does not disclose secret' => [401, 0,
                '{"message":"APPLICATION_SEC_original rejected"}', 'PayWay message: [redacted] rejected'],
        ];
    }

    /**
     * A non-success status when validating the replacement key must reject that key.
     */
    public function test_replacement_token_verification_requires_successful_http_status(): void {
        $this->create_gateway();
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('failed testing: Secret API key validation failed. HTTP status: 401');

        try {
            $this->run_mocked_task(
                new api_response(200, '{"key":"APPLICATION_SEC_replacement"}'),
                verificationstatus: 401,
            );
        } finally {
            $this->assertSame($this->initialconfig['secretkey'], $this->get_config()->secretkey);
        }
    }

    /**
     * A lock conflict leaves the existing configuration untouched.
     */
    public function test_lock_conflict_does_not_update_token(): void {
        $this->create_gateway();

        $this->run_mocked_task(
            new api_response(200, '{"key":"APPLICATION_SEC_replacement"}'),
            lockavailable: false,
        );

        $this->assertSame($this->initialconfig['secretkey'], $this->get_config()->secretkey);
    }

    /**
     * The configuration lock remains held throughout PayWay key verification.
     */
    public function test_configuration_lock_is_held_during_api_requests(): void {
        $this->create_gateway();
        $lockwasheldduringverification = false;

        $this->run_mocked_task(
            new api_response(200, '{"key":"APPLICATION_SEC_replacement"}'),
            duringverification: function (bool $lockacquired) use (&$lockwasheldduringverification): void {
                $lockwasheldduringverification = $lockacquired;
            },
        );

        $this->assertTrue($lockwasheldduringverification);
        $this->assertSame('APPLICATION_SEC_replacement', $this->get_config()->secretkey);
        $lock = \paygw_payway\gateway::get_configuration_lock($this->gatewayid, timeout: 0, lifetime: 60);
        $this->assertNotFalse($lock, 'The task should release its lock after the entire renewal operation.');
        $lock->release();
    }

    /**
     * Disabled gateways are ignored without making API requests.
     */
    public function test_disabled_gateway_is_ignored_without_api_calls(): void {
        global $DB;
        $this->create_gateway();
        $DB->set_field('payment_gateways', 'enabled', 0, ['id' => $this->gatewayid]);

        $this->assertSame(0, $this->run_mocked_task(new api_response(200, '{"key":"APPLICATION_SEC_replacement"}')));
        $this->assertSame($this->initialconfig['secretkey'], $this->get_config()->secretkey);
    }

    /**
     * The scheduled task queues checks only for enabled PayWay gateways.
     */
    public function test_scheduled_task_queues_enabled_gateways_only(): void {
        global $DB;
        $this->create_gateway();
        $disabledaccount = helper::save_payment_account((object) [
            'name' => 'Disabled PayWay renewal account',
            'idnumber' => '',
        ]);
        helper::save_payment_gateway((object) [
            'accountid' => $disabledaccount->get('id'),
            'gateway' => 'payway',
            'enabled' => 0,
            'config' => json_encode($this->initialconfig, JSON_THROW_ON_ERROR),
        ]);

        ob_start();
        try {
            (new check_token_renewal())->execute();
        } finally {
            ob_end_clean();
        }

        $queuedtasks = $DB->get_records('task_adhoc', [
            'classname' => '\\' . check_token_renewal_adhoc::class,
        ]);
        $this->assertCount(1, $queuedtasks);
        $customdata = json_decode(reset($queuedtasks)->customdata, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($this->gatewayid, (int) $customdata->gatewayid);
    }
}
