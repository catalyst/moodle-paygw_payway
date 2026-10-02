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
use paygw_payway\local\payment;
use paygw_payway\local\status;

/**
 * Tests PayWay transaction response parsing and status policy.
 *
 * @package    paygw_payway
 * @copyright 2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \paygw_payway\local\payment
 */
final class payment_test extends advanced_testcase {
    /**
     * Check which statuses allow delivery and require notifications.
     *
     * @dataProvider status_policy_provider
     * @param string $status
     * @param bool $accepted
     * @param bool $notifiable
     */
    public function test_status_policy_matches_readme(string $status, bool $accepted, bool $notifiable): void {
        $payment = payment::try_parse($this->response_json($status))->unwrap();

        $this->assertSame($accepted, $payment->is_accepted());
        $this->assertSame($notifiable, $payment->is_notifiable());
    }

    /**
     * Provide transaction statuses and their expected policy.
     *
     * @return array<string, array{string, bool, bool}>
     */
    public static function status_policy_provider(): array {
        return [
            'approved' => [status::Approved->value, true, false],
            'approved with warning' => [status::ApprovedAsterisk->value, true, true],
            'pending' => [status::Pending->value, true, true],
            'declined' => [status::Declined->value, false, false],
            'voided' => [status::Voided->value, false, true],
            'suspended' => [status::Suspended->value, false, true],
        ];
    }

    /**
     * Reject malformed PayWay transaction responses.
     *
     * @dataProvider malformed_response_provider
     * @param string $response
     */
    public function test_malformed_responses_are_rejected(string $response): void {
        $result = payment::try_parse($response);

        $this->assertTrue($result->is_err());
    }

    /**
     * Provide malformed transaction response bodies.
     *
     * @return array<string, array{string}>
     */
    public static function malformed_response_provider(): array {
        return [
            'invalid json' => ['not json'],
            'json array' => ['[]'],
            'missing transaction id' => [json_encode([
                'receiptNumber' => 2, 'status' => 'approved', 'responseCode' => '08', 'responseText' => 'Approved',
            ])],
            'unknown status' => [json_encode([
                'transactionId' => 1, 'receiptNumber' => 2, 'status' => 'unknown',
                'responseCode' => '08', 'responseText' => 'Unknown',
            ])],
            'missing response text' => ['{"transactionId": 1, "receiptNumber": 2, "status": "approved", "responseCode": "08"}'],
        ];
    }

    /**
     * Build a representative PayWay transaction response.
     *
     * @param string $status
     * @return string
     */
    private function response_json(string $status): string {
        return json_encode([
            'transactionId' => 123,
            'receiptNumber' => 456,
            'status' => $status,
            'responseCode' => '08',
            'responseText' => 'Approved',
        ], JSON_THROW_ON_ERROR);
    }
}
