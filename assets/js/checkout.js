const UnzerConfig = {
    getPublicKey() {
        return unzer_parameters.publicKey || '';
    },
    getLocale() {
        return unzer_parameters.locale || navigator.language || navigator.userLanguage || null;
    }
}

const UnzerManager = {
    instance: null,
    initTimeout: null,
    init() {
        console.log('UnzerManager.init()');
        if (UnzerManager.initTimeout) {
            clearTimeout(UnzerManager.initTimeout);
        }
        UnzerManager.initTimeout = setTimeout(() => {
            console.log('UnzerManager - process delayed init');
            UnzerManager.instance = UnzerManager.instance || new unzer(UnzerConfig.getPublicKey(), {locale: UnzerConfig.getLocale()});
            UnzerManager.initCard();
            UnzerManager.initDirectDebit();
            UnzerManager.initDirectDebitSecured();
            UnzerManager.initInstallment();
            UnzerManager.initEps();
            UnzerManager.initIdeal();
            UnzerManager.initInvoice();
            UnzerManager.initSavedPaymentInstruments();
        }, 500);
    },

    initSavedPaymentInstruments(){
        const updateFunction = ()=>{
            const containers = document.querySelectorAll('.unzer-saved-payment-instruments-container');
            if(!containers){
                return;
            }
            containers.forEach((container)=>{
               const newForm = container.querySelector('.unzer-payment-instrument-new-form');
               if(container.querySelector('.unzer-payment-instrument-new-radio:checked')){
                   newForm.style.display = 'block';
               }else{
                   newForm.style.display = 'none';
               }
            });
        }
        updateFunction();
        const allRadios = document.querySelectorAll('.unzer-payment-instrument-radio');
        if(allRadios){
            allRadios.forEach((radio)=>{
                radio.addEventListener('change', updateFunction);
            })
        }
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
        document.getElementById('unzer-card-id').value = '';
        jQuery( document.body ).on( 'checkout_error', ()=>{
            //document.getElementById('unzer-card-id').value = '';
        });
        jQuery('.woocommerce-checkout').off('checkout_place_order_unzer_card');
        jQuery('.woocommerce-checkout').on('checkout_place_order_unzer_card', function () {
            const selectedSavedCard = document.querySelector('[name="unzer_card_payment_instrument"]:checked');
            if(selectedSavedCard && selectedSavedCard.value){
                return true;
            }
            if (document.getElementById('unzer-card-id').value) {
                return true;
            }
            cardInstance.createResource()
                .then(function (result) {
                    document.getElementById('unzer-card-id').value = result.id;
                    document.getElementById('unzer-card-form').innerHTML = '<div style="font-size:0.8em;">' + result.cardHolder + '<br/>' + result.number + '<br/>' + result.expiryDate + '</div>';
                    console.log(result);
                    UnzerManager.getCheckoutForm().trigger('submit');
                })
                .catch(function (error) {
                    console.warn(error);
                    UnzerManager.error(error.customerMessage || error.message);
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
        document.getElementById('unzer-direct-debit-id').value = '';
        jQuery( document.body ).on( 'checkout_error', ()=>{
            document.getElementById('unzer-direct-debit-id').value = '';
        });
        jQuery('.woocommerce-checkout').off('checkout_place_order_unzer_direct_debit');
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
                    UnzerManager.error(error.customerMessage || error.message);
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
        document.getElementById('unzer-direct-debit-secured-id').value = '';
        jQuery( document.body ).on( 'checkout_error', ()=>{
            document.getElementById('unzer-direct-debit-secured-id').value = '';
        });
        jQuery('.woocommerce-checkout').off('checkout_place_order_unzer_direct_debit_secured');
        jQuery('.woocommerce-checkout').on('checkout_place_order_unzer_direct_debit_secured', function () {
            if (!document.getElementById('unzer-direct-debit-secured-dob').value) {
                UnzerManager.error(unzer_i18n.errorDob || 'Please enter your date fo birth');
                return false;
            }
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
                    UnzerManager.error(error.customerMessage || error.message);
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

    initInvoice() {
        if (!document.getElementById('unzer-invoice-form')) {
            return;
        }
        if (document.getElementById('unzer-invoice-form').getAttribute('is-init')) {
            return;
        }
        document.getElementById('unzer-invoice-form').setAttribute('is-init', true);

        const invoiceInstance = UnzerManager.instance.PaylaterInvoice();
        invoiceInstance.create({
            containerId: 'unzer-invoice-fields',
            customerType: 'B2C',
        })
        document.getElementById('unzer-invoice-id').value = '';
        jQuery( document.body ).on( 'checkout_error', ()=>{
            document.getElementById('unzer-invoice-id').value = '';
        });
        jQuery('.woocommerce-checkout').off('checkout_place_order_unzer_invoice');
        jQuery('.woocommerce-checkout').on('checkout_place_order_unzer_invoice', function () {
            if (document.getElementById('unzer-invoice-id').value) {
                return true;
            }
            if (!document.getElementById('unzer-invoice-dob').value) {
                UnzerManager.error(unzer_i18n.errorDob || 'Please enter your date fo birth');
                return false;
            }
            if (UnzerManager.isB2B() && !document.getElementById('unzer-invoice-company-type').value) {
                UnzerManager.error(unzer_i18n.errorCompanyType || 'Please enter your company type');
                return false;
            }
            invoiceInstance.createResource()
                .then(function (result) {
                    document.getElementById('unzer-invoice-id').value = result.id;
                    UnzerManager.getCheckoutForm().trigger('submit');
                })
                .catch(function (error) {
                    console.warn(error);
                    UnzerManager.error(error.customerMessage || error.message);
                })
            return false;
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
        epsInstance.addEventListener('change', ()=>{
            document.getElementById('unzer-eps-id').value = '';
        });
        document.getElementById('unzer-eps-id').value = '';
        jQuery( document.body ).on( 'checkout_error', ()=>{
            document.getElementById('unzer-eps-id').value = '';
        });
        jQuery('.woocommerce-checkout').off('checkout_place_order_unzer_eps');
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
                    UnzerManager.error(error.customerMessage || error.message);
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
        idealInstance.addEventListener('change', ()=>{
            document.getElementById('unzer-ideal-id').value = '';
        });
        document.getElementById('unzer-ideal-id').value = '';
        jQuery( document.body ).on( 'checkout_error', ()=>{
            document.getElementById('unzer-ideal-id').value = '';
        });
        jQuery('.woocommerce-checkout').off('checkout_place_order_unzer_ideal');
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
                    UnzerManager.error(error.customerMessage || error.message);
                })
            return false;
        });
    },

    getCheckoutForm() {
        return jQuery('form.woocommerce-checkout');
    },

    error(message) {
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

    isB2B(){
        const companyNameInput = document.getElementById('billing_company');
        return (companyNameInput && companyNameInput.value !== '');
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
    setInterval(function (){
        const companyTypeInputContainer = document.getElementById('unzer-invoice-company-type-container');
        if(!companyTypeInputContainer){
            return;
        }
        companyTypeInputContainer.style.display = UnzerManager.isB2B()?'block':'none';
    }, 500);
});

