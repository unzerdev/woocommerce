<?php

namespace UnzerPayments\Gateways;


use UnzerPayments\Services\PaymentService;

if (!defined('ABSPATH')) {
    exit;
}

class Eps extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_eps';
    public $method_title = 'Unzer EPS';
    public $method_description;
    public $title = 'EPS';
    public $description = '';
    public $id = self::GATEWAY_ID;
    public $plugin_id;
    public $supports = [
        'products',
        'refunds',
    ];

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
        <div id="unzer-eps-form" class="unzerUI form" novalidate>
            <input type="hidden" id="unzer-eps-id" name="unzer-eps-id" value=""/>
            <div id="unzer-eps" class="field"></div>
        </div>
        <?php
    }

    public function get_form_fields()
    {
        return apply_filters(
            'wc_unzer_settings',
            [

                'enabled' => [
                    'title' => __('Enable/Disable', 'unzer-payments'),
                    'label' => __('Enable Unzer EPS', 'unzer-payments'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'unzer-payments'),
                    'default' => __('EPS', 'unzer-payments'),
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

    public function process_payment($order_id)
    {
        if(empty($_POST['unzer-eps-id'])){
            throw new \Exception(__('Please select your bank'));
        }
        $this->logger->debug('start payment for #' . $order_id . ' with ' . self::GATEWAY_ID);
        $return = [
            'result' => 'success',
        ];
        $transaction = (new PaymentService())->performChargeForOrder($order_id, $this, $_POST['unzer-eps-id']);
        if ($transaction->getPayment()->getRedirectUrl()) {
            $return['redirect'] = $transaction->getPayment()->getRedirectUrl();
        }
        return $return;
    }
}
