<?php

namespace UnzerPayments\Controllers;

use Exception;
use UnzerPayments\Gateways\AbstractGateway;
use UnzerPayments\Main;
use UnzerPayments\Services\CustomerService;
use UnzerPayments\Services\LogService;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Util;
use UnzerSDK\Constants\PaymentState;
use WC_Order;

class CheckoutController {

	const GET_UNZER_CUSTOMER_SLUG = 'get-unzer-customer';

	public function confirm() {
		$logger = ( new LogService() );
		$logger->debug( 'CheckoutController::confirm()' );
		$orderId = (int) WC()->session->get( 'order_awaiting_payment' );
		if ( empty( $orderId ) ) {
			$logger->debug( 'order id from unzer_confirm_order_id' );
			$orderId = (int) WC()->session->get( 'unzer_confirm_order_id' );
			WC()->session->set( 'unzer_confirm_order_id', null );
		}
		if ( empty( $orderId ) ) {
			$logger->debug( 'order id from store_api_draft_order' );
			$orderId = (int) WC()->session->get( 'store_api_draft_order' );
		}
		if ( empty( $orderId ) ) {
			$logger->debug( 'order id from get query' );
			$orderId = (int) ( $_GET['unzer_confirm_order_id'] ?? 0 );
		}
		if ( empty( $orderId ) ) {
			$logger->error( 'empty order id for confirmation endpoint' );
			wp_redirect( wc_get_checkout_url() );
			die;
		}
		$order              = wc_get_order( $orderId );
		$unzerPluginManager = Main::getInstance();
		$paymentGateway     = $unzerPluginManager->getPaymentGateway( $order->get_payment_method() );
		if ( ! $paymentGateway ) {
			$order->update_status( 'failed' );
			$logger->error( 'payment method unknown', $order->get_payment_method() );
			wc_add_notice( __( 'Payment error', 'unzer-payments' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			die;
		}
		$paymentService = new PaymentService();
		$transaction    = $paymentService->getChargeOrAuthorizationFromOrder( $orderId, $paymentGateway );

		if ( ! $transaction ) {
			$order->update_status( 'failed' );
			$paymentService->removeTransactionMetaData( $orderId );
			$logger->error( 'no authorization/charge found', array( 'order' => $orderId ) );
			wc_add_notice( __( 'Payment error', 'unzer-payments' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			die;
		}

		if ( $transaction->getPayment()->getState() === PaymentState::STATE_CANCELED ) {
			$paymentService->removeTransactionMetaData( $orderId );
			$logger->debug(
				'payment cancelled',
				array(
					'order'       => $orderId,
					'transaction' => $transaction->expose(),
					'reason'      => $transaction->getMessage()->getMerchant(),
				)
			);
			$order->update_status( 'failed' );
			wc_add_notice( __( 'Payment cancelled', 'unzer-payments' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			die;
		}

		if ( method_exists( $paymentGateway, 'maybeSavePaymentInstrument' ) ) {
			if ( WC()->session->get( 'save_payment_instrument' ) ) {
				$paymentGateway->maybeSavePaymentInstrument( $transaction->getPayment()->getPaymentType()->getId() );
			}
		}
		$orderService = new OrderService();
		try {
			$orderService->processPaymentStatus( $transaction, $order );
			self::clearSessionData();
			wp_redirect( $order->get_checkout_order_received_url() );
		} catch ( Exception $e ) {
			$logger->error(
				'exception in trying to process payment status',
				array(
					'orderId'     => $order->get_id(),
					'transaction' => $transaction->expose(),
					'exception'   => $e->getMessage(),
				)
			);
			$order->update_status( 'failed' );
			wc_add_notice( __( 'Payment error', 'unzer-payments' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
		}
		WC()->session->set( 'save_payment_instrument', false );
		die;
	}

	public function getUnzerCustomerData() {
		$paymentMethodGatewayId = Util::getNonceCheckedPostValue( 'payment_method' );
		$cartData               = json_decode( Util::getNonceCheckedPostValue( 'data' ), true );
		$paymentMethodGateway   = Main::getInstance()->getPaymentGateway( $paymentMethodGatewayId );
		$billingData            = array();
		foreach ( $cartData['billingAddress'] as $k => $v ) {
			$billingData[ 'billing_' . $k ] = $v;
		}
		$unzerCustomer = ( new CustomerService() )->getCustomerFromData( $paymentMethodGateway, $billingData );

		$paymentService = new PaymentService();
		$publicKey      = $paymentService->getPublicKey( $paymentMethodGateway, ! empty( $billingData['billing_company'] ), $cartData['totals']['currency_code'] );

		$this->renderJson(
			array(
				'customer'  => $unzerCustomer->expose(),
				'publicKey' => $publicKey,
			)
		);
	}

	/**
	 * @param WC_Order $order
	 * @return void
	 */
	public static function checkoutSuccess( $order ) {
		self::clearSessionData();
		( new OrderService() )->printPaymentInstructionsHtml( $order );
	}

	protected function renderJson( array $data ) {
		header( 'Content-Type: application/json' );
		echo wp_json_encode( Util::escape_array_html( $data ) );
		die;
	}

	protected static function clearSessionData() {
		setcookie( CustomerService::SESSION_KEY_USER_ID, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
	}
}
