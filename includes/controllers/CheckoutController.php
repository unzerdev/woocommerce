<?php

namespace UnzerPayments\Controllers;

use Exception;
use UnzerPayments\Gateways\ApplePay;
use UnzerPayments\Gateways\Prepayment;
use UnzerPayments\Main;
use UnzerPayments\Services\LogService;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerSDK\Adapter\ApplepayAdapter;
use UnzerSDK\Constants\PaymentState;
use UnzerSDK\Resources\ExternalResources\ApplepaySession;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use WC_Order;

class CheckoutController
{
    const APPLE_PAY_MERCHANT_VALIDATION_ROUTE_SLUG = 'unzer_apple_pay_merchant_validation';

    public function confirm()
    {
        $logger = (new LogService());
        $logger->debug('CheckoutController::confirm()');
        $orderId = (int)WC()->session->get('order_awaiting_payment');
        if (empty($orderId)) {
            $logger->error('empty order id for confirmation endpoint');
            wp_redirect(wc_get_checkout_url());
            die;
        }
        $order = wc_get_order($orderId);
        $unzerPluginManager = Main::getInstance();
        $paymentGateway = $unzerPluginManager->getPaymentGateway($order->get_payment_method());
        if (!$paymentGateway) {
            $logger->error('payment method unknown', $order->get_payment_method());
            wc_add_notice(__('Payment error', 'unzer-payments'), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }
        $paymentService = new PaymentService();
        $transaction = $paymentService->getChargeOrAuthorizationFromOrder($orderId, $paymentGateway);

        if (!$transaction) {
            $paymentService->removeTransactionMetaData($orderId);
            $logger->error('no authorization/charge found', ['order' => $orderId]);
            wc_add_notice(__('Payment error', 'unzer-payments'), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }

        if ($transaction->getPayment()->getState() === PaymentState::STATE_CANCELED) {
            $paymentService->removeTransactionMetaData($orderId);
            $logger->debug('payment cancelled', ['order' => $orderId, 'transaction' => $transaction->expose(), 'reason' => $transaction->getMessage()->getMerchant()]);
            wc_add_notice(__('Payment cancelled', 'unzer-payments'), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }

        if (method_exists($paymentGateway, 'isSaveInstruments')) {
            if (WC()->session->get('save_payment_instrument')) {
                $paymentGateway->maybeSavePaymentInstrument($transaction->getPayment()->getPaymentType()->getId());
            }
        }
        $orderService = new OrderService();
        if ($orderService->areAmountsEqual($order, $transaction->getPayment())) {
            if ($transaction instanceof Authorization) {
                $logger->debug('CheckoutController::confirm() - set authorized');
                $orderService->setOrderAuthorized($order, $transaction->getPayment()->getId());
            } else {
                $logger->debug('CheckoutController::confirm() - payment_complete');
                $order->payment_complete($transaction->getPayment()->getId());
                $order->set_transaction_id($transaction->getPayment()->getId());
                if (get_option('unzer_captured_order_status')) {
                    $order->set_status(get_option('unzer_captured_order_status'));
                }
                $order->save();
            }
            wp_redirect($order->get_checkout_order_received_url());
        } else {
            $logger->error('amounts do not match', ['charged' => $transaction->getPayment()->getAmount()->getCharged(), 'invoiced' => $order->get_total()]);
            wc_add_notice(__('Payment error', 'unzer-payments'), 'error');
            wp_redirect(wc_get_checkout_url());
        }
        WC()->session->set('save_payment_instrument', false);
        die;
    }

    /**
     * @param WC_Order $order
     * @return void
     */
    public static function checkoutSuccess($order)
    {
        if ($paymentInstructions = get_post_meta($order->get_id(), Main::ORDER_META_KEY_PAYMENT_INSTRUCTIONS, true)) {
            if ($order->get_payment_method() !== Prepayment::GATEWAY_ID) {
                return;
            }
            echo '<div id="unzer-payment-instructions" style="margin:20px 0;">' . $paymentInstructions . '</div>';
        }
    }

    public function validateApplePayMerchant()
    {
        $applePayGateway = new ApplePay();
        $applePaySession = new ApplepaySession(
            $applePayGateway->get_option('merchant_id'),
            get_bloginfo('name'),
            $_SERVER['HTTP_HOST']
        );
        $appleAdapter = new ApplepayAdapter();

        $certificateTempPath = tempnam(sys_get_temp_dir(), 'WpUnzerPayments');
        $keyTempPath = tempnam(sys_get_temp_dir(), 'WpUnzerPayments');

        if (!$certificateTempPath || !$keyTempPath) {
            throw new Exception('Error on temporary file creation');
        }

        file_put_contents($certificateTempPath, get_option('unzer_apple_pay_merchant_id_certificate'));
        file_put_contents($keyTempPath, get_option('unzer_apple_pay_merchant_id_key'));

        try {
            $appleAdapter->init($certificateTempPath, $keyTempPath);
            $merchantValidationUrl = urldecode($_POST['validation_url']);
            try {
                $validationResponse = $appleAdapter->validateApplePayMerchant(
                    $merchantValidationUrl,
                    $applePaySession
                );
                (new LogService())->debug('apple pay validation response', ['response' => $validationResponse]);
                $this->renderJson(['response' => $validationResponse]);
            } catch (Exception $e) {
                (new LogService())->error('merchant validation failed', ['error' => $e->getMessage(), 'merchantValidationUrl' => $merchantValidationUrl, 'GET' => print_r($_GET, true), 'POST' => print_r($_POST, true)]);
            }
        } finally {
            unlink($keyTempPath);
            unlink($certificateTempPath);
        }
    }

    protected function renderJson(array $data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        die;
    }
}
