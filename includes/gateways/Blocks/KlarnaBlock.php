<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Klarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KlarnaBlock extends AbstractBlock {

	public const GATEWAY_ID    = Klarna::GATEWAY_ID;
	public const GATEWAY_CLASS = Klarna::class;
	protected $name            = self::GATEWAY_ID;
}
