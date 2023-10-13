<?php

namespace UnzerPayments\Gateways;


use Exception;
use UnzerPayments\Main;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;

if (!defined('ABSPATH')) {
    exit;
}

class Installment extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_installment';
    public $method_title = 'Unzer Installment (Paylater)';
    public $method_description;
    public $title = 'Installment';
    public $description = '';
    public $id = self::GATEWAY_ID;
    public $plugin_id;
    public $supports = [
        'products',
        'refunds',
    ];
    public $allowedCurrencies = ['EUR', 'CHF'];
    public $allowedCountries = ['AT', 'CH', 'DE'];

    public function __construct()
    {
        parent::__construct();
        $this->method_title = __('Unzer Installment (Paylater)', 'unzer-payments');
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
        <div class="unzer-checkout-field-row form-row">
            <label><?php echo esc_html(__('Date of birth', 'unzer-payments')); ?></label>
            <input type="date" id="unzer-installment-dob" name="unzer-installment-dob" class="input-text" value="<?php echo esc_attr($this->getUserBirthDate()); ?>" max="<?php echo date('Y-m-d'); ?>"/>
        </div>
        <div id="unzer-installment-form" class="unzerUI form">
            <input type="hidden" id="unzer-installment-id" name="unzer-installment-id" value=""/>
            <input type="hidden" id="unzer-installment-amount" name="unzer-installment-amount" value="<?php echo WC()->cart->get_total('plain'); ?>"/>
            <div class="field">
                <div id="unzer-installment-fields">
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

        if (empty(WC()->session->get('unzerThreatMetrixId'))) {
            WC()->session->set('unzerThreatMetrixId', uniqid('unzer_tm_'));
        }
        wp_enqueue_script('unzer_threat_metrix_js', 'https://h.online-metrix.net/fp/tags.js?org_id=363t8kgq&session_id=' . WC()->session->get('unzerThreatMetrixId'));

        $this->addCheckoutAssets();
    }

    public function get_form_fields()
    {
        return apply_filters(
            'wc_unzer_settings',
            [

                'enabled' => [
                    'title' => __('Enable/Disable', 'unzer-payments'),
                    'label' => __('Enable Unzer Installment', 'unzer-payments'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'unzer-payments'),
                    'default' => __('Installment', 'unzer-payments'),
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
                'public_key_chf_b2c' => [
                    'title' => __('Public Key CHF/B2C', 'unzer-payments'),
                    'type' => 'text',
                    'desc' => '',
                    'default' => '',
                ],
                'private_key_chf_b2c' => [
                    'title' => __('Private Key CHF/B2C', 'unzer-payments'),
                    'type' => 'text',
                    'desc' => '',
                    'default' => '',
                ],
                'key_check_chf_b2c' => [
                    'title' => __('Key Check CHF/B2C', 'unzer-payments'),
                    'type' => 'key_check',
                    'slug' => 'chf_b2c',
                    'desc' => '',
                    'default' => '',
                ],
            ]
        );
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
        try {
            $paymentService = new PaymentService();
            $cancellation = $paymentService->performRefundOrReversalOnPayment($order_id, $amount);
            return $cancellation->isSuccess();
        } catch (\Exception $e) {
            $this->logger->error('refund error: ' . $e->getMessage(), ['orderId' => $order_id, 'amount' => $amount]);
            throw $e;
        }
    }


    /**
     * @param $order_id
     * @return array
     * @throws \WC_Data_Exception|Exception
     */
    public function process_payment($order_id)
    {
        $return = [
            'result' => 'success',
        ];
        $order = wc_get_order($order_id);
        $this->handleDateOfBirth($order, $_POST['unzer-installment-dob']);
        $_POST['unzer-dob'] = $_POST['unzer-installment-dob'];
        $order->save_meta_data();

        try {
            $authorization = (new PaymentService())->performAuthorizationForOrder(
                $order_id,
                $this,
                $_POST['unzer-installment-id'],
                function (Authorization $authorization) {
                    AbstractGateway::addRiskDataToAuthorization($authorization);
                }
            );
        } catch (UnzerApiException $e) {
            throw new Exception($e->getClientMessage() ?: $e->getMessage());
        }
        if (!($authorization->isPending() || $authorization->isSuccess())) {
            throw new Exception($authorization->getMessage()->getCustomer());
        }
        if ($authorization->isSuccess()) {
            $order = wc_get_order($order_id);
            $orderService = new OrderService();
            $orderService->setOrderAuthorized($order, $authorization->getPayment()->getId());
        } else {
            $this->set_order_transaction_number(wc_get_order($order_id), $authorization->getPayment()->getId());
        }
        $return['redirect'] = $this->get_return_url(wc_get_order($order_id));
        return $return;
    }

    public function get_payment_information(AbstractTransactionType $chargeOrAuthorization)
    {
        return sprintf(__("Payment details:<br /><br />"
            . "Holder: %s<br/>"
            . "IBAN: %s<br/>"
            . "BIC: %s<br/><br/>"
            . "<i>Descriptor: </i><br/>"
            . "%s", 'unzer-payments'),
            $chargeOrAuthorization->getHolder(),
            $chargeOrAuthorization->getIban(),
            $chargeOrAuthorization->getBic(),
            $chargeOrAuthorization->getDescriptor()
        );
    }
}
