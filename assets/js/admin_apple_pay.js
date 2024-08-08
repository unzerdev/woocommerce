document.addEventListener(
	'DOMContentLoaded',
	function () {
		document.querySelectorAll( '.apple-pay-remove-key' ).forEach(
			function (el) {
				el.addEventListener(
					'click',
					function (e) {
						e.preventDefault();
						const key      = el.getAttribute( 'data-key' );
						const formData = new FormData();
						formData.append( 'key', key );
						formData.append( 'unzer_nonce', el.getAttribute( 'data-nonce' ) );
						fetch(
							el.href,
							{
								method: 'POST',
								body: formData
							}
						)
						.then(
							function (response) {
								return response.json()
							}
						)
						.then(
							function (data) {
								if (data.success) {
									document.getElementById( 'unzer_apple_pay_' + key + '_preview' ).remove();
									el.remove();
								}
							}
						);
					}
				);
			}
		);

		if (unzerApplePay && unzerApplePay.validationUrl) {
			fetch( unzerApplePay.validationUrl )
			.then(
				function (response) {
					return response.json()
				}
			)
				.then(
					function (data) {
						for (const key in data.status) {
							const container = document.getElementById( key + '_status' );
							if (container) {
								const statusIndicator = container.querySelector( '.unzer-status-circle' );
								const message         = container.querySelector( '.unzer-status-text' );

								if (data.status[key] === "0") {
									statusIndicator.classList.remove( 'success' );
									statusIndicator.classList.add( 'error' );
								} else {
									statusIndicator.classList.remove( 'error' );
									statusIndicator.classList.add( 'success' );
								}
								if (typeof data.messages[key] !== 'undefined') {
									message.innerHTML = data.messages[key];
								}
							}
						}
					}
				);
		}
	}
);