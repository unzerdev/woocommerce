<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Services\PaymentService;
use UnzerSDK\Resources\TransactionTypes\Charge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Klarna extends AbstractGateway {

	const GATEWAY_ID     = 'unzer_klarna';
	public $method_title = 'Unzer Klarna';
	public $method_description;
	public $title       = 'Klarna';
	public $description = '';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer Klarna', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Klarna', 'unzer-payments' ),
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
		$return = array(
			'result' => 'success',
		);
		$charge = ( new PaymentService() )->performChargeForOrder(
			$order_id,
			$this,
			\UnzerSDK\Resources\PaymentTypes\Klarna::class,
			function ( Charge $charge ) {
				$charge
					->setTermsAndConditionUrl( 'https://google.com' )
					->setPrivacyPolicyUrl( 'https://google.com/de/' );
			}
		);

		if ( $charge->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $charge->getPayment()->getRedirectUrl();
		}
		return $return;
	}
}
