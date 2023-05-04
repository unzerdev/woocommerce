<?php

namespace UnzerPayments;


use UnzerPayments\Controllers\AccountController;
use UnzerPayments\Controllers\AdminController;
use UnzerPayments\Controllers\CheckoutController;
use UnzerPayments\Controllers\WebhookController;
use UnzerPayments\Gateways\AbstractGateway;
use UnzerPayments\Gateways\Alipay;
use UnzerPayments\Gateways\Bancontact;
use UnzerPayments\Gateways\Card;
use UnzerPayments\Gateways\DirectDebit;
use UnzerPayments\Gateways\DirectDebitSecured;
use UnzerPayments\Gateways\Eps;
use UnzerPayments\Gateways\Giropay;
use UnzerPayments\Gateways\Ideal;
use UnzerPayments\Gateways\Installment;
use UnzerPayments\Gateways\Invoice;
use UnzerPayments\Gateways\Klarna;
use UnzerPayments\Gateways\Paypal;
use UnzerPayments\Gateways\Pis;
use UnzerPayments\Gateways\Prepayment;
use UnzerPayments\Gateways\Przelewy24;
use UnzerPayments\Gateways\Sofort;
use UnzerPayments\Gateways\WeChatPay;

class Main
{
    public static $instance;
    const ORDER_META_KEY_AUTHORIZATION_ID = 'unzer_authorization_id';
    const ORDER_META_KEY_CHARGE_ID = 'unzer_charge_id';
    const ORDER_META_KEY_PAYMENT_ID = 'unzer_payment_id';
    const ORDER_META_KEY_PAYMENT_SHORT_ID = 'unzer_payment_short_id';
    const ORDER_META_KEY_PAYMENT_INSTRUCTIONS = 'unzer_payment_instructions';
    const ORDER_META_KEY_CANCELLATION_ID = 'unzer_cancellation_id';
    const ORDER_META_KEY_DATE_OF_BIRTH = 'unzer_dob';
    const ORDER_META_KEY_COMPANY_TYPE = 'unzer_company_type';
    const ORDER_META_KEYS = [
        self::ORDER_META_KEY_AUTHORIZATION_ID,
        self::ORDER_META_KEY_CHARGE_ID,
        self::ORDER_META_KEY_PAYMENT_ID,
        self::ORDER_META_KEY_PAYMENT_SHORT_ID,
        self::ORDER_META_KEY_PAYMENT_INSTRUCTIONS,
        self::ORDER_META_KEY_CANCELLATION_ID,
        self::ORDER_META_KEY_DATE_OF_BIRTH
    ];

    const USER_META_KEY_PAYMENT_INSTRUMENTS = 'payment_instruments';

    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void
    {
        $this->registerEvents();
    }

    public function registerEvents(): void
    {
        add_filter('woocommerce_get_settings_checkout', [$this, 'addGlobalSettings'], 10, 3);
        add_filter('woocommerce_payment_gateways', [$this, 'addPaymentGateways']);
        add_filter('is_protected_meta', [$this, 'setMetaProtected'], 10, 3);
        add_action('woocommerce_api_' . AbstractGateway::CONFIRMATION_ROUTE_SLUG, [new CheckoutController(), 'confirm']);
        add_action('woocommerce_api_' . AdminController::GET_ORDER_TRANSACTIONS_ROUTE_SLUG, [new AdminController(), 'getOrderTransactions']);
        add_action('woocommerce_api_' . AdminController::CHARGE_ROUTE_SLUG, [new AdminController(), 'doCharge']);
        add_action('woocommerce_api_' . AdminController::WEBHOOK_MANAGEMENT_ROUTE_SLUG, [new AdminController(), 'webhookManagement']);
        add_action('woocommerce_api_' . AdminController::KEY_VALIDATION_ROUTE_SLUG, [new AdminController(), 'validateKeypair']);
        add_action('woocommerce_api_' . WebhookController::WEBHOOK_ROUTE_SLUG, [new WebhookController(), 'receiveWebhook']);
        add_action('woocommerce_api_' . AccountController::DELETE_PAYMENT_INSTRUMENT_URL_SLUG, [new AccountController(), 'deletePaymentInstrument']);
        add_filter('plugin_action_links_' . plugin_basename(UNZER_PLUGIN_PATH . 'unzer-payments' . '.php'), [$this, 'addPluginSettingsLink']);
        add_action( 'add_meta_boxes', array( $this, 'addMetaBoxes' ), 40 );
        add_action('woocommerce_settings_checkout', [AdminController::class, 'renderGlobalSettingsStart']);
        add_action('woocommerce_settings_tabs_checkout', [AdminController::class, 'renderGlobalSettingsEnd'], 10);
        add_action('woocommerce_settings_tabs_checkout', [new AdminController(), 'renderWebhookManagement'], 20);
        add_action('woocommerce_order_details_after_order_table', [CheckoutController::class, 'checkoutSuccess'], 10);
        add_action('woocommerce_after_edit_account_form', [new AccountController(), 'accountPaymentInstruments']);
        add_action('woocommerce_update_options_payment_gateways_unzer_card', [$this, 'savePaymentMethodSettingsCard']);
        add_action('woocommerce_update_options_payment_gateways_unzer_paypal', [$this, 'savePaymentMethodSettingsPaypal']);
    }

