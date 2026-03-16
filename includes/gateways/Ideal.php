<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Gateways\Blocks\IdealBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ideal extends AbstractGateway {


	const GATEWAY_ID            = 'unzer_ideal';
	const BLOCK_CLASS           = IdealBlock::class;
	public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\Ideal::class;
	public $allowedCountries    = array( 'NL' );
	public $allowedCurrencies   = array( 'EUR' );
	public $method_title        = 'Unzer iDEAL | Wero';
	public $method_description;
	public $title       = 'iDEAL | Wero';
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
					'label'       => __( 'Enable Unzer iDEAL | Wero', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'iDEAL | wero', 'unzer-payments' ),
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
