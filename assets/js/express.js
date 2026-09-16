const ExpressButtons = {
    interval: null,
    intervalCounter: 0,
    cartTotal: null,
    attemptToPlaceButton: function () {
        let container = document.querySelector( '.wc-block-mini-cart__footer');

        if ( ! container ) {
            container = document.querySelector( '.wc-block-cart__submit-container' );
        }
        if ( ! container) {
            container = document.querySelector('.wc-proceed-to-checkout');
            if ( ! container) {
                return false;
            }
            container = container.parentElement;
        }
        if (document.getElementById( 'unzer-express-buttons-container' )) {
            return false;
        }

        const button_container = document.createElement( 'div' );
        button_container.id    = 'unzer-express-buttons-container';

        const unzer_express_error_container = document.createElement('div');
        unzer_express_error_container.classList.add('unzer-express-buttons-error');
        unzer_express_error_container.classList.add('woocommerce-error');
        unzer_express_error_container.hidden = true;
        unzer_express_error_container.innerHTML = unzer_parameters.generic_error_message_express;
        button_container.appendChild(unzer_express_error_container);

        const unzer_payment_container = document.createElement( 'unzer-payment' );
        unzer_payment_container.classList.add('unzer-express-payment');
        unzer_payment_container.setAttribute('publicKey', unzer_parameters.publicKey);
        unzer_payment_container.setAttribute('locale', unzer_parameters.locale);

        if (unzer_parameters.express.paypal) {
            const unzer_paypal_express_container = document.createElement('div');
            unzer_paypal_express_container.classList.add('unzer-paypal-express-container');
            const unzer_paypal_express_element = document.createElement('unzer-paypal-express');
            unzer_paypal_express_element.classList.add('unzer-paypal-express');
            unzer_paypal_express_container.appendChild(unzer_paypal_express_element);
            unzer_payment_container.appendChild(unzer_paypal_express_container);
        }

        if (unzer_parameters.express.googlepay) {
            const unzer_google_pay_express_container = document.createElement('div');
            unzer_google_pay_express_container.classList.add('unzer-google-pay-express-container');
            const unzer_google_pay_express_element = document.createElement('unzer-google-pay');
            unzer_google_pay_express_element.classList.add('unzer-google-pay');
            unzer_google_pay_express_container.appendChild(unzer_google_pay_express_element);
            unzer_payment_container.appendChild(unzer_google_pay_express_container);
        }

        if (unzer_parameters.express.applepay) {
            const unzer_applepay_express_container = document.createElement('div');
            unzer_applepay_express_container.classList.add('unzer-applepay-express-container');
            const unzer_applepay_express_element = document.createElement('unzer-apple-pay');
            unzer_applepay_express_element.classList.add('unzer-apple-pay');
            unzer_applepay_express_container.appendChild(unzer_applepay_express_element);
            unzer_payment_container.appendChild(unzer_applepay_express_container);
        }

        button_container.appendChild(unzer_payment_container);

        container.appendChild( button_container );
        return true;
    },

    getTotals: async function() {
        const cartTotal = await getWooCommerceCartAmount();

        if (!cartTotal) {
            console.warn('Warenkorb-Betrag konnte nicht ermittelt werden.');
            return null;
        }

        ExpressButtons.cartTotal = cartTotal;
        return cartTotal;
    },

    init: async function () {
        if (!unzer_parameters.express.paypal && !unzer_parameters.express.googlepay && !unzer_parameters.express.applepay) {
            return false;
        }
        if (unzer_parameters.express.is_express) {
            return false;
        }

        const cartTotal = await this.getTotals();

        if (!cartTotal || !cartTotal.amount) {
            return false;
        }
        if ( ! ExpressButtons.attemptToPlaceButton()) {
            return false;
        }

        customElements.whenDefined('unzer-payment').then(() => {
            const unzerExpressPayment = document.querySelector(
                '.unzer-express-payment'
            );

            // setMerchantConfigData?

            const unzerPaypalExpress = document.querySelector(
                '.unzer-paypal-express'
            );
            const unzerGooglePay = document.querySelector('.unzer-google-pay');
            const unzerApplePay = document.querySelector('.unzer-apple-pay');

            if (!unzerExpressPayment) {
                return;
            }

            if (unzerPaypalExpress) {
                this.registerPaypalExpress(
                    unzerPaypalExpress,
                    unzerExpressPayment
                );
            }

            if (unzerGooglePay) {
                this.registerGooglePay(unzerGooglePay, unzerExpressPayment);
                const container = unzerGooglePay.parentNode;
                unzerGooglePay.remove();
                container.appendChild(unzerGooglePay);
            }

            if (unzerApplePay) {
                this.registerApplePay(unzerApplePay, unzerExpressPayment);
                const container = unzerApplePay.parentNode;
                unzerApplePay.remove();
                container.appendChild(unzerApplePay);
            }
        });
        return true;
    },

    refresh: function()
    {
        if (window.unzerCurrentAmount && this.cartTotal) {
            this.cartTotal.amount = window.unzerCurrentAmount;
        } else {
            this.getTotals();
        }
        if (document.getElementById('unzer-express-buttons-container')) {
            document.getElementById('unzer-express-buttons-container').remove();
        }
        this.init();
    },

    showError: function() {
        const errorElement = document.querySelector(
            '.unzer-express-buttons-error'
        );
        if (errorElement) {
            errorElement.removeAttribute('hidden');
        }
    },

    registerPaypalExpress: function (paypalButton, unzerExpressPayment) {
        paypalButton.id =
            'unzer-paypal-button-' + Math.floor(Math.random() * 10000);
        paypalButton.addEventListener('click', async (event) => {
            event.stopPropagation();
            const response = await unzerExpressPayment.submit();

            if (!response?.submitResponse?.success) {
                this.showError();
                return;
            }
            const paymentTypeId = response.submitResponse.data.id;
            fetch(unzer_parameters.urls.paypal, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    paymentTypeId: paymentTypeId,
                }),
            })
                .then((response) => response.json())
                .then((json) => {
                    location.href = json.redirectUrl;
                })
                .catch(() => {
                    this.showError();
                });
        });
    },

    registerGooglePay: function(googlePayButton, unzerExpressPayment) {
        const googlePayData = {
            gatewayMerchantId: unzer_parameters.google_pay_options.gatewayMerchantId,
            merchantInfo: unzer_parameters.google_pay_options.merchantInfo,
            transactionInfo: {
                currencyCode: unzer_parameters.currency,
                countryCode: unzer_parameters.google_pay_options.countryCode,
                totalPriceStatus: 'ESTIMATED',
                checkoutOption: 'DEFAULT',
                totalPrice: String(ExpressButtons.cartTotal.amount),
            },
            buttonOptions: unzer_parameters.google_pay_options.buttonOptions,
            allowedCardNetworks: unzer_parameters.google_pay_options.allowedCardNetworks,
            allowCreditCards: unzer_parameters.google_pay_options.allowCreditCards,
            allowPrepaidCards: unzer_parameters.google_pay_options.allowPrepaidCards,
            billingAddressParameters: { format: 'MIN' },
            billingAddressRequired: true,
            emailRequired: true,
            onPaymentDataChangedCallback: () => {
                return {};
            },
            shippingOptionParameters: {},
            onPaymentAuthorizedCallback: async (
                paymentData,
                approve,
                reject
            ) => {
                // You can create customer here based on paymentData
                const response = await unzerExpressPayment.submit();
                if (!response?.submitResponse?.success) {
                    this.showError();
                    return;
                }
                const paymentTypeId = response.submitResponse.data.id;

                fetch(unzer_parameters.urls.googlePay, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        paymentTypeId,
                        paymentData,
                    }),
                })
                    .then((response) => response.json())
                    .then((json) => {
                        location.href = json.redirectUrl;
                    })
                    .catch(() => {
                        this.showError();
                    });
            },
            shippingAddressRequired: true,
            shippingOptionRequired: false,
        };
        unzerExpressPayment.setGooglePayData(googlePayData);
    },

    registerApplePay: function(applePayButton, unzerExpressPayment) {
        console.log('register amount', ExpressButtons.cartTotal.amount);
         const applePayPaymentRequest = {
            countryCode: unzer_parameters.applepay_options.countryCode,
            currencyCode: unzer_parameters.currency,
            supportedNetworks: unzer_parameters.applepay_options.supportedNetworks,
            merchantCapabilities: unzer_parameters.applepay_options.merchantCapabilities,
            total: {
                label: unzer_parameters.applepay_options.shopName,
                amount: String(ExpressButtons.cartTotal.amount),
            },
            requiredShippingContactFields: [
                'postalAddress',
                'name',
                'email',
                'phone',
            ],
            requiredBillingContactFields: [
                'postalAddress',
                'name',
                'email',
                'phone',
            ],

            onPaymentAuthorizedCallback: async (
                paymentData,
                approve,
                reject,
                event
            ) => {
                let shippingContact = event.payment.shippingContact; // Store the shipping contact data for express checkout
                let billingContact = event.payment.billingContact; // Store the billing contact data for express checkout

                // You can create customer here based on paymentData
                const response = await unzerExpressPayment.submit();
                if (response.submitResponse.success) {
                    approve();
                } else {
                    reject();
                    this.showError();
                    return;
                }
                const paymentTypeId = response.submitResponse.data.id;

                fetch(unzer_parameters.urls.applePay, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        paymentTypeId,
                        paymentData,
                        shippingContact,
                        billingContact,
                    }),
                })
                    .then((response) => response.json())
                    .then((json) => {
                        location.href = json.redirectUrl;
                    })
                    .catch(() => {
                        this.showError();
                    });
            },
        };
        applePayPaymentRequest.initApplePaySession = (applePaySession) => {
            const session = applePaySession;
        };
        unzerExpressPayment?.setApplePayData(applePayPaymentRequest);
    }
}

