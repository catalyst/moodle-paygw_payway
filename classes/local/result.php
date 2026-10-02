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

use coding_exception;

// phpcs:disable moodle.Commenting.ValidTags.Invalid -- Generic annotations are required for static analysis.
/**
 * A rust-style result class for returning from functions, instead of throwing exceptions on errors.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @template T
 */
final class result {
    /**
     * Create a result - use result::ok() or result::err() instead.
     *
     * @param bool $ok whether this is an ok result
     * @param mixed $value if successful, the value returned
     * @param string|null $error if errored, the error message
     */
    private function __construct(
        /** @var bool $ok whether this is an ok result */
        private readonly bool $ok,
        /** @var T|null $value if successful, the value returned */
        private readonly mixed $value,
        /** @var string|null $error if errored, the error message */
        public readonly ?string $error,
    ) {
    }

    /**
     * Create error result
     * @param string $error
     * @return result<never>
     */
    public static function err(string $error): result {
        return new result(false, null, $error);
    }

    /**
     * Create ok result
     * @template U
     * @param mixed $value
     * @phpstan-param U $value
     * @return result<U>
     */
    public static function ok(mixed $value): result {
        return new result(true, $value, null);
    }
    // phpcs:enable moodle.Commenting.ValidTags.Invalid

    /**
     * If is ok result
     * @return bool
     */
    public function is_ok(): bool {
        return $this->ok;
    }

    /**
     * If is error result
     * @return bool
     */
    public function is_err(): bool {
        return !$this->ok;
    }

    /**
     * Get the ok value, throwing if this is an error result.
     * @return T
     */
    public function unwrap(): mixed {
        if (!$this->ok) {
            throw new coding_exception("Tried to unwrap error result: {$this->error}");
        }
        return $this->value;
    }
}
