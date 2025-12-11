<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Eps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EpsBlock extends AbstractBlock {

	public const GATEWAY_ID    = Eps::GATEWAY_ID;
	public const GATEWAY_CLASS = Eps::class;
	protected $name            = self::GATEWAY_ID;
}
