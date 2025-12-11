<?php

namespace UnzerPayments\Services;

use Exception;
use UnzerPayments\Gateways\AbstractGateway;
use UnzerPayments\Gateways\DirectDebitSecured;
use UnzerPayments\Gateways\Installment;
use UnzerPayments\Gateways\Invoice;
use UnzerPayments\Main;
use UnzerPayments\Util;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\AbstractUnzerResource;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Cancellation;
use UnzerSDK\Resources\TransactionTypes\Charge;
use UnzerSDK\Unzer;
use WC_Geolocation;
use WC_Order;

class PaymentService {


	protected LogService $logger;

	public function __construct() {
		$this->logger = new LogService();
	}

	public function getPublicKey( ?AbstractGateway $paymentGateway = null, $isB2B = null, $currency = null ): string {
		if ( $paymentGateway === null ) {
			return get_option( 'unzer_public_key' );
		} else {
			$keyTail = $this->getKeyTail( $paymentGateway, $isB2B, $currency );
			if ( $keyTail !== null ) {
				$publicKey = $paymentGateway->get_option( 'public_key_' . $keyTail );
			}
			if ( empty( $publicKey ) ) {
				$publicKey = $paymentGateway->get_public_key();
			}
			return $publicKey;
		}
	}

	/**
	 * @param AbstractGateway|null $paymentGateway
	 * @return Unzer
	 */
	public function getUnzerManager( ?AbstractGateway $paymentGateway = null, $isB2B = null, $currency = null ): Unzer {
		if ( $paymentGateway === null ) {
			$privateKey = get_option( 'unzer_private_key' );
		} else {
			$keyTail = $this->getKeyTail( $paymentGateway, $isB2B, $currency );
			if ( $keyTail !== null ) {
				$privateKey = $paymentGateway->get_option( 'private_key_' . $keyTail );
			}
			if ( empty( $privateKey ) ) {
				$privateKey = $paymentGateway->get_private_key();
			}
		}
		$unzer = new Unzer( $privateKey );
		$unzer->setClientIp( WC_Geolocation::get_ip_address() ?: null );
		return $unzer;
	}

	protected function getKeyTail( AbstractGateway $paymentGateway, $isB2B = null, $currency = null ): ?string {

			$result = null;
		if ( $paymentGateway instanceof Invoice && $currency !== null && $isB2B !== null ) {
			$result = strtolower( $currency ) . '_' . ( $isB2B ? 'b2b' : 'b2c' );
		} elseif ( $paymentGateway instanceof Installment && $currency !== null ) {
			// B2C only
			$result = strtolower( $currency ) . '_b2c';
		} elseif ( $paymentGateway instanceof DirectDebitSecured ) {
			// B2C EUR only
			$result = 'eur_b2c';
		}
			return $result;
	}

	/**
	 * @param WC_Order|null $order
	 * @return Unzer
	 */
	public function getUnzerManagerForOrder( WC_Order $order = null ): Unzer {
		$unzerPluginManager = Main::getInstance();
		$paymentGateway     = $unzerPluginManager->getPaymentGateway( $order->get_payment_method() );
		return $this->getUnzerManager( $paymentGateway, ! empty( $order->get_billing_company() ), $order->get_currency() );
	}

	public function performChargeOnAuthorization( $orderId, $amount = null ) {
		if ( empty( $orderId ) ) {
			throw new Exception( 'empty order id for performChargeOnAuthorization' );
		}
		$order = wc_get_order( $orderId );

		$paymentId = $order->get_meta( Main::ORDER_META_KEY_PAYMENT_ID, true );
		if ( empty( $paymentId ) ) {
			throw new Exception( 'This order has not been authorized with Unzer' );
		}
		$charge = new Charge();
		if ( $amount ) {
			$charge->setAmount( $amount );
		}
		$unzerManager = $this->getUnzerManagerForOrder( $order );
		$unzerManager->performChargeOnPayment( $paymentId, $charge );
	}

	/**
	 * @throws UnzerApiException
	 */
	public function performChargeForOrder(
		$orderId,
		AbstractGateway $paymentGateway,
		$paymentType,
		$chargeEditor = null
	): Charge {
		return $this->performChargeOrAuthorizationForOrder( $orderId, $paymentGateway, $paymentType, $chargeEditor );
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
	): Authorization {
		return $this->performChargeOrAuthorizationForOrder( $orderId, $paymentGateway, $paymentType, $chargeEditor, AbstractGateway::TRANSACTION_TYPE_AUTHORIZE );
	}

