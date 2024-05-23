<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Services\PaymentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Przelewy24 extends AbstractGateway {

	const GATEWAY_ID            = 'unzer_przelewy24';
	public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\Przelewy24::class;
	public $method_title        = 'Unzer Przelewy24';
	public $method_description;
	public $title       = 'Przelewy24';
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
					'label'       => __( 'Enable Unzer Przelewy24', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Przelewy24', 'unzer-payments' ),
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
