<?php

namespace UnzerPayments\Gateways;


use Exception;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Traits\SavePaymentInstrumentTrait;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class Card extends AbstractGateway
{
    use SavePaymentInstrumentTrait;

    const GATEWAY_ID = 'unzer_card';
    public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\Card::class;
    public $method_title = 'Unzer Credit Card';
    public $method_description;
    public $title = 'Credit Card';
    public $description = 'Use any credit card';
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
            echo wpautop(wptexturize($description));
        }
        $form = '
            <input type="hidden" id="unzer-card-id" name="unzer-card-id" value=""/>
        <div id="unzer-card-form" class="unzerUI form">
            
            <div class="field">
                <div id="unzer-card-form-holder" class="unzerInput">
                    <!-- Card holder UI Element is inserted here. -->
                </div>
            </div>
            <div class="field">
                <div id="unzer-card-form-number" class="unzerInput">
                    <!-- Card number UI Element will be inserted here. -->
                </div>
            </div>
            <div class="two fields">
                <div class="field ten wide">
                    <div id="unzer-card-form-expiry" class="unzerInput">
                        <!-- Card expiry date UI Element will be inserted here. -->
                    </div>
                </div>
                <div class="field six wide">
                    <div id="unzer-card-form-cvc" class="unzerInput">
                        <!-- Card CVC UI Element will be inserted here. -->
                    </div>
                </div>
            </div>
        </div>
        ';
        echo $this->renderSavedInstrumentsSelection($form);
    }

    public function payment_scripts()
    {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        if (!$this->is_enabled()) {
            return;
        }
        $this->addCheckoutAssets();
    }

    public function get_form_fields()
    {
        return apply_filters(
            'wc_unzer_settings',
            [

                'enabled' => [
                    'title' => __('Enable/Disable', 'unzer-payments'),
                    'label' => __('Enable Unzer Card Payments', 'unzer-payments'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'unzer-payments'),
                    'default' => __('Credit Card', 'unzer-payments'),
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
                AbstractGateway::SETTINGS_KEY_SAVE_INSTRUMENTS => [
                    'title' => __('Save card for registered customers', 'unzer-payments'),
                    'label' => __('&nbsp;', 'unzer-payments'),
                    'type' => 'select',
                    'description' => '',
                    'default' => 'no',
                    'options' => [
                        'no' => __('No', 'unzer-payments'),
                        'yes' => __('Yes', 'unzer-payments'),
                    ],
                ],
                /*
                'capture_trigger_order_status' => [
                    'title' => __('Capture status', 'unzer-payments'),
                    'label' => '',
                    'type' => 'select',
                    'description' => __('When this status is assigned to an order, the funds will be captured', 'unzer-payments'),
                    'options' => array_merge(['' => ''], wc_get_order_statuses()),
                ],
                */
            ]
        );
    }

    public function process_payment($order_id)
    {
        $this->logger->debug('start payment for #' . $order_id . ' with ' . self::GATEWAY_ID);
        $return = [
            'result' => 'success',
        ];

        // for saved payment instruments
        $cardId = !empty($_POST[static::GATEWAY_ID . '_payment_instrument']) ? $_POST[static::GATEWAY_ID . '_payment_instrument'] : $_POST['unzer-card-id'];
        WC()->session->set('save_payment_instrument', !empty($_POST['unzer-save-payment-instrument']));

        if ($this->get_option('transaction_type') === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE) {
            $transaction = (new PaymentService())->performAuthorizationForOrder($order_id, $this, $cardId);
        } else {
            $transaction = (new PaymentService())->performChargeForOrder($order_id, $this, $cardId);
        }

        if ($transaction->getPayment()->getRedirectUrl()) {
            $return['redirect'] = $transaction->getPayment()->getRedirectUrl();
        } elseif ($transaction->isSuccess()) {
            $return['redirect'] = $this->get_confirm_url();
        }
        return $return;
    }


    /**
     * @param WC_Order $order
     * @param float $amount
     * @throws Exception
     */
    public function capture(WC_Order $order, $amount = null)
    {

    }
}
