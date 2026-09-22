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

/**
 * A rust-style result class for returning from functions, instead of throwing exceptions on errors.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class result {
    /**
     * Create a result
     *
     * @param string|null $value if successful, the value returned
     * @param string|null $error if errored, the error message
     */
    public function __construct(
        /** @var string|null $value if successful, the value returned */
        public readonly string|null $value,
        /** @var string|null $error if errored, the error message */
        public readonly string|null $error,
    ) {
        if (empty($value) && empty($error)) {
            throw new coding_exception("At least one of value or error must be given");
        }
        if (!empty($value) && !empty($error)) {
            throw new coding_exception("At most one of value or error can be given");
        }
    }

    /**
     * Create error result
     * @param string $error
     * @return result
     */
    public static function err(string $error): result {
        return new result(null, $error);
    }

    /**
     * Create ok result
     * @param string $value
     * @return result
     */
    public static function ok(string $value): result {
        return new result($value, null);
    }

    /**
     * If is ok result
     * @return bool
     */
    public function is_ok(): bool {
        return !empty($this->value) && empty($this->error);
    }

    /**
     * If is error result
     * @return bool
     */
    public function is_err(): bool {
        return empty($this->value) && !empty($this->error);
    }
}
