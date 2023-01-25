<?php

namespace UnzerPayments\Controllers;

use UnzerPayments\Services\LogService;
use UnzerPayments\Services\OrderService;
use UnzerSDK\Constants\WebhookEvents;

class WebhookController
{
    const WEBHOOK_ROUTE_SLUG = 'unzer_webhook';

    const REGISTERED_EVENTS = [
        WebhookEvents::CHARGE_CANCELED,
        WebhookEvents::AUTHORIZE_CANCELED,
        WebhookEvents::AUTHORIZE_SUCCEEDED,
        WebhookEvents::CHARGE_SUCCEEDED,
    ];

    /**
     * @var LogService
     */
    private $logger;
    /**
     * @var OrderService
     */
    private $orderService;

    public function __construct()
    {
        $this->logger = new LogService();
        $this->orderService = new OrderService();
    }

    public function receiveWebhook()
    {
        if($_GET['test']){
            $data = [
                'event'=>'authorize.canceled',
                'paymentId'=>'s-pay-398'
            ];
        }else {
            $data = json_decode(file_get_contents('php://input'), true);
            //$data = unserialize('a:4:{s:5:"event";s:15:"charge.canceled";s:9:"publicKey";s:38:"s-pub-2a10SPT8OwkWUbkLNCygS7kug5JOY3KW";s:11:"retrieveUrl";s:83:"https://sbx-api.heidelpay.com/v1/payments/s-pay-293/charges/s-chg-1/cancels/s-cnl-1";s:9:"paymentId";s:9:"s-pay-292";}');
        }
        if (empty($data)) {
            $this->logger->debug('empty webhook', ['server' => $_SERVER]);
            status_header(404);
            wp_die();
        }

        $this->logger->debug('webhook received', ['data' => $data]);

        if (!in_array($data['event'], self::REGISTERED_EVENTS, true)) {
            $this->logger->debug('webhook event not relevant');
            $this->renderJson(['success' => true]);
            die;
        }

        if (empty($data['paymentId'])) {
            $this->logger->warning('no payment id in webhook event', ['webhook_data' => $data]);
            return;
        }

        $orderId = $this->orderService->getOrderIdFromPaymentId($data['paymentId']);
        if (empty($orderId)) {
            $this->logger->warning('no order id for payment id in webhook event', ['webhook_data' => $data]);
            return;
        }

        switch ($data['event']) {
            case WebhookEvents::CHARGE_CANCELED:
            case WebhookEvents::AUTHORIZE_CANCELED:
                $this->handleCancel($data['paymentId'], $orderId);
                break;
            case WebhookEvents::AUTHORIZE_SUCCEEDED:
                $this->handleAuthorizeSucceeded($data['paymentId'], $orderId);
                break;
            case WebhookEvents::CHARGE_SUCCEEDED:
                $this->handleChargeSucceeded($data['paymentId'], $orderId);
                break;
        }
        $this->renderJson(['success' => true]);
    }

    protected function renderJson(array $data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        die;
    }

    private function handleCancel($paymentId, $orderId)
    {
        sleep(2);
        $this->logger->debug('webhook handleCancel', ['paymentId' => $paymentId, 'orderId' => $orderId]);
        $this->orderService->updateRefunds($paymentId, $orderId);
    }

    private function handleAuthorizeSucceeded($paymentId, $orderId)
    {
        $this->logger->debug('webhook handleAuthorizeSucceeded', ['paymentId' => $paymentId, 'orderId' => $orderId]);
        $order = wc_get_order($orderId);
        if (empty($order->get_transaction_id())) {
            $this->orderService->setOrderAuthorized($order, $paymentId);
        }
    }

    private function handleChargeSucceeded($paymentId, $orderId)
    {
        $this->logger->debug('webhook handleChargeSucceeded', ['paymentId' => $paymentId, 'orderId' => $orderId]);
        $order = wc_get_order($orderId);
        if (empty($order->get_transaction_id())) {
            $order->payment_complete($paymentId);
        }
    }
}
