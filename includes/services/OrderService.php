<?php

namespace UnzerPayments\Services;


use Exception;
use UnzerPayments\Main;
use UnzerPayments\Util;
use UnzerSDK\Constants\BasketItemTypes;
use UnzerSDK\Constants\CompanyRegistrationTypes;
use UnzerSDK\Constants\ShippingTypes;
use UnzerSDK\Resources\Basket;
use UnzerSDK\Resources\Customer;
use UnzerSDK\Resources\EmbeddedResources\Address;
use UnzerSDK\Resources\EmbeddedResources\BasketItem;
use UnzerSDK\Resources\EmbeddedResources\CompanyInfo;
use UnzerSDK\Resources\Payment;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Cancellation;
use WC_Abstract_Order;
use WC_Order;
use WC_Order_Item_Coupon;
use WC_Order_Refund;

class OrderService
{

    /**
     * @var LogService
     */
    protected $logger;

    public function __construct()
    {
        $this->logger = new LogService();
    }

    /**
     * @param int|WC_Order $order
     * @return Basket
     */
    public function getBasket($order): Basket
    {
        $order = is_object($order) ? $order : wc_get_order($order);
        $totalLeft = $order->get_total();
        $basket = (new Basket())
            ->setTotalValueGross($order->get_total())
            ->setOrderId($order->get_id())
            ->setCurrencyCode($order->get_currency());

        $basketItems = [];
        /** @var \WC_Order_Item_Product $orderItem */
        foreach ($order->get_items() as $orderItem) {

            $basketItem = (new BasketItem())
                ->setTitle($orderItem->get_name())
                ->setQuantity($orderItem->get_quantity())
                ->setType(BasketItemTypes::GOODS);
            $price = 0;
            $vatRate = 0;
            if (is_callable([$orderItem, 'get_total'])) {
                $totalLinePrice = $orderItem->get_subtotal() + $orderItem->get_subtotal_tax();
                $price = round($orderItem->get_quantity() ? $totalLinePrice / $orderItem->get_quantity() : 0, 2);
                if ($orderItem->get_subtotal() > 0) {
                    $vatRate = round($orderItem->get_subtotal_tax() / $orderItem->get_subtotal() * 100, 1);
                }
                $totalLeft -= $price * $orderItem->get_quantity();
            }
            if ($discount = $this->getDiscountFromOrderItem($orderItem)) {
                $basketItem->setAmountDiscountPerUnitGross($discount);
                $price += $discount;
            }
            if ($price != 0) {
                $basketItem
                    ->setAmountPerUnitGross($price)
                    ->setVat($vatRate);

                $basketItems[] = $basketItem;
            }
        }

        if ($order->get_shipping_total()) {
            $vatRate = round($order->get_shipping_tax() / $order->get_shipping_total() * 100, 1);
            $basketItem = (new BasketItem())
                ->setTitle($order->get_shipping_method())
                ->setQuantity(1)
                ->setType(BasketItemTypes::SHIPMENT)
                ->setAmountPerUnitGross(round((float)$order->get_shipping_total() + (float)$order->get_shipping_tax(), 2))
                ->setVat($vatRate);
            $basketItems[] = $basketItem;
            $totalLeft -= $basketItem->getAmountPerUnitGross();
        }

        if ($coupons = $order->get_coupons()) {

            /** @var WC_Order_Item_Coupon $coupon */
            foreach ($coupons as $coupon) {
                $vatRate = round($coupon->get_discount_tax() / $order->get_discount_total() * 100, 1);
                $basketItem = (new BasketItem())
                    ->setTitle($coupon->get_code())
                    ->setQuantity(1)
                    ->setType(BasketItemTypes::VOUCHER)
                    ->setAmountDiscountPerUnitGross(round((float)$order->get_discount_total() + (float)$order->get_discount_tax(), 2))
                    ->setVat($vatRate);
                $basketItems[] = $basketItem;
                $totalLeft += $basketItem->getAmountDiscountPerUnitGross();
            }
        }

        if (number_format($totalLeft, 2) !== '0.00') {
            if ($totalLeft < 0) {
                $basketItem = (new BasketItem())
                    ->setTitle('Rounding fix')
                    ->setQuantity(1)
                    ->setType(BasketItemTypes::VOUCHER)
                    ->setAmountDiscountPerUnitGross(round($totalLeft*-1, 2))
                    ->setVat(0);
                $basketItems[] = $basketItem;
            } else {
                $basketItem = (new BasketItem())
                    ->setTitle('---')
                    ->setQuantity(1)
                    ->setType(BasketItemTypes::GOODS)
                    ->setAmountPerUnitGross($totalLeft);
                $basketItems[] = $basketItem;
            }
        }
        $basket->setBasketItems($basketItems);

        return $basket;
    }

