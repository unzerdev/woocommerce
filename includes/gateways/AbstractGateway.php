<?php

namespace UnzerPayments\Gateways;

use DateTime;
use Exception;
use UnzerPayments\Controllers\AdminController;
use UnzerPayments\Controllers\CheckoutController;
use UnzerPayments\Main;
use UnzerPayments\Services\LogService;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Util;
use UnzerSDK\Resources\EmbeddedResources\RiskData;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use WC_Data_Exception;
use WC_Order;
use WC_Payment_Gateway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractGateway extends WC_Payment_Gateway {



	const CONFIRMATION_ROUTE_SLUG = 'unzer-confirm';

	const TRANSACTION_TYPE_AUTHORIZE = 'authorize';
	const TRANSACTION_TYPE_CHARGE    = 'charge';

	const SETTINGS_KEY_SAVE_INSTRUMENTS = 'save_instruments';
	/**
	 * @var string
	 */
	public $paymentTypeResource = '';
	/**
	 * @var LogService
	 */
	protected $logger;

	/**
	 * @var null|array
	 */
	public $allowedCurrencies = null;
	public $allowedCountries  = null;

	public function __construct() {
		$this->logger    = new LogService();
		$this->plugin_id = 'unzer-payments';
		$this->init_settings();
		if ( $this->get_public_key() && $this->get_private_key() ) {
			$this->method_description = sprintf( __( 'The Unzer API settings can be adjusted <a href="%s">here</a>', 'unzer-payments' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=unzer_general' ) );
		} else {
			$this->method_description = '<div class="error" style="padding:10px;">' . sprintf( __( 'To start using Unzer payment methods, please enter your credentials first. <br><a href="%s" class="button-primary">API settings</a>', 'unzer-payments' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=unzer_general' ) ) . '</div>';
		}
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			return;
		}
		$this->addCheckoutAssets();
	}

	public function get_private_key() {
		return get_option( 'unzer_private_key' );
	}

	public function get_public_key() {
		return get_option( 'unzer_public_key' );
	}

	public function needs_setup() {
		return true;
	}

	public function is_enabled() {
		return $this->enabled === 'yes';
	}

	public function is_available() {
		$isAvailable = parent::is_available();
		if ( $isAvailable && ! empty( $this->allowedCurrencies ) ) {
			$isAvailable = in_array( get_woocommerce_currency(), $this->allowedCurrencies );
		}
		if ( $isAvailable && ! empty( $this->allowedCountries ) ) {
			$country = Util::getNonceCheckedPostValue( 'country' );
			if ( ! empty( $country ) && ! in_array( $country, $this->allowedCountries ) ) {
				$isAvailable = false;
			}
		}
		return $isAvailable;
	}

	public function init_settings() {
		parent::init_settings();
		if ( ! $this->get_private_key() || ! $this->get_public_key() ) {
			$this->enabled = 'no';
		}
	}

	public function process_payment( $order_id ) {
		$return = array(
			'result' => 'success',
		);
		$charge = ( new PaymentService() )->performChargeForOrder( $order_id, $this, $this->paymentTypeResource );
		if ( $charge->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $charge->getPayment()->getRedirectUrl();
		}
		return $return;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		try {
			$paymentService = new PaymentService();
			$cancellation   = $paymentService->performRefundOrReversal( $order_id, $this, $amount );
			return $cancellation->isSuccess();
		} catch ( \Exception $e ) {
			$this->logger->error(
				'refund error: ' . $e->getMessage(),
				array(
					'orderId' => $order_id,
					'amount'  => $amount,
				)
			);
			throw $e;
		}
	}

	public function process_refund_on_payment( $order_id, $amount = null, $reason = '' ) {
		try {
			$paymentService = new PaymentService();
			$cancellation   = $paymentService->performRefundOrReversalOnPayment( $order_id, $amount );
			return $cancellation->isSuccess();
		} catch ( \Exception $e ) {
			$this->logger->error(
				'refund error: ' . $e->getMessage(),
				array(
					'orderId' => $order_id,
					'amount'  => $amount,
				)
			);
			throw $e;
		}
	}

	public function get_confirm_url(): string {
		return WC()->api_request_url( static::CONFIRMATION_ROUTE_SLUG );
	}

	public function admin_options() {
		wp_enqueue_style( 'unzer_admin_css', UNZER_PLUGIN_URL . '/assets/css/admin.css', array(), UNZER_VERSION );
		wp_register_script( 'unzer_admin_js', UNZER_PLUGIN_URL . '/assets/js/admin.js', array(), UNZER_VERSION, array( 'in_footer' => false ) );
		wp_localize_script(
			'unzer_admin_js',
			'unzer_i18n',
			array(
				'deletePaymentInstrumentsWarning' => __( 'Turning off this feature will delete all stored payment instruments of your customers. Change this setting back to "yes" if you want to keep your customers\' payment instruments.', 'unzer-payments' ),
			)
		);
		wp_enqueue_script( 'unzer_admin_js' );
		echo '<img src="' . esc_url( UNZER_PLUGIN_URL . '/assets/img/logo.svg' ) . '" width="150" alt="Unzer" style="margin-top:20px;"/>';
		echo '<div>' . wp_kses_post( wpautop( $this->get_method_description() ) ) . '</div>';
		echo '<div class="unzer-content-container">';
		echo '<h2><span class="unzer-dropdown-icon unzer-content-toggler" data-target=".unzer-payment-navigation" title="' . esc_html__( 'Select another Unzer payment method', 'unzer-payments' ) . '"></span> ' . esc_html( $this->get_method_title() );
		wc_back_link( __( 'Return to payments', 'unzer-payments' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';
		echo '<style id="unzer-payment-navigation-temp-style">.unzer-payment-navigation { display:none !important; }</style>';
		echo wp_kses_post( $this->getCompletePaymentMethodListHtml() );
		// escaping $this->generate_settings_html would break the form html, using default WooCommerce method here
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>';
		if ( method_exists( $this, 'get_additional_options_html' ) ) {
			$this->get_additional_options_html();
		}
		echo '</div>';
	}


	public function generate_key_check_html( $key, $data ) {
		$slug          = $data['slug'];
		$gateway       = esc_attr( $this->id );
		$title         = wp_kses_post( $data['title'] );
		$isInvalidText = esc_html__( 'Keys are not valid', 'unzer-payments' );
		$isValidText   = esc_html__( 'Keys are valid', 'unzer-payments' );
		wp_enqueue_script( 'unzer_admin_key_management_js', UNZER_PLUGIN_URL . '/assets/js/admin_key_management.js', array(), UNZER_VERSION, array( 'in_footer' => false ) );
		wp_enqueue_script( 'unzer_admin_webhook_management_js', UNZER_PLUGIN_URL . '/assets/js/admin_webhook_management.js', array(), UNZER_VERSION, array( 'in_footer' => false ) );

		$webhookHtml = '';
		if ( $this->get_option( 'private_key_' . $slug ) && $this->get_option( 'public_key_' . $slug ) ) {
			ob_start();
			include UNZER_PLUGIN_PATH . 'html/admin/webhooks.php';
			$webhookHtml = ob_get_contents();
			ob_end_clean();
		}
		$ajaxUrl    = WC()->api_request_url( AdminController::KEY_VALIDATION_ROUTE_SLUG );
		$returnHtml = '
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="$key">$title</label>
                </th>
                <td class="forminp">
                    <div id="unzer-key-status-$slug" class="unzer-key-status" data-slug="$slug" data-gateway="$gateway" data-url="$ajaxUrl" style="margin-bottom:20px;">
                        <div class="is-error" style="color:#dc1b1b; display:none;"><span class="unzer-status-circle" style="background:#cc0000;"></span>$isInvalidText</div>
                        <div class="is-success" style=" display:none;"><span class="unzer-status-circle" style="background:#00a800;"></span>$isValidText</div>
                    </div>
                    $webhookHtml
                </td>
            </tr>
            ';
		return $returnHtml;
	}

	public function generate_readonly_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?><?php echo wp_kses_post( $this->get_tooltip_html( $data ) ); ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" readonly type="<?php echo esc_attr( $data['type'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> />
					<?php echo wp_kses_post( $this->get_description_html( $data ) ); ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	protected function getCompletePaymentMethodListHtml(): string {
		$gateways = Main::getInstance()->getPaymentGateways();
		$html     = '<ul class="unzer-payment-navigation">';
		$entries  = array();
		foreach ( $gateways as $gatewayId => $gatewayClass ) {
			/** @var AbstractGateway $gateway */
			$gateway                           = new $gatewayClass();
			$caption                           = str_replace( 'Unzer ', '', $gateway->method_title );
			$entries[ strtolower( $caption ) ] = '<li><a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $gatewayId ) . '">' . $caption . '</a></li>';
		}
		ksort( $entries );
		$html .= implode( '', $entries ) . '</ul>';
		return $html;
	}

	/**
	 * @param WC_Order $order
	 * @param string   $unzerPaymentId
	 * @return void
	 * @throws WC_Data_Exception
	 */
	protected function set_order_transaction_number( $order, $unzerPaymentId ) {
		$order->set_transaction_id( $unzerPaymentId );
		$order->save();
	}

	/**
	 * @param WC_Order $order
	 * @return void
	 * @throws Exception
	 */
	protected function handleDateOfBirth( $order, $dateOfBirth ) {
		$birthDate = new DateTime( $dateOfBirth );
		$maxDate   = new DateTime( '-18 years' );
		$minDate   = new DateTime( '-120 years' );
		if ( $birthDate >= $maxDate ) {
			throw new Exception( esc_html__( 'You have to be at least 18 years old for this payment method', 'unzer-payments' ) );
		}
		if ( $birthDate < $minDate ) {
			throw new Exception( esc_html__( 'Please check your date of birth', 'unzer-payments' ) );
		}
		$order->update_meta_data( Main::ORDER_META_KEY_DATE_OF_BIRTH, gmdate( 'Y-m-d', strtotime( $dateOfBirth ) ) );
		$order->save_meta_data();

		$user = wp_get_current_user();
		if ( $user->ID ) {
			update_user_meta( $user->ID, Main::ORDER_META_KEY_DATE_OF_BIRTH, gmdate( 'Y-m-d', strtotime( $dateOfBirth ) ) );
		}
	}

	protected function getUserBirthDate(): string {
		$dob  = '';
		$user = wp_get_current_user();
		if ( $user->ID ) {
			$dobFromUser = get_user_meta( $user->ID, Main::ORDER_META_KEY_DATE_OF_BIRTH, true );
			if ( $dobFromUser ) {
				$dob = gmdate( 'Y-m-d', strtotime( $dobFromUser ) );
			}
		}
		return $dob;
	}

	protected function addCheckoutAssets() {
		wp_enqueue_script( 'unzer_js', 'https://static.unzer.com/v1/unzer.js', array(), UNZER_VERSION, array( 'in_footer' => false ) );
		wp_enqueue_style( 'unzer_css', 'https://static.unzer.com/v1/unzer.css', array(), UNZER_VERSION );
		wp_enqueue_style( 'woocommerce_unzer_css', UNZER_PLUGIN_URL . '/assets/css/checkout.css', array(), UNZER_VERSION );
		wp_register_script( 'woocommerce_unzer', UNZER_PLUGIN_URL . '/assets/js/checkout.js', array( 'unzer_js', 'jquery' ), UNZER_VERSION, array( 'in_footer' => false ) );

		// for separate api keys
		$paylaterGateway           = new Invoice();
		$installmentGateway        = new Installment();
		$directDebitSecuredGateway = new DirectDebitSecured();
		$googlePayGateway          = new GooglePay();

		wp_localize_script(
			'woocommerce_unzer',
			'unzer_parameters',
			array(
				'publicKey'                            => $this->get_public_key(),
				'publicKey_eur_b2b'                    => $paylaterGateway->get_option( 'public_key_eur_b2b' ),
				'publicKey_eur_b2c'                    => $paylaterGateway->get_option( 'public_key_eur_b2c' ),
				'publicKey_chf_b2b'                    => $paylaterGateway->get_option( 'public_key_chf_b2b' ),
				'publicKey_chf_b2c'                    => $paylaterGateway->get_option( 'public_key_chf_b2c' ),
				'publicKey_installment_eur_b2c'        => $installmentGateway->get_option( 'public_key_eur_b2c' ),
				'publicKey_installment_chf_b2c'        => $installmentGateway->get_option( 'public_key_chf_b2c' ),
				'publicKey_directdebitsecured_eur_b2c' => $directDebitSecuredGateway->get_option( 'public_key_eur_b2c' ),
				'generic_error_message'                => __( 'An error occurred while processing your payment. Please try another payment method.', 'unzer-payments' ),
				'locale'                               => get_locale(),
				'store_name'                           => get_bloginfo( 'name' ),
				'store_country'                        => strtoupper( substr( get_option( 'woocommerce_default_country' ), 0, 2 ) ),
				'apple_pay_merchant_validation_url'    => WC()->api_request_url( CheckoutController::APPLE_PAY_MERCHANT_VALIDATION_ROUTE_SLUG ),
				'currency'                             => get_woocommerce_currency(),
				'google_pay_options'                   => array(
					'gatewayMerchantId'   => $googlePayGateway->get_option( 'channel_id' ),
					'merchantInfo'        => array(
						'merchantName' => $googlePayGateway->get_option( 'merchant_name' ),
						'merchantId'   => $googlePayGateway->get_option( 'merchant_id' ),
					),
					'transactionInfo'     => array(
						'countryCode' => $googlePayGateway->get_option( 'country_code' ),
					),
					'buttonOptions'       => array(
						'buttonColor'    => $googlePayGateway->get_option( 'button_color' ),
						'buttonSizeMode' => $googlePayGateway->get_option( 'button_size_mode' ),
					),
					'allowCreditCards'    => $googlePayGateway->get_option( 'credit_cards_allowed' ) === 'yes',
					'allowPrepaidCards'   => $googlePayGateway->get_option( 'prepaid_cards_allowed' ) === 'yes',
					'allowedCardNetworks' => (array) $googlePayGateway->get_option( 'card_networks' ),
				),
			)
		);
		wp_localize_script(
			'woocommerce_unzer',
			'unzer_i18n',
			array(
				'errorDob'         => __( 'Please enter your date of birth', 'unzer-payments' ),
				'errorCompanyType' => __( 'Please enter your company type', 'unzer-payments' ),
				'errorSepaMandate' => __( 'Please accept the SEPA mandate', 'unzer-payments' ),
			)
		);
		wp_enqueue_script( 'woocommerce_unzer' );
	}

	public static function addRiskDataToAuthorization( Authorization $authorization ) {
		$riskData = new RiskData();
		$riskData->setThreatMetrixId( WC()->session->get( 'unzerThreatMetrixId' ) );
		if ( is_user_logged_in() ) {
			/** @var \WP_User $user */
			$user = wp_get_current_user();
			$date = $user->user_registered ? gmdate( 'Ymd', strtotime( $user->user_registered ) ) : null;
			$riskData->setRegistrationLevel( 1 );
			$riskData->setRegistrationDate( $date );
		} else {
			$riskData->setRegistrationLevel( 0 );
		}
		$authorization->setRiskData( $riskData );
	}

	public static function removeRiskDataFromSession() {
		WC()->session->set( 'unzerThreatMetrixId', null );
	}


	protected function threatmetrix_payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( empty( WC()->session->get( 'unzerThreatMetrixId' ) ) ) {
			WC()->session->set( 'unzerThreatMetrixId', uniqid( 'unzer_tm_' ) );
		}
		wp_enqueue_script( 'unzer_threat_metrix_js', 'https://h.online-metrix.net/fp/tags.js?org_id=363t8kgq&session_id=' . WC()->session->get( 'unzerThreatMetrixId' ), array(), UNZER_VERSION, array( 'in_footer' => false ) );

		$this->addCheckoutAssets();
	}
}
