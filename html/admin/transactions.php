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

?>

<div><b><?php echo esc_html( 'ID #' . $paymentShortId ); ?></b></div>

<?php
$paymentInstructions = $order->get_meta( \UnzerPayments\Main::ORDER_META_KEY_PAYMENT_INSTRUCTIONS, true );

if ( $paymentInstructions ) {
	// translatepress fix
	if ( strpos( $paymentInstructions, '#!trpst#' ) !== false ) {
		$paymentInstructions = preg_replace( '/#!trpst#trp-gettext[^#]*#!trpen#/', '', $paymentInstructions );
		$paymentInstructions = str_replace( '#!trpst#/trp-gettext#!trpen#', '', $paymentInstructions );
	}
	?>
	<h3><?php echo esc_html__( 'Payment Instructions', 'unzer-payments' ); ?></h3>
	<div><?php echo wp_kses_post( $paymentInstructions ); ?></div>
	<?php
}
?>
<h3><?php echo esc_html__( 'Totals', 'unzer-payments' ); ?></h3>
<div id="unzer-status-message"></div>
<table id="unzer-sums">
	<tbody id="unzer-sums-body">

	</tbody>
</table>
<h3><?php echo esc_html__( 'Detailed Transactions', 'unzer-payments' ); ?></h3>
<table id="unzer-transactions" style="width: 100%;">
	<thead style="text-align: left;">
	<tr>
		<th><?php echo esc_html__( 'Time', 'unzer-payments' ); ?></th>
		<th><?php echo esc_html__( 'Type', 'unzer-payments' ); ?></th>
		<th>ID</th>
		<th><?php echo esc_html__( 'Amount', 'unzer-payments' ); ?></th>
		<th><?php echo esc_html__( 'Status', 'unzer-payments' ); ?></th>
	</tr>
	</thead>
	<tbody id="unzer-transactions-body">

	</tbody>
</table>
<div style="margin-top:20px;">
	<a href="#" onclick="document.getElementById('unzer-debug').style.display = 'block'; return false;" class="button">
		<?php echo esc_html__( 'Show Debug Information', 'unzer-payments' ); ?>
	</a>
	<pre id="unzer-debug" style="display: none; font-size:10px;">

	</pre>
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
						let color = transaction.status === 'error' ? '#cc0000' : '#000000';
						color = transaction.status === 'pending' ? '#bbb' : color;
						tHtml += `
					<tr style="color:${color};">
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

				document.getElementById('unzer-status-message').innerHTML = data.status === 'chargeback' ? '<div style="color:#cc0000; margin:10px 0; font-weight:bold;">CHARGEBACK!</div>' : '';

				let captureAction = '';
				if (data.remainingPlain && data.paymentMethod !== 'unzer_prepayment') {
					captureAction = '<div><input type="number" step="0.01" min="0.01"  max="' + data.remainingPlain + '" value="' + data.remainingPlain + '" id="unzer-capture-amount-input" /></div> ' +
						'<a href="#" onclick="unzerCaptureOrder(unzerOrderId, document.getElementById(\'unzer-capture-amount-input\').value, \'<?php echo esc_attr( Util::getNonce() ); ?>\'); return false;" class="button button-small" style="width:100%; text-align: center;"><?php echo esc_html__( 'Capture Amount', 'unzer-payments' ); ?></a>'

				}

				let amountHtml = `
				<tr><th style="text-align: left;"><?php echo esc_html__( 'Total amount', 'unzer-payments' ); ?>: </th><td style="text-align: right;">${data.amount}</td></tr>
				<tr><th style="text-align: left;"><?php echo esc_html__( 'Charged amount', 'unzer-payments' ); ?>: </th><td style="text-align: right;">${data.charged}</td></tr>
				<tr><th style="text-align: left;"><?php echo esc_html__( 'Cancelled amount', 'unzer-payments' ); ?>: </th><td style="text-align: right;">${data.cancelled}</td></tr>
				<tr><th style="text-align: left;"><?php echo esc_html__( 'Remaining amount', 'unzer-payments' ); ?>: </th><td style="text-align: right;">${data.remaining}</td></tr>
				<tr><td colspan="2">${captureAction}</td></tr>
			`;

				document.getElementById('unzer-sums-body').innerHTML = amountHtml;
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
				}
				unzerRefreshData();
			})

	}
<?php
$script = ob_get_clean();
wp_enqueue_script( 'unzer_admin_webhook_management_js', 'inline-only', array(), UNZER_VERSION, array( 'in_footer' => true ) );
wp_add_inline_script( 'unzer_admin_webhook_management_js', $script );

