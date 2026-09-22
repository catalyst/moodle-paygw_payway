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

/**
 * PayWay.js bootstrapper
 *
 * @module     paygw_payway/gateways_modal
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const PAYWAY_URL = 'https://api.payway.com.au/rest/v1/payway.js';

let loadingPromise;

/**
 * Load payway.js onto the page via a <script> tag.
 * We can't just call it directly/bake it into amd modules due to PCI-DSS / origin restrictions.
 *
 * @returns {Promise<object>} promise that resolves to the PayWay object
 */
export const bootstrap_payway = () => {
    if (window.payway) {
        return Promise.resolve(window.payway);
    }

    if (loadingPromise) {
        return loadingPromise;
    }

    loadingPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = PAYWAY_URL;
        script.async = true;
        script.onload = () => window.payway ? resolve(window.payway) : reject(new Error('PayWay library did not initialise'));
        script.onerror = () => reject(new Error('Failed to load PayWay library'));
        document.head.appendChild(script);
    });

    loadingPromise = loadingPromise.catch(error => {
        loadingPromise = undefined;
        throw error;
    });

    return loadingPromise;
};
