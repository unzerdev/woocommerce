<?php

namespace UnzerPayments\Gateways;

use Exception;
use UnzerPayments\Gateways\Blocks\DirectDebitSecuredBlock;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Util;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Charge;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DirectDebitSecured extends AbstractGateway {


	const GATEWAY_ID          = 'unzer_direct_debit_secured';
	const BLOCK_CLASS         = DirectDebitSecuredBlock::class;
	public $allowedCountries  = array( 'AT', 'DE' );
	public $allowedCurrencies = array( 'EUR' );
	public $isAllowedForB2B   = false;
	public $method_title      = 'Unzer Direct Debit';
	public $method_description;
	public $title       = 'Direct Debit';
	public $description = '';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);

	public function __construct() {
		parent::__construct();
		$this->method_title = __( 'Unzer Direct Debit', 'unzer-payments' );
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
		 <input type="hidden" id="unzer-direct-debit-secured-id" name="unzer-direct-debit-secured-id" value=""/>
		 <input type="hidden" id="unzer-direct-debit-secured-customer-id" name="unzer-direct-debit-secured-customer-id" value=""/>
		 <input type="hidden" id="unzer-direct-debit-secured-risk-id" name="unzer-direct-debit-secured-risk-id" value=""/>
            <div class="unzer-ui-container"></div>
            <template class="unzer-ui-template">
                <unzer-payment
                        id="unzer-paylater-direct-debit-payment-component"
                        publicKey="' . esc_attr( $this->get_current_public_key() ) . '"
                        locale="' . esc_attr( get_locale() ) . '"            
                        data-customer="' . esc_attr( $this->get_checkout_customer_json_encoded() ) . '"    
                >
                    <unzer-paylater-direct-debit id="unzer-paylater-direct-debit"></unzer-paylater-direct-debit>
                </unzer-payment>
            </template>     
        ';
		echo wp_kses( $form, $this->get_allowed_html_tags() );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'             => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer Direct Debit Payments', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'               => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Direct Debit', 'unzer-payments' ),
				),
				'description'         => array(
					'title'       => __( 'Description', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'unzer-payments' ),
					'default'     => '',
				),
				'additional_key_info' => array(
					'type' => 'unzer_additional_key_info',
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
			)
		);
	}

	public function process_payment( $order_id ) {
		$this->logger->debug( 'start payment for #' . $order_id . ' with ' . self::GATEWAY_ID );
		$order         = wc_get_order( $order_id );
		$return        = array(
			'result' => 'success',
		);
		$paymentMeanId = Util::getNonceCheckedPostValue( 'unzer-direct-debit-secured-id' );
		$riskId        = Util::getNonceCheckedPostValue( 'unzer-direct-debit-secured-risk-id' );

		$authorization = ( new PaymentService() )->performAuthorizationForOrder(
			$order_id,
			$this,
			$paymentMeanId,
			function ( Authorization $authorization ) use ( $riskId ) {
				AbstractGateway::addRiskDataToAuthorization( $authorization, $riskId );
			}
		);
		$this->before_payment_redirect( $order_id );
		if ( $authorization->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $authorization->getPayment()->getRedirectUrl();
		} elseif ( $authorization->isSuccess() ) {
			try {
				// this is repeated in confirmAction, but we need to make sure, that the order is updated if anything goes wrong
				( new OrderService() )->processPaymentStatus( $authorization, $order );
			} catch ( Exception $e ) {
				// silent catch
			}
			WC()->session->set( 'unzer_confirm_order_id', $order_id );
			$return['redirect'] = $this->get_confirm_url( $order_id );
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


	/**
	 * @param WC_Order $order
	 * @param float    $amount
	 * @throws Exception
	 */
	public function capture( WC_Order $order, $amount = null ) {
	}

	/**
	 * @param Charge|Authorization $chargeOrAuthorization
	 * @return string
	 */
	public function get_payment_information( AbstractTransactionType $chargeOrAuthorization ) {
		return sprintf(
			__( "An amount of %1\$s will be deducted from your account using the descriptor '%2\$s' according to the SEPA mandate", 'unzer-payments' ),
			wc_price( $chargeOrAuthorization->getAmount(), array( 'currency' => $chargeOrAuthorization->getCurrency() ) ),
			$chargeOrAuthorization->getDescriptor()
		);
	}

	private function get_current_public_key() {
		$keyName   = 'public_key_eur_b2c';
		$publicKey = $this->get_option( $keyName );
		if ( empty( $publicKey ) ) {
			$publicKey = get_option( 'unzer_public_key' );
		}
		return $publicKey;
	}
}
