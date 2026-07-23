<?php

namespace UnzerPayments\Controllers;

use Exception;
use UnzerPayments\Gateways\AbstractGateway;
use UnzerPayments\Gateways\ApplePayV2;
use UnzerPayments\Gateways\GooglePay;
use UnzerPayments\Gateways\Paypal;
use UnzerPayments\Main;
use UnzerPayments\Services\CustomerService;
use UnzerPayments\Services\ExpressCheckoutService;
use UnzerPayments\Services\LogService;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Services\ShopService;
use UnzerPayments\Util;
use UnzerSDK\Constants\PaymentState;
use UnzerSDK\Resources\Basket;
use UnzerSDK\Resources\EmbeddedResources\BasketItem;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Charge;
use WC_Order;

class ExpressController
{
    const PAYPAL_EXPRESS_SLUG = 'paypal_express';
    const PAYPAL_EXPRESS_RETURN_SLUG = 'paypal_express_return';
    const GOOGLE_PAY_EXPRESS_SLUG = 'google_pay_express';
    const APPLE_PAY_EXPRESS_SLUG = 'apple_pay_express';

    public function paypalExpress()
    {

        try {
            $paymentTypeId = sanitize_text_field(
                $this->getRequestValue('paymentTypeId', '')
            );

            if (empty($paymentTypeId)) {
                wp_send_json_error([
                    'message' => 'paymentTypeId is missing',
                ], 400);
            }

            if (!WC()->cart) {
                wc_load_cart();
            }

            $cartTotal = (float) WC()->cart->get_total('edit');
            $currency  = get_woocommerce_currency();

            $paymentService = new PaymentService();

            $basket = (new Basket())
                ->setTotalValueGross($cartTotal)
                ->setCurrencyCode($currency);

            $basketItem = (new BasketItem())
                ->setAmountPerUnitGross($cartTotal)
                ->setTitle('-');

            $basket->addBasketItem($basketItem);
            $basketResult = $paymentService->getUnzerManager()->createBasket($basket);

            $shopservice = new ShopService();
            $metadata = $shopservice->getMetadata();
            $shopservice->setIsExpress($metadata, true);

            $bookingMode = (new Paypal())->get_option( 'transaction_type' );

            $returnUrl = WC()->api_request_url(self::PAYPAL_EXPRESS_RETURN_SLUG);

            if ($bookingMode === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE) {
                $authorization = (new Authorization(
                    $cartTotal,
                    $currency,
                    $returnUrl
                ))->setCheckoutType('express', $paymentTypeId);

                $resultTransaction = $paymentService->getUnzerManager()->performAuthorization(
                    $authorization,
                    $paymentTypeId,
                    null,
                    $metadata,
                    $basketResult
                );

            } else {
                $charge = (new Charge(
                    $cartTotal,
                    $currency,
                    $returnUrl
                ))->setCheckoutType('express', $paymentTypeId);

                $resultTransaction = $paymentService->getUnzerManager()->performCharge(
                    $charge,
                    $paymentTypeId,
                    null,
                    $metadata,
                    $basketResult
                );
            }

            WC()->session->set(
                ExpressCheckoutService::SESSION_PAYPAL_PAYMENT_ID,
                $resultTransaction->getPaymentId()
            );

            WC()->session->set(
                ExpressCheckoutService::SESSION_PAYPAL_PAYMENT_TYPE_ID,
                $paymentTypeId
            );

            wp_send_json([
                'paymentId'   => $resultTransaction->getPaymentId(),
                'redirectUrl' => $resultTransaction->getRedirectUrl(),

            ]);

        } catch (Exception $e) {

            wp_send_json_error([
                'message' => $e->getMessage(),
            ], 500);

        }
    }

    public function paypalExpressReturn()
    {
        $paymentId = WC()->session->get(ExpressCheckoutService::SESSION_PAYPAL_PAYMENT_ID);

        if (empty($paymentId)) {
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }
        $paymentService = new PaymentService();

        try {
            $payment = $paymentService->getUnzerManager()->fetchPayment($paymentId);
            (new ExpressCheckoutService())->startCheckoutSessionFromUnzerPayment(
                $payment,
                Paypal::GATEWAY_ID
            );

        } catch (\Exception $e) {
            wc_add_notice( __( 'Payment error', 'unzer-payments' ), 'error' );
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }

        WC()->session->set(
            ExpressCheckoutService::SESSION_SELECTED_EXPRESS_METHOD,
            Paypal::GATEWAY_ID
        );

        wp_redirect(
            add_query_arg(
                'isExpressCheckout',
                'true',
                wc_get_checkout_url()
            )
        );
        exit;
    }

    public function googleExpress()
    {
        $paymentTypeId = sanitize_text_field(
            $this->getRequestValue('paymentTypeId', '')
        );
        $paymentData = $this->getRequestValue('paymentData', '');
        try {
            (new ExpressCheckoutService())->startCheckoutSessionFromGooglePayData($paymentData, GooglePay::GATEWAY_ID);
        } catch (\Exception $e) {
            wc_add_notice( __( 'Payment error', 'unzer-payments' ), 'error' );
            wp_safe_redirect(wc_get_cart_url());
        }
        WC()->session->set(ExpressCheckoutService::SESSION_GOOGLE_PAYMENT_TYPE_ID, $paymentTypeId);
        WC()->session->set(ExpressCheckoutService::SESSION_SELECTED_EXPRESS_METHOD, GooglePay::GATEWAY_ID);

        wp_send_json(
            [
                'redirectUrl' => add_query_arg(
                    'isExpressCheckout',
                    'true',
                    wc_get_checkout_url()
                )
            ]
        );
        exit();
    }

    public function applepayExpress()
    {
        $logger = ( new LogService() );
        $paymentTypeId = sanitize_text_field(
            $this->getRequestValue('paymentTypeId', '')
        );
        $paymentData = $this->getRequestValue('paymentData', '');
        $shippingContact = $this->getRequestValue('shippingContact', '');
        $billingContact = $this->getRequestValue('billingContact', '');
        try {
            (new ExpressCheckoutService())->startCheckoutSessionFromApplePayData($paymentData, $shippingContact, $billingContact, ApplePayV2::GATEWAY_ID);
        } catch (\Exception $e) {
            wc_add_notice( __( 'Payment error', 'unzer-payments' ), 'error' );
            wp_safe_redirect(wc_get_cart_url());
        }
        WC()->session->set(ExpressCheckoutService::SESSION_APPLEPAY_PAYMENT_TYPE_ID, $paymentTypeId);
        WC()->session->set(ExpressCheckoutService::SESSION_SELECTED_EXPRESS_METHOD, ApplePayV2::GATEWAY_ID);

        wp_send_json(
            [
                'redirectUrl' => add_query_arg(
                    'isExpressCheckout',
                    'true',
                    wc_get_checkout_url()
                )
            ]
        );
        exit();
    }

    private function getRequestValue(string $key, $default = null)
    {
        if (isset($_REQUEST[$key])) {
            return wp_unslash($_REQUEST[$key]);
        }
        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);
        if (is_array($payload) && array_key_exists($key, $payload)) {
            return $payload[$key];
        }
        return $default;
    }

}