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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace paygw_payway;

use advanced_testcase;
use paygw_payway\local\api_response;

/**
 * Tests PayWay HTTP response classification.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_payway\local\api_response
 */
final class api_response_test extends advanced_testcase {
    /**
     * Successful HTTP statuses are not retryable.
     *
     * @dataProvider success_status_provider
     * @param int $status
     */
    public function test_success_statuses_are_successful(int $status): void {
        $response = new api_response($status, '{}');

        $this->assertTrue($response->is_success());
        $this->assertFalse($response->is_retryable());
    }

    /**
     * Provide successful HTTP statuses.
     *
     * @return array<string, array{int}>
     */
    public static function success_status_provider(): array {
        return [
            'ok' => [200],
            'created' => [201],
            'accepted' => [202],
            'no content' => [204],
        ];
    }

    /**
     * Transient HTTP failures are retryable.
     *
     * @dataProvider retryable_status_provider
     * @param int $status
     */
    public function test_retryable_statuses_are_retryable(int $status): void {
        $this->assertTrue((new api_response($status, '{}'))->is_retryable());
    }

    /**
     * Provide retryable HTTP statuses.
     *
     * @return array<string, array{int}>
     */
    public static function retryable_status_provider(): array {
        return [
            'too many requests' => [429],
            'service unavailable' => [503],
        ];
    }

    public function test_transport_failure_is_retryable(): void {
        $this->assertTrue((new api_response(0, '', CURLE_OPERATION_TIMEDOUT))->is_retryable());
    }

    /**
     * Permanent HTTP errors are not retryable.
     *
     * @dataProvider permanent_status_provider
     * @param int $status
     */
    public function test_other_error_statuses_are_not_retryable(int $status): void {
        $response = new api_response($status, '{}');

        $this->assertFalse($response->is_success());
        $this->assertFalse($response->is_retryable());
    }

    /**
     * Provide HTTP statuses for permanent failures.
     *
     * @return array<string, array{int}>
     */
    public static function permanent_status_provider(): array {
        return [
            'bad request' => [400],
            'unauthorised' => [401],
            'forbidden' => [403],
            'unprocessable entity' => [422],
            'internal server error' => [500],
        ];
    }
}
