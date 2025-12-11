<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\PostFinanceCard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostFinanceCardBlock extends AbstractBlock {

	public const GATEWAY_ID    = PostFinanceCard::GATEWAY_ID;
	public const GATEWAY_CLASS = PostFinanceCard::class;
	protected $name            = self::GATEWAY_ID;
}
