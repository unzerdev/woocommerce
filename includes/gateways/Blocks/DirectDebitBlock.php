<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\DirectDebit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DirectDebitBlock extends AbstractBlock {


	public const GATEWAY_ID    = DirectDebit::GATEWAY_ID;
	public const GATEWAY_CLASS = DirectDebit::class;
	protected $name            = self::GATEWAY_ID;
}
