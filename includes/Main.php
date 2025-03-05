<?php

namespace UnzerPayments;

use UnzerPayments\Controllers\AccountController;
use UnzerPayments\Controllers\AdminController;
use UnzerPayments\Controllers\CheckoutController;
use UnzerPayments\Controllers\WebhookController;
use UnzerPayments\Gateways\AbstractGateway;
use UnzerPayments\Gateways\Alipay;
use UnzerPayments\Gateways\ApplePay;
use UnzerPayments\gateways\ApplePayV2;
use UnzerPayments\Gateways\Bancontact;
use UnzerPayments\Gateways\Card;
use UnzerPayments\Gateways\DirectDebit;
use UnzerPayments\Gateways\DirectDebitSecured;
use UnzerPayments\Gateways\Eps;
use UnzerPayments\gateways\GooglePay;
use UnzerPayments\Gateways\Ideal;
use UnzerPayments\Gateways\Installment;
use UnzerPayments\Gateways\Invoice;
use UnzerPayments\gateways\OpenBanking;
use UnzerPayments\Gateways\Paypal;
use UnzerPayments\Gateways\PostFinanceCard;
use UnzerPayments\Gateways\PostFinanceEfinance;
use UnzerPayments\Gateways\Prepayment;
use UnzerPayments\Gateways\Przelewy24;
use UnzerPayments\Gateways\Sofort;
use UnzerPayments\gateways\Twint;
use UnzerPayments\Gateways\WeChatPay;
use UnzerPayments\SdkExtension\Resource\ApplePayCertificate;
use UnzerPayments\SdkExtension\Resource\ApplePayPrivateKey;
use UnzerPayments\SdkExtension\Services\AppleKeyService;
use UnzerPayments\Services\DashboardService;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;

class Main {


	public static $instance;
	const ORDER_META_KEY_AUTHORIZATION_ID     = 'unzer_authorization_id';
	const ORDER_META_KEY_CHARGE_ID            = 'unzer_charge_id';
	const ORDER_META_KEY_PAYMENT_ID           = 'unzer_payment_id';
	const ORDER_META_KEY_PAYMENT_SHORT_ID     = 'unzer_payment_short_id';
	const ORDER_META_KEY_PAYMENT_INSTRUCTIONS = 'unzer_payment_instructions';
	const ORDER_META_KEY_CANCELLATION_ID      = 'unzer_cancellation_id';
	const ORDER_META_KEY_DATE_OF_BIRTH        = 'unzer_dob';
	const ORDER_META_KEY_COMPANY_TYPE         = 'unzer_company_type';
	const ORDER_META_KEYS                     = array(
		self::ORDER_META_KEY_AUTHORIZATION_ID,
		self::ORDER_META_KEY_CHARGE_ID,
		self::ORDER_META_KEY_PAYMENT_ID,
		self::ORDER_META_KEY_PAYMENT_SHORT_ID,
		self::ORDER_META_KEY_PAYMENT_INSTRUCTIONS,
		self::ORDER_META_KEY_CANCELLATION_ID,
		self::ORDER_META_KEY_DATE_OF_BIRTH,
	);

	const USER_META_KEY_PAYMENT_INSTRUMENTS = 'payment_instruments';

