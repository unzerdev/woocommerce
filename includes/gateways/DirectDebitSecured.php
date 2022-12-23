<?php

namespace UnzerPayments\Gateways;


use Exception;
use UnzerPayments\Services\PaymentService;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class DirectDebitSecured extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_direct_debit_secured';
    public $method_title = 'Unzer Direct Debit Secured';
    public $method_description;
    public $title = 'Direct Debit';
    public $description = '';
    public $id = self::GATEWAY_ID;
    public $plugin_id;
    public $supports = [
        'products',
        'refunds',
    ];

    public function __construct()
    {
        parent::__construct();
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
    }

    public function has_fields()
    {
        return true;
    }


    public function payment_fields()
    {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description)); // @codingStandardsIgnoreLine.
        }
        ?>
        <div id="unzer-direct-debit-secured-form" class="unzerUI form">
            <input type="hidden" id="unzer-direct-debit-secured-id" name="unzer-direct-debit-secured-id" value=""/>
            <div class="field">
                <div id="unzer-direct-debit-secured-iban" class="unzerInput">
                </div>
            </div>
        </div>
        <?php
    }

    public function payment_scripts()
    {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        if (!$this->is_enabled()) {
            return;
        }

        wp_enqueue_script('unzer_js', 'https://static.unzer.com/v1/unzer.js');
        wp_enqueue_style('unzer_css', 'https://static.unzer.com/v1/unzer.css');
        wp_register_script('woocommerce_unzer', UNZER_PLUGIN_URL . '/assets/js/checkout.js', ['unzer_js', 'jquery']);

        // in most payment processors you have to use PUBLIC KEY to obtain a token
        wp_localize_script('woocommerce_unzer', 'unzer_parameters', [
            'publicKey' => $this->get_public_key(),
        ]);
        wp_enqueue_script('woocommerce_unzer');

    }

    public function get_form_fields()
    {
        return apply_filters(
            'wc_unzer_settings',
            [

                'enabled' => [
                    'title' => __('Enable/Disable', UNZER_PLUGIN_NAME),
                    'label' => __('Enable Unzer Direct Debit Payments', UNZER_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', UNZER_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', UNZER_PLUGIN_NAME),
                    'default' => __('Direct Debit', UNZER_PLUGIN_NAME),
                ],
                'description' => [
                    'title' => __('Description', UNZER_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.', UNZER_PLUGIN_NAME),
                    'default' => '',
                ],
            ]
        );
    }

    public function process_payment($order_id)
    {
        $this->logger->debug('start payment for #' . $order_id . ' with ' . self::GATEWAY_ID);
        $return = [
            'result' => 'success',
        ];
        $charge = (new PaymentService())->performChargeForOrder($order_id, $this, $_POST['unzer-direct-debit-secured-id']);

        if (!($charge->isPending() || $charge->isSuccess())) {
            throw new Exception($charge->getMessage()->getCustomer());
        }
        if($charge->isSuccess()){
            $order = wc_get_order($order_id);
            $order->payment_complete($charge->getPayment()->getId());
        }else{
            $this->set_order_transaction_number(wc_get_order($order_id), $charge->getPayment()->getId());
        }
        $return['redirect'] = $this->get_return_url(wc_get_order($order_id));
        return $return;
    }


    /**
     * @param WC_Order $order
     * @param float $amount
     * @throws Exception
     */
    public function capture(WC_Order $order, $amount = null)
    {

    }
}
