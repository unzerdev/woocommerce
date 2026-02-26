<?php

namespace UnzerPayments\Controllers;

use Exception;
use UnzerPayments\Gateways\DirectDebitSecured;
use UnzerPayments\Gateways\Installment;
use UnzerPayments\Gateways\Invoice;
use UnzerPayments\Main;
use UnzerPayments\Services\DashboardService;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Services\WebhookManagementService;
use UnzerPayments\Util;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Cancellation;
use UnzerSDK\Resources\TransactionTypes\Charge;
use UnzerSDK\Unzer;
use WP_Post;

class AdminController {


	const GET_ORDER_TRANSACTIONS_ROUTE_SLUG = 'admin_unzer_get_order_transactions';
	const CHARGE_ROUTE_SLUG                 = 'admin_unzer_charge';
	const WEBHOOK_MANAGEMENT_ROUTE_SLUG     = 'admin_unzer_webhooks';
	const KEY_VALIDATION_ROUTE_SLUG         = 'admin_unzer_key_validation';
	const NOTIFICATION_SLUG                 = 'admin_unzer_notification';

	public function getOrderTransactions() {
		try {
			if ( ! current_user_can( 'edit_shop_orders' ) || empty( $_GET['order_id'] ) ) {
				wp_die();
			}
			$order     = wc_get_order( (int) $_GET['order_id'] );
			$paymentId = $order->get_meta( Main::ORDER_META_KEY_PAYMENT_ID, true );

			if ( empty( $paymentId ) ) {
				$this->renderJson( array() );
			}

			$unzer        = ( new PaymentService() )->getUnzerManagerForOrder( $order );
			$payment      = $unzer->fetchPayment( $paymentId );
			$currency     = $payment->getCurrency();
			$transactions = array();
			if ( $payment->getAuthorization() ) {
				$transactions[] = $payment->getAuthorization();
				if ( $payment->getAuthorization()->getCancellations() ) {
					$transactions = array_merge( $transactions, $payment->getAuthorization()->getCancellations() );
				}
			}
			if ( $payment->getCharges() ) {
				foreach ( $payment->getCharges() as $charge ) {
					$transactions[] = $charge;
					if ( $charge->getCancellations() ) {
						$transactions = array_merge( $transactions, $charge->getCancellations() );
					}
				}
			}
			if ( $payment->getReversals() ) {
				foreach ( $payment->getReversals() as $reversal ) {
					$transactions[] = $reversal;
				}
			}
			if ( $payment->getRefunds() ) {
				foreach ( $payment->getRefunds() as $refund ) {
					$transactions[] = $refund;
				}
			}
			// $transactions = array_merge($transactions, $payment->getCharges(), $payment->getRefunds(), $payment->getReversals());
			$transactionTypes = array(
				Cancellation::class  => 'cancellation',
				Charge::class        => 'charge',
				Authorization::class => 'authorization',
			);

			$transactions = array_map(
				function ( AbstractTransactionType $transaction ) use ( $transactionTypes, $currency ) {
					$return         = $transaction->expose();
					$class          = get_class( $transaction );
					$return['type'] = $transactionTypes[ $class ] ?? $class;
					$return['time'] = $transaction->getDate();
					if ( method_exists( $transaction, 'getAmount' ) && method_exists( $transaction, 'getCurrency' ) ) {
						$return['amount'] = wc_price( $transaction->getAmount(), array( 'currency' => $transaction->getCurrency() ) );
					} elseif ( isset( $return['amount'] ) ) {
						$return['amount'] = wc_price( $return['amount'], array( 'currency' => $currency ) );
					}
					$status           = $transaction->isSuccess() ? 'success' : 'error';
					$status           = $transaction->isPending() ? 'pending' : $status;
					$return['status'] = $status;

					return $return;
				},
				$transactions
			);
			usort(
				$transactions,
				function ( $a, $b ) {
					return strcmp( $a['time'], $b['time'] );
				}
			);

			$data = array(
				'id'             => $payment->getId(),
				'paymentMethod'  => $order->get_payment_method(),
				'amount'         => wc_price( $payment->getAmount()->getTotal(), array( 'currency' => $payment->getAmount()->getCurrency() ) ),
				'charged'        => wc_price( $payment->getAmount()->getCharged(), array( 'currency' => $payment->getAmount()->getCurrency() ) ),
				'cancelled'      => wc_price( $payment->getAmount()->getCanceled(), array( 'currency' => $payment->getAmount()->getCurrency() ) ),
				'remaining'      => wc_price( $payment->getAmount()->getRemaining(), array( 'currency' => $payment->getAmount()->getCurrency() ) ),
				'remainingPlain' => $payment->getAmount()->getRemaining(),
				'transactions'   => $transactions,
				'status'         => $payment->getStateName(),
				'raw'            => print_r( $payment, true ),
			);

			$this->renderJson( $data );
		} catch ( Exception $e ) {
			$this->renderJson(
				array(
					'error' => $e->getMessage(),
				)
			);
		}
	}


