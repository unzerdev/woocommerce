<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Main;
use UnzerPayments\Services\LogService;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
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
    /**
     * @var LogService
     */
    protected $logger;

    public function __construct()
    {
        $this->logger = new LogService();
        $this->plugin_id = UNZER_PLUGIN_NAME;
        $this->init_settings();
        if ($this->get_public_key() && $this->get_private_key()) {
            $this->method_description = sprintf(__('The Unzer API settings can be adjusted <a href="%s">here</a>', UNZER_PLUGIN_NAME), admin_url('admin.php?page=wc-settings&tab=checkout&section=unzer_general'));
        } else {
            $this->method_description = '<div class="error" style="padding:10px;">' . sprintf(__('To start using Unzer payment methods, please enter your credentials first. <br><a href="%s" class="button-primary">API settings</a>', UNZER_PLUGIN_NAME), admin_url('admin.php?page=wc-settings&tab=checkout&section=unzer_general')) . '</div>';
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

    public function init_settings()
    {
        parent::init_settings();
        if (!$this->get_private_key() || !$this->get_public_key()) {
            $this->enabled = 'no';
        }
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        try {
            $paymentService = new PaymentService();
            $cancellation = $paymentService->performRefundOrReversal($order_id, $this, $amount);
            return $cancellation->isSuccess();
        }catch (\Exception $e){
            $this->logger->error('refund error: '.$e->getMessage(), ['orderId'=>$order_id, 'amount'=>$amount]);
            throw $e;
        }
    }

    public function get_confirm_url()
    {
        return WC()->api_request_url(static::CONFIRMATION_ROUTE_SLUG);
    }

    public function admin_options() {
        echo '<link rel="stylesheet" href="'.UNZER_PLUGIN_URL.'/assets/css/admin.css" />';
        echo '<script src="'.UNZER_PLUGIN_URL.'/assets/js/admin.js"></script>';
        echo '<img src="'.UNZER_PLUGIN_URL.'/assets/img/logo.svg" width="150" alt="Unzer" style="margin-top:20px;"/>';
        echo '<div>'.wp_kses_post( wpautop( $this->get_method_description() ) ).'</div>';
        echo '<div class="unzer-content-container">';
        echo '<h2><span class="unzer-dropdown-icon unzer-content-toggler" data-target=".unzer-payment-navigation" title="'.__('Select another Unzer payment method', UNZER_PLUGIN_NAME).'"></span> ' . esc_html( $this->get_method_title() );
        wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
        echo '</h2>';
        echo $this->getCompletePaymentMethodListHtml();
        echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>'; // WPCS: XSS ok.
        echo '</div>';
    }

    protected function getCompletePaymentMethodListHtml()
    {
        $gateways = Main::getInstance()->getPaymentGateways();
        $html = '<ul class="unzer-payment-navigation" style="display: none;">';
        $entries = [];
        foreach($gateways as $gatewayId=>$gatewayClass){
            /** @var AbstractGateway $gateway */
            $gateway = new $gatewayClass;
            $caption = str_replace('Unzer ', '', $gateway->method_title);
            $entries[strtolower($caption)] = '<li><a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section='.$gatewayId).'">'.$caption.'</a></li>';
        }
        ksort($entries);
        $html .= implode('', $entries).'</ul>';
        return $html;
    }

    /**
     * @param WC_Order $order
     * @param string $unzerPaymentId
     * @return void
     */
    protected function set_order_transaction_number($order, $unzerPaymentId){
        $order->set_transaction_id($unzerPaymentId);
        $order->save();
    }
}