	/**
	 * @param $orderId
	 * @param AbstractGateway $paymentGateway
	 * @param $paymentType
	 * @param callable|null   $transactionEditor
	 * @param string          $type
	 * @return Charge|Authorization
	 * @throws UnzerApiException|Exception
	 */
	protected function performChargeOrAuthorizationForOrder(
		$orderId,
		AbstractGateway $paymentGateway,
		$paymentType,
		callable $transactionEditor = null,
		string $type = AbstractGateway::TRANSACTION_TYPE_CHARGE
	): AbstractTransactionType {
		$this->removeTransactionMetaData( $orderId );
		$order = wc_get_order( $orderId );
		$order->get_payment_method();
		$this->logger->debug( 'start authorization/charge for #' . $orderId . ' with ' . $order->get_payment_method() );
		$basket   = ( new OrderService() )->getBasket( $order );
		$customer = ( new CustomerService() )->getCustomerFromOrder( $order );

		if ( $paymentGateway instanceof Installment ) {
			$shippingAddress = $customer->getShippingAddress();
			$billingAddress  = $customer->getBillingAddress();
			if ( $shippingAddress->getName() !== $billingAddress->getName() ) {
				throw new Exception( esc_html__( 'Installment payment is only available for shipping and billing address with the same name', 'unzer-payments' ) );
			}
		}

		$unzer       = $this->getUnzerManagerForOrder( $order );
		$paymentType = class_exists( $paymentType ) ? $unzer->createPaymentType( new $paymentType() ) : $paymentType;

		try {
			$this->logger->debug(
				'try authorization/charge for #' . $orderId . ' with ' . $order->get_payment_method(),
				array(
					'type'     => $type,
					'basket'   => $basket->expose(),
					'customer' => $customer->expose(),
				)
			);
			if ( $type === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE ) {
				$authorization = new Authorization( $basket->getTotalValueGross(), $basket->getCurrencyCode(), $paymentGateway->get_confirm_url( $orderId ) );
				$authorization->setOrderId( $order->get_id() );
				if ( $transactionEditor !== null ) {
					$transactionEditor( $authorization );
				}
				$transactionObject = $unzer->performAuthorization( $authorization, $paymentType, $customer, ( new ShopService() )->getMetadata(), $basket );
				$order->update_meta_data( Main::ORDER_META_KEY_AUTHORIZATION_ID, $transactionObject->getId() );
			} else {
				$charge = new Charge( $basket->getTotalValueGross(), $basket->getCurrencyCode(), $paymentGateway->get_confirm_url( $orderId ) );
				$charge->setOrderId( $order->get_id() );
				if ( $transactionEditor !== null ) {
					$transactionEditor( $charge );
				}
				$transactionObject = $unzer->performCharge( $charge, $paymentType, $customer, ( new ShopService() )->getMetadata(), $basket );
				$order->update_meta_data( Main::ORDER_META_KEY_CHARGE_ID, $transactionObject->getId() );
			}

			$order->update_meta_data( Main::ORDER_META_KEY_PAYMENT_ID, $transactionObject->getPayment()->getId() );
			$order->update_meta_data( Main::ORDER_META_KEY_PAYMENT_SHORT_ID, $transactionObject->getShortId() );
			if ( method_exists( $paymentGateway, 'get_payment_information' ) ) {
				$order->update_meta_data( Main::ORDER_META_KEY_PAYMENT_INSTRUCTIONS, $paymentGateway->get_payment_information( $transactionObject ) );
			}
			$order->save_meta_data();
			$order->add_order_note( __( 'Unzer Payment ID: ', 'unzer-payments' ) . $transactionObject->getPayment()->getId() );
			$this->logger->debug(
				'authorization/charge result for #' . $orderId . ' with ' . $order->get_payment_method(),
				array(
					'id'    => $transactionObject->getId(),
					'class' => get_class( $transactionObject ),
				)
			);
		} catch ( Exception $e ) {
			$this->logger->error(
				'authorization/charge failed for #' . $orderId . ' with ' . $order->get_payment_method(),
				array(
					'msg'   => $e->getMessage(),
					'code'  => $e->getCode(),
					'trace' => $e->getTraceAsString(),
				)
			);
			$order->add_order_note( 'authorization/charge with ' . $order->get_payment_method() . ' failed: ' . $e->getMessage() );
			throw $e;
		}
		return $transactionObject;
	}


	/**
	 * @param $orderId
	 * @param AbstractGateway $paymentGateway
	 * @return Charge|Authorization|null
	 */
	public function getChargeOrAuthorizationFromOrder( $orderId, AbstractGateway $paymentGateway ): ?AbstractTransactionType {
		$order = wc_get_order( $orderId );
		if ( ! $order ) {
			$this->logger->warning( 'order not found', array( 'orderId' => $orderId ) );
			return null;
		}

		$unzerChargeId = $order->get_meta( Main::ORDER_META_KEY_CHARGE_ID, true );
		if ( empty( $unzerChargeId ) ) {
			$unzerAuthorizationId = $order->get_meta( Main::ORDER_META_KEY_AUTHORIZATION_ID, true );
			if ( empty( $unzerAuthorizationId ) ) {
				$this->logger->warning( 'could not find authorization/charge id in order #' . $orderId );
				return null;
			}
		}
		$unzerPaymentId = $order->get_meta( Main::ORDER_META_KEY_PAYMENT_ID, true );
		if ( empty( $unzerPaymentId ) ) {
			$this->logger->warning( 'could not find payment id in order #' . $orderId );
			return null;
		}
		$unzer = $this->getUnzerManagerForOrder( wc_get_order( $orderId ) );
		try {
			return ( $unzerChargeId ? $unzer->fetchChargeById( $unzerPaymentId, $unzerChargeId ) : $unzer->fetchAuthorization( $unzerPaymentId ) );
		} catch ( Exception $e ) {
			$this->logger->warning(
				'unable to fetch authorization/charge',
				array(
					'payment' => $unzerPaymentId,
					'charge'  => $unzerChargeId,
					'error'   => $e->getMessage(),
				)
			);
		}
		return null;
	}

