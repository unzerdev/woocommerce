<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Prepayment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PrepaymentBlock extends AbstractBlock {

	public const GATEWAY_ID    = Prepayment::GATEWAY_ID;
	public const GATEWAY_CLASS = Prepayment::class;
	protected $name            = self::GATEWAY_ID;
}
