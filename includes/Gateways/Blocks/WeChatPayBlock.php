<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\WeChatPay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WeChatPayBlock extends AbstractBlock {

	public const GATEWAY_ID    = WeChatPay::GATEWAY_ID;
	public const GATEWAY_CLASS = WeChatPay::class;
	protected $name            = self::GATEWAY_ID;
}
