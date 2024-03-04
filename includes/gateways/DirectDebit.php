<?php

namespace UnzerPayments\Gateways;


use Exception;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Traits\SavePaymentInstrumentTrait;
use UnzerSDK\Resources\PaymentTypes\SepaDirectDebit;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class DirectDebit extends AbstractGateway
{
    use SavePaymentInstrumentTrait;

    public $paymentTypeResource = SepaDirectDebit::class;
    const GATEWAY_ID = 'unzer_direct_debit';
    public $method_title = 'Unzer SEPA Direct Debit';
    public $method_description;
    public $title = 'SEPA Direct Debit';
    public $description = '';
    public $id = self::GATEWAY_ID;
    public $plugin_id;
    public $supports = [
        'products',
        'refunds',
    ];
    protected string $defaultMandateText;

    public function __construct()
    {
        $this->defaultMandateText = __('By signing this mandate form, you authorize %merchant% to send instructions to your bank to debit your account and your bank to debit your account in accordance with the instructions from %merchant%.

Note: As part of your rights, you are entitled to a refund from your bank under the terms and conditions of your agreement with your bank. A refund must be claimed within 8 weeks starting from the date on which your account was debited. Your rights regarding this SEPA mandate are explained in a statement that you can obtain from your bank.

In case of refusal or rejection of direct debit payment I instruct my bank irrevocably to inform %merchant% or any third party upon request about my name, address and date of birth.', 'unzer-payments');
        parent::__construct();
        $this->method_title = __('Unzer SEPA Direct Debit', 'unzer-payments');
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
        <div id="unzer-direct-debit-sepa-mandate-container">
            <label>
                <input type="checkbox" name="unzer-accept-sepa-mandate" value="1" id="unzer-accept-sepa-mandate-checkbox" />
                <span class="label">' .
            __('I accept the SEPA mandate', 'unzer-payments') .
            ' <a href="#" onclick="document.getElementById(\'unzer-direct-debit-sepa-mandate-complete\').style.display = \'block\'; this.remove(); return false;">' .
            __('(read more)', 'unzer-payments') .
            '</a>' .
            '<div id="unzer-direct-debit-sepa-mandate-complete" style="display: none;">' .
            nl2br(
                str_replace('%merchant%', get_bloginfo('name'), $this->get_option('sepa_mandate') ?: $this->defaultMandateText)
            ).
            '</div>
                </span>
            </label>
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
                    'label' => __('Enable Unzer SEPA Direct Debit Payments', 'unzer-payments'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'unzer-payments'),
                    'default' => __('SEPA Direct Debit', 'unzer-payments'),
                ],
                'description' => [
                    'title' => __('Description', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.', 'unzer-payments'),
                    'default' => '',
                ],
                'sepa_mandate' => [
                    'title' => __('Alternative SEPA mandate description', 'unzer-payments'),
                    'type' => 'textarea',
                    'description' => __('Leave empty to display the default text', 'unzer-payments'),
                    'placeholder' => $this->defaultMandateText,
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
