<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Controllers\AdminController;
use UnzerPayments\Main;
use UnzerPayments\Services\CustomerService;
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



	const ALLOWED_HTML            = array(
		'unzer-payment'               => array(
			'id'            => true,
			'class'         => true,
			'locale'        => true,
			'publicKey'     => true,
			'publickey'     => true,
			'data-customer' => true,
			'disableCTP'    => true,
			'disablectp'    => true,
		),
		'unzer-checkout'              => array(
			'id'    => true,
			'class' => true,
		),
		'unzer-card'                  => array(
			'id'    => true,
			'class' => true,
		),
		'unzer-apple-pay'             => array(
			'id'    => true,
			'class' => true,
		),
		'unzer-google-pay'            => array(
			'id'    => true,
			'class' => true,
		),
		'unzer-sepa-direct-debit'     => array(
			'id'    => true,
			'class' => true,
		),
		'unzer-paylater-direct-debit' => array(
			'id'    => true,
			'class' => true,
		),
		'unzer-paylater-installment'  => array(
			'id'    => true,
			'class' => true,
		),
		'unzer-paylater-invoice'      => array(
			'id'    => true,
			'class' => true,
		),
		'unzer-open-banking'          => array(
			'id'    => true,
			'class' => true,
		),
		'template'                    => array(
			'id'    => true,
			'class' => true,
		),
	);
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
	public $allowedCurrencies          = null;
	public $allowedCountries           = null;
	public $isAllowedForB2B            = null;
	public $allowedCountryCurrencySets = null;

	public function __construct() {
		$this->logger    = new LogService();
		$this->plugin_id = 'unzer-payments';
		$this->init_settings();
		if ( $this->get_public_key() && $this->get_private_key() ) {
			$this->method_description = sprintf( __( 'The Unzer API settings can be adjusted <a href="%s">here</a>', 'unzer-payments' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=unzer_general' ) );
		} else {
			$this->method_description = '<div class="error inline" style="padding:10px;">' . sprintf( __( 'To start using Unzer payment methods, please enter your credentials first. <br><a href="%s" class="button-primary">API settings</a>', 'unzer-payments' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=unzer_general' ) ) . '</div>';
		}
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}

	protected function get_allowed_html_tags() {
		$response                     = array_merge( wp_kses_allowed_html( 'post' ), self::ALLOWED_HTML );
		$response['input']['checked'] = true;
		return $response;
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

	protected function get_amount() {
		global $wp;
		if ( ! empty( $wp->query_vars['order-pay'] ) ) {
			$order = wc_get_order( $wp->query_vars['order-pay'] );
			return $order->get_total();
		} else {
			return WC()->cart->get_total( 'plain' );
		}
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

	protected function get_billing_data_from_post(): array {
		$postData = Util::getNonceCheckedPostValue( 'post_data' );
		if ( ! empty( $postData ) ) {
			parse_str( $postData, $params );
			return $params;
		}
		return array();
	}

	protected function get_company_from_post() {
		$company = Util::getNonceCheckedPostValue( 'company' );
		if ( ! empty( $company ) ) {
			return $company;
		}

		$postData = Util::getNonceCheckedPostValue( 'post_data' );
		if ( ! empty( $postData ) ) {
			parse_str( $postData, $params );
			if ( ! empty( $params['billing_company'] ) ) {
				return $params['billing_company'];
			}
		}
		return '';
	}

	protected function getCurrentCountry() {
		$country = Util::getNonceCheckedPostValue( 'country' );
		if ( ! empty( $country ) ) {
			return $country;
		}
		if ( WC()->session !== null ) {
			$customer = WC()->session->get( 'customer' );
			if ( ! empty( $customer['country'] ) ) {
				return $customer['country'];
			}
		}
		return null;
	}

	public function is_available() {
		$isAvailable = parent::is_available();
		if ( $isAvailable && ! empty( $this->allowedCurrencies ) ) {
			$isAvailable = in_array( get_woocommerce_currency(), $this->allowedCurrencies );
		}
		if ( $isAvailable && ! empty( $this->allowedCountries ) ) {
			$country = $this->getCurrentCountry();
			if ( ! empty( $country ) && ! in_array( $country, $this->allowedCountries ) ) {
				$isAvailable = false;
			}
		}
		if ( $isAvailable && $this->isAllowedForB2B === false ) {
			$company = $this->get_company_from_post();
			if ( ! empty( $company ) ) {
				$isAvailable = false;
			}
		}

		if ( $isAvailable && ! empty( $this->allowedCountryCurrencySets ) ) {
			$country  = $this->getCurrentCountry();
			$currency = get_woocommerce_currency();
			$isFound  = false;
			foreach ( $this->allowedCountryCurrencySets as $allowedCountryCurrencySet ) {
				if ( ! empty( $country ) && $allowedCountryCurrencySet['country'] !== $country ) {
					continue;
				}
				if ( $allowedCountryCurrencySet['currency'] !== $currency ) {
					continue;
				}
				$isFound = true;
				break;
			}
			if ( ! $isFound ) {
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
		$this->before_payment_redirect( $order_id );
		if ( $charge->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $charge->getPayment()->getRedirectUrl();
		}
		return $return;
	}

	protected function before_payment_redirect( $order_id ) {
		WC()->session->set( 'unzer_confirm_order_id', $order_id );
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

	public function get_confirm_url( $order_id = null ): string {
		$url = WC()->api_request_url( static::CONFIRMATION_ROUTE_SLUG );
		if ( $order_id !== null ) {
			$separator = strpos( $url, '?' ) === false ? '?' : '&';
			$url      .= $separator . 'unzer_confirm_order_id=' . $order_id;
		}
		return $url;
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
		echo wp_kses_post( $this->getCompletePaymentMethodListHtml() );
		// escaping $this->generate_settings_html would break the form html, using default WooCommerce method here
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>';
		if ( method_exists( $this, 'get_additional_options_html' ) ) {
			$this->get_additional_options_html();
		}
		echo '</div>';
	}

	public function generate_unzer_additional_key_info_html( $key, $data ) {
		return '
            <tr valign="top" class="unzer-alert-info-row">
                <td colspan="2">
                    <div class="unzer-alert-info">
                        ' . esc_html__( 'In the section below you may enter additional key pairs. If you leave fields empty, the main key pair will be used by default.' ) . '
                    </div>
                </td>
            </tr>
            ';
	}


	public function generate_key_check_html( $key, $data ) {
		$slug  = $data['slug'];
		$title = $data['title'] ?? '';

		if ( empty( $this->get_option( 'private_key_' . $slug ) ) || empty( $this->get_option( 'public_key_' . $slug ) ) ) {
			return '';
		}

		wp_enqueue_script( 'unzer_admin_key_management_js', UNZER_PLUGIN_URL . '/assets/js/admin_key_management.js', array(), UNZER_VERSION, array( 'in_footer' => false ) );
		wp_enqueue_script( 'unzer_admin_webhook_management_js', UNZER_PLUGIN_URL . '/assets/js/admin_webhook_management.js', array(), UNZER_VERSION, array( 'in_footer' => false ) );

		$webhookHtml = '';

		ob_start();
		$paymentMethod = static::GATEWAY_ID;
		include UNZER_PLUGIN_PATH . 'html/admin/webhooks.php';
		$webhookHtml = ob_get_contents();
		ob_end_clean();

		$ajaxUrl = WC()->api_request_url( AdminController::KEY_VALIDATION_ROUTE_SLUG );
		return '
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="$key">' . esc_html( $title ) . '</label>
                </th>
                <td class="forminp">
                    <div id="unzer-key-status-' . esc_attr( $slug ) . '" class="unzer-key-status" data-slug="' . esc_attr( $slug ) . '" data-gateway="' . esc_attr( $this->id ) . '" data-url="' . esc_attr( $ajaxUrl ) . '" data-nonce="' . esc_attr( Util::getNonce() ) . '" style="margin-bottom:20px;">
                        <div class="is-error" style="color:#dc1b1b; display:none;"><span class="unzer-status-circle" style="background:#cc0000;"></span>' . esc_html__( 'Keys are not valid', 'unzer-payments' ) . '</div>
                        <div class="is-success" style=" display:none;"><span class="unzer-status-circle" style="background:#00a800;"></span>' . esc_html__( 'Keys are valid', 'unzer-payments' ) . '</div>
                    </div>
                    ' . $webhookHtml . '
                </td>
            </tr>
            ';
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

	public function get_checkout_customer_json() {
		global $wp;
		$orderId  = $this->isOrderPay() ? (int) $wp->query_vars['order-pay'] : null;
		$customer = ( new CustomerService() )->getCustomerFromSession( $this, $orderId );
		return $customer !== null ? json_encode( $customer->expose() ) : '';
	}

	protected function get_checkout_customer_json_encoded() {
		return base64_encode( $this->get_checkout_customer_json() );
	}

	protected function addCheckoutAssets() {
		global $wp;
		// TODO replace when minimum WP version is 6.5 (wp_enqueue_script_module)
		add_filter(
			'script_loader_tag',
			function ( $tag, $handle, $src ) {
				// if not your script, do nothing and return original $tag
				if ( 'unzer_ui_v2_js' !== $handle ) {
					return $tag;
				}
				// change the script tag by adding type="module" and return it.
				$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
				return $tag;
			},
			10,
			3
		);
		wp_enqueue_script( 'unzer_ui_v2_js', 'https://static-v2.unzer.com/v2/ui-components/index.js', array(), UNZER_VERSION, array( 'in_footer' => true ) ); // https://static-v2.unzer.com/v2/ui-components/index.js
		wp_enqueue_style( 'woocommerce_unzer_css', UNZER_PLUGIN_URL . '/assets/css/checkout.css', array(), UNZER_VERSION );

		if ( ( $this instanceof GooglePay ) && empty( $this->get_description() ) ) {
			wp_add_inline_style( 'woocommerce_unzer_css', '.payment_box.payment_method_unzer_google_pay{display:none !important;}' );
		} elseif ( ( $this instanceof ApplePayV2 ) && empty( $this->get_description() ) ) {
			wp_add_inline_style( 'woocommerce_unzer_css', '.payment_box.payment_method_unzer_apple_pay_v2{display:none !important;}' );
		}

		wp_register_script( 'woocommerce_unzer', UNZER_PLUGIN_URL . '/assets/js/checkout.js', array( 'jquery' ), UNZER_VERSION, array( 'in_footer' => false ) );
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
				'is_order_pay'                         => self::isOrderPay() ? 'true' : 'false',
				'currency'                             => get_woocommerce_currency(),
				'google_pay_options'                   => $googlePayGateway->getPublicOptions(),
			)
		);
		wp_localize_script(
			'woocommerce_unzer',
			'unzer_i18n',
			array(
				'errorSepaMandate' => __( 'Please accept the SEPA mandate', 'unzer-payments' ),
			)
		);
		wp_enqueue_script( 'woocommerce_unzer' );
	}

	public static function isOrderPay() {
		global $wp;
		return ! empty( $wp->query_vars['order-pay'] );
	}

	public static function addRiskDataToAuthorization( Authorization $authorization, ?string $riskId ) {
		$riskData = new RiskData();
		if ( is_user_logged_in() ) {
			/** @var \WP_User $user */
			$user = wp_get_current_user();
			$date = $user->user_registered ? gmdate( 'Ymd', strtotime( $user->user_registered ) ) : null;
			$riskData->setRegistrationLevel( 1 );
			$riskData->setRegistrationDate( $date );
		} else {
			$riskData->setRegistrationLevel( 0 );
		}
		if ( $riskId !== null ) {
			$riskData->setThreatMetrixId( $riskId );
		}
		$authorization->setRiskData( $riskData );
	}

	public static function isUnzerPaymentMethod( string $paymentMethodId ) {
		return substr( $paymentMethodId, 0, 6 ) === 'unzer_';
	}
}
