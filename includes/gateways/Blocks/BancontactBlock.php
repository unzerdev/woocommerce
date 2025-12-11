<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Bancontact;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BancontactBlock extends AbstractBlock {

	public const GATEWAY_ID    = Bancontact::GATEWAY_ID;
	public const GATEWAY_CLASS = Bancontact::class;
	protected $name            = self::GATEWAY_ID;
}