    public function savePaymentMethodSettingsCard(){
        $cardGateway = new Card();
        if($_POST['unzer-paymentsunzer_card_save_instruments'] === 'no'){
            $cardGateway->deleteAllSavedPaymentInstruments();
        }
    }

    public function savePaymentMethodSettingsPaypal(){
        $paypalGateway = new Paypal();
        if($_POST['unzer-paymentsunzer_paypal_save_instruments'] === 'no'){
            $paypalGateway->deleteAllSavedPaymentInstruments();
        }
    }

    public function setMetaProtected($protected, $meta_key, $meta_type){
        if(in_array($meta_key, self::ORDER_META_KEYS)){
            return true;
        }
        return $protected;
    }

    public function addMetaBoxes(){
        if (!current_user_can('edit_shop_orders') || empty($_GET['post'])) {
            return;
        }
        $paymentId = get_post_meta($_GET['post'], Main::ORDER_META_KEY_PAYMENT_ID, true);
        if (empty($paymentId)) {
            return;
        }
        $paymentShortId = get_post_meta($_GET['post'], Main::ORDER_META_KEY_PAYMENT_SHORT_ID, true);
        add_meta_box( 'woocommerce-unzer-transactions', __( 'Unzer Transactions', 'unzer-payments').' #'.$paymentShortId, AdminController::class.'::renderTransactionTable', 'shop_order', 'normal', 'high' );
    }
    public function addPluginSettingsLink($links): array
    {
        $settingsLink = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=unzer_general') . '">' . __('Unzer API settings', 'unzer-payments') . '</a>';
        array_unshift($links, $settingsLink);
        return $links;
    }

    public function addGlobalSettings($settings, $currentSection): array
    {
        if ($currentSection === 'unzer_general') {
            $settings = [
                'title' => [
                    'type' => 'title',
                    'desc' => '',
                ],
                'public_key' => [
                    'title' => __('Public Key', 'unzer-payments'),
                    'type' => 'text',
                    'desc' => '',
                    'id' => 'unzer_public_key',
                    'value' => get_option('unzer_public_key'),
                ],
                'private_key' => [
                    'title' => __('Private Key', 'unzer-payments'),
                    'type' => 'text',
                    'desc' => '',
                    'id' => 'unzer_private_key',
                    'value' => get_option('unzer_private_key'),
                ],
                'authorized_order_status' => [
                    'title' => __('Order status for authorized payments', 'unzer-payments'),
                    'label' => '',
                    'type' => 'select',
                    'desc' => __('This status is assigned for orders, that are authorized', 'unzer-payments'),
                    'options' => array_merge(['' => __('[Use WooC default status]', 'unzer-payments')], wc_get_order_statuses()),
                    'id' => 'unzer_authorized_order_status',
                    'value' => get_option('unzer_authorized_order_status'),
                ],
                'captured_order_status' => [
                    'title' => __('Order status for captured payments', 'unzer-payments'),
                    'label' => '',
                    'type' => 'select',
                    'desc' => __('This status is assigned for orders, that are captured', 'unzer-payments'),
                    'options' => array_merge(['' => __('[Use WooC default status]', 'unzer-payments')], wc_get_order_statuses()),
                    'id' => 'unzer_captured_order_status',
                    'value' => get_option('unzer_captured_order_status'),
                ],
                'sectionend' => [
                    'type' => 'sectionend',
                ],
            ];
        }
        return $settings;
    }

    public function addPaymentGateways($gateways): array
    {
        return array_merge($gateways, array_values($this->getPaymentGateways()));
    }

    public function getPaymentGateways(): array
    {
        return [
            Card::GATEWAY_ID => Card::class,
            Paypal::GATEWAY_ID => Paypal::class,
            Bancontact::GATEWAY_ID => Bancontact::class,
            Przelewy24::GATEWAY_ID => Przelewy24::class,
            WeChatPay::GATEWAY_ID => WeChatPay::class,
            Alipay::GATEWAY_ID => Alipay::class,
            Eps::GATEWAY_ID => Eps::class,
            Giropay::GATEWAY_ID => Giropay::class,
            Sofort::GATEWAY_ID => Sofort::class,
            //Klarna::GATEWAY_ID => Klarna::class,
            //Pis::GATEWAY_ID => Pis::class,
            DirectDebit::GATEWAY_ID => DirectDebit::class,
            //DirectDebitSecured::GATEWAY_ID => DirectDebitSecured::class,
            Invoice::GATEWAY_ID => Invoice::class,
            //Installment::GATEWAY_ID => Installment::class,
            Prepayment::GATEWAY_ID => Prepayment::class,
            Ideal::GATEWAY_ID => Ideal::class, //TODO setBIC
        ];
    }

    public function getPaymentGateway($key): ?AbstractGateway
    {
        $class = $this->getPaymentGateways()[$key] ?? null;
        if ($class) {
            return new $class;
        }
        return null;
    }
}
