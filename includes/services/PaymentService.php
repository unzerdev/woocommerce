<?php

namespace UnzerPayments\Services;


use Exception;
use UnzerPayments\Gateways\AbstractGateway;
use UnzerPayments\Main;
use UnzerPayments\Util;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\AbstractUnzerResource;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Cancellation;
use UnzerSDK\Resources\TransactionTypes\Charge;
use UnzerSDK\Unzer;

class PaymentService
{
    public function __construct()
    {
        $this->logger = new LogService();
    }

    /**
     * @param AbstractGateway|null $paymentGateway
     * @return Unzer
     */
    public function getUnzerManager(AbstractGateway $paymentGateway = null): Unzer
    {
        return new Unzer($paymentGateway ? $paymentGateway->get_private_key() : get_option('unzer_private_key'));
    }

    public function performChargeOnAuthorization($orderId, $amount = null)
    {
        if (empty($orderId)) {
            throw new Exception('empty order id for performChargeOnAuthorization');
        }
        $order = wc_get_order($orderId);

        $paymentId = get_post_meta($orderId, Main::ORDER_META_KEY_PAYMENT_ID, true);
        if (empty($paymentId)) {
            throw new Exception('This order has not been authorized with Unzer');
        }
        $charge = new Charge();
        if ($amount) {
            $charge->setAmount($amount);
        }
        $unzerManager = $this->getUnzerManager();
        $unzerManager->performChargeOnPayment($paymentId, $charge);
    }

    /**
     * @throws UnzerApiException
     */
    public function performChargeForOrder(
        $orderId,
        AbstractGateway $paymentGateway,
        $paymentType,
        $chargeEditor = null
    ): Charge
    {
        return $this->performChargeOrAuthorizationForOrder($orderId, $paymentGateway, $paymentType, $chargeEditor);
    }

    /**
     * @param $orderId
     * @param AbstractGateway $paymentGateway
     * @param $paymentType
     * @param $chargeEditor
     * @return Authorization
     * @throws UnzerApiException
     */
    public function performAuthorizationForOrder(
        $orderId,
        AbstractGateway $paymentGateway,
        $paymentType,
        $chargeEditor = null
    ): Authorization
    {
        return $this->performChargeOrAuthorizationForOrder($orderId, $paymentGateway, $paymentType, $chargeEditor, AbstractGateway::TRANSACTION_TYPE_AUTHORIZE);
    }

    /**
     * @param $orderId
     * @param AbstractGateway $paymentGateway
     * @param $paymentType
     * @param callable|null $transactionEditor
     * @param string $type
     * @return Charge|Authorization
     * @throws UnzerApiException
     */
    protected function performChargeOrAuthorizationForOrder(
        $orderId,
        AbstractGateway $paymentGateway,
        $paymentType,
        callable $transactionEditor = null,
        string $type = AbstractGateway::TRANSACTION_TYPE_CHARGE
    ): AbstractTransactionType
    {
        $this->removeTransactionMetaData($orderId);
        $order = wc_get_order($orderId);
        $order->get_payment_method();
        $this->logger->debug('start authorization/charge for #' . $orderId . ' with ' . $order->get_payment_method());
        $basket = (new OrderService())->getBasket($order);
        $customer = (new CustomerService())->getCustomerFromOrder($order);
        $unzer = $this->getUnzerManager($paymentGateway);
        $paymentType = class_exists($paymentType) ? $unzer->createPaymentType(new $paymentType) : $paymentType;

        try {
            $this->logger->debug('try authorization/charge for #' . $orderId . ' with ' . $order->get_payment_method(), ['type' => $type, 'basket' => $basket->expose(), 'customer' => $customer->expose()]);
            if ($type === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE) {
                $authorization = new Authorization($basket->getTotalValueGross(), $basket->getCurrencyCode(), $paymentGateway->get_confirm_url());
                if ($transactionEditor !== null) {
                    $transactionEditor($authorization);
                }
                $authorization = $unzer->performAuthorization($authorization, $paymentType, $customer, (new ShopService())->getMetadata(), $basket);
                update_post_meta($order->get_id(), Main::ORDER_META_KEY_AUTHORIZATION_ID, $authorization->getId());
                update_post_meta($order->get_id(), Main::ORDER_META_KEY_PAYMENT_ID, $authorization->getPayment()->getId());
                update_post_meta($order->get_id(), Main::ORDER_META_KEY_PAYMENT_SHORT_ID, $authorization->getShortId());
                if (method_exists($paymentGateway, 'get_payment_information')) {
                    update_post_meta($order->get_id(), Main::ORDER_META_KEY_PAYMENT_INSTRUCTIONS, $paymentGateway->get_payment_information($authorization));
                }
                $this->logger->debug('authorization result for #' . $orderId . ' with ' . $order->get_payment_method(), ['id' => $authorization->getId()]);
                $return = $authorization;
            } else {
                $charge = new Charge($basket->getTotalValueGross(), $basket->getCurrencyCode(), $paymentGateway->get_confirm_url());
                if ($transactionEditor !== null) {
                    $transactionEditor($charge);
                }
                $charge = $unzer->performCharge($charge, $paymentType, $customer, (new ShopService())->getMetadata(), $basket);
                update_post_meta($order->get_id(), Main::ORDER_META_KEY_CHARGE_ID, $charge->getId());
                update_post_meta($order->get_id(), Main::ORDER_META_KEY_PAYMENT_ID, $charge->getPayment()->getId());
                update_post_meta($order->get_id(), Main::ORDER_META_KEY_PAYMENT_SHORT_ID, $charge->getShortId());
                if (method_exists($paymentGateway, 'get_payment_information')) {
                    update_post_meta($order->get_id(), Main::ORDER_META_KEY_PAYMENT_INSTRUCTIONS, $paymentGateway->get_payment_information($charge));
                }
                $this->logger->debug('charge result for #' . $orderId . ' with ' . $order->get_payment_method(), ['id' => $charge->getId()]);
                $return = $charge;
            }

        } catch (Exception $e) {
            $this->logger->error('authorization/charge failed for #' . $orderId . ' with ' . $order->get_payment_method(), ['msg' => $e->getMessage()]);
            throw $e;
        }
        return $return;
    }


