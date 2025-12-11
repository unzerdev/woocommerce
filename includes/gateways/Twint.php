<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Gateways\Blocks\TwintBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Twint extends AbstractGateway {

	const GATEWAY_ID            = 'unzer_twint';
	const BLOCK_CLASS           = TwintBlock::class;
	public $allowedCountries    = array( 'CH' );
	public $allowedCurrencies   = array( 'CHF' );
	public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\Twint::class;
	public $method_title        = 'Unzer TWINT';
	public $method_description;
	public $title       = 'TWINT';
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
					'label'       => __( 'Enable Unzer TWINT', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'TWINT', 'unzer-payments' ),
				),
				'description' => array(
					'title'       => __( 'Description', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'unzer-payments' ),
					'default'     => '',
				),
			)
		);
	}
}
