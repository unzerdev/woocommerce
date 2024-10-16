const UnzerConfig = {
	getPublicKey() {
		return unzer_parameters.publicKey || '';
	},
	getLocale() {
		return unzer_parameters.locale || navigator.language || navigator.userLanguage || null;
	},
	getPublicKeyForPayLater( isB2B, currency ) {
		if (currency === 'EUR') {
			return isB2B ? unzer_parameters.publicKey_eur_b2b : unzer_parameters.publicKey_eur_b2c;
		} else if (currency === 'CHF') {
			return isB2B ? unzer_parameters.publicKey_chf_b2b : unzer_parameters.publicKey_chf_b2c;
		}
		return null;
	},
	getPublicKeyForInstallment( currency ) {
		if (currency === 'EUR') {
			return unzer_parameters.publicKey_installment_eur_b2c;
		} else if (currency === 'CHF') {
			return unzer_parameters.publicKey_installment_chf_b2c;
		}
		return null;
	},
	getPublicKeyForDirectDebitSecured() {
		return unzer_parameters.publicKey_directdebitsecured_eur_b2c;
	}
}

const UnzerManager = {
	instance: null,
	currency: null,
	country: null,
	instancePayLaterB2B: null,
	instancePayLaterB2C: null,
	instanceInstallments: null,
	b2bState: false,
	initTimeout: null,
	init() {
		if (UnzerManager.initTimeout) {
			clearTimeout( UnzerManager.initTimeout );
		}

		UnzerManager.initTimeout = setTimeout(
			function () {
				UnzerManager.currency = unzer_parameters.currency;
				UnzerManager.instance = UnzerManager.instance || new unzer( UnzerConfig.getPublicKey(), {locale: UnzerConfig.getLocale()} );
				UnzerManager.b2bState = UnzerManager.isB2B();
				UnzerManager.checkCountry();
				// separate instances for invoice/paylater + b2b/b2c
				if (UnzerConfig.getPublicKeyForPayLater( true, UnzerManager.currency )) {
					UnzerManager.instancePayLaterB2B = UnzerManager.instancePayLaterB2B || new unzer(
						UnzerConfig.getPublicKeyForPayLater( true, UnzerManager.currency ),
						{
							locale: UnzerConfig.getLocale(),
							showNotify: false
						}
					);
				}
				if (UnzerConfig.getPublicKeyForPayLater( false, UnzerManager.currency )) {
					UnzerManager.instancePayLaterB2C = UnzerManager.instancePayLaterB2C || new unzer(
						UnzerConfig.getPublicKeyForPayLater( false, UnzerManager.currency ),
						{
							locale: UnzerConfig.getLocale(),
							showNotify: false
						}
					);
				}
				if (UnzerConfig.getPublicKeyForInstallment( UnzerManager.currency )) {
					UnzerManager.instanceInstallments = UnzerManager.instanceInstallments || new unzer(
						UnzerConfig.getPublicKeyForInstallment( UnzerManager.currency ),
						{
							locale: UnzerConfig.getLocale(),
							showNotify: false
						}
					);
				}
				if (UnzerConfig.getPublicKeyForDirectDebitSecured()) {
					UnzerManager.instanceDirectDebitSecured = UnzerManager.instanceDirectDebitSecured || new unzer(
						UnzerConfig.getPublicKeyForDirectDebitSecured(),
						{
							locale: UnzerConfig.getLocale(),
							showNotify: false
						}
					);
				}

				document.addEventListener(
					'unzer_country_changed',
					function () {
						UnzerManager.toggleDirectDebitSecuredDisplay();
						UnzerManager.toggleInstallmentDisplay();
					}
				);
				UnzerManager.initCard();
				UnzerManager.initDirectDebit();
				UnzerManager.initDirectDebitSecured();
				UnzerManager.initInstallment();
				UnzerManager.initIdeal();
				UnzerManager.initInvoice();
				UnzerManager.initApplePay();
				UnzerManager.initGooglePay();
				UnzerManager.initSavedPaymentInstruments();
			},
			500
		);
	},

	initSavedPaymentInstruments() {
		const updateFunction = function () {
			const containers = document.querySelectorAll( '.unzer-saved-payment-instruments-container' );
			if ( ! containers) {
				return;
			}
			containers.forEach(
				function (container) {
					const newForm = container.querySelector( '.unzer-payment-instrument-new-form' );
					if (container.querySelector( '.unzer-payment-instrument-new-radio:checked' )) {
						newForm.style.display = 'block';
					} else {
						newForm.style.display = 'none';
					}
				}
			);
		}
		updateFunction();
		const allRadios      = document.querySelectorAll( '.unzer-payment-instrument-radio' );
		if (allRadios) {
			allRadios.forEach(
				function (radio) {
					radio.addEventListener( 'change', updateFunction );
				}
			)
		}
	},

	initCard() {
		const cardForm = document.getElementById( 'unzer-card-form' );
		if ( ! cardForm) {
			return;
		}
		if (cardForm.getAttribute( 'is-init' )) {
			return;
		}
		cardForm.setAttribute( 'is-init', true );
		const cardFormId = cardForm.getAttribute( 'data-form-id' );

		// Create an Unzer instance with your public key

		const cardInstance = UnzerManager.instance.Card();
		cardInstance.create(
			'holder',
			{
				containerId: 'unzer-card-form-holder-' + cardFormId,
				onlyIframe: false
			}
		);
		cardInstance.create(
			'number',
			{
				containerId: 'unzer-card-form-number-' + cardFormId,
				onlyIframe: false
			}
		);
		cardInstance.create(
			'expiry',
			{
				containerId: 'unzer-card-form-expiry-' + cardFormId,
				onlyIframe: false
			}
		);
		cardInstance.create(
			'cvc',
			{
				containerId: 'unzer-card-form-cvc-' + cardFormId,
				onlyIframe: false
			}
		);
		document.getElementById( 'unzer-card-id' ).value = '';
		jQuery( document.body ).on(
			'checkout_error',
			function () {
				// document.getElementById('unzer-card-id').value = '';
			}
		);
		jQuery( '.woocommerce-checkout' ).off( 'checkout_place_order_unzer_card' );
		jQuery( '.woocommerce-checkout' ).on(
			'checkout_place_order_unzer_card',
			function () {
				const selectedSavedCard = document.querySelector( '[name="unzer_card_payment_instrument"]:checked' );
				if (selectedSavedCard && selectedSavedCard.value) {
					return true;
				}
				if (document.getElementById( 'unzer-card-id' ).value) {
					return true;
				}
				cardInstance.createResource()
					.then(
						function (result) {
							document.getElementById( 'unzer-card-id' ).value       = result.id;
							document.getElementById( 'unzer-card-form' ).innerHTML = '<div style="font-size:0.8em;">' + result.cardHolder + '<br/>' + result.number + '<br/>' + result.expiryDate + '</div>';
							UnzerManager.getCheckoutForm().trigger( 'submit' );
						}
					)
					.catch(
						function (error) {
							console.warn( error );
							UnzerManager.error( error.customerMessage || error.message );
						}
					)
				return false;
			}
		);
	},

	initDirectDebit() {
		const form = document.getElementById( 'unzer-direct-debit-form' );
		if ( ! form) {
			return;
		}
		if (form.getAttribute( 'is-init' )) {
			return;
		}
		form.setAttribute( 'is-init', true );
		const formId = form.getAttribute( 'data-form-id' );
		// Create an Unzer instance with your public key

		const directDebitInstance = UnzerManager.instance.SepaDirectDebit();

		directDebitInstance.create(
			'sepa-direct-debit',
			{
				containerId: 'unzer-direct-debit-iban-' + formId
			}
		);
		document.getElementById( 'unzer-direct-debit-id' ).value = '';
		jQuery( document.body ).on(
			'checkout_error',
			function () {
				document.getElementById( 'unzer-direct-debit-id' ).value = '';
			}
		);
		jQuery( '.woocommerce-checkout' ).off( 'checkout_place_order_unzer_direct_debit' );
		jQuery( '.woocommerce-checkout' ).on(
			'checkout_place_order_unzer_direct_debit',
			function () {
				const selectedSavedCard = document.querySelector( '[name="unzer_direct_debit_payment_instrument"]:checked' );
				if (selectedSavedCard && selectedSavedCard.value) {
					return true;
				}
				const sepaMandateCheckbox = document.getElementById( 'unzer-accept-sepa-mandate-checkbox' );
				if (sepaMandateCheckbox && ! sepaMandateCheckbox.checked) {
					UnzerManager.error( unzer_i18n.errorSepaMandate || 'Please accept the SEPA mandate' );
					return false;
				}

				if (document.getElementById( 'unzer-direct-debit-id' ).value) {
					return true;
				}
				directDebitInstance.createResource()
					.then(
						function (result) {
							document.getElementById( 'unzer-direct-debit-id' ).value = result.id;
							UnzerManager.getCheckoutForm().trigger( 'submit' );
						}
					)
					.catch(
						function (error) {
							console.warn( error );
							UnzerManager.error( error.customerMessage || error.message );
						}
					)
				return false;
			}
		);
	},

	initDirectDebitSecured() {
		const form = document.getElementById( 'unzer-direct-debit-secured-form' );
		if ( ! form) {
			return;
		}
		if (form.getAttribute( 'is-init' )) {
			return;
		}
		if ( ! UnzerManager.instanceDirectDebitSecured) {
			return;
		}
		form.setAttribute( 'is-init', true );
		const formId = form.getAttribute( 'data-form-id' );
		UnzerManager.toggleDirectDebitSecuredDisplay();

		const directDebitSecuredInstance = UnzerManager.instanceDirectDebitSecured.PaylaterDirectDebit();
		directDebitSecuredInstance.create(
			'paylater-direct-debit',
			{
				containerId: 'unzer-direct-debit-secured-form-ui-' + formId
			}
		);
		document.getElementById( 'unzer-direct-debit-secured-id' ).value = '';
		jQuery( document.body ).on(
			'checkout_error',
			function () {
				document.getElementById( 'unzer-direct-debit-secured-id' ).value = '';
			}
		);
		jQuery( '.woocommerce-checkout' ).off( 'checkout_place_order_unzer_direct_debit_secured' );
		jQuery( '.woocommerce-checkout' ).on(
			'checkout_place_order_unzer_direct_debit_secured',
			function () {
				if ( ! document.getElementById( 'unzer-direct-debit-secured-dob' ).value) {
					UnzerManager.error( unzer_i18n.errorDob || 'Please enter your date fo birth' );
					return false;
				}
				if (document.getElementById( 'unzer-direct-debit-secured-id' ).value) {
					return true;
				}
				directDebitSecuredInstance.createResource()
					.then(
						function (result) {
							document.getElementById( 'unzer-direct-debit-secured-id' ).value = result.id;
							UnzerManager.getCheckoutForm().trigger( 'submit' );
						}
					)
					.catch(
						function (error) {
							console.warn( error );
							UnzerManager.error( error.customerMessage || error.message );
						}
					)
				return false;
			}
		);
	},

	initInstallment() {
		const form = document.getElementById( 'unzer-installment-form' );
		if ( ! form) {
			return;
		}
		if (form.getAttribute( 'is-init' )) {
			return;
		}

		const unzerInstance = UnzerManager.instanceInstallments;
		UnzerManager.toggleInstallmentDisplay();
		if ( ! unzerInstance) {
			return;
		}


		form.setAttribute( 'is-init', true );

		UnzerManager.checkCountry();
		const installmentInstance = unzerInstance.PaylaterInstallment();
		installmentInstance.create(
			{
				containerId: 'unzer-installment-fields',
				amount: parseFloat( document.getElementById( 'unzer-installment-amount' ).value ),
				currency: UnzerManager.currency,
				customerType: 'B2C',
				country: UnzerManager.country
			}
		);

		const installmentCountryChangedHandler = function () {
			const isInstallmentAvailable = UnzerManager.toggleInstallmentDisplay();
			if (isInstallmentAvailable) {
				const fetchPlansPromise = installmentInstance.fetchPlans(
					{
						country: UnzerManager.country
					}
				);
				fetchPlansPromise
					.then(
						function (data) {
						}
					)
					.catch(
						function (error) {
						}
					);
			}
		}
		document.addEventListener( 'unzer_country_changed', installmentCountryChangedHandler );

		document.getElementById( 'unzer-installment-id' ).value = '';
		jQuery( document.body ).on(
			'checkout_error',
			function () {
				document.getElementById( 'unzer-installment-id' ).value = '';
			}
		);
		jQuery( '.woocommerce-checkout' ).off( 'checkout_place_order_unzer_installment' );
		jQuery( '.woocommerce-checkout' ).on(
			'checkout_place_order_unzer_installment',
			function () {
				if (document.getElementById( 'unzer-installment-id' ).value) {
					return true;
				}
				if ( ! document.getElementById( 'unzer-installment-dob' ).value) {
					UnzerManager.error( unzer_i18n.errorDob || 'Please enter your date fo birth' );
					return false;
				}
				installmentInstance.createResource()
					.then(
						function (result) {
							document.getElementById( 'unzer-installment-id' ).value = result.id;
							UnzerManager.getCheckoutForm().trigger( 'submit' );
						}
					)
					.catch(
						function (error) {
							console.warn( error );
							UnzerManager.error( error.customerMessage || error.message );
						}
					)
				return false;
			}
		);
	},

	toggleInstallmentDisplay() {
		const paymentMethodContainer = document.querySelector( '.wc_payment_method.payment_method_unzer_installment' );
		if ( ! paymentMethodContainer) {
			return false;
		}

		if ( ! UnzerManager.instanceInstallments || UnzerManager.isB2B() || ['DE', 'AT', 'CH'].indexOf( UnzerManager.country ) === -1) {
			paymentMethodContainer.style.display = 'none';
			// uncheck radio button
			document.getElementById( 'payment_method_unzer_installment' ).checked = false;
			return false;
		} else {
			paymentMethodContainer.style.display = '';
			return true;
		}
	},

	toggleDirectDebitSecuredDisplay() {
		const paymentMethodContainer = document.querySelector( '.wc_payment_method.payment_method_unzer_direct_debit_secured' );
		if ( ! paymentMethodContainer) {
			return false;
		}

		if (UnzerManager.isB2B() || ['DE', 'AT'].indexOf( UnzerManager.country ) === -1) {
			paymentMethodContainer.style.display = 'none';
			// uncheck radio button
			document.getElementById( 'payment_method_unzer_direct_debit_secured' ).checked = false;
			return false;
		} else {
			paymentMethodContainer.style.display = '';
			return true;
		}
	},

	rerenderInvoice() {
		const form = document.getElementById( 'unzer-invoice-form' );
		if ( ! form) {
			return;
		}
		form.removeAttribute( 'is-init' );
		form.querySelector( '#unzer-invoice-fields' ).innerHTML = '';
		UnzerManager.initInvoice();
	},

	initInvoice() {
		const form = document.getElementById( 'unzer-invoice-form' );
		if ( ! form) {
			return;
		}

		if (form.getAttribute( 'is-init' )) {
			return;
		}

		let unzerInstance = null;
		if (UnzerManager.isB2B()) {
			unzerInstance = UnzerManager.instancePayLaterB2B;
		} else {
			unzerInstance = UnzerManager.instancePayLaterB2C;
		}
		if ( ! unzerInstance) {
			document.querySelector( '.wc_payment_method.payment_method_unzer_invoice' ).style.display = 'none';
			// uncheck radio button
			document.getElementById( 'payment_method_unzer_invoice' ).checked = false;
			return;
		}
		document.querySelector( '.wc_payment_method.payment_method_unzer_invoice' ).style.display = '';
		form.setAttribute( 'is-init', true );

		const invoiceInstance                               = unzerInstance.PaylaterInvoice();
		invoiceInstance.create(
			{
				containerId: 'unzer-invoice-fields',
				customerType: 'B2C',
			}
		)
		document.getElementById( 'unzer-invoice-id' ).value = '';
		jQuery( document.body ).on(
			'checkout_error',
			function () {
				document.getElementById( 'unzer-invoice-id' ).value = '';
			}
		);
		jQuery( '.woocommerce-checkout' ).off( 'checkout_place_order_unzer_invoice' );
		jQuery( '.woocommerce-checkout' ).on(
			'checkout_place_order_unzer_invoice',
			function () {
				if (document.getElementById( 'unzer-invoice-id' ).value) {
					return true;
				}
				if ( ! document.getElementById( 'unzer-invoice-dob' ).value) {
					UnzerManager.error( unzer_i18n.errorDob || 'Please enter your date fo birth' );
					return false;
				}
				if (UnzerManager.isB2B() && ! document.getElementById( 'unzer-invoice-company-type' ).value) {
					UnzerManager.error( unzer_i18n.errorCompanyType || 'Please enter your company type' );
					return false;
				}
				invoiceInstance.createResource()
					.then(
						function (result) {
							document.getElementById( 'unzer-invoice-id' ).value = result.id;
							UnzerManager.getCheckoutForm().trigger( 'submit' );
						}
					)
					.catch(
						function (error) {
							console.warn( error );
							UnzerManager.error( error.customerMessage || error.message );
						}
					)
				return false;
			}
		);
	},

	initIdeal() {
		if ( ! document.getElementById( 'unzer-ideal-form' )) {
			return;
		}
		if (document.getElementById( 'unzer-ideal-form' ).getAttribute( 'is-init' )) {
			return;
		}
		document.getElementById( 'unzer-ideal-form' ).setAttribute( 'is-init', true );

		// Create an Unzer instance with your public key

		const idealInstance = UnzerManager.instance.Ideal();

		idealInstance.create(
			'ideal',
			{
				containerId: 'unzer-ideal'
			}
		);
		idealInstance.addEventListener(
			'change',
			function () {
				document.getElementById( 'unzer-ideal-id' ).value = '';
			}
		);
		document.getElementById( 'unzer-ideal-id' ).value = '';
		jQuery( document.body ).on(
			'checkout_error',
			function () {
				document.getElementById( 'unzer-ideal-id' ).value = '';
			}
		);
		jQuery( '.woocommerce-checkout' ).off( 'checkout_place_order_unzer_ideal' );
		jQuery( '.woocommerce-checkout' ).on(
			'checkout_place_order_unzer_ideal',
			function () {
				if (document.getElementById( 'unzer-ideal-id' ).value) {
					return true;
				}
				idealInstance.createResource()
					.then(
						function (result) {
							document.getElementById( 'unzer-ideal-id' ).value = result.id;
							UnzerManager.getCheckoutForm().trigger( 'submit' );
						}
					)
					.catch(
						function (error) {
							console.warn( error );
							UnzerManager.error( error.customerMessage || error.message );
						}
					)
				return false;
			}
		);
	},

	initApplePay() {
		if ( ! document.getElementById( 'unzer-apple-pay-id' )) {
			return;
		}
		const applePayInstance = UnzerManager.instance.ApplePay();
		window.api             = applePayInstance;

		jQuery( '.woocommerce-checkout' ).off( 'checkout_place_order_unzer_apple_pay' );
		jQuery( '.woocommerce-checkout' ).on(
			'checkout_place_order_unzer_apple_pay',
			function () {
				// from customer submit
				if (window.doNotStartApplePaySession) {
					window.doNotStartApplePaySession = false;
					return true;
				}
				UnzerManager.startApplePaySession();
				return true;
			}
		);

		const unzerApplePayProcessingAction = function (msg) {
			if (msg && msg.indexOf( '<!-- start-unzer-apple-pay -->' ) !== -1) {
				UnzerManager.beginApplePaySession();
			}
		}

		jQuery( document.body ).on(
			'checkout_error',
			function (e, msg) {
				unzerApplePayProcessingAction( msg );
			}
		);

		// for plugin CheckoutWC
		window.addEventListener(
			'cfw-checkout-failed-before-error-message',
			function (event) {
				if (typeof event.detail.response.messages === 'undefined') {
					return;
				}
				unzerApplePayProcessingAction( event.detail.response.messages );
			}
		);

	},

	startApplePaySession() {
		const unzerApplePayInstance = UnzerManager.instance.ApplePay();
		let applePayPaymentRequest  = {
			countryCode: unzer_parameters.store_country,
			currencyCode: UnzerManager.currency,
			supportedNetworks: ['visa', 'masterCard'],
			merchantCapabilities: ['supports3DS'],
			total: {
				label: unzer_parameters.store_name,
				amount: parseFloat( document.getElementById( 'unzer-apple-pay-amount' ).value )
			}
		};

		window.apsession = new window.ApplePaySession( 6, applePayPaymentRequest );

		window.apsession.onvalidatemerchant = function (event) {
			try {
				jQuery.post(
					unzer_parameters.apple_pay_merchant_validation_url,
					{
						validation_url: event.validationURL,
						unzer_nonce: document.getElementById( 'unzer-apple-pay-nonce' ).value
					},
					function (data) {
						window.apsession.completeMerchantValidation( JSON.parse( data.response ) );
					}
				);
			} catch (e) {
				window.apsession.abort();
				UnzerManager.error( unzer_parameters.generic_error_message );
			}
		}

		window.apsession.onpaymentauthorized = function (event) {
			let paymentData = event.payment.token.paymentData;
			unzerApplePayInstance.createResource( paymentData )
				.then(
					function (createdResource) {
						document.getElementById( 'unzer-apple-pay-id' ).value = createdResource.id;
						window.apsession.completePayment( {status: window.ApplePaySession.STATUS_SUCCESS} );
						window.doNotStartApplePaySession = true;
						UnzerManager.getCheckoutForm().trigger( 'submit' );
						setTimeout(
							function () {
								window.doNotStartApplePaySession = false;
							},
							500
						);
					}
				)
				.catch(
					function (error) {
						window.apsession.abort();
						UnzerManager.error( error.customerMessage || error.message || unzer_parameters.generic_error_message );
						console.log( error );
					}
				)
		}
	},
	beginApplePaySession() {
		window.apsession.begin();
	},


	initGooglePay() {
		if ( ! document.getElementById( 'unzer-google-pay-id' )) {
			return;
		}
		if (document.getElementById( 'unzer-google-pay-id' ).getAttribute( 'is-init' )) {
			return;
		}
		document.getElementById( 'unzer-google-pay-id' ).setAttribute( 'is-init', true );

		const options = unzer_parameters.google_pay_options;
		if ( ! options) {
			return;
		}
		UnzerManager.createGooglePayButtonContainer();
		const googlePayInstance        = UnzerManager.instance.Googlepay();
		const paymentDataRequestObject = googlePayInstance.initPaymentDataRequestObject(
			{
				gatewayMerchantId: options.gatewayMerchantId,
				merchantInfo: options.merchantInfo,
				transactionInfo: {
					currencyCode: UnzerManager.currency,
					countryCode: options.transactionInfo.countryCode,
					totalPriceStatus: 'ESTIMATED',
					totalPrice: document.getElementById( 'unzer-google-pay-amount' ).value,
				},
				buttonOptions: options.buttonOptions,
				allowedCardNetworks: options.allowedCardNetworks,
				allowCreditCards: options.allowCreditCards,
				allowPrepaidCards: options.allowPrepaidCards,
				onPaymentAuthorizedCallback: function (paymentData) {
					return googlePayInstance.createResource( paymentData )
						.then(
							function (result) {
								document.getElementById( 'unzer-google-pay-id' ).value = result.id;
								UnzerManager.getCheckoutForm().trigger( 'submit' );
								return {
									status: 'success'
								}
							}
						)
						.catch(
							function (error) {
								const errorMessage = error.customerMessage || error.message || 'Error';
								UnzerManager.error( errorMessage );
								return {
									status: 'error',
									message: errorMessage || 'Unexpected error'
								}
							}
						)
				}
			}
		);
		googlePayInstance.create(
			{
				containerId: 'unzer_google_pay_place_order',
			},
			paymentDataRequestObject
		);

		jQuery( '.woocommerce-checkout' ).off( 'checkout_place_order_unzer_google_pay' );
		jQuery( '.woocommerce-checkout' ).on(
			'checkout_place_order_unzer_google_pay',
			function () {
				if (document.getElementById( 'unzer-google-pay-id' ).value) {
					return true;
				}
				console.error( 'Google Pay: Checkout triggered without payment data.' );
				UnzerManager.error( unzer_parameters.generic_error_message );
			}
		);
	},

	createGooglePayButtonContainer() {
		const placeOrderButton = document.querySelector( '#place_order' );
		if (placeOrderButton) {
			let googlePayButton = document.getElementById( 'unzer_google_pay_place_order' );
			if ( ! googlePayButton) {
				googlePayButton               = document.createElement( 'div' );
				googlePayButton.id            = 'unzer_google_pay_place_order';
				googlePayButton.style.display = 'none';
				placeOrderButton.parentNode.appendChild( googlePayButton );
			} else {
				googlePayButton.innerHTML = '';
			}
		} else {
			console.warn( 'placeOrderButton not found for Google Pay Button' );
		}
	},

	getCheckoutForm() {
		return jQuery( 'form.woocommerce-checkout' );
	},

	error( message ) {
		console.error( message );
		jQuery( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
		const $checkoutForm = UnzerManager.getCheckoutForm();
		$checkoutForm.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div class="woocommerce-error">' + message + '</div></div>' ); // eslint-disable-line max-len
		$checkoutForm.removeClass( 'processing' ).unblock();
		$checkoutForm.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).trigger( 'blur' );
		UnzerManager.scrollToNotices();
		jQuery( document.body ).trigger( 'checkout_error', [message] );
	},

	scrollToNotices() {
		let scrollElement = jQuery( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );
		if ( ! scrollElement.length) {
			scrollElement = jQuery( 'form.checkout' );
		}
		jQuery.scroll_to_notices( scrollElement );
	},

	isB2B() {
		const companyNameInput = document.getElementById( 'billing_company' );
		return (companyNameInput && companyNameInput.value !== '');
	},

	checkCountry() {
		const countryInput = document.getElementById( 'billing_country' );
		let value          = null;
		if (countryInput) {
			value = countryInput.value;
		}
		if (value !== UnzerManager.country) {
			UnzerManager.country = value;
			// trigger event
			const event = new CustomEvent( 'unzer_country_changed', {detail: {country: value}} );
			document.dispatchEvent( event );
		}
	},
	customDebug( data ) {
		if ( ! document.getElementById( 'unzer_debug' )) {
			const debug                 = document.createElement( 'div' );
			debug.id                    = 'unzer_debug';
			debug.style.position        = 'fixed';
			debug.style.top             = '0';
			debug.style.right           = '0';
			debug.style.zIndex          = '100000';
			debug.style.padding         = '10px';
			debug.style.backgroundColor = 'rgba(255,255,255,0.9)';
			debug.style.border          = '1px solid #ccc';
			debug.style.fontFamily      = 'monospace';
			debug.style.fontSize        = '12px';
			debug.style.maxWidth        = '300px';
			debug.style.overflow        = 'auto';
			debug.style.maxHeight       = '40vh';
			document.body.appendChild( debug );
		}
		document.getElementById( 'unzer_debug' ).innerHTML += "\n\n" + JSON.stringify( data, null, 2 );
	}
}


