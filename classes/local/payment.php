<?php
// This file is part of Moodle - https://moodle.org/
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

namespace paygw_payway\local;

/**
 * Represents a payment response from PayWay, matches 1:1 with their JSON api response.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment {
    /**
     * Store a parsed PayWay transaction response.
     *
     * @param int $transactionid PayWay transaction ID
     * @param int $receiptnumber PayWay receipt number
     * @param status $status Transaction status
     * @param string $responsecode PayWay response code
     * @param string $responsetext PayWay response text
     */
    public function __construct(
        /** @var int PayWay transaction ID */
        public readonly int $transactionid,
        /** @var int PayWay receipt number */
        public readonly int $receiptnumber,
        /** @var status Transaction status used to determine whether a payment succeeded */
        public readonly status $status,
        /** @var string Response code with more information about the status */
        public readonly string $responsecode,
        /** @var string Corresponding text for responsecode */
        public readonly string $responsetext,
    ) {
    }

    /**
     * Try to construct self from a PayWay API response json string
     *
     * @param string $json the PayWay API response string
     * @return result<payment> Result containing payment result if ok, else error message.
     */
    public static function try_parse(string $json): result {
        try {
            $decoded = json_decode($json, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return result::err("Could not decode JSON response from PayWay API");
        }

        if (!is_object($decoded)) {
            return result::err("Expected a JSON object from PayWay API");
        }

        $transactionid = $decoded->transactionId ?? '';
        $receiptnumber = $decoded->receiptNumber ?? '';
        $status = $decoded->status ?? '';
        $responsecode = $decoded->responseCode ?? '';
        $responsetext = $decoded->responseText ?? '';

        // Sanity check - i would not expect this to ever happen.
        $invalidformat = !is_numeric($transactionid) || !is_numeric($receiptnumber);
        $empty = in_array(true, array_map(
            fn($v) => empty($v),
            [$transactionid, $receiptnumber, $status, $responsecode, $responsetext]
        ));
        if ($empty || $invalidformat) {
            return result::err("Unexpected payment response from PayWay API");
        }

        // Try coerce status into enum.
        $statusenum = status::tryFrom($status);
        if ($statusenum === null) {
            return result::err("Invalid status " . $status);
        }

        return result::ok(new payment(
            transactionid: $transactionid,
            receiptnumber: $receiptnumber,
            status: $statusenum,
            responsecode: $responsecode,
            responsetext: $responsetext
        ));
    }

    /**
     * Whether this payment status permits delivery of the purchased item.
     *
     * @return bool
     */
    public function is_accepted(): bool {
        // This must stay in sync with the status policy in the README.
        return in_array($this->status, [status::Approved, status::ApprovedAsterisk, status::Pending]);
    }

    /**
     * Whether this payment status requires notifying an administrator.
     *
     * @return bool
     */
    public function is_notifiable(): bool {
        // This must stay in sync with the status policy in the README.
        return in_array($this->status, [status::ApprovedAsterisk, status::Pending, status::Voided, status::Suspended]);
    }
}