    /**
     * @param \WC_Order_Item_Product $orderItem
     * @return float
     */
    private function getDiscountFromOrderItem($orderItem)
    {

        if (!is_callable([$orderItem, 'get_total'])) {
            return 0;
        }

        if (!is_callable([$orderItem, 'get_product']) || !$orderItem->get_product() || !$orderItem->get_product()->get_regular_price()) {
            return 0;
        }
        $totalLinePrice = (float)$orderItem->get_subtotal() + (float)$orderItem->get_subtotal_tax();
        $singlePrice = $totalLinePrice / ($orderItem->get_quantity() ?: 1);
        $regularSinglePrice = $orderItem->get_product()->get_regular_price();
        if (!Util::safeCompareAmount($singlePrice, $regularSinglePrice)) {
            return $regularSinglePrice - $singlePrice;
        }
        return 0;
    }

    /**
     * @param int|WC_Order $order
     * @return Customer
     */
    public function getCustomer($order): Customer
    {
        $order = is_object($order) ? $order : wc_get_order($order);

        if (is_user_logged_in()) {
            $paymentService = new PaymentService();
            $unzer = $paymentService->getUnzerManager();
            try {
                $customer = $unzer->fetchCustomerByExtCustomerId('wp-' . wp_get_current_user()->ID);
            } catch (Exception $e) {
                //no worries, we cover this by creating a new customer
            }
        }

        if (empty($customer)) {
            $customer = new Customer();
            if (is_user_logged_in()) {
                $customer->setCustomerId('wp-' . wp_get_current_user()->ID);
            }
        }

        $customer
            ->setFirstname($order->get_billing_first_name())
            ->setLastname($order->get_billing_last_name())
            ->setPhone($order->get_billing_phone())
            ->setCompany($order->get_billing_company())
            ->setEmail($order->get_billing_email());


        $dob = $order->get_meta(Main::ORDER_META_KEY_DATE_OF_BIRTH);
        if (empty($dob)) {
            if (!empty($_POST['unzer-invoice-dob'])) {
                $dob = $_POST['unzer-invoice-dob'];
            }
        }
        if (!empty($dob)) {
            $customer->setBirthDate(date('Y-m-d', strtotime($dob)));
        }

        if ($order->get_billing_company()) {
            $companyType = $order->get_meta(Main::ORDER_META_KEY_COMPANY_TYPE);
            if (empty($companyType)) {
                if (!empty($_POST['unzer-invoice-company-type'])) {
                    $companyType = $_POST['unzer-invoice-company-type'];
                }
            }
            if (!empty($companyType)) {
                $companyInfo = (new CompanyInfo())
                    ->setCompanyType($companyType)
                    ->setRegistrationType(CompanyRegistrationTypes::REGISTRATION_TYPE_NOT_REGISTERED)
                    ->setFunction('OWNER');
//                  ->setRegistrationType(CompanyRegistrationTypes::REGISTRATION_TYPE_REGISTERED)
//                  ->setCommercialRegisterNumber(uniqid());
                $customer->setCompanyInfo($companyInfo);
            }
        }


        $this->setAddresses($customer, $order);
        return $customer;
    }

    /**
     * @param WC_Order $order
     * @param string|null $transactionId
     * @return bool
     */
    public function setOrderAuthorized(WC_Order $order, ?string $transactionId = null): bool
    {
        // this is almost a copy of WC_Order::payment_complete()

        try {
            if (WC()->session) {
                WC()->session->set('order_awaiting_payment', false);
            }

            if (!empty($transactionId)) {
                $order->set_transaction_id($transactionId);
            }
            if (get_option('unzer_authorized_order_status')) {
                $order->set_status(get_option('unzer_authorized_order_status'));
            } else {
                $order->set_status(apply_filters('woocommerce_payment_complete_order_status', $order->needs_processing() ? 'processing' : 'completed', $order->get_id(), $order));
            }

            $order->set_date_paid(null);
            $order->save();

        } catch (Exception $e) {
            /**
             * If there was an error completing the payment, log to a file and add an order note so the admin can take action.
             */
            $logger = wc_get_logger();
            $logger->error(
                sprintf(
                    'Error completing payment for order #%d',
                    $order->get_id()
                ),
                [
                    'order' => $this,
                    'error' => $e,
                ]
            );
            $order->add_order_note(__('Payment complete event failed.', 'woocommerce') . ' ' . $e->getMessage());
            return false;
        }
        return true;

    }

