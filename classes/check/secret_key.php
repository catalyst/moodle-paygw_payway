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

namespace paygw_payway\check;

use action_link;
use core\check\check;
use core\check\result;
use moodle_url;
use paygw_payway\local\api_credential;
use paygw_payway\local\payway_api;
use Throwable;

/**
 * Secret key api token check.
 *
 * @package    paygw_payway
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class secret_key extends check {
    /**
     * Create check
     * @param int $gatewayid payment_gateway db record id
     * @param bool $gatewayenabled if the gateway is enabled
     * @param api_credential $credential the credentials configured for this gateway
     */
    public function __construct(
        /** @var int $gatewayid payment_gateway db record id */
        protected readonly int $gatewayid,
        /** @var bool $gatewayenabled if the gateway is enabled */
        protected readonly bool $gatewayenabled,
        /** @var api_credential $credential the credentials configured for this gateway */
        protected readonly api_credential $credential
    ) {
    }

    /**
     * Action link
     * @return action_link|null
     */
    public function get_action_link(): ?action_link {
        return new action_link(
            new moodle_url('/payment/manage_gateway.php', ['id' => $this->gatewayid]),
            get_string('managegateway', 'paygw_payway')
        );
    }

    /**
     * Get check result
     * @return result
     */
    public function get_result(): result {
        try {
            $api = payway_api::new($this->credential);
            $keyname = api_credential::parse_and_validate_key_name($this->credential->secretkey, api_credential::TYPE_SECRET);
            $status = $api->test_secret_key();

            // Ok if 200, else warning if not enabled or error if is enabled.
            $resultcode = $status == 200 ? result::OK : ($this->gatewayenabled ? result::ERROR : result::WARNING);
            return new result(
                $resultcode,
                get_string('connectiontest', 'paygw_payway', ['status' => $status, 'keyname' => $keyname])
            );
        } catch (Throwable $e) {
            return new result(result::UNKNOWN, get_string('connectiontestunknown', 'paygw_payway', $e->getMessage()));
        }
    }
}