jQuery(
	function () {
		UnzerManager.init();
		const paymentContainer = document.querySelector( '.woocommerce-checkout-payment' );
		if (paymentContainer) {
			const observer = new MutationObserver( UnzerManager.init );
			observer.observe( paymentContainer, {attributes: true, childList: true, subtree: true} );
		}
		jQuery( document.body ).on(
			'updated_checkout',
			function () {
				UnzerManager.init();
				UnzerManager.checkCountry();
			}
		);

		setInterval(
			function () {
				if (UnzerManager.b2bState !== UnzerManager.isB2B()) {
					UnzerManager.b2bState = UnzerManager.isB2B();
					UnzerManager.rerenderInvoice();
					UnzerManager.toggleInstallmentDisplay();
					UnzerManager.toggleDirectDebitSecuredDisplay();
				}

				UnzerManager.checkCountry();

				const companyTypeInputContainer = document.getElementById( 'unzer-invoice-company-type-container' );
				if (companyTypeInputContainer) {
					companyTypeInputContainer.style.display = UnzerManager.isB2B() ? 'block' : 'none';
				}

				const placeOrderButton   = document.querySelector( '#place_order' );
				let showPlaceOrderButton = true;

				const applePayContainer = document.querySelector( '.payment_method_unzer_apple_pay' );
				if (applePayContainer) {
					if (window.ApplePaySession && window.ApplePaySession.canMakePayments() && window.ApplePaySession.supportsVersion( 6 )) {
						applePayContainer.style.display = '';

						if (placeOrderButton) {
							let applePayButton = document.getElementById( 'unzer_apple_pay_place_order' );
							if ( ! applePayButton) {
								applePayButton    = document.createElement( 'div' );
								applePayButton.id = 'unzer_apple_pay_place_order';
								placeOrderButton.parentNode.appendChild( applePayButton );
								applePayButton.innerHTML = '<apple-pay-button onclick="document.querySelector(\'#place_order\').click(); return false;" buttonstyle="black" type="buy" locale="' + UnzerConfig.getLocale() + '"></apple-pay-button>';
							}

							if (document.getElementById( 'payment_method_unzer_apple_pay' ).checked) {
								applePayButton.style.display = '';
								showPlaceOrderButton         = false;
							} else {
								applePayButton.style.display = 'none';
							}
						}
					} else {
						applePayContainer.style.display = 'none';
					}
				}

				const googlePayContainer = document.querySelector( '.payment_method_unzer_google_pay' );
				const googlePayButton    = document.getElementById( 'unzer_google_pay_place_order' );
				if (googlePayContainer && googlePayButton && placeOrderButton) {
					if (document.getElementById( 'payment_method_unzer_google_pay' ).checked) {
						googlePayButton.style.display = '';
						showPlaceOrderButton          = false;
					} else {
						googlePayButton.style.display = 'none';
					}
				}

				if (showPlaceOrderButton) {
					placeOrderButton.style.display = '';
				} else {
					placeOrderButton.style.display = 'none';
				}
			},
			500
		);
	}
);
