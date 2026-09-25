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
 * Waits for a single click on the given element.
 *
 * @param {HTMLElement} element The element to listen for a click on
 * @returns {Promise<void>}
 */
const waitForClick = (element) => new Promise((resolve) => {
    element.addEventListener('click', () => resolve(), {once: true});
});

/**
 * Converts a PayWay-style callback function (that calls back with (err, result)) into a Promise.
 *
 * @param {Function} callbackFn Function that accepts a single (err, result) callback
 * @returns {Promise<*>} Resolves with the result, or rejects with the error
 */
const callbackToPromise = (callbackFn) => new Promise((resolve, reject) => {
    callbackFn((err, result) => err ? reject(err) : resolve(result));
});

/**
 * Creates the trusted PayWay credit card frame within the modal.
 *
 * @param {object} payway The bootstrapped PayWay object
 * @param {string} publishableApiKey The PayWay publishable API key
 * @param {HTMLElement} submitButton The submit button, enabled/disabled based on card validity
 * @returns {Promise<object>} The created credit card frame
 */
const createCreditCardFrame = (payway, publishableApiKey, submitButton) => callbackToPromise((callback) => {
    payway.createCreditCardFrame({
        publishableApiKey,
        tokenMode: 'callback',
        onValid: () => {
            submitButton.disabled = false;
        },
        onInvalid: () => {
            submitButton.disabled = true;
        },
    }, callback);
});

/**
 * Waits for the submit button to be clicked, then attempts to obtain a single-use token and
 * process the payment. Returns whether the payment succeeded, so the caller can decide whether
 * to try again.
 *
 * @param {object} creditCardFrame The trusted PayWay credit card frame
 * @param {HTMLElement} submitButton The submit button
 * @param {HTMLElement} errorElement The element used to display form errors
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @returns {Promise<boolean>} True if the payment succeeded
 */
const attemptPayment = async(creditCardFrame, submitButton, errorElement, component, paymentArea, itemId) => {
    await waitForClick(submitButton);
    hideFormError(errorElement);
    submitButton.disabled = true;

    try {
        const {singleUseTokenId} = await callbackToPromise((callback) => creditCardFrame.getToken(callback));
        const response = await processPayment(component, paymentArea, itemId, singleUseTokenId);

        if (response.status !== 'ok') {
            showFormError(errorElement, response.status);
            return false;
        }

        return true;
    } catch (e) {
        // Catches both invalid card errors from PayWay, and any failures from the processPayment webservice.
        showFormError(errorElement, e.message ?? String(e));
        return false;
    } finally {
        submitButton.disabled = false;
    }
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
    const [modalBody] = await modal.getBodyPromise();

    const submitButton = modalBody.querySelector('#payway-cc-submit');
    const errorElement = modalBody.querySelector('#payway-cc-error');

    let creditCardFrame;
    try {
        creditCardFrame = await createCreditCardFrame(payway, config.publishablekey, submitButton);
    } catch (e) {
        // Hide our modal first, so the error is visible instead of stuck behind it.
        modal.hide();
        throw new Error(await getString('error:paymentsetupfailed', 'paygw_payway'));
    }

    // Keep letting the user retry until the payment succeeds.
    let paymentSucceeded = false;
    while (!paymentSucceeded) {
        paymentSucceeded = await attemptPayment(
            creditCardFrame, submitButton, errorElement, component, paymentArea, itemId
        );
    }

    creditCardFrame.destroy();
    await showSuccessAndWaitForContinue(modal);
    modal.hide();
    return 'ok';
};
