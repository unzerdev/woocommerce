<?php

namespace UnzerPayments\Gateways;


use Exception;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Traits\SavePaymentInstrumentTrait;
use UnzerSDK\Constants\RecurrenceTypes;
use UnzerSDK\Resources\PaymentTypes\SepaDirectDebit;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Charge;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class DirectDebit extends AbstractGateway
{
    use SavePaymentInstrumentTrait;

    public $paymentTypeResource = SepaDirectDebit::class;
    const GATEWAY_ID = 'unzer_direct_debit';
    public $method_title = 'Unzer Direct Debit';
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

        $form = '
        <div id="unzer-direct-debit-form" class="unzerUI form">
            <input type="hidden" id="unzer-direct-debit-id" name="unzer-direct-debit-id" value=""/>
            <div class="field">
                <div id="unzer-direct-debit-iban" class="unzerInput">
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
                AbstractGateway::SETTINGS_KEY_SAVE_INSTRUMENTS => [
                    'title' => __('Save bank details for registered customers', 'unzer-payments'),
                    'label' => __('&nbsp;', 'unzer-payments'),
                    'type' => 'select',
                    'description' => '',
                    'default' => 'no',
                    'options' => [
                        'no' => __('No', 'unzer-payments'),
                        'yes' => __('Yes', 'unzer-payments'),
                    ],
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

        $savePaymentInstrument = !empty($_POST['unzer-save-payment-instrument-' . $this->id]);
        WC()->session->set('save_payment_instrument', $savePaymentInstrument);
        $transactionEditorFunction = null;

        $directDebitId = !empty($_POST[static::GATEWAY_ID . '_payment_instrument']) ? $_POST[static::GATEWAY_ID . '_payment_instrument'] : $_POST['unzer-direct-debit-id'];
        $charge = (new PaymentService())->performChargeForOrder($order_id, $this, $directDebitId, $transactionEditorFunction);

        if (!($charge->isPending() || $charge->isSuccess())) {
            throw new Exception($charge->getMessage()->getCustomer());
        }
        if ($charge->isSuccess()) {
            $order = wc_get_order($order_id);
            $order->payment_complete($charge->getPayment()->getId());
        } else {
            $this->set_order_transaction_number(wc_get_order($order_id), $charge->getPayment()->getId());
        }
        WC()->session->set('unzer_confirm_order_id', $order_id);
        $return['redirect'] = $this->get_confirm_url();
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
