<?php

namespace UnzerPayments\Gateways;


if (!defined('ABSPATH')) {
    exit;
}

class PostFinanceEfinance extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_postfinance_efinance';
    public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\PostFinanceEfinance::class;
    public $method_title = 'Unzer Post Finance eFinance';
    public $method_description;
    public $title = 'Post Finance eFinance';
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
                    'label' => __('Enable Unzer Post Finance eFinance', 'unzer-payments'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'unzer-payments'),
                    'default' => __('Post Finance eFinance', 'unzer-payments'),
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
