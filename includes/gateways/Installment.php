<?php

namespace UnzerPayments\Gateways;


use UnzerPayments\Services\PaymentService;

if (!defined('ABSPATH')) {
    exit;
}

class Installment extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_installment';
    public $method_title = 'Unzer Installment';
    public $method_description;
    public $title = 'Installment';
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
        <div id="unzer-installment-form" class="unzerUI form">
            <input type="hidden" id="unzer-installment-id" name="unzer-installment-id" value=""/>
            <div class="field">
                <div id="unzer-installment">
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
                    'label' => __('Enable Unzer Installment', UNZER_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', UNZER_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', UNZER_PLUGIN_NAME),
                    'default' => __('Installment', UNZER_PLUGIN_NAME),
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
        $return = [
            'result' => 'success',
        ];
        $charge = (new PaymentService())->performChargeForOrder($order_id, $this, \UnzerSDK\Resources\PaymentTypes\InstallmentSecured::class);

        if ($charge->getPayment()->getRedirectUrl()) {
            $return['redirect'] = $charge->getPayment()->getRedirectUrl();
        }
        return $return;
    }
}
