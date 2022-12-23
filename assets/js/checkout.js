const UnzerConfig = {
    getPublicKey() {
        return unzer_parameters.publicKey || '';
    }
}

const UnzerManager = {
    instance: null,
    initTimeout: null,
    init() {
        if (UnzerManager.initTimeout) {
            clearTimeout(UnzerManager.initTimeout);
        }
        UnzerManager.initTimeout = setTimeout(() => {
            UnzerManager.instance = UnzerManager.instance || new unzer(UnzerConfig.getPublicKey());
            UnzerManager.initCard();
            UnzerManager.initDirectDebit();
            UnzerManager.initDirectDebitSecured();
            UnzerManager.initInstallment();
            UnzerManager.initEps();
            UnzerManager.initIdeal();
        }, 500);
    },

    initCard() {
        if (!document.getElementById('unzer-card-form')) {
            return;
        }
        if (document.getElementById('unzer-card-form').getAttribute('is-init')) {
            return;
        }
        document.getElementById('unzer-card-form').setAttribute('is-init', true);

        // Create an Unzer instance with your public key

        const cardInstance = UnzerManager.instance.Card();
        cardInstance.create('holder', {
            containerId: 'unzer-card-form-holder',
            onlyIframe: false
        });
        cardInstance.create('number', {
            containerId: 'unzer-card-form-number',
            onlyIframe: false
        });
        cardInstance.create('expiry', {
            containerId: 'unzer-card-form-expiry',
            onlyIframe: false
        });
        cardInstance.create('cvc', {
            containerId: 'unzer-card-form-cvc',
            onlyIframe: false
        });
        jQuery('.woocommerce-checkout').on('checkout_place_order_unzer_card', function () {
            if (document.getElementById('unzer-card-id').value) {
                return true;
            }
            cardInstance.createResource()
                .then(function (result) {
                    document.getElementById('unzer-card-id').value = result.id;
                    UnzerManager.getCheckoutForm().trigger('submit');
                    console.log(result);
                })
                .catch(function (error) {
                    console.warn(error);
                    UnzerManager.error(error.message);
                })
            return false;
        });
    },

    initDirectDebit() {
        if (!document.getElementById('unzer-direct-debit-form')) {
            return;
        }
        if (document.getElementById('unzer-direct-debit-form').getAttribute('is-init')) {
            return;
        }
        document.getElementById('unzer-direct-debit-form').setAttribute('is-init', true);

        // Create an Unzer instance with your public key

        const directDebitInstance = UnzerManager.instance.SepaDirectDebit();

        directDebitInstance.create('sepa-direct-debit', {
            containerId: 'unzer-direct-debit-iban'
        });

        jQuery('.woocommerce-checkout').on('checkout_place_order_unzer_direct_debit', function () {
            if (document.getElementById('unzer-direct-debit-id').value) {
                return true;
            }
            directDebitInstance.createResource()
                .then(function (result) {
                    document.getElementById('unzer-direct-debit-id').value = result.id;
                    UnzerManager.getCheckoutForm().trigger('submit');
                })
                .catch(function (error) {
                    console.warn(error);
                    UnzerManager.error(error.message);
                })
            return false;
        });
    },

    initDirectDebitSecured() {
        if (!document.getElementById('unzer-direct-debit-secured-form')) {
            return;
        }
        if (document.getElementById('unzer-direct-debit-secured-form').getAttribute('is-init')) {
            return;
        }
        document.getElementById('unzer-direct-debit-secured-form').setAttribute('is-init', true);


        const directDebitSecuredInstance = UnzerManager.instance.SepaDirectDebitSecured();
        directDebitSecuredInstance.create('sepa-direct-debit-secured', {
            containerId: 'unzer-direct-debit-secured-iban'
        });

        jQuery('.woocommerce-checkout').on('checkout_place_order_unzer_direct_debit_secured', function () {
            if (document.getElementById('unzer-direct-debit-secured-id').value) {
                return true;
            }
            directDebitSecuredInstance.createResource()
                .then(function (result) {
                    document.getElementById('unzer-direct-debit-secured-id').value = result.id;
                    UnzerManager.getCheckoutForm().trigger('submit');
                })
                .catch(function (error) {
                    console.warn(error);
                    UnzerManager.error(error.message);
                })
            return false;
        });
    },

    initInstallment() {
        const form = document.getElementById('unzer-installment-form');
        if (!form) {
            return;
        }
        if (form.getAttribute('is-init')) {
            return;
        }
        form.setAttribute('is-init', true);


        const installmentInstance = UnzerManager.instance.InstallmentSecured();
        installmentInstance.create({
            containerId: 'unzer-installment',
            amount: 100,
            currency: 'EUR',
            orderDate: '2022-10-11',
            effectiveInterest: 4.5,
        });

    },

    initEps() {
        if (!document.getElementById('unzer-eps-form')) {
            return;
        }
        if (document.getElementById('unzer-eps-form').getAttribute('is-init')) {
            return;
        }
        document.getElementById('unzer-eps-form').setAttribute('is-init', true);

        // Create an Unzer instance with your public key

        const epsInstance = UnzerManager.instance.EPS();

        epsInstance.create('eps', {
            containerId: 'unzer-eps'
        });

        jQuery('.woocommerce-checkout').on('checkout_place_order_unzer_eps', function () {
            if (document.getElementById('unzer-eps-id').value) {
                return true;
            }
            epsInstance.createResource()
                .then(function (result) {
                    document.getElementById('unzer-eps-id').value = result.id;
                    UnzerManager.getCheckoutForm().trigger('submit');
                })
                .catch(function (error) {
                    console.warn(error);
                    UnzerManager.error(error.message);
                })
            return false;
        });
    },

    initIdeal() {
        if (!document.getElementById('unzer-ideal-form')) {
            return;
        }
        if (document.getElementById('unzer-ideal-form').getAttribute('is-init')) {
            return;
        }
        document.getElementById('unzer-ideal-form').setAttribute('is-init', true);

        // Create an Unzer instance with your public key

        const idealInstance = UnzerManager.instance.Ideal();

        idealInstance.create('ideal', {
            containerId: 'unzer-ideal'
        });

        jQuery('.woocommerce-checkout').on('checkout_place_order_unzer_ideal', function () {
            if (document.getElementById('unzer-ideal-id').value) {
                return true;
            }
            idealInstance.createResource()
                .then(function (result) {
                    document.getElementById('unzer-ideal-id').value = result.id;
                    UnzerManager.getCheckoutForm().trigger('submit');
                })
                .catch(function (error) {
                    console.warn(error);
                    UnzerManager.error(error.message);
                })
            return false;
        });
    },

    getCheckoutForm() {
        return jQuery('.woocommerce-checkout');
    },
    error(message) {
        jQuery('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
        const $checkoutForm = UnzerManager.getCheckoutForm();
        $checkoutForm.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + message + '</div>'); // eslint-disable-line max-len
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
    }
}


jQuery(() => {
    UnzerManager.init();
    const paymentContainer = document.querySelector('.woocommerce-checkout-payment');
    if (paymentContainer) {
        const observer = new MutationObserver(UnzerManager.init);
        observer.observe(paymentContainer, {attributes: true, childList: true, subtree: true});
    }
    jQuery(document.body).on('updated_checkout', UnzerManager.init);
});

