<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Services\PaymentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Giropay extends AbstractGateway {

	const GATEWAY_ID            = 'unzer_giropay';
	public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\Giropay::class;
	public $method_title        = 'Unzer Giropay';
	public $method_description;
	public $title       = 'Giropay';
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
					'label'       => __( 'Enable Unzer Giropay', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Giropay', 'unzer-payments' ),
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