	public static function getInstance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		$this->registerEvents();
		$this->registerOrderStatus();
	}

	public function registerEvents(): void {
		add_filter( 'woocommerce_get_settings_checkout', array( $this, 'addGlobalSettings' ), 10, 3 );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'addPaymentGateways' ) );
		add_filter( 'is_protected_meta', array( $this, 'setMetaProtected' ), 10, 3 );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'addOrderStatusesForPaymentComplete' ) );

		add_action( 'woocommerce_api_' . AdminController::GET_ORDER_TRANSACTIONS_ROUTE_SLUG, array( new AdminController(), 'getOrderTransactions' ) );
		add_action( 'woocommerce_api_' . AdminController::CHARGE_ROUTE_SLUG, array( new AdminController(), 'doCharge' ) );
		add_action( 'woocommerce_api_' . AdminController::WEBHOOK_MANAGEMENT_ROUTE_SLUG, array( new AdminController(), 'webhookManagement' ) );
		add_action( 'woocommerce_api_' . AdminController::KEY_VALIDATION_ROUTE_SLUG, array( new AdminController(), 'validateKeypair' ) );
		add_action( 'woocommerce_api_' . AdminController::NOTIFICATION_SLUG, array( new AdminController(), 'handleNotification' ) );
		add_action( 'woocommerce_api_' . AdminController::APPLE_PAY_REMOVE_KEY_ROUTE_SLUG, array( new AdminController(), 'applePayRemoveKey' ) );
		add_action( 'woocommerce_api_' . AdminController::APPLE_PAY_VALIDATE_CREDENTIALS_ROUTE_SLUG, array( new AdminController(), 'applePayValidateCredentials' ) );

		add_action( 'woocommerce_api_' . AbstractGateway::CONFIRMATION_ROUTE_SLUG, array( new CheckoutController(), 'confirm' ) );
		add_action( 'woocommerce_api_' . WebhookController::WEBHOOK_ROUTE_SLUG, array( new WebhookController(), 'receiveWebhook' ) );
		add_action( 'woocommerce_api_' . AccountController::DELETE_PAYMENT_INSTRUMENT_URL_SLUG, array( new AccountController(), 'deletePaymentInstrument' ) );
		add_action( 'woocommerce_api_' . CheckoutController::APPLE_PAY_MERCHANT_VALIDATION_ROUTE_SLUG, array( new CheckoutController(), 'validateApplePayMerchant' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UNZER_PLUGIN_PATH . 'unzer-payments.php' ), array( $this, 'addPluginSettingsLink' ) );
		add_action( 'add_meta_boxes', array( $this, 'addMetaBoxes' ), 40 );
		add_action( 'woocommerce_settings_checkout', array( AdminController::class, 'renderGlobalSettingsStart' ) );
		add_action( 'woocommerce_settings_tabs_checkout', array( AdminController::class, 'renderGlobalSettingsEnd' ), 10 );
		add_action( 'woocommerce_settings_tabs_checkout', array( new AdminController(), 'renderWebhookManagement' ), 20 );
		add_action( 'woocommerce_order_details_after_order_table', array( CheckoutController::class, 'checkoutSuccess' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'addOrderEmailData' ) );
		add_action( 'woocommerce_after_edit_account_form', array( new AccountController(), 'accountPaymentInstruments' ) );
		add_action( 'woocommerce_update_options_payment_gateways_unzer_card', array( $this, 'savePaymentMethodSettingsCard' ) );
		add_action( 'woocommerce_update_options_payment_gateways_unzer_paypal', array( $this, 'savePaymentMethodSettingsPaypal' ) );
		add_action( 'woocommerce_update_options_payment_gateways_unzer_direct_debit', array( $this, 'savePaymentMethodSettingsDirectDebit' ) );
		add_action( 'woocommerce_update_options_payment_gateways_unzer_apple_pay', array( $this, 'savePaymentMethodSettingsApplePay' ) );
		add_action( 'woocommerce_update_options_payment_gateways_unzer_apple_pay_v2', array( $this, 'savePaymentMethodSettingsApplePayV2' ) );
		add_action( 'woocommerce_update_options_checkout_unzer_general', array( $this, 'saveGeneralSettings' ) );
		add_action( 'admin_notices', array( new DashboardService(), 'showNotifications' ) );
		add_action(
			'admin_enqueue_scripts',
			function () {
				wp_enqueue_script( 'unzer_global_admin_js', UNZER_PLUGIN_URL . '/assets/js/admin_global.js', array(), UNZER_VERSION, array( 'in_footer' => false ) );
			}
		);
		add_filter(
			'wp_kses_allowed_html',
			function ( $tags, $context ) {
				if ( $context === 'post' && is_array( $tags ) ) {
					$tags['input']  = array(
						'value'       => true,
						'type'        => true,
						'id'          => true,
						'name'        => true,
						'class'       => true,
						'placeholder' => true,
						'required'    => true,
					);
					$tags['select'] = array(
						'value'       => true,
						'type'        => true,
						'id'          => true,
						'name'        => true,
						'class'       => true,
						'placeholder' => true,
						'required'    => true,
					);
					$tags['option'] = array(
						'value' => true,
					);
				}
				return $tags;
			},
			10,
			2
		);
	}

	public function addOrderEmailData( $order ) {
		( new OrderService() )->printPaymentInstructionsHtml( $order );
	}

	protected function registerOrderStatus(): void {
		add_action(
			'init',
			function () {
				register_post_status(
					OrderService::ORDER_STATUS_CHARGEBACK,
					array(
						'label'                     => __( 'Chargeback', 'unzer-payments' ),
						'public'                    => true,
						'exclude_from_search'       => false,
						'show_in_admin_all_list'    => true,
						'show_in_admin_status_list' => true,
					)
				);
				register_post_status(
					OrderService::ORDER_STATUS_AUTHORIZED,
					array(
						'label'                     => __( 'Ready to Capture', 'unzer-payments' ),
						'public'                    => true,
						'exclude_from_search'       => false,
						'show_in_admin_all_list'    => true,
						'show_in_admin_status_list' => true,
					)
				);
				register_post_status(
					OrderService::ORDER_STATUS_WAITING_FOR_PAYMENT,
					array(
						'label'                     => __( 'Waiting for payment', 'unzer-payments' ),
						'public'                    => true,
						'exclude_from_search'       => false,
						'show_in_admin_all_list'    => true,
						'show_in_admin_status_list' => true,
					)
				);
			}
		);

		add_filter(
			'wc_order_statuses',
			function ( $statusList ) {
				$statusList[ OrderService::ORDER_STATUS_CHARGEBACK ]          = __( 'Chargeback', 'unzer-payments' );
				$statusList[ OrderService::ORDER_STATUS_AUTHORIZED ]          = __( 'Ready to Capture', 'unzer-payments' );
				$statusList[ OrderService::ORDER_STATUS_WAITING_FOR_PAYMENT ] = __( 'Waiting for payment', 'unzer-payments' );
				return $statusList;
			}
		);
	}

	public function saveGeneralSettings() {
		$googlePay = self::getInstance()->getPaymentGateway( GooglePay::GATEWAY_ID );
		if ( $googlePay ) {
			$googlePay->fetchAndSaveChannelId();
		}
	}

	public function savePaymentMethodSettingsCard(): void {
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			return;
		}
		$cardGateway = new Card();
		if ( isset( $_POST['unzer-paymentsunzer_card_save_instruments'] ) && $_POST['unzer-paymentsunzer_card_save_instruments'] === 'no' ) {
			$cardGateway->deleteAllSavedPaymentInstruments();
		}
	}

	public function savePaymentMethodSettingsPaypal(): void {
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			return;
		}
		$paypalGateway = new Paypal();
		if ( isset( $_POST['unzer-paymentsunzer_paypal_save_instruments'] ) && $_POST['unzer-paymentsunzer_paypal_save_instruments'] === 'no' ) {
			$paypalGateway->deleteAllSavedPaymentInstruments();
		}
	}

	public function savePaymentMethodSettingsDirectDebit(): void {
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			return;
		}
		$gateway = new DirectDebit();
		if ( isset( $_POST['unzer-paymentsunzer_direct_debit_save_instruments'] ) && $_POST['unzer-paymentsunzer_direct_debit_save_instruments'] === 'no' ) {
			$gateway->deleteAllSavedPaymentInstruments();
		}
	}

	public function savePaymentMethodSettingsApplePayV2(): void {
		$dirExists = is_dir( ABSPATH . '.well-known' );
		if ( ! $dirExists ) {
			$dirExists = mkdir( ABSPATH . '.well-known' );
		}
		$targetFile = ABSPATH . '.well-known/apple-developer-merchantid-domain-association';
		if ( $dirExists && ! file_exists( $targetFile ) ) {
			copy( UNZER_PLUGIN_PATH . 'assets/txt/apple-developer-merchantid-domain-association', $targetFile );
		}
		if ( ! file_exists( $targetFile ) ) {
			( new DashboardService() )->addError( 'apple_pay_id_file' );
		}
	}
	public function savePaymentMethodSettingsApplePay(): void {
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			return;
		}
		if ( ! empty( $_FILES['unzer_apple_pay_payment_processing_certificate']['tmp_name'] ) && ! empty( $_FILES['unzer_apple_pay_payment_processing_key']['tmp_name'] ) ) {
			$client      = ( new PaymentService() )->getUnzerManager();
			$certificate = file_get_contents( sanitize_url( $_FILES['unzer_apple_pay_payment_processing_certificate']['tmp_name'] ) );
			$key         = file_get_contents( sanitize_url( $_FILES['unzer_apple_pay_payment_processing_key']['tmp_name'] ) );

			if ( extension_loaded( 'openssl' ) && ! openssl_x509_parse( $certificate ) ) {
				throw new \Exception( 'Invalid Payment Processing certificate given' );
			}

			$privateKeyResource = new ApplePayPrivateKey();
			$privateKeyResource->setCertificate( $key );
			$client->getResourceService()->createResource( $privateKeyResource->setParentResource( $client ) );
			/** @var string $privateKeyId */
			$privateKeyId = $privateKeyResource->getId();
			update_option( 'unzer_apple_pay_payment_key_id', $privateKeyId );
			$certificateResource = new ApplePayCertificate();
			$certificateResource->setCertificate( $certificate );
			$certificateResource->setPrivateKey( $privateKeyId );
			$client->getResourceService()->createResource( $certificateResource->setParentResource( $client ) );

			$certificateService = new AppleKeyService( $client );
			$activateSuccess    = $certificateService->activateCertificate( $certificateResource->getId() );
			if ( ! $activateSuccess ) {
				throw new \Exception( 'Could not activate Apple Pay certificate' );
			}
			update_option( 'unzer_apple_pay_payment_certificate_id', $certificateResource->getId() );
		}

		if ( ! empty( $_FILES['unzer_apple_pay_merchant_id_certificate']['tmp_name'] ) ) {
			$certificate = file_get_contents( sanitize_url( $_FILES['unzer_apple_pay_merchant_id_certificate']['tmp_name'] ) );
			update_option( 'unzer_apple_pay_merchant_id_certificate', $certificate );
		}

		if ( ! empty( $_FILES['unzer_apple_pay_merchant_id_key']['tmp_name'] ) ) {
			$certificate = file_get_contents( sanitize_url( $_FILES['unzer_apple_pay_merchant_id_key']['tmp_name'] ) );
			update_option( 'unzer_apple_pay_merchant_id_key', $certificate );
		}
	}

	public function setMetaProtected( $protected, $meta_key, $meta_type ) {
		if ( in_array( $meta_key, self::ORDER_META_KEYS ) ) {
			return true;
		}
		return $protected;
	}

	public function addMetaBoxes() {
		if ( Util::isHPOS() ) {
			$screen = wc_get_page_screen_id( 'shop-order' );
		} else {
			$screen = 'shop_order';
		}
		add_meta_box( 'woocommerce-unzer-transactions', __( 'Unzer Transactions', 'unzer-payments' ), AdminController::class . '::renderTransactionTable', $screen, 'normal', 'high' );
	}

	public function addPluginSettingsLink( $links ): array {
		$settingsLink = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=unzer_general' ) . '">' . __( 'Unzer API settings', 'unzer-payments' ) . '</a>';
		array_unshift( $links, $settingsLink );
		return $links;
	}

	public function addGlobalSettings( $settings, $currentSection ): array {
		if ( $currentSection === 'unzer_general' ) {
			if ( get_option( 'unzer_chargeback_order_status' ) === false ) {
				update_option( 'unzer_chargeback_order_status', OrderService::ORDER_STATUS_CHARGEBACK );
			}
			if ( get_option( 'unzer_authorized_order_status' ) === false ) {
				update_option( 'unzer_authorized_order_status', OrderService::ORDER_STATUS_AUTHORIZED );
			}
			$settings = array(
				'title'                   => array(
					'type' => 'title',
					'desc' => '',
				),
				'public_key'              => array(
					'title'   => __( 'Public Key', 'unzer-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'id'      => 'unzer_public_key',
					'value'   => get_option( 'unzer_public_key' ),
					'default' => '',
				),
				'private_key'             => array(
					'title'   => __( 'Private Key', 'unzer-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'id'      => 'unzer_private_key',
					'value'   => get_option( 'unzer_private_key' ),
					'default' => '',
				),
				'authorized_order_status' => array(
					'title'   => __( 'Order status for authorized payments', 'unzer-payments' ),
					'label'   => '',
					'type'    => 'select',
					'desc'    => __( 'This status is assigned for orders, that are authorized', 'unzer-payments' ),
					'options' => array_merge( array( '' => __( '[Use WooC default status]', 'unzer-payments' ) ), wc_get_order_statuses() ),
					'id'      => 'unzer_authorized_order_status',
					'value'   => get_option( 'unzer_authorized_order_status' ),
					'default' => OrderService::ORDER_STATUS_AUTHORIZED,
				),
				'captured_order_status'   => array(
					'title'   => __( 'Order status for captured payments', 'unzer-payments' ),
					'label'   => '',
					'type'    => 'select',
					'desc'    => __( 'This status is assigned for orders, that are captured', 'unzer-payments' ),
					'options' => array_merge( array( '' => __( '[Use WooC default status]', 'unzer-payments' ) ), wc_get_order_statuses() ),
					'id'      => 'unzer_captured_order_status',
					'value'   => get_option( 'unzer_captured_order_status' ),
					'default' => '',
				),
				'chargeback_order_status' => array(
					'title'   => __( 'Order status for chargebacks', 'unzer-payments' ),
					'label'   => '',
					'type'    => 'select',
					'desc'    => __( 'This status is assigned for orders with chargebacks', 'unzer-payments' ),
					'options' => array_merge( array( '' => __( '[No status change]', 'unzer-payments' ) ), wc_get_order_statuses() ),
					'id'      => 'unzer_chargeback_order_status',
					'value'   => get_option( 'unzer_chargeback_order_status' ),
					'default' => OrderService::ORDER_STATUS_CHARGEBACK,
				),
				'sectionend'              => array(
					'type' => 'sectionend',
				),
			);
		}
		return $settings;
	}

	public function addOrderStatusesForPaymentComplete( $statuses ): array {
		if ( get_option( 'unzer_authorized_order_status' ) ) {
			$statuses[] = get_option( 'unzer_authorized_order_status' );
		}
		return $statuses;
	}

	public function addPaymentGateways( $gateways ): array {
		return array_merge( $gateways, array_values( $this->getPaymentGateways() ) );
	}

	public function getPaymentGateways(): array {
		return array(
			Card::GATEWAY_ID                => Card::class,
			Paypal::GATEWAY_ID              => Paypal::class,
			Bancontact::GATEWAY_ID          => Bancontact::class,
			Przelewy24::GATEWAY_ID          => Przelewy24::class,
			WeChatPay::GATEWAY_ID           => WeChatPay::class,
			Alipay::GATEWAY_ID              => Alipay::class,
			Eps::GATEWAY_ID                 => Eps::class,
			// Giropay::GATEWAY_ID             => Giropay::class,
			Sofort::GATEWAY_ID              => Sofort::class,
			// Klarna::GATEWAY_ID => Klarna::class,
			// Pis::GATEWAY_ID => Pis::class,
			DirectDebit::GATEWAY_ID         => DirectDebit::class,
			DirectDebitSecured::GATEWAY_ID  => DirectDebitSecured::class,
			Invoice::GATEWAY_ID             => Invoice::class,
			Installment::GATEWAY_ID         => Installment::class,
			Prepayment::GATEWAY_ID          => Prepayment::class,
			Ideal::GATEWAY_ID               => Ideal::class,
			PostFinanceEfinance::GATEWAY_ID => PostFinanceEfinance::class,
			PostFinanceCard::GATEWAY_ID     => PostFinanceCard::class,
			ApplePay::GATEWAY_ID            => ApplePay::class,
			ApplePayV2::GATEWAY_ID          => ApplePayV2::class,
			GooglePay::GATEWAY_ID           => GooglePay::class,
			Twint::GATEWAY_ID               => Twint::class,
			OpenBanking::GATEWAY_ID         => OpenBanking::class,
		);
	}

	public function getPaymentGateway( $key ): ?AbstractGateway {
		$class = $this->getPaymentGateways()[ $key ] ?? null;
		if ( $class ) {
			return new $class();
		}
		return null;
	}
}
