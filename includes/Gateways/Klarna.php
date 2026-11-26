<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Gateways\Blocks\KlarnaBlock;
use UnzerPayments\Services\PaymentService;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\TransactionTypes\Authorization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Klarna extends AbstractGateway {



	const GATEWAY_ID                   = 'unzer_klarna';
	const BLOCK_CLASS                  = KlarnaBlock::class;
	public $allowedCountryCurrencySets = array(
		array(
			'country'  => 'AU',
			'currency' => 'AUD',
		),
		array(
			'country'  => 'AT',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'BE',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'CA',
			'currency' => 'CAD',
		),
		array(
			'country'  => 'CZ',
			'currency' => 'CZK',
		),
		array(
			'country'  => 'DK',
			'currency' => 'DKK',
		),
		array(
			'country'  => 'FI',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'FR',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'DE',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'GR',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'HU',
			'currency' => 'HUF',
		),
		array(
			'country'  => 'IE',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'IT',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'MX',
			'currency' => 'MXN',
		),
		array(
			'country'  => 'NL',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'NZ',
			'currency' => 'NZD',
		),
		array(
			'country'  => 'NO',
			'currency' => 'NOK',
		),
		array(
			'country'  => 'PL',
			'currency' => 'PLN',
		),
		array(
			'country'  => 'PT',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'RO',
			'currency' => 'RON',
		),
		array(
			'country'  => 'SK',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'ES',
			'currency' => 'EUR',
		),
		array(
			'country'  => 'SE',
			'currency' => 'SEK',
		),
		array(
			'country'  => 'CH',
			'currency' => 'CHF',
		),
		array(
			'country'  => 'GB',
			'currency' => 'GBP',
		),
		array(
			'country'  => 'US',
			'currency' => 'USD',
		),
	);
	public $method_title               = 'Unzer Klarna';
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
		$charge = ( new PaymentService() )->performAuthorizationForOrder(
			$order_id,
			$this,
			\UnzerSDK\Resources\PaymentTypes\Klarna::class,
			function ( Authorization $authorization ) {
				$authorization
					->setTermsAndConditionUrl( 'https://unzer.com' )
					->setPrivacyPolicyUrl( 'https://unzer.com' );
			}
		);

		$this->before_payment_redirect( $order_id );

		if ( $charge->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $charge->getPayment()->getRedirectUrl();
		}
		return $return;
	}

	/**
	 * @param $order_id
	 * @param $amount
	 * @param $reason
	 * @return bool
	 * @throws UnzerApiException
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return $this->process_refund_on_payment( $order_id, $amount, $reason );
	}
}