	public function doCharge() {

		try {
			$orderId = Util::getNonceCheckedPostValue( 'order_id' );
			$amount  = Util::getNonceCheckedPostValue( 'amount' );
			if ( empty( $orderId ) ) {
				throw new Exception( 'Order ID is missing' );
			}
			$orderId = (int) $orderId;
			$amount  = $amount ? (float) $amount : null;
			( new PaymentService() )->performChargeOnAuthorization( $orderId, $amount );
		} catch ( Exception $e ) {
			$this->renderJson( array( 'error' => $e->getMessage() ) );
		}
		$this->renderJson( array( 'success' => 1 ) );
	}

	public function webhookManagement() {
		try {
			$slug          = Util::getNonceCheckedPostValue( 'slug' );
			$action        = Util::getNonceCheckedPostValue( 'action' );
			$paymentMethod = Util::getNonceCheckedPostValue( 'unzer_payment_method' );
			$service       = new WebhookManagementService( $slug, $paymentMethod );
			if ( empty( $action ) ) {
				$this->renderJson(
					array(
						'webhooks'     => $service->fetchAllWebhooks(),
						'isRegistered' => $service->isWebhookRegistered(),
					)
				);
			} else {
				switch ( $action ) {
					case 'delete':
						$service->deleteWebhook( Util::getNonceCheckedPostValue( 'id' ) );
						break;
					case 'add':
						$service->addCurrentWebhook();
						break;
				}
			}
		} catch ( Exception $e ) {
			$this->renderJson(
				array(
					'error' => $e->getMessage(),
				)
			);
		}
	}

	public function handleNotification() {
		$dashboardService   = new DashboardService();
		$removeNotification = Util::getNonceCheckedPostValue( 'remove_notification' );
		if ( $removeNotification ) {
			$dashboardService->removeNotification( $removeNotification );
			$this->renderJson(
				array(
					'success' => true,
				)
			);
		}
		$this->renderJson(
			array(
				'msg' => 'nothing to do',
			)
		);
	}

	public function validateKeypair() {
		try {
			$paymentService = new PaymentService();
			$slug           = Util::getNonceCheckedPostValue( 'slug' );
			$gateway        = Util::getNonceCheckedPostValue( 'gateway' );
			if ( ! empty( $slug ) && ! empty( $gateway ) ) {

				if ( $gateway === Installment::GATEWAY_ID ) {
					$paymentGateway = new Installment();
				} elseif ( $gateway === Invoice::GATEWAY_ID ) {
					$paymentGateway = new Invoice();
				} elseif ( $gateway === DirectDebitSecured::GATEWAY_ID ) {
					$paymentGateway = new DirectDebitSecured();
				} else {
					throw new Exception( 'unknown payment method' );
				}
				$privateKey = $paymentGateway->get_option( 'private_key_' . $slug );
				$publicKey  = $paymentGateway->get_option( 'public_key_' . $slug );
				if ( empty( $privateKey ) || empty( $publicKey ) ) {
					throw new Exception( 'missing key' );
				}
				$unzerManager = new Unzer( $privateKey );
			} else {
				$unzerManager = $paymentService->getUnzerManager( null );
				$publicKey    = get_option( 'unzer_public_key' );
			}

			$keyPair = $unzerManager->fetchKeypair();
			if ( ! empty( $keyPair->getPublicKey() ) && $keyPair->getPublicKey() === $publicKey ) {
				$this->renderJson(
					array(
						'isValid' => 1,
					)
				);
			} else {
				throw new Exception();
			}
		} catch ( Exception $e ) {
			$this->renderJson(
				array(
					'isValid' => 0,
					'msg'     => $e->getMessage(),
				)
			);
		}
	}

	public static function renderTransactionTable( $postOrOrderObject ) {
		$order = ( $postOrOrderObject instanceof WP_Post ) ? wc_get_order( $postOrOrderObject->ID ) : $postOrOrderObject;
		if ( ! $order || ! ( $order instanceof \WC_Order ) ) {
			return;
		}
		include UNZER_PLUGIN_PATH . 'html/admin/transactions.php';
	}

	public static function renderGlobalSettingsStart() {
		if ( empty( $_GET['section'] ) || $_GET['section'] !== 'unzer_general' ) {
			return;
		}
		include UNZER_PLUGIN_PATH . 'html/admin/global-settings-start.php';
	}

	public static function renderGlobalSettingsEnd() {
		if ( empty( $_GET['section'] ) || $_GET['section'] !== 'unzer_general' ) {
			return;
		}
		include UNZER_PLUGIN_PATH . 'html/admin/global-settings-end.php';
	}

	public static function renderWebhookManagement() {
		if ( empty( $_GET['section'] ) || $_GET['section'] !== 'unzer_general' ) {
			return;
		}
		include UNZER_PLUGIN_PATH . 'html/admin/webhooks.php';
	}

	protected function renderJson( array $data ) {
		header( 'Content-Type: application/json' );
		echo wp_json_encode( Util::escape_array_html( $data ) );
		die;
	}
}
