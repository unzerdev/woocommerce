<?php

namespace UnzerPayments\Gateways;


use UnzerPayments\Services\PaymentService;

if (!defined('ABSPATH')) {
    exit;
}

class Bancontact extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_bancontact';
    public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\Bancontact::class;
    public $method_title = 'Unzer Bancontact';
    public $method_description;
    public $title = 'Bancontact';
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
                    'title' => __('Enable/Disable', 'unzer-payments'),
                    'label' => __('Enable Unzer Bancontact', 'unzer-payments'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'unzer-payments'),
                    'default' => __('Bancontact', 'unzer-payments'),
                ],
                'description' => [
                    'title' => __('Description', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.', 'unzer-payments'),
                    'default' => '',
                ],
            ]
        );
    }
}
