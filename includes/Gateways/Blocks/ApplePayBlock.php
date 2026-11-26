<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\ApplePayV2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ApplePayBlock extends AbstractBlock {


	public const GATEWAY_ID    = ApplePayV2::GATEWAY_ID;
	public const GATEWAY_CLASS = ApplePayV2::class;
	protected $name            = self::GATEWAY_ID;
}
