document.addEventListener(
	'DOMContentLoaded',
	function (e) {
		document.querySelectorAll( '.dismiss-unzer-notification' ).forEach(
			function (el) {
				el.addEventListener(
					'click',
					function (e) {
						e.preventDefault();
						el.parentNode.parentNode.remove();
						const formData = new FormData();
						formData.append( 'remove_notification', el.getAttribute( 'data-id' ) );
						formData.append( 'unzer_nonce', el.getAttribute( 'data-nonce' ) );
						fetch(
							el.getAttribute( 'data-url' ),
							{
								method: 'POST',
								body: formData
							}
						);
					}
				);
			}
		);
	}
);