<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use UnzerPayments\Controllers\AdminController;
use UnzerPayments\Util;
$removeKeyUrl  = WC()->api_request_url( AdminController::APPLE_PAY_REMOVE_KEY_ROUTE_SLUG );
$validationUrl = WC()->api_request_url( AdminController::APPLE_PAY_VALIDATE_CREDENTIALS_ROUTE_SLUG );

wp_enqueue_style( 'unzer_admin_css', UNZER_PLUGIN_URL . '/assets/css/admin.css', array(), UNZER_VERSION );
wp_enqueue_script( 'unzer_admin_apple_pay_js', UNZER_PLUGIN_URL . '/assets/js/admin_apple_pay.js', array(), UNZER_VERSION, array( 'in_footer' => false ) );
wp_localize_script(
	'unzer_admin_apple_pay_js',
	'unzerApplePay',
	array(
		'validationUrl' => $validationUrl,
	)
);


?>
<div class="apple-pay-certificates"
<h2><?php echo esc_html__( 'Apple Pay certificate settings', 'unzer-payments' ); ?></h2>

<table class="form-table">
	<tbody>
	<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="unzer_apple_pay_payment_processing_certificate"><?php echo esc_html__( 'Payment Processing Certificate (apple_pay.pem)', 'unzer-payments' ); ?></label>
		</th>
		<td class="forminp">
			<div id="unzer_apple_pay_payment_certificate_id_status" style="margin-bottom:20px;">
				<span class="unzer-status-circle"></span><span class="unzer-status-text"></span>
			</div>
			<fieldset>
				<input class="input-text regular-input " type="file" name="unzer_apple_pay_payment_processing_certificate" id="unzer_apple_pay_payment_processing_certificate" style="" value="" placeholder=""/>
			</fieldset>
			<?php
			if ( ! empty( get_option( 'unzer_apple_pay_payment_certificate_id' ) ) ) {
				echo '<pre id="unzer_apple_pay_payment_certificate_id_preview">' . esc_html( get_option( 'unzer_apple_pay_payment_certificate_id' ) ) . '</pre>';
				echo '<div><a href="' . esc_url( $removeKeyUrl ) . '" class="button button-primary apple-pay-remove-key" data-key="payment_certificate_id" data-nonce="' . esc_attr( Util::getNonce() ) . '">' . esc_html__( 'Delete', 'unzer-payments' ) . '</a></div>';
			}
			?>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="unzer_apple_pay_payment_processing_key"><?php echo esc_html__( 'Payment Processing Key (privatekey.key)', 'unzer-payments' ); ?></label>
		</th>
		<td class="forminp">
			<!--
			<div id="unzer_apple_pay_payment_key_id_status" style="margin-bottom:20px;">
				<span class="unzer-status-circle"></span><span class="unzer-status-text"></span>
			</div>
			-->
			<fieldset>
				<input class="input-text regular-input " type="file" name="unzer_apple_pay_payment_processing_key" id="unzer_apple_pay_payment_processing_key" style="" value="" placeholder=""/>
			</fieldset>
			<?php
			if ( ! empty( get_option( 'unzer_apple_pay_payment_key_id' ) ) ) {
				echo '<pre id="unzer_apple_pay_payment_key_id_preview">' . esc_html( get_option( 'unzer_apple_pay_payment_key_id' ) ) . '</pre>';
				echo '<div><a href="' . esc_url( $removeKeyUrl ) . '" class="button button-primary apple-pay-remove-key" data-key="payment_key_id" data-nonce="' . esc_attr( Util::getNonce() ) . '">' . esc_html__( 'Delete', 'unzer-payments' ) . '</a></div>';
			}
			?>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="unzer_apple_pay_merchant_id_certificate"><?php echo esc_html__( 'Merchant Identification Certificate (merchant_id.pem)', 'unzer-payments' ); ?></label>
		</th>
		<td class="forminp">
			<div id="unzer_apple_pay_merchant_id_certificate_status" style="margin-bottom:20px;">
				<span class="unzer-status-circle"></span><span class="unzer-status-text"></span>
			</div>
			<fieldset>
				<input class="input-text regular-input " type="file" name="unzer_apple_pay_merchant_id_certificate" id="unzer_apple_pay_merchant_id_certificate" style="" value="" placeholder=""/>
			</fieldset>
			<?php
			if ( ! empty( get_option( 'unzer_apple_pay_merchant_id_certificate' ) ) ) {
				echo '<pre id="unzer_apple_pay_merchant_id_certificate_preview">' . esc_html( substr( get_option( 'unzer_apple_pay_merchant_id_certificate' ), 0, 50 ) ) . '...</pre>';
				echo '<div><a href="' . esc_url( $removeKeyUrl ) . '" class="button button-primary apple-pay-remove-key" data-key="merchant_id_certificate" data-nonce="' . esc_attr( Util::getNonce() ) . '">' . esc_html__( 'Delete', 'unzer-payments' ) . '</a></div>';
			}
			?>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="unzer_apple_pay_merchant_id_key"><?php echo esc_html__( 'Merchant Identification Key (merchant_id.key)', 'unzer-payments' ); ?></label>
		</th>
		<td class="forminp">
			<div id="unzer_apple_pay_merchant_id_key_status" style="margin-bottom:20px;">
				<span class="unzer-status-circle"></span><span class="unzer-status-text"></span>
			</div>
			<fieldset>
				<input class="input-text regular-input " type="file" name="unzer_apple_pay_merchant_id_key" id="unzer_apple_pay_merchant_id_key" style="" value="" placeholder=""/>
			</fieldset>
			<?php
			if ( ! empty( get_option( 'unzer_apple_pay_merchant_id_key' ) ) ) {
				echo '<pre id="unzer_apple_pay_merchant_id_key_preview">' . esc_html( substr( get_option( 'unzer_apple_pay_merchant_id_key' ), 0, 50 ) ) . '...</pre>';
				echo '<div><a href="' . esc_url( $removeKeyUrl ) . '" class="button button-primary apple-pay-remove-key" data-key="merchant_id_key" data-nonce="' . esc_attr( Util::getNonce() ) . '">' . esc_html__( 'Delete', 'unzer-payments' ) . '</a></div>';
			}
			?>
		</td>
	</tr>
	</tbody>
</table>