	/**
	 * @return Cancellation
	 * @throws Exception|UnzerApiException
	 */
	public function performRefundOrReversal( $orderId, AbstractGateway $paymentGateway, $amount ): AbstractUnzerResource {
		$order = wc_get_order( $orderId );
		if ( ! $order ) {
			throw new Exception( 'Order not found' );
		}

		$errorMessages           = array();
		$amount                  = (float) $amount;
		$unzer                   = $this->getUnzerManagerForOrder( wc_get_order( $orderId ) );
		$paymentId               = $order->get_meta( Main::ORDER_META_KEY_PAYMENT_ID, true );
		$maxCaptureRefund        = 0;
		$numberOfRefundsPossible = 0;
		if ( empty( $paymentId ) ) {
			throw new Exception( 'This is not an Unzer payment' );
		}

		$payment = $unzer->fetchPayment( $paymentId );
		if ( $payment->getCharges() ) {
			/** @var Charge $charge */
			foreach ( $payment->getCharges() as $charge ) {
				try {
					if ( $charge->getTotalAmount() > 0 ) {
						++$numberOfRefundsPossible;
					}
					$maxCaptureRefund = max( $charge->getTotalAmount(), $maxCaptureRefund );
					if ( $charge->getTotalAmount() < $amount && ! Util::safeCompareAmount( $charge->getTotalAmount(), $amount ) ) {
						continue;
					}
					return $charge->cancel( $amount, null, 'fromWordpressOrder' . $orderId . '_' . uniqid() );
				} catch ( Exception $e ) {
					$this->logger->warning( 'refund on charge not possible: ' . $e->getMessage() );
					$errorMessages[] = $e->getMessage();
				}
			}
		}
		try {
			$authorization = $unzer->fetchAuthorization( $paymentId );

			return $authorization->cancel( $amount );
		} catch ( Exception $e ) {
			$this->logger->warning( 'refund on authorization not possible: ' . $e->getMessage() );
			$errorMessages[] = $e->getMessage();
		}
		throw new Exception(
			( $maxCaptureRefund > 0 ? wp_kses_post( sprintf( __( 'Unable to do refund: Maximum amount for single refund is %s.', 'unzer-payments' ), html_entity_decode( wp_strip_all_tags( wc_price( $maxCaptureRefund, array( 'currency' => $payment->getCurrency() ) ) ) ) ) ) : '' ) .
			( $numberOfRefundsPossible > 1 ? ' ' . esc_html( sprintf( __( 'However, you may refund in up to %s smaller chunks.', 'unzer-payments' ), $numberOfRefundsPossible ) ) : '' ) .
			( $errorMessages ? "\n\n" . esc_html__( 'Original error message: ', 'unzer-payments' ) . esc_html( implode( ' ', $errorMessages ) ) : '' )
		);
	}

	/**
	 * This is an exception for the invoice/paylater payment method
	 *
	 * @return Cancellation
	 * @throws UnzerApiException|Exception
	 */
	public function performRefundOrReversalOnPayment( $orderId, $amount ): AbstractUnzerResource {
		$order = wc_get_order( $orderId );
		if ( ! $order ) {
			throw new Exception( 'Order not found' );
		}

		$unzer     = $this->getUnzerManagerForOrder( wc_get_order( $orderId ) );
		$paymentId = $order->get_meta( Main::ORDER_META_KEY_PAYMENT_ID, true );

		if ( empty( $paymentId ) ) {
			throw new Exception( 'This is not an Unzer payment' );
		}

		$payment = $unzer->fetchPayment( $paymentId );
		if ( $payment->getCharges() ) {
			try {
				return $unzer->cancelChargedPayment( $paymentId, new Cancellation( $amount ) );
			} catch ( Exception $e ) {
				$this->logger->warning( 'Refund not possible: ' . $e->getMessage() );
				throw new Exception( esc_html__( 'Refund not possible', 'unzer-payments' ) . ': ' . esc_html( $e->getMessage() ) );
			}
		} else {
			try {
				return $unzer->cancelAuthorizedPayment( $paymentId, new Cancellation( $amount ) );
			} catch ( Exception $e ) {
				$this->logger->warning( 'Reversal not possible: ' . $e->getMessage() );
				throw new Exception( esc_html__( 'Reversal not possible', 'unzer-payments' ) . ': ' . esc_html( $e->getMessage() ) );
			}
		}
	}


	public function removeTransactionMetaData( $orderId ) {
		$order = wc_get_order( $orderId );
		if ( ! $order ) {
			return;
		}
		foreach ( Main::ORDER_META_KEYS as $metaKey ) {
			$order->delete_meta_data( $metaKey );
		}
	}
}