    public function getOrderIdFromPaymentId($paymentId)
    {
        $orderId = null;
        global $wpdb;
        $metaData = $wpdb->get_row($wpdb->prepare("SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = %s AND meta_value = %s", 'unzer_payment_id', $paymentId), ARRAY_A);

        if (isset($metaData['post_id'])) {
            $orderId = $metaData['post_id'];
        }
        return $orderId;
    }

    public function updateRefunds($paymentId, $orderId)
    {
        $paymentService = new PaymentService();
        $unzer = $paymentService->getUnzerManager();
        $payment = $unzer->fetchPayment($paymentId);
        if ($payment->getCancellations()) {
            $order = wc_get_order($orderId);
            $registeredRefunds = $order->get_refunds();
            /** @var Cancellation $unzerRefund */
            foreach ($payment->getCancellations() as $unzerRefund) {
                $refundMatchingId = self::getUnzerCancellationId($unzerRefund);
                $this->logger->debug('unzer refund data', [$unzerRefund->getId()]);
                $unzerRefundAmount = $unzerRefund->getAmount();
                /** @var WC_Order_Refund $registeredRefund */
                foreach ($registeredRefunds as $registeredRefund) {
                    $registeredRefundUnzerId = $registeredRefund->get_meta(Main::ORDER_META_KEY_CANCELLATION_ID, true);

                    if ($registeredRefundUnzerId) {
                        if ($registeredRefundUnzerId === $refundMatchingId) {
                            //this unzer refund is already registered
                            continue 2;
                        }
                        //this is registered to another unzer refund
                        continue;
                    }
                    $registeredRefundAmount = abs($registeredRefund->get_total());
                    $this->logger->debug('refund data', [$registeredRefundAmount, $unzerRefundAmount, $registeredRefund->get_date_created()->getTimestamp(), strtotime($unzerRefund->getDate())]);
                    if (abs($registeredRefundAmount - $unzerRefundAmount) <= 0.01) {
                        $timeDifference = abs($registeredRefund->get_date_created()->getTimestamp() - strtotime($unzerRefund->getDate()));
                        if ($timeDifference <= 10) {
                            //we consider this a match with some tolerance
                            update_post_meta($registeredRefund->get_id(), Main::ORDER_META_KEY_CANCELLATION_ID, $refundMatchingId);
                            $this->logger->debug('refund data match', [$registeredRefundAmount, $unzerRefundAmount, $registeredRefund->get_date_created()->getTimestamp(), strtotime($unzerRefund->getDate())]);
                            continue 2;
                        }
                    }
                }
                $this->logger->debug('refund data no match', [$unzerRefundAmount, $unzerRefund->getId()]);
                //at this point there was no match found in the existing WooC refunds, so we create one
                $this->createShopRefund($orderId, $unzerRefund);
            }
        }
    }

    private function createShopRefund($orderId, Cancellation $unzerRefund)
    {

        $shopRefund = wc_create_refund([
            'amount' => $unzerRefund->getAmount(),
            'reason' => $unzerRefund->getReasonCode(),
            'order_id' => $orderId,
            'refund_payment' => false,
            'restock_items' => false,
        ]);
        if ($shopRefund instanceof WC_Order_Refund) {
            $order = wc_get_order($orderId);
            $order->add_order_note('Refund created from Unzer cancellation ' . $unzerRefund->getId());
            update_post_meta($shopRefund->get_id(), Main::ORDER_META_KEY_CANCELLATION_ID, self::getUnzerCancellationId($unzerRefund));
            (new LogService())->warning('refund created from unzer cancellation', ['refund' => $shopRefund, 'unzerRefund' => $unzerRefund]);
        } else {
            (new LogService())->warning('unable to create shop refund from unzer cancellation', ['order' => $orderId, 'cancellation' => $unzerRefund, 'response' => $shopRefund]);
        }
    }

    public function areAmountsEqual(WC_Order $order, Payment $unzerPayment)
    {
        $processedAmount = $unzerPayment->getAmount()->getTotal();
        (new LogService())->debug('compare amounts', [$processedAmount, $order->get_total()]);
        return number_format($processedAmount, 2) === number_format($order->get_total(), 2);
    }

    public static function getUnzerCancellationId(Cancellation $unzerCancellation)
    {
        $id = $unzerCancellation->getId();
        try {
            $parentTransaction = $unzerCancellation->getParentResource();
            if ($parentTransaction instanceof AbstractTransactionType) {
                $id .= '---' . $parentTransaction->getId();
            }
        } catch (Exception $e) {
            //silent
        }
        return $id;
    }

}
