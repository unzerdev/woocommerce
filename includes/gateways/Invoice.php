<?php

namespace UnzerPayments\Gateways;


use DateTime;
use Exception;
use UnzerPayments\Main;
use UnzerPayments\Services\PaymentService;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\EmbeddedResources\RiskData;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;

if (!defined('ABSPATH')) {
    exit;
}

class Invoice extends AbstractGateway
{
    const GATEWAY_ID = 'unzer_invoice';
    public $method_title = 'Unzer Invoice (Paylater)';
    public $method_description;
    public $title = 'Invoice';
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
        $this->method_title = __('Unzer Invoice (Paylater)', 'unzer-payments');
    }

    public function get_form_fields()
    {
        return apply_filters(
            'wc_unzer_settings',
            [

                'enabled' => [
                    'title' => __('Enable/Disable', 'unzer-payments'),
                    'label' => __('Enable Unzer Invoice (Paylater)', 'unzer-payments'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', 'unzer-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'unzer-payments'),
                    'default' => __('Unzer Invoice (Paylater)', 'unzer-payments'),
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
            <input type="date" id="unzer-invoice-dob" name="unzer-invoice-dob" class="input-text" value="" max="<?php echo date('Y-m-d'); ?>"/>
        </div>
        <div class="unzer-checkout-field-row form-row b2b" id="unzer-invoice-company-type-container">
            <label><?php echo esc_html(__('Type of company', 'unzer-payments')); ?></label>
            <select name="unzer-invoice-company-type" id="unzer-invoice-company-type" class="input-text">
                <option></option>
                <option value="association"><?php echo esc_html(__('Association', 'unzer-payments')); ?></option>
                <option value="authority"><?php echo esc_html(__('Authority', 'unzer-payments')); ?></option>
                <option value="company"><?php echo esc_html(__('Company', 'unzer-payments')); ?></option>
                <option value="sole"><?php echo esc_html(__('Sole', 'unzer-payments')); ?></option>
                <option value="other"><?php echo esc_html(__('Other', 'unzer-payments')); ?></option>
            </select>
        </div>
        <div id="unzer-invoice-form" class="unzerUI form">
            <input type="hidden" id="unzer-invoice-id" name="unzer-invoice-id" value=""/>
            <div class="field">
                <div id="unzer-invoice-fields">
                    <!-- The Payment form UI element (opt-in text and checkbox) will be inserted here -->
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
        $this->handleDateOfBirth($order, $_POST['unzer-invoice-dob']);
        $_POST['unzer-dob'] = $_POST['unzer-invoice-dob'];

        if ($order->get_billing_company()) {
            $companyType = (string)$_POST['unzer-invoice-company-type'];
            if (empty($companyType)) {
                throw new Exception(__('Please enter your company type', 'unzer-payments'));
            }
            $order->update_meta_data(Main::ORDER_META_KEY_COMPANY_TYPE, $companyType);
        }
        $order->save_meta_data();

        try {
            $authorization = (new PaymentService())->performAuthorizationForOrder($order_id, $this, $_POST['unzer-invoice-id'], function (Authorization $authorization) {
                $riskData = new RiskData();
                $riskData->setThreatMetrixId(WC()->session->get('unzerThreatMetrixId'));
                WC()->session->set('unzerThreatMetrixId', '');
                if (is_user_logged_in()) {
                    /** @var \WP_User $user */
                    $user = wp_get_current_user();
                    $date = $user->user_registered ? date('Ymd', strtotime($user->user_registered)) : null;
                    $riskData->setRegistrationLevel(1);
                    $riskData->setRegistrationDate($date);
                } else {
                    $riskData->setRegistrationLevel(0);
                }
                $authorization->setRiskData($riskData);
            });
        } catch (UnzerApiException $e) {
            throw new Exception($e->getClientMessage() ?: $e->getMessage());
        }
        if (!($authorization->isPending() || $authorization->isSuccess())) {
            throw new Exception($authorization->getMessage()->getCustomer());
        }
        if ($authorization->isSuccess()) {
            $order = wc_get_order($order_id);
            $order->payment_complete($authorization->getPayment()->getId());
        } else {
            $this->set_order_transaction_number(wc_get_order($order_id), $authorization->getPayment()->getId());
        }
        $return['redirect'] = $this->get_return_url(wc_get_order($order_id));
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
        try {
            $paymentService = new PaymentService();
            $cancellation = $paymentService->performRefundOrReversalOnPayment($order_id, $this, $amount);
            return $cancellation->isSuccess();
        } catch (\Exception $e) {
            $this->logger->error('refund error: ' . $e->getMessage(), ['orderId' => $order_id, 'amount' => $amount]);
            throw $e;
        }
    }


    public function get_payment_information(AbstractTransactionType $chargeOrAuthorization)
    {
        return sprintf(__("Please transfer the amount of %s to the following account:<br /><br />"
            . "Holder: %s<br/>"
            . "IBAN: %s<br/>"
            . "BIC: %s<br/><br/>"
            . "<i>Please use only this identification number as the descriptor: </i><br/>"
            . "%s"),
            wc_price($chargeOrAuthorization->getAmount(), ['currency' => $chargeOrAuthorization->getCurrency()]),
            $chargeOrAuthorization->getHolder(),
            $chargeOrAuthorization->getIban(),
            $chargeOrAuthorization->getBic(),
            $chargeOrAuthorization->getDescriptor()
        );
    }
}
