<?php

namespace UnzerPayments\Gateways;


use Exception;
use UnzerPayments\Services\PaymentService;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class DirectDebitSecured extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_direct_debit_secured';
    public $method_title = 'Unzer Direct Debit Secured';
    public $method_description;
    public $title = 'Direct Debit';
    public $description = '';
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
        ?>
        <div id="unzer-direct-debit-secured-form" class="unzerUI form">
            <input type="hidden" id="unzer-direct-debit-secured-id" name="unzer-direct-debit-secured-id" value=""/>
            <div class="field">
                <div id="unzer-direct-debit-secured-iban" class="unzerInput">
                </div>
            </div>
        </div>
        <?php
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
                    'label' => __('Enable Unzer Direct Debit Payments', 'unzer-payments'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'unzer-payments'),
                    'default' => __('Direct Debit', 'unzer-payments'),
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
        $this->logger->debug('start payment for #' . $order_id . ' with ' . self::GATEWAY_ID);
        $return = [
            'result' => 'success',
        ];
        $charge = (new PaymentService())->performChargeForOrder($order_id, $this, $_POST['unzer-direct-debit-secured-id']);

        if (!($charge->isPending() || $charge->isSuccess())) {
            throw new Exception($charge->getMessage()->getCustomer());
        }
        if($charge->isSuccess()){
            $order = wc_get_order($order_id);
            $order->payment_complete($charge->getPayment()->getId());
        }else{
            $this->set_order_transaction_number(wc_get_order($order_id), $charge->getPayment()->getId());
        }
        $return['redirect'] = $this->get_return_url(wc_get_order($order_id));
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
