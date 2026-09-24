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

import { bootstrap_payway } from "./bootstrap_payway";
import { getConfigForJs, processPayment } from "./repository";
import Templates from 'core/templates';
import Modal from 'core/modal';
import { getString } from 'core/str';

/**
 * PayWay Modal functions
 *
 * @module     paygw_payway/gateways_modal
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates and shows a modal that contains a loading placeholder.
 *
 * @returns {Promise<Modal>}
 */
const showModalWithLoadingPlaceholder = async() => await Modal.create({
    title: await getString('paytitle', 'paygw_payway'),
    body: await Templates.render('paygw_payway/payway_loading_placeholder', {}),
    show: true,
    removeOnClose: true,
});

/**
 * Shows an inline error message within the credit card form.
 *
 * @param {HTMLElement} errorElement The element used to display form errors
 * @param {string} message The error message to display
 */
const showFormError = (errorElement, message) => {
    errorElement.textContent = message;
    errorElement.style.display = 'block';
};

/**
 * Hides the inline form error message.
 *
 * @param {HTMLElement} errorElement The element used to display form errors
 */
const hideFormError = (errorElement) => {
    errorElement.style.display = 'none';
};

/**
 * Replaces the modal body with a success message and waits for the user to continue.
 *
 * @param {Modal} modal The modal to update
 * @returns {Promise<void>}
 */
const showSuccessAndWaitForContinue = async(modal) => {
    modal.setBody(Templates.render('paygw_payway/payway_success_placeholder', {}));
    const body = await modal.getBodyPromise();

    return new Promise((resolve) => {
        body[0].querySelector('#payway-success-continue').addEventListener('click', () => resolve());
    });
};

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
    const modal = await showModalWithLoadingPlaceholder();

    let payway;
    let config;
    try {
        [payway, config] = await Promise.all([
            bootstrap_payway(),
            getConfigForJs(component, paymentArea, itemId),
        ]);
    } catch (e) {
        // Hide our modal first, so the error is visible instead of stuck behind it.
        modal.hide();
        throw new Error(await getString('error:paymentsetupfailed', 'paygw_payway'));
    }

    modal.setBody(Templates.render('paygw_payway/payway_creditcard_modal', {
        sandbox: config.sandbox,
        description,
        cost: config.cost,
        currency: config.currency.toUpperCase(),
    }));
    const body = await modal.getBodyPromise();

    const modalBody = body[0];
    const submitButton = modalBody.querySelector('#payway-cc-submit');
    const errorElement = modalBody.querySelector('#payway-cc-error');

    return new Promise((resolve, reject) => {
        let creditCardFrame = null;

        // Called once the token has been retrieved from the trusted frame.
        const tokenCallback = (err, data) => {
            if (err) {
                submitButton.disabled = false;
                showFormError(errorElement, err.message);
                return;
            }

            processPayment(component, paymentArea, itemId, data.singleUseTokenId).then(async(response) => {
                if (response.status === 'ok') {
                    await showSuccessAndWaitForContinue(modal);
                    modal.hide();
                    resolve(response.status);
                } else {
                    submitButton.disabled = false;
                    showFormError(errorElement, response.status);
                }
            });

            creditCardFrame.destroy();
            creditCardFrame = null;
        };

        // Called once the trusted frame has been created and is ready for input.
        const createdCallback = async(err, frame) => {
            if (err) {
                // Hide our modal first, so the error is visible instead of stuck behind it.
                modal.hide();
                reject(await getString('error:paymentsetupfailed', 'paygw_payway'));
                return;
            }

            creditCardFrame = frame;
        };

        submitButton.addEventListener('click', () => {
            hideFormError(errorElement);
            submitButton.disabled = true;
            creditCardFrame.getToken(tokenCallback);
        });

        payway.createCreditCardFrame({
            publishableApiKey: config.publishablekey,
            tokenMode: 'callback',
            onValid: () => {
                submitButton.disabled = false;
            },
            onInvalid: () => {
                submitButton.disabled = true;
            }
        }, createdCallback);
    });
}