ExpressButtons.interval = setInterval(
    async function () {
        ExpressButtons.intervalCounter++;
        if (ExpressButtons.init() || ExpressButtons.intervalCounter > 10) {
            clearInterval( ExpressButtons.interval );
        }
    },
    500
);

async function getWooCommerceCartAmount() {

    try {
        if (
            window.wp &&
            window.wp.data &&
            window.wp.data.select('wc/store/cart')
        ) {
            const cart = window.wp.data.select('wc/store/cart').getCartData();

            if (cart?.totals?.total_price) {
                return {
                    amount: parseInt(cart.totals.total_price, 10) / 100,
                    currency: cart.totals.currency_code || null,
                    source: 'wc-blocks'
                };
            }
        }
    } catch (e) {}

    try {
        const response = await fetch('/wp-json/wc/store/v1/cart');
        const cart = await response.json();

        if (cart?.totals?.total_price) {
            return {
                amount: parseInt(cart.totals.total_price, 10) / 100,
                currency: cart.totals.currency_code || null,
                source: 'store-api'
            };
        }
    } catch (e) {}

    const selectors = [
        '.woocommerce-checkout .order-total .woocommerce-Price-amount bdi',
        '.wc-block-mini-cart__footer .wc-block-formatted-money-amount',
        '.woocommerce-cart .cart_totals .order-total .woocommerce-Price-amount bdi',
        '.order-total .woocommerce-Price-amount bdi'
    ];

    for (const selector of selectors) {
        const element = document.querySelector(selector);

        if (element) {
            const amount = parseWooCommercePrice(element.textContent);

            if (!Number.isNaN(amount)) {
                return {
                    amount,
                    currency: getCurrencyFromPriceText(element.textContent),
                    source: 'dom'
                };
            }
        }
    }

    return null;
}

