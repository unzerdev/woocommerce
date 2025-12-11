<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Alipay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlipayBlock extends AbstractBlock {

	public const GATEWAY_ID    = Alipay::GATEWAY_ID;
	public const GATEWAY_CLASS = Alipay::class;
	protected $name            = self::GATEWAY_ID;
}