    /**
     * @param $orderId
     * @param AbstractGateway $paymentGateway
     * @return Charge|Authorization|null
     */
    public function getChargeOrAuthorizationFromOrder($orderId, AbstractGateway $paymentGateway): ?AbstractTransactionType
    {
        $unzerChargeId = get_post_meta($orderId, Main::ORDER_META_KEY_CHARGE_ID, true);
        if (empty($unzerChargeId)) {
            $unzerAuthorizationId = get_post_meta($orderId, Main::ORDER_META_KEY_AUTHORIZATION_ID, true);
            if (empty($unzerAuthorizationId)) {
                $this->logger->warning('could not find authorization/charge id in order #' . $orderId);
                return null;
            }
        }
        $unzerPaymentId = get_post_meta($orderId, Main::ORDER_META_KEY_PAYMENT_ID, true);
        if (empty($unzerPaymentId)) {
            $this->logger->warning('could not find payment id in order #' . $orderId);
            return null;
        }
        $unzer = $this->getUnzerManager($paymentGateway);
        try {
            return ($unzerChargeId ? $unzer->fetchChargeById($unzerPaymentId, $unzerChargeId) : $unzer->fetchAuthorization($unzerPaymentId));
        } catch (Exception $e) {
            $this->logger->warning('unable to fetch authorization/charge', ['payment' => $unzerPaymentId, 'charge' => $unzerChargeId, 'error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * @return Cancellation
     * @throws UnzerApiException
     * @throws Exception
     */
    public function performRefundOrReversal($orderId, AbstractGateway $paymentGateway, $amount): AbstractUnzerResource
    {
        $amount = (float)$amount;
        $unzer = $this->getUnzerManager($paymentGateway);
        $paymentId = get_post_meta($orderId, Main::ORDER_META_KEY_PAYMENT_ID, true);
        $maxCaptureRefund = 0;
        $numberOfRefundsPossible = 0;
        if (empty($paymentId)) {
            throw new Exception('This is not an Unzer payment');
        }

        $payment = $unzer->fetchPayment($paymentId);
        if ($payment->getCharges()) {
            /** @var Charge $charge */
            foreach ($payment->getCharges() as $charge) {
                try {
                    if ($charge->getTotalAmount() > 0) {
                        $numberOfRefundsPossible++;
                    }
                    $maxCaptureRefund = max($charge->getTotalAmount(), $maxCaptureRefund);
                    if ($charge->getTotalAmount() < $amount && !Util::safeCompareAmount($charge->getTotalAmount(), $amount)) {
                        continue;
                    }
                    return $charge->cancel($amount, null, 'fromWordpressOrder' . $orderId . '_' . uniqid());
                } catch (Exception $e) {
                    $this->logger->warning('refund on charge not possible: ' . $e->getMessage());
                }
            }
        }
        try {
            $authorization = $unzer->fetchAuthorization($paymentId);
            return $authorization->cancel($amount);
        } catch (Exception $e) {
            $this->logger->warning('refund on authorization not possible: ' . $e->getMessage());
        }
        throw new Exception(sprintf(__('Unable to do refund: Maximum amount for single refund is %s.'), html_entity_decode(strip_tags(wc_price($maxCaptureRefund, ['currency' => $payment->getCurrency()])))) . ($numberOfRefundsPossible > 1 ? ' ' . sprintf(__('However, you may refund in up to %s smaller chunks.'), $numberOfRefundsPossible) : ''));
    }

    /**
     * This is an exception for the invoice/paylater payment method
     * @return Cancellation
     * @throws UnzerApiException
     * @throws Exception
     */
    public function performRefundOrReversalOnPayment($orderId, AbstractGateway $paymentGateway, $amount): AbstractUnzerResource
    {
        $unzer = $this->getUnzerManager($paymentGateway);
        $paymentId = get_post_meta($orderId, Main::ORDER_META_KEY_PAYMENT_ID, true);

        if (empty($paymentId)) {
            throw new Exception('This is not an Unzer payment');
        }

        $payment = $unzer->fetchPayment($paymentId);
        if ($payment->getCharges()) {
            try {
                return $unzer->cancelChargedPayment($paymentId, new Cancellation($amount));
            } catch (Exception $e) {
                $this->logger->warning('Refund not possible: ' . $e->getMessage());
                throw new Exception(__('Refund not possible', 'unzer-payments').': '.$e->getMessage());
            }
        } else {
            try {
                if (!Util::safeCompareAmount($payment->getAmount()->getTotal(), $amount)) {
                    throw new Exception(__('Reversals prior to capturing are only allowed for the full amount', 'unzer-payments'));
                }
                return $unzer->cancelAuthorizedPayment($paymentId);
            } catch (Exception $e) {
                $this->logger->warning('Reversal not possible: ' . $e->getMessage());
                throw new Exception(__('Reversal not possible', 'unzer-payments').': '.$e->getMessage());
            }
        }
    }


    public function removeTransactionMetaData($orderId)
    {
        foreach (Main::ORDER_META_KEYS as $metaKey) {
            delete_post_meta($orderId, $metaKey);
        }
    }

}