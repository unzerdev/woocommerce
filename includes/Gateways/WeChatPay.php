<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Gateways\Blocks\WeChatPayBlock;
use UnzerPayments\Services\PaymentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WeChatPay extends AbstractGateway {

	const GATEWAY_ID            = 'unzer_wechatpay';
	const BLOCK_CLASS           = WeChatPayBlock::class;
	public $allowedCountries    = array( 'AT', 'BE', 'DK', 'FI', 'FR', 'DE', 'ES', 'GB', 'GR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LU', 'MT', 'NL', 'NO', 'PT', 'SE' );
	public $allowedCurrencies   = array( 'CHF', 'CNY', 'EUR', 'GBP', 'USD' );
	public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\Wechatpay::class;
	public $method_title        = 'Unzer WeChat Pay';
	public $method_description;
	public $title       = 'WeChat Pay';
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
					'label'       => __( 'Enable Unzer WeChat Pay', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'WeChat Pay', 'unzer-payments' ),
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
