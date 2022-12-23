<?php

namespace UnzerPayments\Gateways;


use Exception;
use UnzerPayments\Services\PaymentService;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class Card extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_card';
    public $method_title = 'Unzer Credit Card';
    public $method_description;
    public $title = 'Credit Card';
    public $description = 'Use any credit card';
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
            echo wpautop(wptexturize($description));
        }
        ?>
        <div id="unzer-card-form" class="unzerUI form">
            <input type="hidden" id="unzer-card-id" name="unzer-card-id" value=""/>
            <div class="field">
                <div id="unzer-card-form-holder" class="unzerInput">
                    <!-- Card holder UI Element is inserted here. -->
                </div>
            </div>
            <div class="field">
                <div id="unzer-card-form-number" class="unzerInput">
                    <!-- Card number UI Element will be inserted here. -->
                </div>
            </div>
            <div class="two fields">
                <div class="field ten wide">
                    <div id="unzer-card-form-expiry" class="unzerInput">
                        <!-- Card expiry date UI Element will be inserted here. -->
                    </div>
                </div>
                <div class="field six wide">
                    <div id="unzer-card-form-cvc" class="unzerInput">
                        <!-- Card CVC UI Element will be inserted here. -->
                    </div>
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
                    'label' => __('Enable Unzer Card Payments', UNZER_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', UNZER_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', UNZER_PLUGIN_NAME),
                    'default' => __('Credit Card', UNZER_PLUGIN_NAME),
                ],
                'description' => [
                    'title' => __('Description', UNZER_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.', UNZER_PLUGIN_NAME),
                    'default' => '',
                ],
                'transaction_type' => [
                    'title' => __('Charge or Authorize', UNZER_PLUGIN_NAME),
                    'label' => '',
                    'type' => 'select',
                    'description' => __('Choose "authorize", if you you want to charge the shopper at a later point of time', UNZER_PLUGIN_NAME),
                    'options' => [
                        AbstractGateway::TRANSACTION_TYPE_AUTHORIZE => __('authorize', UNZER_PLUGIN_NAME),
                        AbstractGateway::TRANSACTION_TYPE_CHARGE => __('charge', UNZER_PLUGIN_NAME),
                    ],
                    'default' => 'charge',
                ],
                /*
                'capture_trigger_order_status' => [
                    'title' => __('Capture status', UNZER_PLUGIN_NAME),
                    'label' => '',
                    'type' => 'select',
                    'description' => __('When this status is assigned to an order, the funds will be captured', UNZER_PLUGIN_NAME),
                    'options' => array_merge(['' => ''], wc_get_order_statuses()),
                ],
                */
            ]
        );
    }

    public function process_payment($order_id)
    {
        $this->logger->debug('start payment for #' . $order_id . ' with ' . self::GATEWAY_ID);
        $return = [
            'result' => 'success',
        ];
        if ($this->get_option('transaction_type') === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE) {
            $transaction = (new PaymentService())->performAuthorizationForOrder($order_id, $this, $_POST['unzer-card-id']);
        } else {
            $transaction = (new PaymentService())->performChargeForOrder($order_id, $this, $_POST['unzer-card-id']);
        }
        if ($transaction->getPayment()->getRedirectUrl()) {
            $return['redirect'] = $transaction->getPayment()->getRedirectUrl();
        }
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
