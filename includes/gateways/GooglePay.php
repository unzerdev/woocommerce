<?php

namespace UnzerPayments\gateways;

use UnzerPayments\Services\PaymentService;
use UnzerPayments\Util;
use UnzerSDK\Unzer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GooglePay extends AbstractGateway {

	const GATEWAY_ID     = 'unzer_google_pay';
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
		?>
		<input type="hidden" id="unzer-google-pay-id" name="unzer-google-pay-id" value=""/>
		<input type="hidden" id="unzer-google-pay-amount" name="unzer-google-pay-amount" value="<?php echo esc_attr( WC()->cart->get_total( 'plain' ) ); ?>"/>
		<?php
	}

	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->addCheckoutAssets();
		wp_enqueue_script( 'unzer_google_pay_js', 'https://pay.google.com/gp/p/js/pay.js', array(), UNZER_VERSION, array( 'in_footer' => false ) );
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

		if ( empty( $googlePayId ) ) {
			$this->logger->debug( 'google pay empty id' );
			$return['messages'] = '<!-- start-unzer-google-pay -->';
			return $return;
		}
		if ( $this->get_option( 'transaction_type' ) === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE ) {
			$transaction = ( new PaymentService() )->performAuthorizationForOrder( $order_id, $this, $googlePayId );
		} else {
			$transaction = ( new PaymentService() )->performChargeForOrder( $order_id, $this, $googlePayId );
		}

		$this->logger->debug( 'google pay charge/authorization for order ' . $order_id, array( $transaction->expose() ) );

		if ( $transaction->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $transaction->getPayment()->getRedirectUrl();
		} else {
			$return['redirect'] = $this->get_confirm_url();
		}
		return $return;
	}
}
