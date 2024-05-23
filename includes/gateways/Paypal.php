<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Services\PaymentService;
use UnzerPayments\Traits\SavePaymentInstrumentTrait;
use UnzerPayments\Util;
use UnzerSDK\Resources\PaymentTypes\Paypal as PaypalResource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Paypal extends AbstractGateway {


	use SavePaymentInstrumentTrait;

	public $paymentTypeResource = PaypalResource::class;
	const GATEWAY_ID            = 'unzer_paypal';
	public $method_title        = 'Unzer PayPal';
	public $method_description;
	public $title       = 'PayPal';
	public $description = '';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);

	public function has_fields() {
		return $this->isSaveInstruments();
	}

	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}
		Util::getNonceField();
		echo wp_kses_post( $this->renderSavedInstrumentsSelection( '' ) );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'          => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer PayPal', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'            => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'PayPal', 'unzer-payments' ),
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
				AbstractGateway::SETTINGS_KEY_SAVE_INSTRUMENTS => array(
					'title'       => __( 'Save PayPAl account for registered customers', 'unzer-payments' ),
					'label'       => __( '&nbsp;', 'unzer-payments' ),
					'type'        => 'select',
					'description' => '',
					'default'     => 'no',
					'options'     => array(
						'no'  => __( 'No', 'unzer-payments' ),
						'yes' => __( 'Yes', 'unzer-payments' ),
					),
				),
			)
		);
	}

	public function process_payment( $order_id ) {
		$return                 = array(
			'result' => 'success',
		);
		$savedPaymentInstrument = Util::getNonceCheckedPostValue( 'unzer_paypal_payment_instrument' );
		$paymentMean            = empty( $savedPaymentInstrument ) ? PaypalResource::class : $savedPaymentInstrument;

		$savePaymentInstrument = ! empty( Util::getNonceCheckedPostValue( 'unzer-save-payment-instrument-' . $this->id ) );
		WC()->session->set( 'save_payment_instrument', $savePaymentInstrument );
		$transactionEditorFunction = null;

		if ( $this->get_option( 'transaction_type' ) === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE ) {
			$transaction = ( new PaymentService() )->performAuthorizationForOrder( $order_id, $this, $paymentMean, $transactionEditorFunction );
		} else {
			$transaction = ( new PaymentService() )->performChargeForOrder( $order_id, $this, $paymentMean, $transactionEditorFunction );
		}

		if ( $transaction->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $transaction->getPayment()->getRedirectUrl();
		} elseif ( $transaction->isSuccess() ) {
			$return['redirect'] = $this->get_confirm_url();
		}
		return $return;
	}
}
