function unzerProcessSubKeyCheck(container) {
	const url      = container.getAttribute( 'data-url' );
	const formData = new FormData();
	formData.append( 'slug', container.getAttribute( 'data-slug' ) );
	formData.append( 'gateway', container.getAttribute( 'data-gateway' ) );

	fetch(
		url,
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
				container.querySelectorAll( '.is-success' ).forEach(
					function (el) {
						el.style.display = data.isValid === "0" ? 'none' : 'block';
					}
				);
				container.querySelectorAll( '.is-error' ).forEach(
					function (el) {
						el.style.display = data.isValid === "0" ? 'block' : 'none';
					}
				);
			}
		);
}

document.addEventListener(
	'DOMContentLoaded',
	function () {
		document.querySelectorAll( '.unzer-key-status' ).forEach(
			function (el) {
				unzerProcessSubKeyCheck( el );
			}
		);
	}
);
