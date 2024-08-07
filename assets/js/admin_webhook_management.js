function unzerWebhookRefreshData(slug) {
	unzerStartLoading( slug );
	const formData = new FormData();
	formData.append( 'slug', slug );
	formData.append( 'unzer_nonce', window.unzerWebhookNonce );
	fetch(
		window.unzerWebhookAjaxUrl,
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

				if (data.webhooks) {
					let tHtml = '';
					for (const webhook of data.webhooks) {

						tHtml += '<tr>' +
						'<tr>' +
							'<td>' + webhook.id + '</td>' +
							'<td>' + webhook.event + '</td>' +
							'<td>' + webhook.url + '</td>' +
							'<td> <a href="#" onclick="unzerDeleteWebhook(\'' + webhook.id + '\', \'' + slug + '\'); return false;" class="button button-small"> 🗑️ </a> </td>' +
						'</tr>';
					}
					document.getElementById( 'unzer-webhooks-body' + slug ).innerHTML = tHtml;
				}

				let addWebhook = '';
				let statusText = '';
				if ( ! data.isRegistered) {
					addWebhook = '<a href="#" onclick="unzerAddCurrentWebhook(\'' + slug + '\'); return false;" class="button button-small button-primary">Add Webhook</a>';
					statusText = '<div style="color:#dc1b1b;"><span class="unzer-status-circle" style="background:#cc0000;"></span> inactive</div>';
				} else {
					statusText = '<div><span class="unzer-status-circle" style="background:#00a800;"></span>active</div>';
				}

				document.getElementById( 'unzer-webhook-actions' + slug ).innerHTML = addWebhook;
				document.getElementById( 'unzer-webhooks-status' + slug ).innerHTML = statusText;
				unzerStopLoading( slug );
			}
		);
}

function unzerAddCurrentWebhook(slug) {
	unzerClearData( slug );
	unzerStartLoading( slug );
	const formData = new FormData();
	formData.append( 'action', 'add' );
	formData.append( 'slug', slug );
	formData.append( 'unzer_nonce', window.unzerWebhookNonce );
	fetch(
		window.unzerWebhookAjaxUrl,
		{
			method: 'POST',
			body: formData
		}
	).then(
		function (response) {
			return response.json()
		}
	)
		.then(
			function (data) {
				if (data.error) {
					alert( data.error );
				}
				unzerWebhookRefreshData( slug );
			}
		)
}

function unzerDeleteWebhook(id, slug) {
	unzerClearData( slug );
	unzerStartLoading( slug );
	const formData = new FormData();
	formData.append( 'action', 'delete' );
	formData.append( 'id', id );
	formData.append( 'slug', slug );
	formData.append( 'unzer_nonce', window.unzerWebhookNonce );
	fetch(
		window.unzerWebhookAjaxUrl,
		{
			method: 'POST',
			body: formData
		}
	).then(
		function (response) {
			return response.json()
		}
	)
		.then(
			function (data) {
				if (data.error) {
					alert( data.error );
				}
				unzerWebhookRefreshData( slug );
			}
		)
}

function unzerStartLoading(slug) {
	document.getElementById( 'unzer-spinner-container' + slug ).style.display = 'block';
}

function unzerStopLoading(slug) {
	document.getElementById( 'unzer-spinner-container' + slug ).style.display = 'none';
}

function unzerClearData(slug) {
	document.getElementById( 'unzer-webhooks-body' + slug ).innerHTML   = '';
	document.getElementById( 'unzer-webhook-actions' + slug ).innerHTML = '';
	document.getElementById( 'unzer-webhooks-status' + slug ).innerHTML = '';
}


document.addEventListener(
	'DOMContentLoaded',
	function () {
		document.querySelectorAll( '.unzer-webhook-container' ).forEach(
			function (element) {
				if (element.getAttribute( 'data-url' ) && element.getAttribute( 'data-nonce' )) {
					window.unzerWebhookAjaxUrl = element.getAttribute( 'data-url' );
					window.unzerWebhookNonce   = element.getAttribute( 'data-nonce' );
					unzerWebhookRefreshData( element.getAttribute( 'data-slug' ) );
				}
			}
		);

	}
);