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

namespace paygw_payway\local;

/**
 * Response metadata from a PayWay HTTP request.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api_response {
    /**
     * Create a PayWay API response.
     *
     * @param int $httpcode HTTP response code, or 0 for a transport failure
     * @param string $body Response body
     * @param int $curlerrno cURL error number, or 0 when there was no transport error
     */
    public function __construct(
        /** @var int HTTP response code, or 0 for a transport failure */
        public readonly int $httpcode,
        /** @var string Response body */
        public readonly string $body,
        /** @var int cURL error number, or 0 when there was no transport error */
        public readonly int $curlerrno = 0,
    ) {
    }

    /**
     * Whether this response represents a successful HTTP request.
     *
     * @return bool
     */
    public function is_success(): bool {
        return $this->httpcode >= 200 && $this->httpcode < 300;
    }

    /**
     * Whether this request may safely be retried with the same idempotency key.
     *
     * @return bool
     */
    public function is_retryable(): bool {
        // PayWay documents retrying 429, 503, and transient network failures
        // with the same Idempotency-Key at the URL below.
        // See https://www.payway.com.au/docs/rest.html#basics-sending-requests (PayWay).
        return $this->curlerrno !== 0 || in_array($this->httpcode, [429, 503], true);
    }
}
