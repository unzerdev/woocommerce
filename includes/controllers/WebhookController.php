<?php

namespace UnzerPayments\Controllers;

use UnzerPayments\Services\DashboardService;
use UnzerPayments\Services\LogService;
use UnzerPayments\Services\OrderService;
use UnzerSDK\Constants\WebhookEvents;

class WebhookController {


	const WEBHOOK_ROUTE_SLUG = 'unzer_webhook';

	const REGISTERED_EVENTS = array(
		WebhookEvents::CHARGE_CANCELED,
		WebhookEvents::AUTHORIZE_CANCELED,
		WebhookEvents::AUTHORIZE_SUCCEEDED,
		WebhookEvents::CHARGE_SUCCEEDED,
		WebhookEvents::PAYMENT_CHARGEBACK,
	);

	/**
	 * @var LogService
	 */
	private $logger;
	/**
	 * @var OrderService
	 */
	private $orderService;

	public function __construct() {
		$this->logger       = new LogService();
		$this->orderService = new OrderService();
	}

	public function receiveWebhook() {
		$data = json_decode( file_get_contents( 'php://input' ), true );
		if ( empty( $data ) ) {
			$this->logger->debug( 'empty webhook', array( 'server' => $_SERVER ) );
			status_header( 404 );
			wp_die();
		}

		if ( ! in_array( $data['event'], self::REGISTERED_EVENTS, true ) ) {
			$this->renderJson(
				array(
					'success' => true,
					'msg'     => 'event not relevant',
				)
			);
			die;
		}

		// TODO: evaluate if we have better options to prevent the race condition
		// problem here: there is a cache on the order object, so once we create it here, the status change is applied twice with duplicate order notes
		sleep( 2 );

		$this->logger->debug( 'webhook received', array( 'data' => $data ) );
		if ( empty( $data['paymentId'] ) ) {
			$this->logger->warning( 'no payment id in webhook event', array( 'webhook_data' => $data ) );
			return;
		}

		$orderId = $this->orderService->getOrderIdFromPaymentId( $data['paymentId'] );
		if ( empty( $orderId ) ) {
			$this->logger->warning( 'no order id for payment id in webhook event', array( 'webhook_data' => $data ) );
			return;
		}

		$eventHash = 'unzer_event_' . md5( $data['paymentId'] . '|' . $data['event'] );
		if ( get_transient( $eventHash ) ) {
			$this->logger->debug(
				'event already processed',
				array(
					'event'     => $data['event'],
					'paymentId' => $data['paymentId'],
				)
			);
			return;
		}
		set_transient( $eventHash, true, 5 );

		switch ( $data['event'] ) {
			case WebhookEvents::CHARGE_CANCELED:
			case WebhookEvents::AUTHORIZE_CANCELED:
				$this->handleCancel( $data['paymentId'], $orderId );
				break;
			case WebhookEvents::AUTHORIZE_SUCCEEDED:
				$this->handleAuthorizeSucceeded( $data['paymentId'], $orderId );
				break;
			case WebhookEvents::CHARGE_SUCCEEDED:
				$this->handleChargeSucceeded( $data['paymentId'], $orderId );
				break;
			case WebhookEvents::PAYMENT_CHARGEBACK:
				$this->handleChargeback( $data['paymentId'], $orderId );
				break;
		}
		$this->renderJson( array( 'success' => true ) );
	}

	protected function renderJson( array $data ) {
		header( 'Content-Type: application/json' );
		echo json_encode( $data );
		die;
	}

	public function handleChargeback( $paymentId, $orderId ) {
		$this->logger->debug(
			'webhook handleChargeback',
			array(
				'paymentId' => $paymentId,
				'orderId'   => $orderId,
			)
		);
		$order = wc_get_order( $orderId );

		if ( $order ) {
			$orderStatus = get_option( 'unzer_chargeback_order_status' );
			$this->logger->debug(
				'chargeback order',
				array(
					'order'  => $order->get_id(),
					'status' => $orderStatus,
				)
			);
			if ( $orderStatus ) {
				$order->update_status( $orderStatus, __( 'Chargeback received', 'unzer-payments' ) );
			}
		} else {
			$this->logger->debug( 'no order for chargeback', array( 'orderId' => $orderId ) );
		}
		// trigger admin notice
		( new DashboardService() )->addError( 'chargeback', array( $orderId ) );
	}

	private function handleCancel( $paymentId, $orderId ) {
		$this->logger->debug(
			'webhook handleCancel',
			array(
				'paymentId' => $paymentId,
				'orderId'   => $orderId,
			)
		);
		$this->orderService->updateRefunds( $paymentId, $orderId );
	}

	private function handleAuthorizeSucceeded( $paymentId, $orderId ) {
		$this->logger->debug(
			'webhook handleAuthorizeSucceeded',
			array(
				'paymentId' => $paymentId,
				'orderId'   => $orderId,
			)
		);
		$order = wc_get_order( $orderId );
		if ( empty( $order->get_transaction_id() ) ) {
			$this->orderService->setOrderAuthorized( $order, $paymentId );
		}
	}

	private function handleChargeSucceeded( $paymentId, $orderId ) {
		$this->logger->debug(
			'webhook handleChargeSucceeded',
			array(
				'paymentId' => $paymentId,
				'orderId'   => $orderId,
			)
		);
		$order = wc_get_order( $orderId );
		$order->payment_complete( $paymentId );
		$order->set_transaction_id( $paymentId );
		if ( get_option( 'unzer_captured_order_status' ) ) {
			$order->set_status( get_option( 'unzer_captured_order_status' ) );
		}
		$order->save();
	}
}
