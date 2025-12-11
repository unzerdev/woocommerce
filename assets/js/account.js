document.addEventListener(
	'DOMContentLoaded',
	function () {
		document.querySelectorAll( '.unzer-delete-instrument' ).forEach(
			function ( el ) {
				el.addEventListener(
					'click',
					function ( e ) {
						e.preventDefault();
						const instrumentId = this.getAttribute( 'data-instrument-id' );
						const deleteUrl    = this.getAttribute( 'data-delete-url' );
						const nonce        = this.getAttribute( 'data-nonce' );

						fetch(
							deleteUrl,
							{
								method: 'POST',
								body: 'instrument=' + encodeURIComponent( instrumentId ) + '&unzer_nonce=' + encodeURIComponent( nonce ),
								headers: {
									'Content-Type': 'application/x-www-form-urlencoded'
								}
							}
						).then(
							function () {
								location.reload();
							}
						);
					}
				);
			}
		);
	}
);
