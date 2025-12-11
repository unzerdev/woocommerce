<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\DirectDebitSecured;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DirectDebitSecuredBlock extends AbstractBlock {


	public const GATEWAY_ID    = DirectDebitSecured::GATEWAY_ID;
	public const GATEWAY_CLASS = DirectDebitSecured::class;
	protected $name            = self::GATEWAY_ID;
}
