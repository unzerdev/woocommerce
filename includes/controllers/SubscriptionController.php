<?php

namespace UnzerPayments\Controllers;

use Exception;
use Throwable;
use UnzerPayments\Gateways\Paypal as PaypalGateway;
use UnzerPayments\Gateways\SubscriptionGateway;
use UnzerPayments\Services\LogService;
use UnzerPayments\Services\PaymentService;
use UnzerSDK\Constants\RecurrenceTypes;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\PaymentTypes\Paypal as PaypalPaymentType;
use WC_Subscription;
use WC_Subscriptions_Change_Payment_Gateway;

class SubscriptionController {

	protected LogService $logger;

	public function __construct( protected SubscriptionGateway $gateway ) {
		$this->logger = new LogService();
	}

	public function completePaypalSubscription(): void {
		$id = absint( wp_unslash( $_GET['id'] ?? '0' ) );
		$type = sanitize_text_field( wp_unslash( $_GET['type'] ?? '' ) );
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );

		try {
			if ( ! wp_verify_nonce( $nonce, "{$this->gateway->id}_subscription_{$id}_{$type}" ) ) {
				throw new Exception( 'Invalid PayPal subscription activation nonce.' );
			}

			if ( ! $this->gateway instanceof PaypalGateway ) {
				throw new Exception( 'Invalid PayPal gateway instance.' );
			}

			$unzer = ( new PaymentService() )->getUnzerManager( $this->gateway );
			$paymentType = $unzer->fetchPaymentType( $type );

			if ( ! $paymentType instanceof PaypalPaymentType ) {
				throw new Exception( 'PayPal payment type is invalid.' );
			}

			if ( ! $paymentType->isRecurring() ) {
				throw new Exception( 'PayPal payment type is not recurring.' );
			}

			if ( empty( $this->gateway->settings ) ) {
				$this->gateway->init_settings();
			}

			$this->gateway->settings['transaction_type'] = PaypalGateway::TRANSACTION_TYPE_CHARGE;
			$this->gateway->paymentRecurrence = RecurrenceTypes::SCHEDULED;

			$result = $this->gateway->process_payment_for_order( wc_get_order( $id ), $type );

			if ( 'success' !== ( $result['result'] ?? null ) || empty( $result['redirect'] ) ) {
				throw new Exception( 'Initial PayPal subscription charge failed.' );
			}

			wp_redirect( $result['redirect'] );
			exit;
		} catch ( Throwable $e ) {
			$message = $e instanceof UnzerApiException ? $e->getMerchantMessage() : $e->getMessage();

			$this->logger->error(
				'PayPal subscription activation completion failed',
				array(
					'message' => $message,
					'orderId' => $id,
				),
			);
			wc_add_notice( __( 'Payment error', 'unzer-payments' ), 'error' );

			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}

	public function completePaymentMethodChange(): void {
		$id = absint( wp_unslash( $_GET['id'] ?? '0' ) );
		$type = sanitize_text_field( wp_unslash( $_GET['type'] ?? '' ) );
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );

		$subscription = null;

		try {
			if ( ! wp_verify_nonce($nonce, "payment_method_change_{$this->gateway->id}_{$id}_{$type}" ) ) {
				throw new Exception( 'Invalid subscription payment method change nonce.' );
			}

			$subscription = wcs_get_subscription( $id );

			if ( ! $subscription instanceof WC_Subscription ) {
				throw new Exception( 'Invalid subscription payment method setup.' );
			}

			$unzer = ( new PaymentService() )->getUnzerManager( $this->gateway );

			$this->gateway->update_subscription_payment_method( $subscription, $unzer->fetchPaymentType( $type ) );

			if ( WC_Subscriptions_Change_Payment_Gateway::will_subscription_update_all_payment_methods( $subscription ) ) {
				WC_Subscriptions_Change_Payment_Gateway::update_all_payment_methods_from_subscription(
					$subscription,
					$this->gateway->id,
				);
			}

			wc_add_notice( __( 'Payment method updated.', 'woocommerce-subscriptions' ), 'success' );

		} catch ( Throwable $e ) {
			$message = $e instanceof UnzerApiException ? $e->getMerchantMessage() : $e->getMessage();

			$this->logger->error( 'Unable to complete subscription payment method setup', array( 'message' => $message ) );

			wc_add_notice( __( 'Payment error', 'unzer-payments' ), 'error' );
		}

		wp_safe_redirect( $subscription instanceof WC_Subscription ? $subscription->get_view_order_url() : wc_get_checkout_url() );
		exit;
	}
}
