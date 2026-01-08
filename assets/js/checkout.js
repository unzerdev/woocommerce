const UnzerManager = {
    currency: null,
    country: null,
    instancePayLaterB2B: null,
    instancePayLaterB2C: null,
    instanceInstallments: null,
    initTimeout: null,
    init() {
        if (UnzerManager.initTimeout) {
            clearTimeout(UnzerManager.initTimeout);
        }
        UnzerManager.initTimeout = setTimeout(
            function () {
                UnzerManager.currency = unzer_parameters.currency;
                UnzerManager.initCard();
                UnzerManager.initDirectDebit();
                UnzerManager.initDirectDebitSecured();
                UnzerManager.initInstallment();
                UnzerManager.initInvoice();
                UnzerManager.initApplePay();
                UnzerManager.initGooglePay();
                UnzerManager.initSavedPaymentInstruments();
            },
            10
        );
    },

    async submitPaymentElement(unzerPaymentElement) {
        const response = await unzerPaymentElement.submit();
        console.log(JSON.stringify(response));
        if (response.submitResponse) {
            if (response.submitResponse.success === true) {
                const result = {
                    paymentTypeId: response.submitResponse.data.id,
                    threatMetrixId: response.threatMetrixId || null,
                };
                if (response.customerResponse && response.customerResponse.success) {
                    result.customerId = response.customerResponse.data.id;
                }
                return result;
            } else {
                UnzerManager.error('GENERAL ERROR');
                return null;
            }
        } else {
            UnzerManager.error('EXCEPTIONAL ERROR');
            return null;
        }
    },

    initSavedPaymentInstruments() {
        const updateFunction = function () {
            const containers = document.querySelectorAll('.unzer-saved-payment-instruments-container');
            if (!containers) {
                return;
            }
            containers.forEach(
                function (container) {
                    const newForm = container.querySelector('.unzer-payment-instrument-new-form');
                    if (container.querySelector('.unzer-payment-instrument-new-radio:checked')) {
                        newForm.style.display = 'block';
                    } else {
                        newForm.style.display = 'none';
                    }
                }
            );
        }
        updateFunction();
        const allRadios = document.querySelectorAll('.unzer-payment-instrument-radio');
        if (allRadios) {
            allRadios.forEach(
                function (radio) {
                    radio.addEventListener('change', updateFunction);
                }
            )
        }
    },

    initCard() {
        jQuery(document.body).on(
            'checkout_error',
            function () {
                document.getElementById('unzer-card-id').value = '';
            }
        );
        jQuery('.woocommerce-checkout').off('checkout_place_order_unzer_card');
        jQuery('.woocommerce-checkout').on(
            'checkout_place_order_unzer_card',
            function () {
                const selectedSavedCard = document.querySelector('[name="unzer_card_payment_instrument"]:checked');
                if (selectedSavedCard && selectedSavedCard.value) {
                    return true;
                }
                return UnzerManager.handleSubmit('unzer-card-id', 'unzer-card-payment-component');
            }
        );
    },

    initDirectDebit() {
        jQuery(document.body).on(
            'checkout_error',
            function () {
                if (document.getElementById('unzer-direct-debit-id')) {
                    document.getElementById('unzer-direct-debit-id').value = '';
                }
            }
        );
        jQuery('.woocommerce-checkout').off('checkout_place_order_unzer_direct_debit');
        jQuery('.woocommerce-checkout').on(
            'checkout_place_order_unzer_direct_debit',
            function () {
                const selectedSavedCard = document.querySelector('[name="unzer_direct_debit_payment_instrument"]:checked');
                if (selectedSavedCard && selectedSavedCard.value) {
                    return true;
                }
                return UnzerManager.handleSubmit('unzer-direct-debit-id', 'unzer-sepa-payment-component');
            }
        );
    },

    initDirectDebitSecured() {
        UnzerManager._setCustomerDataToPaymentComponent('#unzer-paylater-direct-debit-payment-component');
        UnzerManager.initDefaultPaymentMethod(
            'unzer-direct-debit-secured-id',
            'unzer-paylater-direct-debit-payment-component',
            'unzer_direct_debit_secured'
        );
    },

    initInstallment() {
        UnzerManager._setCustomerDataToPaymentComponent('#unzer-paylater-installment-payment-component');
        const unzerPaymentElement = document.getElementById('unzer-paylater-installment-payment-component');
        if (unzerPaymentElement && unzerPaymentElement.setBasketData) {
            unzerPaymentElement.setBasketData(
                {
                    amount: parseFloat(document.getElementById('unzer-installment-amount').value),
                    currencyType: UnzerManager.currency,
                    country: UnzerManager.getCountry()
                }
            );
        }
        UnzerManager.initDefaultPaymentMethod(
            'unzer-installment-id',
            'unzer-paylater-installment-payment-component',
            'unzer_installment'
        );
    },


    initInvoice() {
        UnzerManager._setCustomerDataToPaymentComponent('#unzer-paylater-invoice-payment-component');
        jQuery(document.body).on(
            'checkout_error',
            function () {
                if (document.getElementById('unzer-invoice-id')) {
                    document.getElementById('unzer-invoice-id').value = '';
                }
            }
        );
        jQuery('.woocommerce-checkout').off('checkout_place_order_unzer_invoice');
        jQuery('.woocommerce-checkout').on(
            'checkout_place_order_unzer_invoice',
            function () {
                if (document.getElementById('unzer-invoice-id').value) {
                    return true;
                }
                return UnzerManager.handleSubmit('unzer-invoice-id', 'unzer-paylater-invoice-payment-component');
            }
        );
    },


    initGooglePay() {

        const options = unzer_parameters.google_pay_options;
        if (!options) {
            return;
        }
        UnzerManager.createGooglePayButtonContainer();
        const unzerPaymentElement = document.getElementById('unzer-google-pay-payment-component');
        if (unzerPaymentElement && unzerPaymentElement.setGooglePayData) {

            const paymentDataRequestObject = {
                gatewayMerchantId: options.gatewayMerchantId,
                merchantInfo: options.merchantInfo,
                transactionInfo: {
                    currencyCode: UnzerManager.currency,
                    countryCode: options.transactionInfo.countryCode,
                    totalPriceStatus: 'ESTIMATED',
                    totalPrice: document.getElementById('unzer-google-pay-amount').value,
                },
                buttonOptions: options.buttonOptions,
                allowedCardNetworks: options.allowedCardNetworks,
                allowCreditCards: options.allowCreditCards,
                allowPrepaidCards: options.allowPrepaidCards
            };
            // for some reason this does not get applied without timeout?
            setTimeout(
                function () {
                    unzerPaymentElement.setGooglePayData(paymentDataRequestObject);
                },
                100
            );


            const unzerCheckout = document.getElementById('unzer-google-pay-checkout-component');
            unzerCheckout.onPaymentSubmit = function (response) {
                if (response.submitResponse && response.submitResponse.success) {
                    document.getElementById('unzer-google-pay-id').value = response.submitResponse.data.id;
                    UnzerManager.getCheckoutForm().trigger('submit');
                } else {
                    UnzerManager.error(unzer_parameters.generic_error_message);
                    console.log('ERROR', response);
                }
            };
        }
        jQuery('.woocommerce-checkout').off('checkout_place_order_unzer_google_pay');
        jQuery('.woocommerce-checkout').on(
            'checkout_place_order_unzer_google_pay',
            function () {
                if (document.getElementById('unzer-google-pay-id').value) {
                    return true;
                }
                console.error('Google Pay: Checkout triggered without payment data.');
                UnzerManager.error(unzer_parameters.generic_error_message);
            }
        );
    },

    initApplePay() {
        UnzerManager.createApplePayButtonContainer();
        const unzerPaymentElement = document.getElementById('unzer-apple-pay-payment-component');
        if (unzerPaymentElement && unzerPaymentElement.setApplePayData) {
            const applePayPaymentRequest = {
                countryCode: unzer_parameters.store_country,
                currencyCode: UnzerManager.currency,
                supportedNetworks: ['visa', 'masterCard'],
                merchantCapabilities: ['supports3DS'],
                total: {
                    label: unzer_parameters.store_name,
                    amount: parseFloat(document.getElementById('unzer-apple-pay-v2-amount').value)
                }
            };
            // for some reason this does not get applied without timeout?
            setTimeout(
                function () {
                    unzerPaymentElement.setApplePayData(applePayPaymentRequest);
                },
                100
            );
            const unzerCheckout = document.getElementById('unzer-apple-pay-checkout-component');
            unzerCheckout.onPaymentSubmit = function (response) {
                if (response.submitResponse && response.submitResponse.data && response.submitResponse.data.id && response.submitResponse.data.id.indexOf('apple') === -1) {
                    return
                }
                if (response.submitResponse && response.submitResponse.success) {
                    document.getElementById('unzer-apple-pay-v2-id').value = response.submitResponse.data.id;
                    UnzerManager.getCheckoutForm().trigger('submit');
                } else {
                    UnzerManager.error(unzer_parameters.generic_error_message);
                    console.log('ERROR', response);
                }
            };
        }
        jQuery('.woocommerce-checkout').off('checkout_place_order_unzer_apple_pay_v2');
        jQuery('.woocommerce-checkout').on(
            'checkout_place_order_unzer_apple_pay_v2',
            function () {
                if (document.getElementById('unzer-apple-pay-v2-id').value) {
                    return true;
                }
                console.error('Apple Pay: Checkout triggered without payment data.');
                UnzerManager.error(unzer_parameters.generic_error_message);
            }
        );
    },

    createGooglePayButtonContainer() {
        const isGooglePay = UnzerManager.getSelectedPaymentMethod() === 'unzer_google_pay';
        const placeOrderButton = document.querySelector('#place_order');
        const googlePayHtml = document.querySelector('.unzer-google-pay-ui-template');

        if (!isGooglePay) {
            let googlePayButton = document.getElementById('unzer_google_pay_place_order');
            if (googlePayButton) {
                googlePayButton.remove();
            }
            return;
        }


        if (placeOrderButton && googlePayHtml) {
            let googlePayButton = document.getElementById('unzer_google_pay_place_order');
            if (!googlePayButton) {
                googlePayButton = document.createElement('div');
                googlePayButton.id = 'unzer_google_pay_place_order';
                googlePayButton.style.display = 'none';
                googlePayButton.innerHTML = googlePayHtml.innerHTML;
                placeOrderButton.parentNode.appendChild(googlePayButton);
            }
        } else {
            console.warn('placeOrderButton not found for Google Pay Button');
        }
    },

    createApplePayButtonContainer() {
        const isApplePay = UnzerManager.getSelectedPaymentMethod() === 'unzer_apple_pay_v2';
        const placeOrderButton = document.querySelector('#place_order');
        const applePayHtml = document.querySelector('.unzer-apple-pay-ui-template');

        if (!isApplePay) {
            let applePayButton = document.getElementById('unzer_apple_pay_v2_place_order');
            if (applePayButton) {
                applePayButton.remove();
            }
            return;
        }

        if (placeOrderButton && applePayHtml) {
            let applePayButton = document.getElementById('unzer_apple_pay_v2_place_order');
            if (!applePayButton) {
                applePayButton = document.createElement('div');
                applePayButton.id = 'unzer_apple_pay_v2_place_order';
                applePayButton.style.display = 'none';
                applePayButton.innerHTML = applePayHtml.innerHTML;
                placeOrderButton.parentNode.appendChild(applePayButton);
            }
        } else {
            console.warn('placeOrderButton not found for Apple Pay Button');
        }
    },

    getCheckoutForm() {
        return jQuery('form.woocommerce-checkout, form#order_review');
    },

    showLoading() {
        const $checkoutForm = UnzerManager.getCheckoutForm();
        if ($checkoutForm) {
            $checkoutForm.block(
                {
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                }
            );
        }
    },

    hideLoading() {
        const $checkoutForm = UnzerManager.getCheckoutForm();
        if ($checkoutForm) {
            $checkoutForm.removeClass('processing').unblock();
        }
    },

    error(message) {
        console.error(message);
        jQuery('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
        const $checkoutForm = UnzerManager.getCheckoutForm();
        $checkoutForm.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div class="woocommerce-error">' + message + '</div></div>'); // eslint-disable-line max-len
        $checkoutForm.removeClass('processing').unblock();
        $checkoutForm.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');
        UnzerManager.scrollToNotices();
        jQuery(document.body).trigger('checkout_error', [message]);
    },

    scrollToNotices() {
        let scrollElement = jQuery('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');
        if (!scrollElement.length) {
            scrollElement = jQuery('form.checkout');
        }
        jQuery.scroll_to_notices(scrollElement);
    },

    isB2B() {
        const companyNameInput = document.getElementById('billing_company');
        return (companyNameInput && companyNameInput.value !== '');
    },

    getCountry() {
        const countryInput = document.getElementById('billing_country');
        let value = null;
        return countryInput ? countryInput.value : null
    },
    customDebug(data) {
        if (!document.getElementById('unzer_debug')) {
            const debug = document.createElement('div');
            debug.id = 'unzer_debug';
            debug.style.position = 'fixed';
            debug.style.top = '0';
            debug.style.right = '0';
            debug.style.zIndex = '100000';
            debug.style.padding = '10px';
            debug.style.backgroundColor = 'rgba(255,255,255,0.9)';
            debug.style.border = '1px solid #ccc';
            debug.style.fontFamily = 'monospace';
            debug.style.fontSize = '12px';
            debug.style.maxWidth = '300px';
            debug.style.overflow = 'auto';
            debug.style.maxHeight = '40vh';
            document.body.appendChild(debug);
        }
        document.getElementById('unzer_debug').innerHTML += "\n\n" + JSON.stringify(data, null, 2);
    },

    getSelectedPaymentMethod() {
        const selectedRadio = document.querySelector('input[name="payment_method"]:checked');
        if (!selectedRadio) {
            return null;
        }
        return selectedRadio.value;
    },
    renderCurrentPaymentMethod() {
        const method = UnzerManager.getSelectedPaymentMethod();
        document.querySelectorAll('.unzer-ui-container').forEach(
            function (el) {
                el.innerHTML = '';
            }
        );

        const currentBox = document.querySelector('div.payment_box.payment_method_' + method);
        if (!currentBox) {
            return;
        }
        const unzerUiTemplate = currentBox.querySelector('.unzer-ui-template');
        if (!unzerUiTemplate) {
            return;
        }

        const thisContainer = currentBox.querySelector('.unzer-ui-container');
        if (thisContainer.querySelector('unzer-payment')) {
            // already there
            return;
        }

        thisContainer.innerHTML = unzerUiTemplate.innerHTML;
    },
    initDefaultPaymentMethod(paymentTypeIdInputFieldId, unzerUiPaymentElementId, paymentMethodName) {
        jQuery(document.body).on(
            'checkout_error',
            function () {
                const inputField = document.getElementById(paymentTypeIdInputFieldId);
                if (inputField) {
                    inputField.value = '';
                }
            }
        );
        jQuery('.woocommerce-checkout').off('checkout_place_order_' + paymentMethodName);
        jQuery('.woocommerce-checkout').on(
            'checkout_place_order_' + paymentMethodName,
            function () {
                return UnzerManager.handleSubmit(paymentTypeIdInputFieldId, unzerUiPaymentElementId);
            }
        );
    },
    handleSubmit(inputFieldId, unzerUiPaymentElementId) {
        if (document.getElementById(inputFieldId).value) {
            return true;
        }
        const unzerUiPaymentElement = document.getElementById(unzerUiPaymentElementId);
        UnzerManager.showLoading();
        UnzerManager.submitPaymentElement(unzerUiPaymentElement).then(
            function (submitResult) {
                UnzerManager.hideLoading();
                if (submitResult) {
                    document.getElementById(inputFieldId).value = submitResult.paymentTypeId;
                    const inputFieldsIdBase = inputFieldId.substring(0, inputFieldId.length - 2);
                    console.log(inputFieldsIdBase);
                    if (submitResult.threatMetrixId) {
                        const riskIdField = document.getElementById(inputFieldsIdBase + 'risk-id');
                        if (riskIdField) {
                            riskIdField.value = submitResult.threatMetrixId;
                        }
                    }
                    if (submitResult.customerId) {
                        const customerIdField = document.getElementById(inputFieldsIdBase + 'customer-id');
                        if (customerIdField) {
                            customerIdField.value = submitResult.customerId;
                        }
                    }
                    UnzerManager.getCheckoutForm().trigger('submit');
                }

            }
        ).catch(
            (error) => {
                console.error(error);
                UnzerManager.hideLoading();
            }
        );


        return false;
    },
    supportsApplePay() {
        return window.ApplePaySession && window.ApplePaySession.canMakePayments() && window.ApplePaySession.supportsVersion(6);
    },
    _setCustomerDataToPaymentComponent(selector) {
        const paymentElement = document.querySelector(selector);
        if (paymentElement) {
            Promise.all([customElements.whenDefined('unzer-payment')]).then(
                () => {
                    try {
                        const customerData = JSON.parse(atob(paymentElement.getAttribute('data-customer')));
                        console.log(
                            'set customer data',
                            customerData
                        );
                        if (customerData) {
                            paymentElement.setCustomerData(
                                customerData
                            );
                        }
                    } catch (e) {
                        console.error(e);
                    }
                }
            );
        }
    }
}


jQuery(
    function () {
        UnzerManager.init();
        const paymentContainer = document.querySelector('.woocommerce-checkout-payment');
        if (paymentContainer) {
            const observer = new MutationObserver(UnzerManager.init);
            observer.observe(paymentContainer, {attributes: true, childList: true, subtree: true});
        }
        jQuery(document.body).on(
            'updated_checkout',
            function () {
                UnzerManager.init();
                UnzerManager.renderCurrentPaymentMethod();
            }
        );

        jQuery(document.body).on(
            'payment_method_selected',
            function () {
                UnzerManager.init();
                UnzerManager.renderCurrentPaymentMethod();
            }
        );

        if (jQuery(document.body).hasClass('woocommerce-order-pay')) {
            const $form = jQuery('#order_review');
            jQuery($form).on(
                'submit',
                function (e) {
                    const selectedPaymentMethod = $form.find('[name="payment_method"]:checked').val();
                    if (selectedPaymentMethod.substring(0, 6) === 'unzer_') {
                        const returnValue = jQuery('.woocommerce-checkout').triggerHandler('checkout_place_order_' + selectedPaymentMethod);
                        if (returnValue === false) {
                            e.preventDefault();
                        }
                    }
                }
            );
        }

        jQuery('#billing_company').on(
            'keyup',
            function () {
                if (UnzerManager.billingCompanyTimeout) {
                    clearTimeout(UnzerManager.billingCompanyTimeout);
                }
                UnzerManager.billingCompanyTimeout = setTimeout(
                    function () {
                        jQuery('form.checkout').trigger('update');
                    },
                    1000
                );

            }
        );

        setInterval(
            function () {
                const placeOrderButton = document.querySelector('#place_order');
                let showPlaceOrderButton = true;

                const applePayContainer = document.querySelector('.payment_method_unzer_apple_pay_v2');
                if (applePayContainer) {
                    const applePayButton = document.getElementById('unzer_apple_pay_v2_place_order');
                    if (!UnzerManager.supportsApplePay()) {
                        applePayContainer.style.display = 'none';
                    }
                    if (applePayButton && placeOrderButton) {
                        if (document.getElementById('payment_method_unzer_apple_pay_v2').checked && UnzerManager.supportsApplePay()) {
                            applePayButton.style.display = '';
                            showPlaceOrderButton = false;
                        } else {
                            applePayButton.style.display = 'none';
                        }
                    }
                }

                const googlePayContainer = document.querySelector('.payment_method_unzer_google_pay');
                const googlePayButton = document.getElementById('unzer_google_pay_place_order');
                if (googlePayContainer && googlePayButton && placeOrderButton) {
                    if (document.getElementById('payment_method_unzer_google_pay').checked) {
                        googlePayButton.style.display = '';
                        showPlaceOrderButton = false;
                    } else {
                        googlePayButton.style.display = 'none';
                    }
                }

                if (placeOrderButton) {
                    if (showPlaceOrderButton) {
                        placeOrderButton.style.display = '';
                    } else {
                        placeOrderButton.style.display = 'none';
                    }
                }
            },
            500
        );
    }
);

