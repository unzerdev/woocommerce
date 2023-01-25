<?php

namespace UnzerPayments\Gateways;


use UnzerPayments\Services\PaymentService;

if (!defined('ABSPATH')) {
    exit;
}

class Giropay extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_giropay';
    public $method_title = 'Unzer Giropay';
    public $method_description;
    public $title = 'Giropay';
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
                    'label' => __('Enable Unzer Giropay', UNZER_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', UNZER_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', UNZER_PLUGIN_NAME),
                    'default' => __('Giropay', UNZER_PLUGIN_NAME),
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
        $charge = (new PaymentService())->performChargeForOrder($order_id, $this, \UnzerSDK\Resources\PaymentTypes\Giropay::class);

        if ($charge->getPayment()->getRedirectUrl()) {
            $return['redirect'] = $charge->getPayment()->getRedirectUrl();
        }
        return $return;
    }
}
