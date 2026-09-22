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

namespace paygw_payway\local;

use coding_exception;

/**
 * API credential data storage class
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_credential {
    /** @var string key type segment expected for secret keys */
    public const TYPE_SECRET = 'SEC';

    /** @var string key type segment expected for publishable keys */
    public const TYPE_PUBLISHABLE = 'PUB';

    /**
     * Create credential class
     * @param string $publishablekey the public key
     * @param string $secretkey the secret key
     * @param environment $environment the environment
     */
    public function __construct(
        /** @var string $publishablekey the public key */
        public readonly string $publishablekey,
        /** @var string $secretkey the secret key */
        public readonly string $secretkey,
        /** @var string $environment the environment */
        public readonly environment $environment,
    ) {
    }

    /**
     * Given a key, validate the structure and return the key name
     * @param string $key
     * @param string|null $expectedtype if provided, checks the key's type segment matches this (e.g. self::TYPE_SECRET)
     * @return result result containing key name -  in format APPLICATION_TYPE...LAST3CHARS, else error message
     * This is what is used in the PayWay interface to identify keys.
     */
    public static function parse_and_validate_key_name(string $key, ?string $expectedtype = null): result {
        $parts = explode('_', $key);
        if (count($parts) != 3) {
            return result::err("Unexpected key format, expected 3 parts separated by underscores");
        }
        if (strlen($parts[2]) <= 3) {
            return result::err("Unexpected length of random part of key, expected > 3 chars");
        }

        $application = $parts[0];
        $type = $parts[1];
        $last3chars = substr($parts[2], -3);

        if ($expectedtype !== null && strtoupper($type) !== strtoupper($expectedtype)) {
            return result::err("Unexpected key type, expected {$expectedtype} but got {$type}");
        }

        return result::ok($application . '_' . $type . '...' . $last3chars);
    }

    /**
     * Creates a api_credential class from the stored json configuration
     * stored under payment_gateways in Moodle.
     *
     * @param string $jsonstoredconfig the JSON configuration for a payment_gateway.
     * @return api_credential
     */
    public static function from_stored_config(string $jsonstoredconfig): api_credential {
        $data = json_decode($jsonstoredconfig, flags: JSON_THROW_ON_ERROR);
        $publishablekey = $data?->publishablekey;
        $secretkey = $data?->secretkey;
        $environment = $data?->environment;

        // Realistically this should never happen - sanity check.
        if (in_array(null, [$publishablekey, $secretkey, $environment])) {
            throw new coding_exception("A required configuration for PayWay gateway was missing");
        }

        // Coerce enviromnet into enum.
        $environment = environment::from($environment);

        return new api_credential(
            $publishablekey,
            $secretkey,
            $environment
        );
    }
}
