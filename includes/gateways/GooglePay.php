<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Gateways\Blocks\GooglePayBlock;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Util;
use UnzerSDK\Unzer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GooglePay extends AbstractGateway {

	const GATEWAY_ID     = 'unzer_google_pay';
	const BLOCK_CLASS    = GooglePayBlock::class;
	public $method_title = 'Unzer Google Pay';
	public $method_description;
	public $title       = 'Google Pay';
	public $description = '';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);

	public function __construct() {
		parent::__construct();
	}

	public function admin_options() {
		if ( ! $this->get_option( 'channel_id' ) ) {
			$this->fetchAndSaveChannelId();
		}
		parent::admin_options();
	}

	public function getPublicOptions() {
		return array(
			'gatewayMerchantId'   => $this->get_option( 'channel_id' ),
			'merchantInfo'        => array(
				'merchantName' => $this->get_option( 'merchant_name' ),
				'merchantId'   => $this->get_option( 'merchant_id' ),
			),
			'transactionInfo'     => array(
				'countryCode' => $this->get_option( 'country_code' ),
			),
			'buttonOptions'       => array(
				'buttonColor'    => $this->get_option( 'button_color' ),
				'buttonSizeMode' => $this->get_option( 'button_size_mode' ),
			),
			'allowCreditCards'    => $this->get_option( 'credit_cards_allowed' ) === 'yes',
			'allowPrepaidCards'   => $this->get_option( 'prepaid_cards_allowed' ) === 'yes',
			'allowedCardNetworks' => (array) $this->get_option( 'card_networks' ),
		);
	}

	public function fetchAndSaveChannelId() {
		if ( ! $this->get_private_key() ) {
			$this->update_option( 'channel_id', '' );
			return;
		}
		try {
			$unzerManager = new Unzer( $this->get_private_key() );
			$keyPair      = $unzerManager->fetchKeypair( true );
			foreach ( $keyPair->getPaymentTypes() as $paymentType ) {
				if ( $paymentType->type === 'googlepay' ) {
					$channelId = $paymentType->supports[0]->channel ?? null;
					if ( $channelId ) {
						$this->update_option( 'channel_id', $channelId );
						return;
					}
				}
			}
		} catch ( \Exception $e ) {
			$this->logger->error( 'Error fetching keypair', array( $e->getMessage() ) );
		}
		// will only be reached, if no channel id was found
		$this->update_option( 'channel_id', '' );
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
		    <input type="hidden" id="unzer-google-pay-id" name="unzer-google-pay-id" value=""/>
		    <input type="hidden" id="unzer-google-pay-amount" name="unzer-google-pay-amount" value="' . esc_attr( $this->get_amount() ) . '"/>
            <template class="unzer-google-pay-ui-template">
                <unzer-payment
                        id="unzer-google-pay-payment-component"
                        publicKey="' . esc_attr( $this->get_public_key() ) . '"
                        locale="' . esc_attr( get_locale() ) . '">
                    <unzer-google-pay></unzer-google-pay>
                </unzer-payment>
                <unzer-checkout id="unzer-google-pay-checkout-component"></unzer-checkout>
            </template>     
        ';
		echo wp_kses( $form, $this->get_allowed_html_tags() );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'               => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer Google Pay', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'                 => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Google Pay', 'unzer-payments' ),
				),
				'description'           => array(
					'title'       => __( 'Description', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'unzer-payments' ),
					'default'     => '',
				),
				'transaction_type'      => array(
					'title'       => __( 'Charge or Authorize', 'unzer-payments' ),
					'label'       => '',
					'type'        => 'select',
					'description' => __( 'Choose "authorize", if you you want to charge the shopper at a later point of time', 'unzer-payments' ),
					'options'     => array(
						AbstractGateway::TRANSACTION_TYPE_AUTHORIZE => __( 'authorize', 'unzer-payments' ),
						AbstractGateway::TRANSACTION_TYPE_CHARGE => __( 'charge', 'unzer-payments' ),
					),
					'default'     => 'charge',
				),
				'channel_id'            => array(
					'title'       => __( 'Gateway Merchant ID', 'unzer-payments' ),
					'type'        => 'readonly',
					'description' => 'The channel ID provided by Unzer',
					'default'     => '',
				),
				'merchant_id'           => array(
					'title'       => __( 'Merchant ID', 'unzer-payments' ),
					'type'        => 'text',
					'description' => 'Provided by Google in the merchant info field',
					'default'     => '',
				),
				'merchant_name'         => array(
					'title'       => __( 'Merchant Name', 'unzer-payments' ),
					'type'        => 'text',
					'description' => 'Provided by Google in the merchant info field',
					'default'     => '',
				),
				'country_code'          => array(
					'title'       => __( 'Country Code', 'unzer-payments' ),
					'label'       => '',
					'type'        => 'select',
					'description' => __( 'Country code of the acquirer', 'unzer-payments' ),
					'options'     => array(
						'DK' => __( 'DK (default)', 'unzer-payments' ),
						'CH' => 'CH',
					),
					'default'     => 'DK',
				),

				'credit_cards_allowed'  => array(
					'title'       => __( 'Allow Credit Cards', 'unzer-payments' ),
					'label'       => '',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'yes',
				),
				'prepaid_cards_allowed' => array(
					'title'       => __( 'Allow Prepaid Cards', 'unzer-payments' ),
					'label'       => '',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'yes',
				),
				'card_networks'         => array(
					'title'       => __( 'Allowed Card Networks', 'unzer-payments' ),
					'label'       => '',
					'type'        => 'multiselect',
					'description' => '',
					'options'     => array(
						'MASTERCARD' => 'MASTERCARD',
						'VISA'       => 'VISA',
					),
					'default'     => array( 'DISCOVER', 'JCB', 'MASTERCARD', 'VISA' ),
				),
				'button_color'          => array(
					'title'       => __( 'Button Color', 'unzer-payments' ),
					'label'       => '',
					'type'        => 'select',
					'description' => '',
					'options'     => array(
						'default' => __( 'Google\'s default', 'unzer-payments' ),
						'black'   => __( 'Black', 'unzer-payments' ),
						'white'   => __( 'White', 'unzer-payments' ),
					),
					'default'     => 'default',
				),
				'button_size_mode'      => array(
					'title'       => __( 'Button Size Mode', 'unzer-payments' ),
					'label'       => '',
					'type'        => 'select',
					'description' => '',
					'options'     => array(
						'fill'   => __( 'Full Width', 'unzer-payments' ),
						'static' => __( 'Static', 'unzer-payments' ),
					),
					'default'     => 'fill',
				),
			)
		);
	}

	public function process_payment( $order_id ) {
		$this->logger->debug( 'start payment for #' . $order_id . ' with ' . self::GATEWAY_ID );
		$return = array(
			'result' => 'success',
		);

		$googlePayId = Util::getNonceCheckedPostValue( 'unzer-google-pay-id' );
		if ( $this->get_option( 'transaction_type' ) === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE ) {
			$transaction = ( new PaymentService() )->performAuthorizationForOrder( $order_id, $this, $googlePayId );
		} else {
			$transaction = ( new PaymentService() )->performChargeForOrder( $order_id, $this, $googlePayId );
		}

		$this->logger->debug( 'google pay charge/authorization for order ' . $order_id, array( $transaction->expose() ) );
		$this->before_payment_redirect( $order_id );
		if ( $transaction->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $transaction->getPayment()->getRedirectUrl();
		} else {
			$return['redirect'] = $this->get_confirm_url( $order_id );
		}
		return $return;
	}
}
