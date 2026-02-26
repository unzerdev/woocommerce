<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Gateways\Blocks\WeroBlock;
use UnzerPayments\Services\PaymentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wero extends AbstractGateway {

	const GATEWAY_ID            = 'unzer_wero';
	const BLOCK_CLASS           = WeroBlock::class;
	public $allowedCountries    = array( 'DE' );
	public $allowedCurrencies   = array( 'EUR' );
	public $isAllowedForB2B     = false;
	public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\Wero::class;
	public $method_title        = 'Unzer Wero';
	public $method_description;
	public $title       = 'Wero';
	public $description = '';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer Wero', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Wero', 'unzer-payments' ),
				),
				'description' => array(
					'title'       => __( 'Description', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'unzer-payments' ),
					'default'     => '',
				),
				/*
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
				*/
			)
		);
	}

	public function process_payment( $order_id ) {
		$return = array(
			'result' => 'success',
		);
		// if ( $this->get_option( 'transaction_type' ) === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE ) {
		// $transaction = ( new PaymentService() )->performAuthorizationForOrder( $order_id, $this, $this->paymentTypeResource );
		// } else {
		$transaction = ( new PaymentService() )->performChargeForOrder( $order_id, $this, $this->paymentTypeResource );
		// }

		$this->before_payment_redirect( $order_id );

		if ( $transaction->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $transaction->getPayment()->getRedirectUrl();
		}
		return $return;
	}
}
