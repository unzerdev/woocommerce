import {select, subscribe} from '@wordpress/data';

window.unzerCurrentPaymentMethod = '';
window.unzerIsInitial = true;
window.unzerCurrentAmount = 0;

function triggerSubmitForPaymentTypeId(paymentComponent, paymentTypeId) {
    paymentComponent.woocommercePaymentTypeId = paymentTypeId;
    const originalCheckoutButtons = document.querySelectorAll('.wc-block-components-checkout-place-order-button');
    const lastOriginalCheckoutButton = originalCheckoutButtons[originalCheckoutButtons.length - 1];
    lastOriginalCheckoutButton.click();
    paymentComponent.style.display = 'none';
    setTimeout(() => {
        unzerCheckForPaymentMethodChanges(true);
    }, 3000);

}

window.unzerInitiatePaymentMethod = (
    longNameInSnakeCase,
    settings,
    cartData
) => {
    const paymentComponent = document.getElementById(settings.paymentComponentId);
    const paymentMethodCheckoutButtonContainer = document.getElementById(settings.paymentComponentId + '-container-for-button');
    const originalCheckoutButtons = document.querySelectorAll('.wc-block-components-checkout-place-order-button');
    originalCheckoutButtons.forEach(originalCheckoutButton => {
        originalCheckoutButton.classList.remove('unzer-place-order-button-hidden');
    });
    document.querySelectorAll('.unzer-payment-method-checkout-button-container').forEach(container => {
        container.remove();
    });
    if (paymentComponent) {
        paymentComponent.style.display = '';
        Promise.all([customElements.whenDefined('unzer-payment')]).then(
            () => {
                try {

                    // move buttons to place order section
                    if (originalCheckoutButtons.length) {
                        if (paymentMethodCheckoutButtonContainer) {
                            //is button payment method
                            originalCheckoutButtons.forEach(originalCheckoutButton => {
                                originalCheckoutButton.classList.add('unzer-place-order-button-hidden');
                            });
                            const lastOriginalCheckoutButton = originalCheckoutButtons[originalCheckoutButtons.length - 1];
                            paymentMethodCheckoutButtonContainer.classList.add('unzer-payment-method-checkout-button-container');
                            lastOriginalCheckoutButton.after(paymentMethodCheckoutButtonContainer);
                        }
                    }

                    if (longNameInSnakeCase === 'unzer_google_pay' && paymentComponent.setGooglePayData) {
                        const options = settings.options;
                        const paymentDataRequestObject = {
                            gatewayMerchantId: options.gatewayMerchantId,
                            merchantInfo: options.merchantInfo,
                            transactionInfo: {
                                currencyCode: cartData.totals.currency_code,
                                countryCode: cartData.billingAddress.country,
                                totalPriceStatus: 'ESTIMATED',
                                totalPrice: (cartData.totals.total_price / 100).toFixed(2),
                            },
                            buttonOptions: options.buttonOptions,
                            allowedCardNetworks: options.allowedCardNetworks,
                            allowCreditCards: options.allowCreditCards,
                            allowPrepaidCards: options.allowPrepaidCards
                        };
                        // for some reason this does not get applied without timeout?
                        setTimeout(
                            function () {
                                paymentComponent.setGooglePayData(paymentDataRequestObject);
                            },
                            1000
                        );

                        const unzerCheckout = document.getElementById('unzer-google-pay-checkout-component');
                        if (unzerCheckout) {
                            unzerCheckout.onPaymentSubmit = function (response) {

                                if (response.submitResponse && response.submitResponse.success) {
                                    triggerSubmitForPaymentTypeId(paymentComponent, response.submitResponse.data.id);
                                } else {
                                    console.error(response);
                                }
                            };
                        }
                    }

                    if (longNameInSnakeCase === 'unzer_apple_pay_v2' && paymentComponent.setApplePayData) {
                        const applePayPaymentRequest = {
                            countryCode: settings.storeCountry,
                            currencyCode: cartData.totals.currency_code,
                            supportedNetworks: ['visa', 'masterCard'],
                            merchantCapabilities: ['supports3DS'],
                            total: {
                                label: settings.storeName,
                                amount: cartData.totals.total_price / 100
                            }
                        };
                        // for some reason this does not get applied without timeout?
                        setTimeout(
                            function () {
                                paymentComponent.setApplePayData(applePayPaymentRequest);
                            },
                            1000
                        );

                        const unzerCheckout = document.getElementById('unzer-apple-pay-v2-checkout-component');
                        if (unzerCheckout) {
                            unzerCheckout.onPaymentSubmit = function (response) {
                                if (response.submitResponse && response.submitResponse.data && response.submitResponse.data.id && response.submitResponse.data.id.indexOf('-apl-') === -1) {
                                    return
                                }
                                if (response.submitResponse && response.submitResponse.success) {
                                    triggerSubmitForPaymentTypeId(paymentComponent, response.submitResponse.data.id);
                                } else {
                                    console.error(response);
                                }
                            };
                        }
                    }

                    const data = new FormData();
                    data.append('data', JSON.stringify(cartData));
                    data.append('payment_method', longNameInSnakeCase);
                    data.append('unzer_nonce', settings.nonce);
                    fetch(settings.getCustomerDataUrl, {
                        method: 'POST',
                        body: data

                    }).then((response) => response.json())
                        .then((responseData) => {
                            const customerData = responseData.customer;
                            if (customerData) {
                                paymentComponent.setCustomerData(
                                    customerData
                                );
                            }

                            if (responseData.publicKey !== paymentComponent.publicKey) {
                                paymentComponent.publicKey = responseData.publicKey;
                                paymentComponent.initOnFirstUpdate();
                            }

                            if (paymentComponent.setBasketData) {
                                paymentComponent.setBasketData(
                                    {
                                        amount: cartData.totals.total_price / 100,
                                        currencyType: cartData.totals.currency_code,
                                        country: cartData.billingAddress.country
                                    }
                                );
                                const installmentComponent = document.getElementById('unzer-paylater-installment-component');
                                if (installmentComponent && installmentComponent.reloadPlansWithRetainedData) {
                                    installmentComponent.updateDataFromStore();
                                    installmentComponent.onDataReady();
                                }
                            }


                        });

                } catch (e) {
                    console.error(e);
                }
            }
        );

    }
}
const updatePaymentMethodData = function (longNameInSnakeCase, cartData) {
    const settings = window.wc.wcSettings.getSetting(longNameInSnakeCase + '_data', {})
    console.log(settings, cartData);

    if (window.unzerIsInitial) {
        const initializeLoopFunction = () => {
            if (!document.querySelector('.wc-block-components-checkout-place-order-button') || !document.querySelector('.wp-block-woocommerce-checkout-payment-block .wc-block-components-radio-control-accordion-option')) {
                setTimeout(initializeLoopFunction, 100);
            } else {
                setTimeout(async () => {
                        window.unzerInitiatePaymentMethod(longNameInSnakeCase, settings, cartData);
                    }, 1000
                )
            }
        }
        initializeLoopFunction();
    } else {
        setTimeout(async () => {
                window.unzerInitiatePaymentMethod(longNameInSnakeCase, settings, cartData);
            }, 1000
        )
    }


}

function unzerCheckForPaymentMethodChanges(force = false) {

    const checkoutStore = select(window.wc.wcBlocksData.CHECKOUT_STORE_KEY);
    const cartStore = select(window.wc.wcBlocksData.CART_STORE_KEY);
    const paymentStore = select(window.wc.wcBlocksData.PAYMENT_STORE_KEY);

    const cartData = cartStore.getCartData();
    let hasChanged = false;
    if (window.unzerCurrentPaymentMethod !== paymentStore.getActivePaymentMethod()) {
        hasChanged = true;
    }

    if (!hasChanged && window.unzerCurrentAmount !== cartData.totals.total_price) {
        hasChanged = true;
    }
    if (hasChanged || force) {
        console.log('payment method changed to: ' + paymentStore.getActivePaymentMethod());
        window.unzerCurrentPaymentMethod = paymentStore.getActivePaymentMethod();
        window.unzerCurrentAmount = cartData.totals.total_price;
        updatePaymentMethodData(window.unzerCurrentPaymentMethod, cartData);
    }

}

subscribe(unzerCheckForPaymentMethodChanges, 'wc/store/cart');