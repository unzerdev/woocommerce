<?php

namespace UnzerPayments\Gateways;


if (!defined('ABSPATH')) {
    exit;
}

class PostFinanceCard extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_postfinance_card';
    public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\PostFinanceCard::class;
    public $method_title = 'Unzer Post Finance Card';
    public $method_description;
    public $title = 'Post Finance Card';
    public $description = '';
    public $id = self::GATEWAY_ID;
    public $plugin_id;
    public $supports = [
        'products',
        'refunds',
    ];
    public $allowedCurrencies = ['CHF'];

    public function get_form_fields()
    {
        return apply_filters(
            'wc_unzer_settings',
            [

                'enabled' => [
                    'title' => __('Enable/Disable', 'unzer-payments'),
                    'label' => __('Enable Unzer Post Finance Card', 'unzer-payments'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'unzer-payments'),
                    'default' => __('Post Finance Card', 'unzer-payments'),
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
