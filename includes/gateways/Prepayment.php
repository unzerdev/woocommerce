<?php

namespace UnzerPayments\Gateways;


use Exception;
use UnzerPayments\Services\OrderService;
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
                    'title' => __('Enable/Disable', 'unzer-payments'),
                    'label' => __('Enable Unzer Prepayment', 'unzer-payments'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'unzer-payments'),
                    'default' => __('Prepayment', 'unzer-payments'),
                ],
                'description' => [
                    'title' => __('Description', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.', 'unzer-payments'),
                    'default' => '',
                ],
                'order_status' => [
                    'title' => __('Order status', 'unzer-payments'),
                    'label' => '',
                    'type' => 'select',
                    'description' => __('This status is assigned to all orders created with this payment method', 'unzer-payments'),
                    'options' => array_merge(['' => __('[Use WooC default status]', 'unzer-payments')], wc_get_order_statuses()),
                    'default' => OrderService::ORDER_STATUS_WAITING_FOR_PAYMENT,
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

        $order = wc_get_order($order_id);
        $order->set_transaction_id($charge->getPayment()->getId());
        if ($status = $this->get_option('order_status')) {
            $order->set_status($status);
        }
        $order->save();
        $return['redirect'] = $this->get_return_url($order);
        return $return;
    }

    public function get_payment_information(Charge $charge)
    {
        return sprintf(__("Please transfer the amount of %s to the following account:<br /><br />"
            . "Holder: %s<br/>"
            . "IBAN: %s<br/>"
            . "BIC: %s<br/><br/>"
            . "<i>Please use only this identification number as the descriptor: </i><br/>"
            . "%s", 'unzer-payments'),
            wc_price($charge->getAmount(), ['currency' => $charge->getCurrency()]),
            $charge->getHolder(),
            $charge->getIban(),
            $charge->getBic(),
            $charge->getDescriptor()
        );
    }
}
