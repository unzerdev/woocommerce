<?php

namespace UnzerPayments\Controllers;

use Exception;
use UnzerPayments\Gateways\ApplePay;
use UnzerPayments\Gateways\Prepayment;
use UnzerPayments\Main;
use UnzerPayments\Services\LogService;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Util;
use UnzerSDK\Adapter\ApplepayAdapter;
use UnzerSDK\Constants\PaymentState;
use UnzerSDK\Resources\ExternalResources\ApplepaySession;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use WC_Order;

class CheckoutController {

	const APPLE_PAY_MERCHANT_VALIDATION_ROUTE_SLUG = 'unzer_apple_pay_merchant_validation';

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
			$logger->error( 'empty order id for confirmation endpoint' );
			wp_redirect( wc_get_checkout_url() );
			die;
		}
		$order              = wc_get_order( $orderId );
		$unzerPluginManager = Main::getInstance();
		$paymentGateway     = $unzerPluginManager->getPaymentGateway( $order->get_payment_method() );
		if ( ! $paymentGateway ) {
			$logger->error( 'payment method unknown', $order->get_payment_method() );
			wc_add_notice( __( 'Payment error', 'unzer-payments' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			die;
		}
		$paymentService = new PaymentService();
		$transaction    = $paymentService->getChargeOrAuthorizationFromOrder( $orderId, $paymentGateway );

		if ( ! $transaction ) {
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
			wc_add_notice( __( 'Payment cancelled', 'unzer-payments' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			die;
		}

		if ( method_exists( $paymentGateway, 'isSaveInstruments' ) ) {
			if ( WC()->session->get( 'save_payment_instrument' ) ) {
				$paymentGateway->maybeSavePaymentInstrument( $transaction->getPayment()->getPaymentType()->getId() );
			}
		}
		$orderService = new OrderService();
		try {
			$orderService->processPaymentStatus( $transaction, $order );
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
			wc_add_notice( __( 'Payment error', 'unzer-payments' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
		}
		WC()->session->set( 'save_payment_instrument', false );
		die;
	}

	/**
	 * @param WC_Order $order
	 * @return void
	 */
	public static function checkoutSuccess( $order ) {
		$paymentInstructions = $order->get_meta( Main::ORDER_META_KEY_PAYMENT_INSTRUCTIONS, true );
		if ( $paymentInstructions ) {
			if ( $order->get_payment_method() !== Prepayment::GATEWAY_ID ) {
				return;
			}
			echo '<div id="unzer-payment-instructions" style="margin:20px 0;">' . wp_kses_post( $paymentInstructions ) . '</div>';
		}
	}

	public function validateApplePayMerchant() {
		$applePayGateway = new ApplePay();
		$applePaySession = new ApplepaySession(
			$applePayGateway->get_option( 'merchant_id' ),
			get_bloginfo( 'name' ),
			isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : get_bloginfo( 'url' ),
		);
		$appleAdapter    = new ApplepayAdapter();

		$certificateTempPath = tempnam( sys_get_temp_dir(), 'WpUnzerPayments' );
		$keyTempPath         = tempnam( sys_get_temp_dir(), 'WpUnzerPayments' );

		if ( ! $certificateTempPath || ! $keyTempPath ) {
			throw new Exception( 'Error on temporary file creation' );
		}

		file_put_contents( $certificateTempPath, get_option( 'unzer_apple_pay_merchant_id_certificate' ) );
		file_put_contents( $keyTempPath, get_option( 'unzer_apple_pay_merchant_id_key' ) );

		try {
			$appleAdapter->init( $certificateTempPath, $keyTempPath );
			$merchantValidationUrl = urldecode( Util::getNonceCheckedPostValue( 'validation_url' ) );
			try {
				$validationResponse = $appleAdapter->validateApplePayMerchant(
					$merchantValidationUrl,
					$applePaySession
				);
				( new LogService() )->debug( 'apple pay validation response', array( 'response' => $validationResponse ) );
				$this->renderJson( array( 'response' => $validationResponse ) );
			} catch ( Exception $e ) {
				( new LogService() )->error(
					'merchant validation failed',
					array(
						'error'                 => $e->getMessage(),
						'merchantValidationUrl' => $merchantValidationUrl,
						'GET'                   => print_r( $_GET, true ),
					)
				);
			}
		} finally {
			unlink( $keyTempPath );
			unlink( $certificateTempPath );
		}
	}

	protected function renderJson( array $data ) {
		header( 'Content-Type: application/json' );
		echo json_encode( $data );
		die;
	}
}
