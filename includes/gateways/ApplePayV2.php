<?php

namespace UnzerPayments\gateways;

use UnzerPayments\Services\PaymentService;
use UnzerPayments\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ApplePayV2 extends AbstractGateway {

	const GATEWAY_ID     = 'unzer_apple_pay_v2';
	public $method_title = 'Unzer Apple Pay';
	public $method_description;
	public $title       = 'Apple Pay';
	public $description = '';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);


	public function has_fields() {
		return true;
	}

	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}
		?>
		<input type="hidden" id="unzer-apple-pay-v2-id" name="unzer-apple-pay-v2-id" value=""/>
		<input type="hidden" id="unzer-apple-pay-v2-nonce" name="unzer-apple-pay-v2-nonce" value="<?php echo esc_attr( Util::getNonce() ); ?>"/>
		<input type="hidden" id="unzer-apple-pay-v2-amount" name="unzer-apple-pay-v2-amount" value="<?php echo esc_attr( WC()->cart->get_total( 'plain' ) ); ?>"/>
		<?php
	}

	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->addCheckoutAssets();
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'          => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer Apple Pay', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'            => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Apple Pay', 'unzer-payments' ),
				),
				'description'      => array(
					'title'       => __( 'Description', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'unzer-payments' ),
					'default'     => '',
				),
				'transaction_type' => array(
					'title'       => __( 'Charge or Authorize', 'unzer-payments' ),
					'label'       => '',
					'type'        => 'select',
					'description' => __( 'Choose "authorize", if you you want to charge the shopper at a later point of time', 'unzer-payments' ),
					'options'     => array(
						AbstractGateway::TRANSACTION_TYPE_AUTHORIZE => __( 'authorize', 'unzer-payments' ),
						AbstractGateway::TRANSACTION_TYPE_CHARGE => __( 'charge', 'unzer-payments' ),
					),
					'default'     => 'charge',
				),
			)
		);
	}

	public function process_payment( $order_id ) {
		$this->logger->debug( 'start payment for #' . $order_id . ' with ' . self::GATEWAY_ID );
		$return = array(
			'result' => 'success',
		);

		$applePayId = Util::getNonceCheckedPostValue( 'unzer-apple-pay-v2-id' );

		if ( empty( $applePayId ) ) {
			$this->logger->debug( 'apple pay empty id', $_POST );
			$return['messages'] = '<!-- start-unzer-apple-pay-v2 -->';
			return $return;
		}
		if ( $this->get_option( 'transaction_type' ) === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE ) {
			$transaction = ( new PaymentService() )->performAuthorizationForOrder( $order_id, $this, $applePayId );
		} else {
			$transaction = ( new PaymentService() )->performChargeForOrder( $order_id, $this, $applePayId );
		}

		$this->logger->debug( 'apple pay charge/authorization for order ' . $order_id, array( $transaction->expose() ) );

		if ( $transaction->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $transaction->getPayment()->getRedirectUrl();
		} else {
			$return['redirect'] = $this->get_confirm_url();
		}
		return $return;
	}
}
