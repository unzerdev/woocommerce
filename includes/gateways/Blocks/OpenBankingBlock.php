<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\OpenBanking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenBankingBlock extends AbstractBlock {

	public const GATEWAY_ID    = OpenBanking::GATEWAY_ID;
	public const GATEWAY_CLASS = OpenBanking::class;
	protected $name            = self::GATEWAY_ID;
}
