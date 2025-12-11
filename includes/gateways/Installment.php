<?php

namespace UnzerPayments\Gateways;

use Exception;
use UnzerPayments\Gateways\Blocks\InstallmentBlock;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Util;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Charge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Installment extends AbstractGateway {


	const GATEWAY_ID          = 'unzer_installment';
	const BLOCK_CLASS         = InstallmentBlock::class;
	public $allowedCurrencies = array( 'EUR', 'CHF' );
	public $allowedCountries  = array( 'AT', 'CH', 'DE' );
	public $isAllowedForB2B   = false;
	public $method_title      = 'Unzer Installment';
	public $method_description;
	public $title       = 'Installment';
	public $description = '';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);

	public function __construct() {
		parent::__construct();
		$this->method_title = __( 'Unzer Installment', 'unzer-payments' );
	}

	public function has_fields() {
		return true;
	}

	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}
		Util::getNonceField();
		$form = '
		 <input type="hidden" id="unzer-installment-id" name="unzer-installment-id" value=""/>
		 <input type="hidden" id="unzer-installment-customer-id" name="unzer-installment-customer-id" value=""/>
		 <input type="hidden" id="unzer-installment-risk-id" name="unzer-installment-risk-id" value=""/>
		 <input type="hidden" id="unzer-installment-amount" name="unzer-installment-amount" value="' . esc_attr( $this->get_amount() ) . '" />
            <div class="unzer-ui-container"></div>
            <template class="unzer-ui-template">
                <unzer-payment
                        id="unzer-paylater-installment-payment-component"
                        publicKey="' . esc_attr( $this->get_current_public_key() ) . '"
                        locale="' . esc_attr( get_locale() ) . '"               
                        data-customer="' . esc_attr( $this->get_checkout_customer_json_encoded() ) . '" 
                >
                    <unzer-paylater-installment id="unzer-paylater-installment"></unzer-paylater-installment>
                </unzer-payment>
            </template>     
        ';
		echo wp_kses( $form, $this->get_allowed_html_tags() );
	}

	public function get_current_public_key() {
		$currency  = get_woocommerce_currency();
		$keyName   = 'public_key_' . strtolower( $currency ) . '_b2c';
		$publicKey = $this->get_option( $keyName );
		if ( empty( $publicKey ) ) {
			$publicKey = get_option( 'unzer_public_key' );
		}
		return $publicKey;
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'             => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer Installment', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'               => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Installment', 'unzer-payments' ),
				),
				'description'         => array(
					'title'       => __( 'Description', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'unzer-payments' ),
					'default'     => '',
				),
				'public_key_eur_b2c'  => array(
					'title'   => __( 'Public Key EUR/B2C', 'unzer-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'default' => '',
				),
				'private_key_eur_b2c' => array(
					'title'   => __( 'Private Key EUR/B2C', 'unzer-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'default' => '',
				),
				'key_check_eur_b2c'   => array(
					'title'   => __( 'Key Check EUR/B2C', 'unzer-payments' ),
					'type'    => 'key_check',
					'slug'    => 'eur_b2c',
					'desc'    => '',
					'default' => '',
				),
				'public_key_chf_b2c'  => array(
					'title'   => __( 'Public Key CHF/B2C', 'unzer-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'default' => '',
				),
				'private_key_chf_b2c' => array(
					'title'   => __( 'Private Key CHF/B2C', 'unzer-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'default' => '',
				),
				'key_check_chf_b2c'   => array(
					'title'   => __( 'Key Check CHF/B2C', 'unzer-payments' ),
					'type'    => 'key_check',
					'slug'    => 'chf_b2c',
					'desc'    => '',
					'default' => '',
				),
			)
		);
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


	/**
	 * @param $order_id
	 * @return array
	 * @throws \WC_Data_Exception|Exception
	 */
	public function process_payment( $order_id ) {
		$return = array(
			'result' => 'success',
		);
		$order  = wc_get_order( $order_id );
		$order->save_meta_data();
		$riskId = Util::getNonceCheckedPostValue( 'unzer-installment-risk-id' );
		try {
			$authorization = ( new PaymentService() )->performAuthorizationForOrder(
				$order_id,
				$this,
				Util::getNonceCheckedPostValue( 'unzer-installment-id' ),
				function ( Authorization $authorization ) use ( $riskId ) {
					AbstractGateway::addRiskDataToAuthorization( $authorization, $riskId );
				}
			);
		} catch ( UnzerApiException $e ) {
			throw new Exception( esc_html( $e->getClientMessage() ?: $e->getMessage() ) );
		}
		if ( ! ( $authorization->isPending() || $authorization->isSuccess() ) ) {
			throw new Exception( esc_html( $authorization->getMessage()->getCustomer() ) );
		}

		if ( $authorization->isSuccess() ) {
			$order        = wc_get_order( $order_id );
			$orderService = new OrderService();
			$orderService->setOrderAuthorized( $order, $authorization->getPayment()->getId() );
		} else {
			$this->set_order_transaction_number( wc_get_order( $order_id ), $authorization->getPayment()->getId() );
		}
		$this->before_payment_redirect( $order_id );
		$return['redirect'] = $this->get_return_url( wc_get_order( $order_id ) );
		return $return;
	}
	/**
	 * @param Charge|Authorization $chargeOrAuthorization
	 * @return string
	 */
	public function get_payment_information( AbstractTransactionType $chargeOrAuthorization ) {
		return sprintf(
			__(
				'Payment details:<br /><br />'
				. 'Holder: %s<br/>'
				. 'IBAN: %s<br/>'
				. 'BIC: %s<br/><br/>'
				. '<i>Descriptor: </i><br/>'
				. '%s',
				'unzer-payments'
			),
			$chargeOrAuthorization->getHolder(),
			$chargeOrAuthorization->getIban(),
			$chargeOrAuthorization->getBic(),
			$chargeOrAuthorization->getDescriptor()
		);
	}
}
