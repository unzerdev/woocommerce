<?php

namespace UnzerPayments\Gateways\Blocks;

use UnzerPayments\Gateways\Invoice;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InvoiceBlock extends AbstractBlock {


	public const GATEWAY_ID    = Invoice::GATEWAY_ID;
	public const GATEWAY_CLASS = Invoice::class;
	protected $name            = self::GATEWAY_ID;
}
