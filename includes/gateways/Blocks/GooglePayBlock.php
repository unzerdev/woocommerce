<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\GooglePay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GooglePayBlock extends AbstractBlock {



	public const GATEWAY_ID    = GooglePay::GATEWAY_ID;
	public const GATEWAY_CLASS = GooglePay::class;
	protected $name            = self::GATEWAY_ID;

	public function get_payment_method_data() {
		$gatewayClass = self::GATEWAY_CLASS;
		return parent::get_payment_method_data() + array(
			'options' => ( new $gatewayClass() )->getPublicOptions(),
		);
	}
}
