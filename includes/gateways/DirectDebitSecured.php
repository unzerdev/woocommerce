<?php

namespace UnzerPayments\Gateways;


use Exception;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class DirectDebitSecured extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_direct_debit_secured';
    public $method_title = 'Unzer Direct Debit';
    public $method_description;
    public $title = 'Direct Debit';
    public $description = '';
    public $id = self::GATEWAY_ID;
    public $allowedCurrencies = ['EUR'];
    public $plugin_id;
    public $supports = [
        'products',
        'refunds',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->method_title = __('Unzer Direct Debit', 'unzer-payments');
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
        <input type="hidden" id="unzer-direct-debit-secured-id" name="unzer-direct-debit-secured-id" value=""/>

        <div class="unzer-checkout-field-row form-row">
            <label><?php echo esc_html(__('Date of birth', 'unzer-payments')); ?></label>
            <input type="date" id="unzer-direct-debit-secured-dob" name="unzer-direct-debit-secured-dob" class="input-text" value="<?php echo esc_attr($this->getUserBirthDate()); ?>" max="<?php echo date('Y-m-d'); ?>"/>
        </div>
        <div id="unzer-direct-debit-secured-form" class="unzerUI form"></div>
        <?php
    }

    public function payment_scripts()
    {
        $this->threatmetrix_payment_scripts();
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
                'public_key_eur_b2c' => [
                    'title' => __('Public Key EUR/B2C', 'unzer-payments'),
                    'type' => 'text',
                    'desc' => '',
                    'default' => '',
                ],
                'private_key_eur_b2c' => [
                    'title' => __('Private Key EUR/B2C', 'unzer-payments'),
                    'type' => 'text',
                    'desc' => '',
                    'default' => '',
                ],
                'key_check_eur_b2c' => [
                    'title' => __('Key Check EUR/B2C', 'unzer-payments'),
                    'type' => 'key_check',
                    'slug' => 'eur_b2c',
                    'desc' => '',
                    'default' => '',
                ],
            ]
        );
    }

    public function process_payment($order_id)
    {
        $this->logger->debug('start payment for #' . $order_id . ' with ' . self::GATEWAY_ID);
        $order = wc_get_order($order_id);
        $return = [
            'result' => 'success',
        ];
        $this->handleDateOfBirth($order, $_POST['unzer-direct-debit-secured-dob']);
        $_POST['unzer-dob'] = $_POST['unzer-direct-debit-secured-dob'];
        $paymentMeanId = $_POST['unzer-direct-debit-secured-id'];

        $authorization = (new PaymentService())->performAuthorizationForOrder(
            $order_id,
            $this,
            $paymentMeanId,
            function (Authorization $authorization) {
                AbstractGateway::addRiskDataToAuthorization($authorization);
            }
        );

        if ($authorization->getPayment()->getRedirectUrl()) {
            $return['redirect'] = $authorization->getPayment()->getRedirectUrl();
        } elseif ($authorization->isSuccess()) {
            try {
                //this is repeated in confirmAction, but we need to make sure, that the order is updated if anything goes wrong
                (new OrderService())->processPaymentStatus($authorization, $order);
            } catch (Exception $e) {
                //silent catch
            }
            WC()->session->set('unzer_confirm_order_id', $order_id);
            $return['redirect'] = $this->get_confirm_url();
        }
        AbstractGateway::removeRiskDataFromSession();
        return $return;
    }

    /**
     * @param $order_id
     * @param $amount
     * @param $reason
     * @return bool
     * @throws UnzerApiException
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return $this->process_refund_on_payment($order_id, $amount, $reason);
    }


    /**
     * @param WC_Order $order
     * @param float $amount
     * @throws Exception
     */
    public function capture(WC_Order $order, $amount = null)
    {

    }

    public function get_payment_information(AbstractTransactionType $chargeOrAuthorization)
    {
        return sprintf(__("An amount of %s will be deducted from your account using the descriptor '%s' according to the SEPA mandate", 'unzer-payments'),
            wc_price($chargeOrAuthorization->getAmount(), ['currency' => $chargeOrAuthorization->getCurrency()]),
            $chargeOrAuthorization->getDescriptor()
        );
    }
}
