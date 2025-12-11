<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CardBlock extends AbstractBlock {

	public const GATEWAY_ID    = Card::GATEWAY_ID;
	public const GATEWAY_CLASS = Card::class;
	protected $name            = self::GATEWAY_ID;
}
