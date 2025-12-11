<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Paypal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaypalBlock extends AbstractBlock {


	public const GATEWAY_ID    = Paypal::GATEWAY_ID;
	public const GATEWAY_CLASS = Paypal::class;
	protected $name            = self::GATEWAY_ID;
}
