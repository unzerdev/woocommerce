<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Wero;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WeroBlock extends AbstractBlock {

	public const GATEWAY_ID    = Wero::GATEWAY_ID;
	public const GATEWAY_CLASS = Wero::class;
	protected $name            = self::GATEWAY_ID;
}
