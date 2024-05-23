<?php

namespace UnzerPayments\Gateways;

use Exception;
use UnzerPayments\Main;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Util;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Invoice extends AbstractGateway {


	const GATEWAY_ID     = 'unzer_invoice';
	public $method_title = 'Unzer Invoice';
	public $method_description;
	public $title       = 'Invoice';
	public $description = '';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);

	public $allowedCurrencies = array( 'EUR', 'CHF' );
	public $allowedCountries  = array( 'AT', 'CH', 'DE', 'NL' );

	public function __construct() {
		parent::__construct();
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		$this->method_title = __( 'Unzer Invoice', 'unzer-payments' );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'             => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer Invoice', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'               => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Invoice', 'unzer-payments' ),
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
				'public_key_eur_b2b'  => array(
					'title'   => __( 'Public Key EUR/B2B', 'unzer-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'default' => '',
				),
				'private_key_eur_b2b' => array(
					'title'   => __( 'Private Key EUR/B2B', 'unzer-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'default' => '',
				),
				'key_check_eur_b2b'   => array(
					'title'   => __( 'Key Check EUR/B2B', 'unzer-payments' ),
					'type'    => 'key_check',
					'slug'    => 'eur_b2b',
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
				'public_key_chf_b2b'  => array(
					'title'   => __( 'Public Key CHF/B2B', 'unzer-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'default' => '',
				),
				'private_key_chf_b2b' => array(
					'title'   => __( 'Private Key CHF/B2B', 'unzer-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'default' => '',
				),
				'key_check_chf_b2b'   => array(
					'title'   => __( 'Key Check CHF/B2B', 'unzer-payments' ),
					'type'    => 'key_check',
					'slug'    => 'chf_b2b',
					'desc'    => '',
					'default' => '',
				),
			)
		);
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
		?>
		<div class="unzer-checkout-field-row form-row">
			<label><?php echo esc_html__( 'Date of birth', 'unzer-payments' ); ?></label>
			<input type="date" id="unzer-invoice-dob" name="unzer-invoice-dob" class="input-text" value="<?php echo esc_attr( $this->getUserBirthDate() ); ?>" max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"/>
		</div>
		<div class="unzer-checkout-field-row form-row b2b" id="unzer-invoice-company-type-container">
			<label><?php echo esc_html__( 'Type of company', 'unzer-payments' ); ?></label>
			<select name="unzer-invoice-company-type" id="unzer-invoice-company-type" class="input-text">
				<option></option>
				<option value="association"><?php echo esc_html__( 'Association', 'unzer-payments' ); ?></option>
				<option value="authority"><?php echo esc_html__( 'Authority', 'unzer-payments' ); ?></option>
				<option value="company"><?php echo esc_html__( 'Company', 'unzer-payments' ); ?></option>
				<option value="sole"><?php echo esc_html__( 'Sole', 'unzer-payments' ); ?></option>
				<option value="other"><?php echo esc_html__( 'Other', 'unzer-payments' ); ?></option>
			</select>
		</div>
		<div id="unzer-invoice-form" class="unzerUI form">
			<input type="hidden" id="unzer-invoice-id" name="unzer-invoice-id" value=""/>
			<div class="field">
				<div id="unzer-invoice-fields">
					<!-- The Payment form UI element (opt-in text and checkbox) will be inserted here -->
				</div>
			</div>
		</div>
		<?php
	}

	public function payment_scripts() {
		$this->threatmetrix_payment_scripts();
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
		$dob    = Util::getNonceCheckedPostValue( 'unzer-invoice-dob' );
		$this->handleDateOfBirth( $order, $dob );
		$_POST['unzer-dob'] = $dob; // for unified handling in CustomerService and OrderService

		if ( $order->get_billing_company() ) {
			$companyType = (string) Util::getNonceCheckedPostValue( 'unzer-invoice-company-type' );
			if ( empty( $companyType ) ) {
				throw new Exception( esc_html__( 'Please enter your company type', 'unzer-payments' ) );
			}
			$order->update_meta_data( Main::ORDER_META_KEY_COMPANY_TYPE, $companyType );
		}
		$order->save_meta_data();

		try {
			$authorization = ( new PaymentService() )->performAuthorizationForOrder(
				$order_id,
				$this,
				Util::getNonceCheckedPostValue( 'unzer-invoice-id' ),
				function ( Authorization $authorization ) {
					AbstractGateway::addRiskDataToAuthorization( $authorization );
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
		$return['redirect'] = $this->get_return_url( wc_get_order( $order_id ) );
		AbstractGateway::removeRiskDataFromSession();
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


	public function get_payment_information( AbstractTransactionType $chargeOrAuthorization ) {
		return sprintf(
			__(
				'Please transfer the amount of %s to the following account:<br /><br />'
				. 'Holder: %s<br/>'
				. 'IBAN: %s<br/>'
				. 'BIC: %s<br/><br/>'
				. '<i>Please use only this identification number as the descriptor: </i><br/>'
				. '%s',
				'unzer-payments'
			),
			wc_price( $chargeOrAuthorization->getAmount(), array( 'currency' => $chargeOrAuthorization->getCurrency() ) ),
			$chargeOrAuthorization->getHolder(),
			$chargeOrAuthorization->getIban(),
			$chargeOrAuthorization->getBic(),
			$chargeOrAuthorization->getDescriptor()
		);
	}
}
