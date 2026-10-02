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

use core\exception\invalid_parameter_exception;

/**
 * API configuration data storage class
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_configuration {
    /** @var string key type segment expected for secret keys */
    public const KEY_TYPE_SECRET = 'SEC';

    /** @var string key type segment expected for publishable keys */
    public const KEY_TYPE_PUBLISHABLE = 'PUB';

    /**
     * Create credential class
     * @param string $publishablekey the public key
     * @param string $secretkey the secret key
     * @param environment $environment the environment
     * @param string $merchantid the merchant id
     * @param string|null $notificationemail email address for payment notifications, if configured
     */
    public function __construct(
        /** @var string $publishablekey the public key */
        public readonly string $publishablekey,
        /** @var string $secretkey the secret key */
        public readonly string $secretkey,
        /** @var string $environment the environment */
        public readonly environment $environment,
        /** @var string $merchantid the merchant id */
        public readonly string $merchantid,
        /** @var string|null $notificationemail email address for payment notifications, if configured */
        public readonly string|null $notificationemail,
    ) {
    }

    /**
     * Given a key, validate the structure and return the key name
     * @param string $key
     * @param string $expectedtype checks the key's type segment matches this (e.g. self::KEY_TYPE_SECRET)
     * @return result<string> result containing key name -  in format APPLICATION_TYPE...LAST3CHARS, else error message
     * This is what is used in the PayWay interface to identify keys.
     */
    public static function parse_and_validate_key_name(string $key, string $expectedtype): result {
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

        if (strtoupper($type) !== strtoupper($expectedtype)) {
            return result::err("Unexpected key type, expected {$expectedtype} but got {$type}");
        }

        return result::ok($application . '_' . $type . '...' . $last3chars);
    }

    /**
     * Validate and parse the configured environment.
     * @param string $environment configured environment name
     * @return result<environment>
     */
    public static function validate_and_parse_environment(string $environment): result {
        $env = environment::tryFrom($environment);
        if (is_null($env)) {
            return result::err(get_string('invalidenvironment', 'paygw_payway', $environment));
        }
        return result::ok($env);
    }

    /**
     * Validate the merchant ID for the selected environment.
     * @param string $merchantid configured merchant ID
     * @param environment $environment selected environment
     * @return result<string>
     */
    public static function validate_merchantid(string $merchantid, environment $environment): result {
        if ($environment == environment::Sandbox && $merchantid !== 'TEST') {
            return result::err(get_string('sandboxmerchantidmustbetest', 'paygw_payway', $merchantid));
        }
        // Live merchant ids must be 8 digits.
        if ($environment == environment::Live && (strlen($merchantid) !== 8 || !is_numeric($merchantid))) {
            return result::err(get_string('livemerchantidinvalidformat', 'paygw_payway', $merchantid));
        }
        return result::ok($merchantid);
    }

    /**
     * Validate stored gateway config and build a credential from it.
     * @param object $data stored gateway config
     * @return result<api_configuration>
     */
    public static function validate_and_parse_stored_config(object $data): result {
        $publishablekey = $data->publishablekey ?? null;
        $secretkey = $data->secretkey ?? null;
        $environment = $data->environment ?? null;
        $merchantid = $data->merchantid ?? null;
        $notificationemail = $data->notificationemail ?? null;

        // Check for null / required.
        foreach (
            [
            'publishablekey' => $publishablekey,
            'secretkey' => $secretkey,
            'environment' => $environment,
            'merchantid' => $merchantid,
            ] as $label => $value
        ) {
            if (empty($value)) {
                return result::err(get_string('missingrequiredfield', 'paygw_payway', $label));
            }
        }

        // Check environment.
        $environmentresult = self::validate_and_parse_environment($environment);
        if ($environmentresult->is_err()) {
            // Propagate error.
            return $environmentresult;
        }
        $environment = $environmentresult->unwrap();

        // Check keys seem legitimate.
        $pubkeyresult = self::parse_and_validate_key_name($publishablekey, self::KEY_TYPE_PUBLISHABLE);
        if ($pubkeyresult->is_err()) {
            // Propagate error.
            return $pubkeyresult;
        }
        $secretkeyresult = self::parse_and_validate_key_name($secretkey, self::KEY_TYPE_SECRET);
        if ($secretkeyresult->is_err()) {
            // Propagate error.
            return $secretkeyresult;
        }

        // Check merchantid.
        $merchantidresult = self::validate_merchantid($merchantid, $environment);
        if ($merchantidresult->is_err()) {
            // Propagate error.
            return $merchantidresult;
        }

        return result::ok(new api_configuration(
            publishablekey: $publishablekey,
            secretkey: $secretkey,
            environment: $environment,
            merchantid: $merchantid,
            notificationemail: $notificationemail,
        ));
    }

    /**
     * Build a credential from stored gateway config, throwing if it is invalid.
     * @param object $data stored gateway config
     * @return api_configuration
     */
    public static function from_stored_config(object $data): api_configuration {
        return self::validate_and_parse_stored_config($data)->unwrap();
    }
}
