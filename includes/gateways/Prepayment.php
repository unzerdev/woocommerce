<?php

namespace UnzerPayments\Gateways;


use Exception;
use UnzerPayments\Services\PaymentService;
use UnzerSDK\Resources\TransactionTypes\Charge;

if (!defined('ABSPATH')) {
    exit;
}

class Prepayment extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_prepayment';
    public $method_title = 'Unzer Prepayment';
    public $method_description;
    public $title = 'Prepayment';
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
                    'label' => __('Enable Unzer Prepayment', UNZER_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', UNZER_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', UNZER_PLUGIN_NAME),
                    'default' => __('Prepayment', UNZER_PLUGIN_NAME),
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
        $charge = (new PaymentService())->performChargeForOrder($order_id, $this, \UnzerSDK\Resources\PaymentTypes\Prepayment::class);

        if (!$charge->getPayment()->isPending()) {
            throw new Exception($charge->getMessage()->getCustomer());
        }
        $this->set_order_transaction_number(wc_get_order($order_id), $charge->getPayment()->getId());
        $return['redirect'] = $this->get_return_url(wc_get_order($order_id));
        return $return;
    }

    public static function get_payment_information(Charge $charge){
        return sprintf(__("Please transfer the amount of %s to the following account:<br /><br />"
            . "Holder: %s<br/>"
            . "IBAN: %s<br/>"
            . "BIC: %s<br/><br/>"
            . "<i>Please use only this identification number as the descriptor: </i><br/>"
            . "%s"),
            wc_price($charge->getAmount(), ['currency' => $charge->getCurrency()]),
            $charge->getHolder(),
            $charge->getIban(),
            $charge->getBic(),
            $charge->getDescriptor()
        );
    }
}
