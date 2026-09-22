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
import ModalEvents from 'core/modal_events';
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
 * Find a required element in the payment form.
 *
 * @param {HTMLElement} root The element to search
 * @param {string} selector The selector to search for
 * @returns {HTMLElement}
 */
const getRequiredElement = (root, selector) => {
    try {
        const element = root.querySelector(selector);
        if (!element) {
            throw new Error(`Missing required PayWay payment element: ${selector}`);
        }
        return element;
    } catch (error) {
        throw new Error(`Unable to initialise PayWay payment form: ${error.message}`);
    }
};

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
 * Create a controller for one PayWay modal instance.
 *
 * The controller owns its state and lifecycle. Callers interact with methods rather than passing
 * mutable state between the individual payment steps.
 *
 * @param {Modal} modal The modal controlled by this instance
 * @param {object} context Payment details used by the form template
 * @returns {object} The payment controller
 */
const createPaymentController = (modal, context) => {
    const state = {
        view: 'loading',
        error: null,
        submitDisabled: true,
        cancelled: false,
    };

    const modalClosed = new Promise((resolve, reject) => {
        const cancelled = () => {
            if (state.view === 'success') {
                resolve();
                return;
            }
            state.cancelled = true;
            const error = new Error('PayWay payment was cancelled');
            error.cancelled = true;
            reject(error);
        };
        modal.getRoot().one(ModalEvents.hidden, cancelled);
        modal.getRoot().one(ModalEvents.destroyed, cancelled);
    });

    const updateModal = async() => {
        if (modal.paywayView !== state.view) {
            const template = state.view === 'loading' ? 'paygw_payway/payway_loading_placeholder' :
                state.view === 'form' ? 'paygw_payway/payway_creditcard_modal' :
                'paygw_payway/payway_success_placeholder';
            const templateContext = state.view === 'form' ? context : {};

            modal.setBody(Templates.render(template, templateContext));
            modal.paywayView = state.view;
        }

        const [body] = await modal.getBodyPromise();

        if (state.view === 'form') {
            const errorElement = getRequiredElement(body, '#payway-cc-error');
            const submitButton = getRequiredElement(body, '#payway-cc-submit');
            errorElement.textContent = state.error ?? '';
            errorElement.style.display = state.error ? 'block' : 'none';
            submitButton.disabled = state.submitDisabled;
        }
    };

    const showForm = config => {
        Object.assign(context, {
            sandbox: config.sandbox,
            cost: config.cost,
            currency: config.currency.toUpperCase(),
        });
        state.view = 'form';
        return updateModal();
    };

    const createCreditCardFrame = (payway, publishableApiKey) => callbackToPromise((callback) => {
        payway.createCreditCardFrame({
            publishableApiKey,
            tokenMode: 'callback',
            onValid: () => {
                state.submitDisabled = false;
                updateModal().catch(error => error);
            },
            onInvalid: () => {
                state.submitDisabled = true;
                updateModal().catch(error => error);
            },
        }, callback);
    });

    const waitForClick = element => Promise.race([
        new Promise(resolve => element.addEventListener('click', resolve, {once: true})),
        modalClosed,
    ]);

    const attemptPayment = async(creditCardFrame, component, paymentArea, itemId) => {
        const [body] = await modal.getBodyPromise();
        const submitButton = getRequiredElement(body, '#payway-cc-submit');
        await waitForClick(submitButton);
        state.error = null;
        state.submitDisabled = true;

        try {
            await updateModal();
            const {singleUseTokenId} = await Promise.race([
                callbackToPromise((callback) => creditCardFrame.getToken(callback)),
                modalClosed,
            ]);
            const response = await Promise.race([
                processPayment(component, paymentArea, itemId, singleUseTokenId),
                modalClosed,
            ]);

            if (response.status !== 'ok') {
                state.error = response.status;
                return false;
            }

            return true;
        } catch (e) {
            if (e.cancelled) {
                throw e;
            }
            state.error = e.message ?? String(e);
            return false;
        } finally {
            if (!state.cancelled) {
                state.submitDisabled = false;
                await updateModal();
            }
        }
    };

    const showSuccessAndWaitForContinue = async() => {
        state.view = 'success';
        await updateModal();
        const [body] = await modal.getBodyPromise();
        const continueButton = getRequiredElement(body, '#payway-success-continue');

        continueButton.addEventListener('click', () => modal.hide(), {once: true});
        return modalClosed;
    };

    return {
        get modalClosed() {
            return modalClosed;
        },
        updateModal,
        showForm,
        createCreditCardFrame,
        attemptPayment,
        showSuccessAndWaitForContinue,
    };
};

/**
 * Run the payment flow in a PayWay modal.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @param {string} description Description of the payment
 * @returns {Promise<string>} Rejects with an error with `cancelled` set if the user closes the modal
 */
const runPayment = async(component, paymentArea, itemId, description) => {
    const modal = await showModalWithLoadingPlaceholder();
    const controller = createPaymentController(modal, {
        sandbox: false,
        description,
        cost: '',
        currency: '',
    });

    let payway;
    let config;
    try {
        [payway, config] = await Promise.race([
            Promise.all([
                bootstrap_payway(),
                getConfigForJs(component, paymentArea, itemId),
            ]),
            controller.modalClosed,
        ]);
    } catch (e) {
        if (e.cancelled) {
            throw e;
        }
        // Hide our modal first, so the error is visible instead of stuck behind it.
        modal.hide();
        throw new Error(await getString('error:paymentsetupfailed', 'paygw_payway'));
    }

    await controller.showForm(config);

    let creditCardFrame;
    try {
        creditCardFrame = await Promise.race([
            controller.createCreditCardFrame(payway, config.publishablekey),
            controller.modalClosed,
        ]);
    } catch (e) {
        if (e.cancelled) {
            throw e;
        }
        // Hide our modal first, so the error is visible instead of stuck behind it.
        modal.hide();
        throw new Error(await getString('error:paymentsetupfailed', 'paygw_payway'));
    }

    try {
        // Keep letting the user retry until the payment succeeds.
        let paymentSucceeded = false;
        while (!paymentSucceeded) {
            paymentSucceeded = await controller.attemptPayment(creditCardFrame, component, paymentArea, itemId);
        }

        await controller.showSuccessAndWaitForContinue();
        return 'ok';
    } finally {
        creditCardFrame.destroy();
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
export const process = async(component, paymentArea, itemId, description) => {
    try {
        return await runPayment(component, paymentArea, itemId, description);
    } catch (e) {
        if (e.cancelled) {
            // Core payment alerts on any rejection, so a cancelled payment never settles (as paygw_paypal does).
            return new Promise(() => null);
        }
        throw e;
    }
};
