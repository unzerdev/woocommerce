<?php

namespace UnzerPayments\Gateways;


use UnzerPayments\Services\PaymentService;
use UnzerPayments\Traits\SavePaymentInstrumentTrait;

if (!defined('ABSPATH')) {
    exit;
}

class Paypal extends AbstractGateway
{
    use SavePaymentInstrumentTrait;

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

    public function has_fields()
    {
        return ($this->isSaveInstruments() && !empty($this->getSavedPaymentInstruments()));
    }

    public function payment_fields()
    {
        $description = $this->get_description();
        if ($description) {
            echo esc_html(wpautop(wptexturize($description)));
        }
        echo $this->renderSavedInstrumentsSelection('');
    }

    public function get_form_fields()
    {
        return apply_filters(
            'wc_unzer_settings',
            [

                'enabled' => [
                    'title' => __('Enable/Disable', 'unzer-payments'),
                    'label' => __('Enable Unzer PayPal', 'unzer-payments'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'unzer-payments'),
                    'default' => __('PayPal', 'unzer-payments'),
                ],
                'description' => [
                    'title' => __('Description', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.', 'unzer-payments'),
                    'default' => '',
                ],
                'transaction_type' => [
                    'title' => __('Charge or Authorize', 'unzer-payments'),
                    'label' => '',
                    'type' => 'select',
                    'description' => __('Choose "authorize", if you you want to charge the shopper at a later point of time', 'unzer-payments'),
                    'options' => [
                        AbstractGateway::TRANSACTION_TYPE_AUTHORIZE => __('authorize', 'unzer-payments'),
                        AbstractGateway::TRANSACTION_TYPE_CHARGE => __('charge', 'unzer-payments'),
                    ],
                    'default' => 'charge',
                ],
//                AbstractGateway::SETTINGS_KEY_SAVE_INSTRUMENTS => [
//                    'title' => __('Save PayPal account for registered customers', 'unzer-payments'),
//                    'label' => __('&nbsp;', 'unzer-payments'),
//                    'type' => 'checkbox',
//                    'description' => '',
//                    'default' => 'no',
//                ],
            ]
        );
    }

    public function process_payment($order_id)
    {
        $return = [
            'result' => 'success',
        ];

        $paymentMean = empty($_POST['unzer_paypal_payment_instrument'])?\UnzerSDK\Resources\PaymentTypes\Paypal::class:$_POST['unzer_paypal_payment_instrument'];

        if ($this->get_option('transaction_type') === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE) {
            $transaction = (new PaymentService())->performAuthorizationForOrder($order_id, $this, $paymentMean);
        } else {
            $transaction = (new PaymentService())->performChargeForOrder($order_id, $this, $paymentMean);
        }

        if ($transaction->getPayment()->getRedirectUrl()) {
            $return['redirect'] = $transaction->getPayment()->getRedirectUrl();
        }elseif ($transaction->isSuccess()){
            $return['redirect'] = $this->get_confirm_url();
        }
        return $return;
    }
}
