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

import { bootstrap_payway, create_payway_creditcard_div } from "./bootstrap_payway";
import { getConfigForJs } from "./repository";

/**
 * PayWay Modal functions
 *
 * @module     paygw_payway/gateways_modal
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Process the payment.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @param {string} description Description of the payment
 * @returns {Promise<string>}
 */
export const process = async (component, paymentArea, itemId, description) => {
    // Load payway.js.
    const payway = await bootstrap_payway();

    // Load config (need the publishable key).
    const config = await getConfigForJs(component, paymentArea, itemId);

    // Setup payway injection element.
    // TODO finish this - we need to pop up a model and add this element in.
    create_payway_creditcard_div()

    return new Promise((resolve, reject) => {
        payway.createCreditCardFrame({
            publishableApiKey: config.publishablekey,
            onValid: () => {
                console.log("valid");
                resolve();
                // TODO.
            },
            onInvalid: () => {
                console.log("invalid");
                reject();
                // TODO.
            }
        });
    })
}
