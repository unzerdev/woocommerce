<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\PostFinanceEfinance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostFinanceEfinanceBlock extends AbstractBlock {

	public const GATEWAY_ID    = PostFinanceEfinance::GATEWAY_ID;
	public const GATEWAY_CLASS = PostFinanceEfinance::class;
	protected $name            = self::GATEWAY_ID;
}
