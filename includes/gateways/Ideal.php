<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Services\PaymentService;
use UnzerPayments\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ideal extends AbstractGateway {

	const GATEWAY_ID     = 'unzer_ideal';
	public $method_title = 'Unzer iDEAL';
	public $method_description;
	public $title       = 'iDEAL';
	public $description = '';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);

	public function has_fields() {
		return true;
	}

	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}
		Util::getNonceField();
		?>
		<div id="unzer-ideal-form" class="unzerUI form" novalidate>
			<input type="hidden" id="unzer-ideal-id" name="unzer-ideal-id" value=""/>
			<div id="unzer-ideal" class="field"></div>
		</div>
		<?php
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer iDEAL', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'iDEAL', 'unzer-payments' ),
				),
				'description' => array(
					'title'       => __( 'Description', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'unzer-payments' ),
					'default'     => '',
				),
			)
		);
	}

	public function process_payment( $order_id ) {
		$this->logger->debug( 'start payment for #' . $order_id . ' with ' . self::GATEWAY_ID );
		$return      = array(
			'result' => 'success',
		);
		$transaction = ( new PaymentService() )->performChargeForOrder( $order_id, $this, Util::getNonceCheckedPostValue( 'unzer-ideal-id' ) );
		if ( $transaction->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $transaction->getPayment()->getRedirectUrl();
		}
		return $return;
	}
}
