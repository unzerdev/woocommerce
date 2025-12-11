<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Ideal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IdealBlock extends AbstractBlock {

	public const GATEWAY_ID    = Ideal::GATEWAY_ID;
	public const GATEWAY_CLASS = Ideal::class;
	protected $name            = self::GATEWAY_ID;
}
