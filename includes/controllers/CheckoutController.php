<?php

namespace UnzerPayments\Controllers;

use UnzerPayments\Gateways\Prepayment;
use UnzerPayments\Main;
use UnzerPayments\Services\LogService;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerSDK\Constants\PaymentState;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Charge;
use WC_Order;

class CheckoutController
{
    public function confirm()
    {
        $orderId = (int)WC()->session->get('order_awaiting_payment');
        if (empty($orderId)) {
            (new LogService())->error('empty order id for confirmation endpoint');
            wp_redirect(wc_get_checkout_url());
            die;
        }
        $order = wc_get_order($orderId);
        $unzerPluginManager = Main::getInstance();
        $paymentGateway = $unzerPluginManager->getPaymentGateway($order->get_payment_method());
        if (!$paymentGateway) {
            (new LogService())->error('payment method unknown', $order->get_payment_method());
            wc_add_notice(__('Payment error', UNZER_PLUGIN_NAME), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }
        $paymentService = new PaymentService();
        $transaction = $paymentService->getChargeOrAuthorizationFromOrder($orderId, $paymentGateway);

        if (!$transaction) {
            $paymentService->removeTransactionMetaData($orderId);
            (new LogService())->error('no authorization/charge found', ['order' => $orderId]);
            wc_add_notice(__('Payment error', UNZER_PLUGIN_NAME), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }

        if($transaction->getPayment()->getState() === PaymentState::STATE_CANCELED){
            $paymentService->removeTransactionMetaData($orderId);
            (new LogService())->debug('payment cancelled', ['order' => $orderId, 'transaction'=>$transaction]);
            wc_add_notice(__('Payment cancelled', UNZER_PLUGIN_NAME), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }
        $orderService = new OrderService();
        if($orderService->areAmountsEqual($order, $transaction->getPayment())){
            if ($transaction instanceof Authorization) {
                $orderService->setOrderAuthorized($order, $transaction->getPayment()->getId());
            } else {
                $order->payment_complete($transaction->getPayment()->getId());
                if(get_option('unzer_captured_order_status')) {
                    $order->set_status(get_option('unzer_captured_order_status'));
                }
                $order->save();
            }
            wp_redirect($order->get_checkout_order_received_url());
        } else {
            (new LogService())->error('amounts do not match', ['charged' => $transaction->getPayment()->getAmount()->getCharged(), 'invoiced' => $order->get_total()]);
            wc_add_notice(__('Payment error', UNZER_PLUGIN_NAME), 'error');
            wp_redirect(wc_get_checkout_url());
        }
        die;
    }

    /**
     * @param WC_Order $order
     * @return void
     */
    public static function checkoutSuccess($order)
    {
        if($paymentInstructions = get_post_meta($order->get_id(), Main::ORDER_META_KEY_PAYMENT_INSTRUCTIONS, true)){
            echo '<div id="unzer-payment-instructions" style="margin:20px 0;">'.$paymentInstructions.'</div>';
        }
    }
}