<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Twint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TwintBlock extends AbstractBlock {

	public const GATEWAY_ID    = Twint::GATEWAY_ID;
	public const GATEWAY_CLASS = Twint::class;
	protected $name            = self::GATEWAY_ID;
}
