<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Gateways\Blocks\PostFinanceCardBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostFinanceCard extends AbstractGateway {

	const GATEWAY_ID            = 'unzer_postfinance_card';
	const BLOCK_CLASS           = PostFinanceCardBlock::class;
	public $allowedCountries    = array( 'CH' );
	public $allowedCurrencies   = array( 'CHF' );
	public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\PostFinanceCard::class;
	public $method_title        = 'Unzer Post Finance Card';
	public $method_description;
	public $title       = 'Post Finance Card';
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
					'label'       => __( 'Enable Unzer Post Finance Card', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Post Finance Card', 'unzer-payments' ),
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
