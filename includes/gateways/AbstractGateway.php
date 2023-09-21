<?php

namespace UnzerPayments\Gateways;

use DateTime;
use Exception;
use UnzerPayments\Controllers\AdminController;
use UnzerPayments\Controllers\CheckoutController;
use UnzerPayments\Main;
use UnzerPayments\Services\LogService;
use UnzerPayments\Services\PaymentService;
use UnzerSDK\Resources\EmbeddedResources\RiskData;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use WC_Data_Exception;
use WC_Order;
use WC_Payment_Gateway;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractGateway extends WC_Payment_Gateway
{
    const CONFIRMATION_ROUTE_SLUG = 'unzer-confirm';

    const TRANSACTION_TYPE_AUTHORIZE = 'authorize';
    const TRANSACTION_TYPE_CHARGE = 'charge';

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
    public $allowedCountries = null;

    public function __construct()
    {
        $this->logger = new LogService();
        $this->plugin_id = 'unzer-payments';
        $this->init_settings();
        if ($this->get_public_key() && $this->get_private_key()) {
            $this->method_description = sprintf(__('The Unzer API settings can be adjusted <a href="%s">here</a>', 'unzer-payments'), admin_url('admin.php?page=wc-settings&tab=checkout&section=unzer_general'));
        } else {
            $this->method_description = '<div class="error" style="padding:10px;">' . sprintf(__('To start using Unzer payment methods, please enter your credentials first. <br><a href="%s" class="button-primary">API settings</a>', 'unzer-payments'), admin_url('admin.php?page=wc-settings&tab=checkout&section=unzer_general')) . '</div>';
        }
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
    }

    public function get_private_key()
    {
        return get_option('unzer_private_key');
    }

    public function get_public_key()
    {
        return get_option('unzer_public_key');
    }

    public function needs_setup()
    {
        return true;
    }

    public function is_enabled()
    {
        return $this->enabled === 'yes';
    }

    public function is_available()
    {
        $isAvailable = parent::is_available();
        if ($isAvailable && !empty($this->allowedCurrencies)) {
            $isAvailable = in_array(get_woocommerce_currency(), $this->allowedCurrencies);
        }
        if ($isAvailable && !empty($this->allowedCountries)) {
            if (!empty($_POST['country']) && !in_array($_POST['country'], $this->allowedCountries)) {
                $isAvailable = false;
            }
        }
        return $isAvailable;
    }

    public function init_settings()
    {
        parent::init_settings();
        if (!$this->get_private_key() || !$this->get_public_key()) {
            $this->enabled = 'no';
        }
    }

    public function process_payment($order_id)
    {
        $return = [
            'result' => 'success',
        ];
        $charge = (new PaymentService())->performChargeForOrder($order_id, $this, $this->paymentTypeResource);
        if ($charge->getPayment()->getRedirectUrl()) {
            $return['redirect'] = $charge->getPayment()->getRedirectUrl();
        }
        return $return;
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        try {
            $paymentService = new PaymentService();
            $cancellation = $paymentService->performRefundOrReversal($order_id, $this, $amount);
            return $cancellation->isSuccess();
        } catch (\Exception $e) {
            $this->logger->error('refund error: ' . $e->getMessage(), ['orderId' => $order_id, 'amount' => $amount]);
            throw $e;
        }
    }

    public function get_confirm_url(): string
    {
        return WC()->api_request_url(static::CONFIRMATION_ROUTE_SLUG);
    }

    public function admin_options()
    {
        wp_enqueue_style('unzer_admin_css', UNZER_PLUGIN_URL . '/assets/css/admin.css');
        wp_register_script('unzer_admin_js', UNZER_PLUGIN_URL . '/assets/js/admin.js');
        wp_localize_script('unzer_admin_js', 'unzer_i18n', [
            'deletePaymentInstrumentsWarning' => __('Turning off this feature will delete all stored payment instruments of your customers. Change this setting back to "yes" if you want to keep your customers\' payment instruments.', 'unzer-payments'),
        ]);
        wp_enqueue_script('unzer_admin_js');
        echo '<img src="' . esc_url(UNZER_PLUGIN_URL . '/assets/img/logo.svg') . '" width="150" alt="Unzer" style="margin-top:20px;"/>';
        echo '<div>' . wp_kses_post(wpautop($this->get_method_description())) . '</div>';
        echo '<div class="unzer-content-container">';
        echo '<h2><span class="unzer-dropdown-icon unzer-content-toggler" data-target=".unzer-payment-navigation" title="' . __('Select another Unzer payment method', 'unzer-payments') . '"></span> ' . esc_html($this->get_method_title());
        wc_back_link(__('Return to payments', 'woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout'));
        echo '</h2>';
        echo $this->getCompletePaymentMethodListHtml();
        echo '<table class="form-table">' . $this->generate_settings_html($this->get_form_fields(), false) . '</table>';
        if (method_exists($this, 'get_additional_options_html')) {
            echo $this->get_additional_options_html();
        }
        echo '</div>';
    }


    public function generate_key_check_html($key, $data)
    {
        $slug = $data['slug'];
        $gateway = esc_attr($this->id);
        $title = wp_kses_post($data['title']);
        $isInvalidText = esc_html(__('Keys are not valid', 'unzer-payments'));
        $isValidText = esc_html(__('Keys are valid', 'unzer-payments'));
        wp_enqueue_script('unzer_admin_key_management_js', UNZER_PLUGIN_URL . '/assets/js/admin_key_management.js');
        wp_enqueue_script('unzer_admin_webhook_management_js', UNZER_PLUGIN_URL . '/assets/js/admin_webhook_management.js');

        $webhookHtml = '';
        if ($this->get_option('private_key_' . $slug) && $this->get_option('public_key_' . $slug)) {
            ob_start();
            include UNZER_PLUGIN_PATH . 'html/admin/webhooks.php';
            $webhookHtml = ob_get_contents();
            ob_end_clean();
        }
        $ajaxUrl = WC()->api_request_url(AdminController::KEY_VALIDATION_ROUTE_SLUG);
        $returnHtml = <<<HTML
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
            
HTML;
        return $returnHtml;

    }

    protected function getCompletePaymentMethodListHtml(): string
    {
        $gateways = Main::getInstance()->getPaymentGateways();
        $html = '<ul class="unzer-payment-navigation" style="display: none;">';
        $entries = [];
        foreach ($gateways as $gatewayId => $gatewayClass) {
            /** @var AbstractGateway $gateway */
            $gateway = new $gatewayClass;
            $caption = str_replace('Unzer ', '', $gateway->method_title);
            $entries[strtolower($caption)] = '<li><a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $gatewayId) . '">' . $caption . '</a></li>';
        }
        ksort($entries);
        $html .= implode('', $entries) . '</ul>';
        return $html;
    }

    /**
     * @param WC_Order $order
     * @param string $unzerPaymentId
     * @return void
     * @throws WC_Data_Exception
     */
    protected function set_order_transaction_number($order, $unzerPaymentId)
    {
        $order->set_transaction_id($unzerPaymentId);
        $order->save();
    }

    /**
     * @param $order
     * @return void
     */
    protected function handleDateOfBirth($order, $dateOfBirth)
    {
        $birthDate = new DateTime($dateOfBirth);
        $maxDate = new DateTime('-18 years');
        $minDate = new DateTime('-120 years');
        if ($birthDate >= $maxDate) {
            throw new Exception(__('You have to be at least 18 years old for this payment method', 'unzer-payments'));
        }
        if ($birthDate < $minDate) {
            throw new Exception(__('Please check your date of birth', 'unzer-payments'));
        }
        $order->update_meta_data(Main::ORDER_META_KEY_DATE_OF_BIRTH, date('Y-m-d', strtotime($dateOfBirth)));
    }

    protected function addCheckoutAssets()
    {
        wp_enqueue_script('unzer_js', 'https://static.unzer.com/v1/unzer.js');
        wp_enqueue_style('unzer_css', 'https://static.unzer.com/v1/unzer.css');
        wp_register_script('woocommerce_unzer', UNZER_PLUGIN_URL . '/assets/js/checkout.js', ['unzer_js', 'jquery']);

        // for separate api keys
        $paylaterGateway = new Invoice();
        $installmentGateway = new Installment();

        wp_localize_script('woocommerce_unzer', 'unzer_parameters', [
            'publicKey' => $this->get_public_key(),
            'publicKey_eur_b2b' => $paylaterGateway->get_option('public_key_eur_b2b'),
            'publicKey_eur_b2c' => $paylaterGateway->get_option('public_key_eur_b2c'),
            'publicKey_chf_b2b' => $paylaterGateway->get_option('public_key_chf_b2b'),
            'publicKey_chf_b2c' => $paylaterGateway->get_option('public_key_chf_b2c'),
            'publicKey_installment_eur_b2c' => $installmentGateway->get_option('public_key_eur_b2c'),
            'publicKey_installment_chf_b2c' => $installmentGateway->get_option('public_key_chf_b2c'),
            'generic_error_message' => __('An error occurred while processing your payment. Please try another payment method.', 'unzer-payments'),
            'locale' => get_locale(),
            'store_name' => get_bloginfo('name'),
            'store_country' => strtoupper(substr(get_option('woocommerce_default_country'), 0, 2)),
            'apple_pay_merchant_validation_url' => WC()->api_request_url(CheckoutController::APPLE_PAY_MERCHANT_VALIDATION_ROUTE_SLUG),
            'currency' => get_woocommerce_currency(),
        ]);
        wp_localize_script('woocommerce_unzer', 'unzer_i18n', [
            'errorDob' => __('Please enter your date of birth', 'unzer-payments'),
            'errorCompanyType' => __('Please enter your company type', 'unzer-payments'),
        ]);
        wp_enqueue_script('woocommerce_unzer');

    }

    public static function addRiskDataToAuthorization(Authorization $authorization)
    {
        $riskData = new RiskData();
        $riskData->setThreatMetrixId(WC()->session->get('unzerThreatMetrixId'));
        WC()->session->set('unzerThreatMetrixId', '');
        if (is_user_logged_in()) {
            /** @var \WP_User $user */
            $user = wp_get_current_user();
            $date = $user->user_registered ? date('Ymd', strtotime($user->user_registered)) : null;
            $riskData->setRegistrationLevel(1);
            $riskData->setRegistrationDate($date);
        } else {
            $riskData->setRegistrationLevel(0);
        }
        $authorization->setRiskData($riskData);
    }
}