function parseWooCommercePrice(priceText) {
    return parseFloat(
        priceText
            .replace(/\s/g, '')
            .replace(/[^\d,.-]/g, '')
            .replace(/\./g, '')
            .replace(',', '.')
    );
}

function getCurrencyFromPriceText(priceText) {
    if (priceText.includes('€')) return 'EUR';
    if (priceText.includes('$')) return 'USD';
    if (priceText.includes('£')) return 'GBP';

    return null;
}

jQuery( function( $ ) {
    $( document.body ).on( 'updated_cart_totals', function() {
        ExpressButtons.refresh();
    });
});

let miniCartRefreshTimeout = null;

function observeMiniCartSubtotal() {
    const target = document.querySelector('.wc-block-mini-cart__footer-subtotal');

    if (!target) {
        return false;
    }

    const observer = new MutationObserver(() => {
        clearTimeout(miniCartRefreshTimeout);

        miniCartRefreshTimeout = setTimeout(() => {
            ExpressButtons.refresh();
        }, 200);
    });

    observer.observe(target, {
        childList: true,
        subtree: true,
        characterData: true
    });

    return true;
}

let miniCartObserverInterval = setInterval(function () {
    if (observeMiniCartSubtotal()) {
        clearInterval(miniCartObserverInterval);
    }
}, 500);