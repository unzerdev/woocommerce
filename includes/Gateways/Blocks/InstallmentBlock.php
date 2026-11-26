<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Installment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InstallmentBlock extends AbstractBlock {


	public const GATEWAY_ID    = Installment::GATEWAY_ID;
	public const GATEWAY_CLASS = Installment::class;
	protected $name            = self::GATEWAY_ID;
}
