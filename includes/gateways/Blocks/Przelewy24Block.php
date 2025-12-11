<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Przelewy24;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Przelewy24Block extends AbstractBlock {

	public const GATEWAY_ID    = Przelewy24::GATEWAY_ID;
	public const GATEWAY_CLASS = Przelewy24::class;
	protected $name            = self::GATEWAY_ID;
}
