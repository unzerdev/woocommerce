<?php

namespace UnzerPayments\Gateways;


use UnzerPayments\Services\PaymentService;

if (!defined('ABSPATH')) {
    exit;
}

class Paypal extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_paypal';
    public $method_title = 'Unzer PayPal';
    public $method_description;
    public $title = 'PayPal';
    public $description = '';
    public $id = self::GATEWAY_ID;
    public $plugin_id;
    public $supports = [
        'products',
        'refunds',
    ];

    public function get_form_fields()
    {
        return apply_filters(
            'wc_unzer_settings',
            [

                'enabled' => [
                    'title' => __('Enable/Disable', UNZER_PLUGIN_NAME),
                    'label' => __('Enable Unzer PayPal', UNZER_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', UNZER_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', UNZER_PLUGIN_NAME),
                    'default' => __('PayPal', UNZER_PLUGIN_NAME),
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
            ]
        );
    }

    public function process_payment($order_id)
    {
        $return = [
            'result' => 'success',
        ];

        if ($this->get_option('transaction_type') === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE) {
            $transaction = (new PaymentService())->performAuthorizationForOrder($order_id, $this, \UnzerSDK\Resources\PaymentTypes\Paypal::class);
        } else {
            $transaction = (new PaymentService())->performChargeForOrder($order_id, $this, \UnzerSDK\Resources\PaymentTypes\Paypal::class);
        }

        if ($transaction->getPayment()->getRedirectUrl()) {
            $return['redirect'] = $transaction->getPayment()->getRedirectUrl();
        }
        return $return;
    }
}
