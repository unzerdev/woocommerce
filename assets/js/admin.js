document.addEventListener(
	'DOMContentLoaded',
	function () {
		const paymentNavigation = document.querySelector( '.unzer-payment-navigation' );
		if ( paymentNavigation) {
			paymentNavigation.style.display = 'none';
		}
		const toggler = document.querySelector( '.unzer-content-toggler' );
		if ( ! toggler) {
			return;
		}
		const target = document.querySelector( toggler.getAttribute( 'data-target' ) );
		toggler.addEventListener(
			'click',
			function (e) {
				e.preventDefault();
				toggler.classList.toggle( 'active' );
				target.style.display = target.style.display === 'none' ? 'initial' : 'none';
			}
		);

		const savePaymentInstrumentSelect = document.querySelector( '#unzer-paymentsunzer_card_save_instruments, #unzer-paymentsunzer_paypal_save_instruments, #unzer-paymentsunzer_paypal_save_instruments, #unzer-paymentsunzer_direct_debit_save_instruments' );
		if (savePaymentInstrumentSelect) {
			savePaymentInstrumentSelect.addEventListener(
				'change',
				function (e) {
					const value = e.target.value;
					if (value === 'no') {
						alert( unzer_i18n.deletePaymentInstrumentsWarning );
					}
				}
			);
		}
	}
);
