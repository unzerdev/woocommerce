<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use UnzerPayments\Controllers\AdminController;
use UnzerPayments\Main;
use UnzerPayments\Util;

/**
 * @var $order WC_Order
 */

$paymentId = $order->get_meta( Main::ORDER_META_KEY_PAYMENT_ID, true );
if ( empty( $paymentId ) ) {
	return;
}
$paymentShortId = $order->get_meta( Main::ORDER_META_KEY_PAYMENT_SHORT_ID, true );
wp_enqueue_style( 'unzer_admin_order_view_css', UNZER_PLUGIN_URL . '/assets/css/admin-order-view.css', array(), UNZER_VERSION );
?>

<div class="unzer-header-row">
	<span class="unzer-payment-id"><?php echo esc_html( '#' . $paymentShortId ); ?></span>
	<span id="unzer-status-message" class="empty"></span>
</div>

<?php
$paymentInstructions = $order->get_meta( \UnzerPayments\Main::ORDER_META_KEY_PAYMENT_INSTRUCTIONS, true );

if ( $paymentInstructions ) {
	// translatepress fix
	if ( strpos( $paymentInstructions, '#!trpst#' ) !== false ) {
		$paymentInstructions = preg_replace( '/#!trpst#trp-gettext[^#]*#!trpen#/', '', $paymentInstructions );
		$paymentInstructions = str_replace( '#!trpst#/trp-gettext#!trpen#', '', $paymentInstructions );
	}
	?>
	<div class="unzer-instructions"><?php echo wp_kses_post( $paymentInstructions ); ?></div>
	<?php
}
?>

<div id="unzer-totals-row" class="unzer-totals-row"></div>
<div id="unzer-capture-row" class="unzer-capture-row"></div>

<table id="unzer-transactions">
	<thead>
	<tr>
		<th><?php echo esc_html__( 'Time', 'unzer-payments' ); ?></th>
		<th><?php echo esc_html__( 'Type', 'unzer-payments' ); ?></th>
		<th>ID</th>
		<th><?php echo esc_html__( 'Amount', 'unzer-payments' ); ?></th>
		<th><?php echo esc_html__( 'Status', 'unzer-payments' ); ?></th>
	</tr>
	</thead>
	<tbody id="unzer-transactions-body"></tbody>
</table>
<div class="unzer-debug-section">
	<a href="#" onclick="document.getElementById('unzer-debug').style.display = 'block'; this.style.display = 'none'; return false;" class="button button-secondary">
		<?php echo esc_html__( 'Debug', 'unzer-payments' ); ?>
	</a>
	<pre id="unzer-debug"></pre>
</div>
<?php
$ajaxUrl  = WC()->api_request_url( AdminController::GET_ORDER_TRANSACTIONS_ROUTE_SLUG );
$ajaxUrl .= ( strpos( $ajaxUrl, '?' ) === false ? '?' : '&' ) . 'order_id=' . $order->get_id();

$chargeUrl = WC()->api_request_url( AdminController::CHARGE_ROUTE_SLUG );

// build JS
ob_start();
?>

	const unzerOrderId = <?php echo (int) $order->get_id(); ?>;

	function unzerRefreshData() {
		fetch('<?php echo esc_url( $ajaxUrl ); ?>')
			.then(response => response.json())
			.then(data => {
				data.remainingPlain = (data.remainingPlain?parseFloat(data.remainingPlain):0);
				if (data.transactions) {
					let tHtml = '';
					for (const transaction of data.transactions) {
						const rowClass = transaction.status === 'error' ? 'unzer-row-error' : (transaction.status === 'pending' ? 'unzer-row-pending' : 'unzer-row-success');
						tHtml += `
					<tr class="${rowClass}">
						<td>${transaction.time}</td>
						<td>${transaction.type}</td>
						<td>${transaction.id}</td>
						<td>${transaction.amount}</td>
						<td>${transaction.status}</td>
					</tr>
				`;
					}
					document.getElementById('unzer-transactions-body').innerHTML = tHtml;
				}

				const orderStatusMessageContainer = document.getElementById('unzer-status-message');
				orderStatusMessageContainer.innerHTML = '<span>'+data.status+'</span>';
				orderStatusMessageContainer.className = "unzer-status-"+data.status;


				const totalsHtml = `
					<span class="unzer-total-item"><span class="unzer-total-label"><?php echo esc_html__( 'Total', 'unzer-payments' ); ?>:</span> <span class="unzer-total-value">${data.amount}</span></span>
					<span class="unzer-total-item"><span class="unzer-total-label"><?php echo esc_html__( 'Charged', 'unzer-payments' ); ?>:</span> <span class="unzer-total-value">${data.charged}</span></span>
					<span class="unzer-total-item"><span class="unzer-total-label"><?php echo esc_html__( 'Cancelled', 'unzer-payments' ); ?>:</span> <span class="unzer-total-value">${data.cancelled}</span></span>
					<span class="unzer-total-item"><span class="unzer-total-label"><?php echo esc_html__( 'Remaining', 'unzer-payments' ); ?>:</span> <span class="unzer-total-value">${data.remaining}</span></span>
				`;
				document.getElementById('unzer-totals-row').innerHTML = totalsHtml;

				let captureHtml = '';
				if (data.remainingPlain && data.paymentMethod !== 'unzer_prepayment' && data.status !== 'canceled') {
					captureHtml = `<input type="number" step="0.01" min="0.01" max="${data.remainingPlain}" value="${data.remainingPlain}" id="unzer-capture-amount-input" />
						<a href="#" id="unzer-capture-btn" onclick="unzerCaptureOrder(unzerOrderId, document.getElementById('unzer-capture-amount-input').value, '<?php echo esc_attr( Util::getNonce() ); ?>'); return false;" class="button button-small"><?php echo esc_html__( 'Capture', 'unzer-payments' ); ?></a>`;
				}
				document.getElementById('unzer-capture-row').innerHTML = captureHtml;
				if (data.raw) {
					document.getElementById('unzer-debug').innerHTML = data.raw;
				}
				if (data.error) {
					document.getElementById('unzer-transactions').parentNode.innerHTML = '<b>ERROR:</b> ' + data.error;
				}
				console.log(data);
			});
	}

	unzerRefreshData();

	function unzerCaptureOrder(orderId, amount, nonce) {
		const btn = document.getElementById('unzer-capture-btn');
		if (btn) {
			btn.classList.add('is-loading');
		}
		const formData = new FormData();
		formData.append('order_id', orderId);
		formData.append('amount', amount);
		formData.append('unzer_nonce', nonce);
		fetch('<?php echo esc_url( $chargeUrl ); ?>', {
			method: 'POST',
			body: formData
		}).then(response => response.json())
			.then(data => {
				if (data.error) {
					alert(data.error);
					if (btn) {
						btn.classList.remove('is-loading');
					}
				}
				unzerRefreshData();
			});
	}
<?php
$script = ob_get_clean();
wp_enqueue_script( 'unzer_admin_webhook_management_js', 'inline-only', array(), UNZER_VERSION, array( 'in_footer' => true ) );
wp_add_inline_script( 'unzer_admin_webhook_management_js', $script );