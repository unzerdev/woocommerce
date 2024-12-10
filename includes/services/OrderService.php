<?php

namespace UnzerPayments\Services;

use Exception;
use UnzerPayments\Gateways\Prepayment;
use UnzerPayments\Main;
use UnzerPayments\Util;
use UnzerSDK\Constants\BasketItemTypes;
use UnzerSDK\Constants\CompanyRegistrationTypes;
use UnzerSDK\Resources\Basket;
use UnzerSDK\Resources\Customer;
use UnzerSDK\Resources\EmbeddedResources\BasketItem;
use UnzerSDK\Resources\EmbeddedResources\CompanyInfo;
use UnzerSDK\Resources\Payment;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Cancellation;
use UnzerSDK\Resources\TransactionTypes\Charge;
use WC_Order;
use WC_Order_Item_Coupon;
use WC_Order_Item_Fee;
use WC_Order_Refund;

class OrderService {



	const ORDER_STATUS_CANCELLED           = 'wc-cancelled';
	const ORDER_STATUS_CHARGEBACK          = 'wc-unzer-chargeback';
	const ORDER_STATUS_AUTHORIZED          = 'wc-unzer-authorized';
	const ORDER_STATUS_WAITING_FOR_PAYMENT = 'wc-unzer-waiting';
	/**
	 * @var LogService
	 */
	protected $logger;

	public function __construct() {
		$this->logger = new LogService();
	}

	/**
	 * @param int|WC_Order $order
	 * @return Basket
	 */
	public function getBasket( $order ): Basket {
		$order = is_object( $order ) ? $order : wc_get_order( $order );

		$basket = ( new Basket() )
			->setTotalValueGross( $order->get_total() )
			->setOrderId( $order->get_id() )
			->setCurrencyCode( $order->get_currency() );

		$basketItems = array();
		/** @var \WC_Order_Item_Product $orderItem */
		foreach ( $order->get_items() as $orderItem ) {
			$basketItem = ( new BasketItem() )
				->setTitle( $orderItem->get_name() )
				->setQuantity( $orderItem->get_quantity() )
				->setType( BasketItemTypes::GOODS );
			$price      = 0;
			$vatRate    = 0;
			if ( is_callable( array( $orderItem, 'get_total' ) ) ) {
				$totalLinePrice = $orderItem->get_subtotal() + $orderItem->get_subtotal_tax();
				$price          = round( $orderItem->get_quantity() ? $totalLinePrice / $orderItem->get_quantity() : 0, 2 );
				if ( $orderItem->get_subtotal() > 0 ) {
					$vatRate = round( $orderItem->get_subtotal_tax() / $orderItem->get_subtotal() * 100, 1 );
				}
			}
			$discount = $this->getDiscountFromOrderItem( $orderItem );
			if ( $discount ) {
				$basketItem->setAmountDiscountPerUnitGross( $discount );
				$price += $discount;
			}
			if ( $price != 0 ) {
				$basketItem
					->setAmountPerUnitGross( $price )
					->setVat( $vatRate );
				$basketItems[] = $basketItem;
			}
		}

		if ( $order->get_shipping_total() ) {
			$vatRate       = round( $order->get_shipping_tax() / $order->get_shipping_total() * 100, 1 );
			$basketItem    = ( new BasketItem() )
				->setTitle( $order->get_shipping_method() )
				->setQuantity( 1 )
				->setType( BasketItemTypes::SHIPMENT )
				->setAmountPerUnitGross( Util::round( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax() ) )
				->setVat( $vatRate );
			$basketItems[] = $basketItem;
		}

		$isVoucherDeducted = false;
		$coupons           = $order->get_coupons();
		if ( $coupons ) {
			/** @var WC_Order_Item_Coupon $coupon */
			foreach ( $coupons as $coupon ) {
				$amountDiscountNet = (float) $coupon->get_discount();
				$discountTax       = (float) $coupon->get_discount_tax();
				$amountDiscount    = $amountDiscountNet + $discountTax;

				if ( round( $amountDiscount, 2 ) <= 0 ) {
					if ( ! $isVoucherDeducted ) {
						$isVoucherDeducted = true;
						$amountDiscountNet = (float) $order->get_total_discount();
						$discountTax       = (float) $order->get_discount_tax();
						$amountDiscount    = $amountDiscountNet + $discountTax;
					}
				}

				// if ( round( $amountDiscount, 2 ) <= 0 ) {
				// $couponEntity = new \WC_Coupon( $coupon->get_code() );
				// if ( $couponEntity->get_amount() ) {
				// $amountDiscount    = $couponEntity->get_amount();
				// $amountDiscountNet = $amountDiscount;
				// $discountTax       = 0;
				// }
				// }

				if ( round( $amountDiscount, 2 ) <= 0 ) {
					continue;
				}

				$vatRate = round( $discountTax / $amountDiscountNet * 100, 1 );

				$basketItem    = ( new BasketItem() )
					->setTitle( $coupon->get_code() )
					->setQuantity( 1 )
					->setType( BasketItemTypes::VOUCHER )
					->setAmountDiscountPerUnitGross( Util::round( $amountDiscount, 2 ) )
					->setVat( $vatRate );
				$basketItems[] = $basketItem;
			}
		}

		$fees = $order->get_fees();
		if ( $fees ) {
			/** @var WC_Order_Item_Fee $fee */
			foreach ( $fees as $fee ) {
				$feeAmountNet   = (float) $fee->get_total();
				$feeAmountTax   = (float) $fee->get_total_tax();
				$feeAmountTotal = $feeAmountNet + $feeAmountTax;

				if ( Util::safeCompareAmount( $feeAmountTotal, 0 ) ) {
					continue;
				}

				$vatRate = Util::round( abs( $feeAmountTax / $feeAmountNet * 100 ), 1 );

				$basketItem = ( new BasketItem() )
					->setTitle( $fee->get_name() )
					->setQuantity( 1 )
					->setType( $feeAmountTotal > 0 ? BasketItemTypes::GOODS : BasketItemTypes::VOUCHER )
					->setVat( $vatRate );
				if ( $feeAmountTotal > 0 ) {
					$basketItem->setAmountPerUnitGross( Util::round( abs( $feeAmountTotal ) ) );
				} else {
					$basketItem->setAmountDiscountPerUnitGross( Util::round( abs( $feeAmountTotal ) ) );
				}
				$basketItems[] = $basketItem;
			}
		}

		$totalLeft = $order->get_total();
		foreach ( $basketItems as $basketItem ) {
			$totalLeft -= $basketItem->getAmountPerUnitGross() * $basketItem->getQuantity();
			$totalLeft += $basketItem->getAmountDiscountPerUnitGross() * $basketItem->getQuantity();
		}

		if ( number_format( $totalLeft, 2 ) !== '0.00' ) {
			if ( $totalLeft < 0 ) {
				$basketItem    = ( new BasketItem() )
					->setTitle( '---' )
					->setQuantity( 1 )
					->setType( BasketItemTypes::VOUCHER )
					->setAmountDiscountPerUnitGross( Util::round( $totalLeft * -1 ) )
					->setVat( 0 );
				$basketItems[] = $basketItem;
			} else {
				$basketItem    = ( new BasketItem() )
					->setTitle( '---' )
					->setQuantity( 1 )
					->setType( BasketItemTypes::GOODS )
					->setAmountPerUnitGross( Util::round( $totalLeft ) );
				$basketItems[] = $basketItem;
			}
		}
		$basket->setBasketItems( $basketItems );

		return $basket;
	}

	/**
	 * @param \WC_Order_Item_Product $orderItem
	 * @return float
	 */
	private function getDiscountFromOrderItem( $orderItem ) {

		if ( ! is_callable( array( $orderItem, 'get_total' ) ) ) {
			return 0;
		}

		if ( ! is_callable( array( $orderItem, 'get_product' ) ) || ! $orderItem->get_product() || ! $orderItem->get_product()->get_regular_price() ) {
			return 0;
		}
		$totalLinePrice     = (float) $orderItem->get_subtotal() + (float) $orderItem->get_subtotal_tax();
		$singlePrice        = $totalLinePrice / ( $orderItem->get_quantity() ? $orderItem->get_quantity() : 1 );
		$regularSinglePrice = wc_get_price_including_tax( $orderItem->get_product() );
		if ( ! Util::safeCompareAmount( $singlePrice, $regularSinglePrice ) && $regularSinglePrice > $singlePrice ) {
			return Util::round( $regularSinglePrice - $singlePrice );
		}
		return 0;
	}

	/**
	 * @param int|WC_Order $order
	 * @return Customer
	 */
	public function getCustomer( $order ): Customer {
		$order = is_object( $order ) ? $order : wc_get_order( $order );

		if ( is_user_logged_in() ) {
			$paymentService = new PaymentService();
			$unzer          = $paymentService->getUnzerManagerForOrder( $order );
			try {
				$customer = $unzer->fetchCustomerByExtCustomerId( 'wp-' . wp_get_current_user()->ID );
			} catch ( Exception $e ) {
				// no worries, we cover this by creating a new customer
			}
		}

		if ( empty( $customer ) ) {
			$customer = new Customer();
			if ( is_user_logged_in() ) {
				$customer->setCustomerId( 'wp-' . wp_get_current_user()->ID );
			}
		}

		$customer
			->setFirstname( $order->get_billing_first_name() )
			->setLastname( $order->get_billing_last_name() )
			->setPhone( $order->get_billing_phone() )
			->setCompany( $order->get_billing_company() )
			->setEmail( $order->get_billing_email() );

		$dob = $order->get_meta( Main::ORDER_META_KEY_DATE_OF_BIRTH );
		if ( empty( $dob ) ) {
			$dob = Util::getDobFromPost();
		}

		if ( ! empty( $dob ) ) {
			$customer->setBirthDate( gmdate( 'Y-m-d', strtotime( $dob ) ) );
		}

		if ( $order->get_billing_company() ) {
			$companyType = $order->get_meta( Main::ORDER_META_KEY_COMPANY_TYPE );
			if ( empty( $companyType ) ) {
				$companyType = Util::getCompanyTypeFromPost();
			}
			if ( ! empty( $companyType ) ) {
				$companyInfo = ( new CompanyInfo() )
					->setCompanyType( $companyType )
					->setRegistrationType( CompanyRegistrationTypes::REGISTRATION_TYPE_NOT_REGISTERED )
					->setFunction( 'OWNER' );
				// ->setRegistrationType(CompanyRegistrationTypes::REGISTRATION_TYPE_REGISTERED)
				// ->setCommercialRegisterNumber(uniqid());
				$customer->setCompanyInfo( $companyInfo );
			}
		}

		$this->setAddresses( $customer, $order );
		return $customer;
	}

	/**
	 * @param Authorization|Charge $transaction
	 * @return void
	 * @throws \WC_Data_Exception|Exception
	 */
	public function processPaymentStatus( AbstractTransactionType $transaction, WC_Order $order ) {
		if ( ! $this->areAmountsEqual( $order, $transaction->getPayment() ) ) {
			$this->logger->error(
				'amounts do not match',
				array(
					'transaction' => $transaction->expose(),
					'orderId'     => $order->get_id(),
					'orderAmount' => $order->get_total(),
				)
			);
			throw new Exception( 'amounts do not match: ' . esc_html( $transaction->getPayment()->getAmount()->getTotal() ) . ' != ' . esc_html( $order->get_total() ) );
		}
		$logger = ( new LogService() );
		if ( $transaction instanceof Authorization ) {
			$logger->debug( 'OrderService::processPaymentStatus() - set authorized' );
			$this->setOrderAuthorized( $order, $transaction->getPayment()->getId() );
		} else {
			$logger->debug( 'OrderService::processPaymentStatus() - payment_complete' );
			$order->payment_complete( $transaction->getPayment()->getId() );
			$order->set_transaction_id( $transaction->getPayment()->getId() );
			if ( get_option( 'unzer_captured_order_status' ) ) {
				$order->set_status( get_option( 'unzer_captured_order_status' ) );
			}
			$order->save();
		}
	}

	/**
	 * @param WC_Order    $order
	 * @param string|null $transactionId
	 * @return bool
	 */
	public function setOrderAuthorized( WC_Order $order, ?string $transactionId = null ): bool {
		// this is almost a copy of WC_Order::payment_complete()

		try {
			if ( WC()->session ) {
				WC()->session->set( 'order_awaiting_payment', false );
			}

			if ( ! empty( $transactionId ) ) {
				$order->set_transaction_id( $transactionId );
			}
			if ( get_option( 'unzer_authorized_order_status' ) ) {
				$order->set_status( get_option( 'unzer_authorized_order_status' ) );
			} else {
				$order->set_status( apply_filters( 'woocommerce_payment_complete_order_status', $order->needs_processing() ? 'processing' : 'completed', $order->get_id(), $order ) );
			}

			$order->set_date_paid( null );
			$order->save();

		} catch ( Exception $e ) {
			/**
			 * If there was an error completing the payment, log to a file and add an order note so the admin can take action.
			 */
			$logger = wc_get_logger();
			$logger->error(
				sprintf(
					'Error completing payment for order #%d',
					$order->get_id()
				),
				array(
					'order' => $this,
					'error' => $e,
				)
			);
			$order->add_order_note( __( 'Payment complete event failed.', 'unzer-payments' ) . ' ' . $e->getMessage() );
			return false;
		}
		return true;
	}

	public function getOrderIdFromPaymentId( $paymentId ) {
		// TODO: TEST
		$orderId = null;
		if ( Util::isHPOS() ) {
			$orders = wc_get_orders(
				array(
					'meta_query' => array(
						array(
							'key'        => Main::ORDER_META_KEY_PAYMENT_ID,
							'value'      => $paymentId,
							'comparison' => 'LIKE',
						),
					),
				)
			);
			if ( is_array( $orders ) && count( $orders ) > 0 ) {
				$orderId = $orders[0]->get_id();
			}
		} else {
			global $wpdb;
			$metaData = $wpdb->get_row( $wpdb->prepare( 'SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s AND meta_value = %s', 'unzer_payment_id', $paymentId ), ARRAY_A );
			if ( isset( $metaData['post_id'] ) ) {
				$orderId = $metaData['post_id'];
			}
		}
		return $orderId;
	}

	public function updateRefunds( $paymentId, $orderId ) {
		$paymentService = new PaymentService();
		$order          = wc_get_order( $orderId );
		$unzer          = $paymentService->getUnzerManagerForOrder( $order );
		$payment        = $unzer->fetchPayment( $paymentId );
		if ( $payment->getCancellations() ) {

			$registeredRefunds = $order->get_refunds();
			/** @var Cancellation $unzerRefund */
			foreach ( $payment->getCancellations() as $unzerRefund ) {
				$refundMatchingId = self::getUnzerCancellationId( $unzerRefund );
				$this->logger->debug( 'unzer refund data', array( $unzerRefund->getId() ) );
				$unzerRefundAmount = $unzerRefund->getAmount();
				/** @var WC_Order_Refund $registeredRefund */
				foreach ( $registeredRefunds as $registeredRefund ) {
					$registeredRefundUnzerId = $registeredRefund->get_meta( Main::ORDER_META_KEY_CANCELLATION_ID, true );

					if ( $registeredRefundUnzerId ) {
						if ( $registeredRefundUnzerId === $refundMatchingId ) {
							// this unzer refund is already registered
							continue 2;
						}
						// this is registered to another unzer refund
						continue;
					}
					$registeredRefundAmount = abs( $registeredRefund->get_total() );
					$this->logger->debug( 'refund data', array( $registeredRefundAmount, $unzerRefundAmount, $registeredRefund->get_date_created()->getTimestamp(), strtotime( $unzerRefund->getDate() ) ) );
					if ( abs( $registeredRefundAmount - $unzerRefundAmount ) <= 0.01 ) {
						$timeDifference = abs( $registeredRefund->get_date_created()->getTimestamp() - strtotime( $unzerRefund->getDate() ) );
						if ( $timeDifference <= 10 ) {
							// we consider this a match with some tolerance
							$registeredRefund->update_meta_data( Main::ORDER_META_KEY_CANCELLATION_ID, $refundMatchingId );
							$registeredRefund->save_meta_data();
							$this->logger->debug( 'refund data match', array( $registeredRefundAmount, $unzerRefundAmount, $registeredRefund->get_date_created()->getTimestamp(), strtotime( $unzerRefund->getDate() ) ) );
							continue 2;
						}
					}
				}
				$this->logger->debug( 'refund data no match', array( $unzerRefundAmount, $unzerRefund->getId() ) );
				// at this point there was no match found in the existing WooC refunds, so we create one
				$this->createShopRefund( $orderId, $unzerRefund );
			}
		}
	}

	private function createShopRefund( $orderId, Cancellation $unzerRefund ) {

		$shopRefund = wc_create_refund(
			array(
				'amount'         => $unzerRefund->getAmount(),
				'reason'         => $unzerRefund->getReasonCode(),
				'order_id'       => $orderId,
				'refund_payment' => false,
				'restock_items'  => false,
			)
		);
		if ( $shopRefund instanceof WC_Order_Refund ) {
			$order = wc_get_order( $orderId );
			$order->add_order_note( 'Refund created from Unzer cancellation ' . $unzerRefund->getId() );
			$shopRefund->update_meta_data( Main::ORDER_META_KEY_CANCELLATION_ID, self::getUnzerCancellationId( $unzerRefund ) );
			$shopRefund->save_meta_data();
			( new LogService() )->warning(
				'refund created from unzer cancellation',
				array(
					'refund'      => $shopRefund,
					'unzerRefund' => $unzerRefund->expose(),
				)
			);
		} else {
			( new LogService() )->warning(
				'unable to create shop refund from unzer cancellation',
				array(
					'order'        => $orderId,
					'cancellation' => $unzerRefund->expose(),
					'response'     => $shopRefund,
				)
			);
		}
	}

	public function areAmountsEqual( WC_Order $order, Payment $unzerPayment ) {
		$processedAmount = $unzerPayment->getAmount()->getTotal();
		( new LogService() )->debug( 'compare amounts', array( $processedAmount, $order->get_total() ) );
		return number_format( $processedAmount, 2 ) === number_format( $order->get_total(), 2 );
	}

	public static function getUnzerCancellationId( Cancellation $unzerCancellation ) {
		$id = $unzerCancellation->getId();
		try {
			$parentTransaction = $unzerCancellation->getParentResource();
			if ( $parentTransaction instanceof AbstractTransactionType ) {
				$id .= '---' . $parentTransaction->getId();
			}
		} catch ( Exception $e ) {
			// silent
		}
		return $id;
	}

	/**
	 * @param WC_Order $order
	 * @return void
	 */
	public function printPaymentInstructionsHtml( $order ) {
		$paymentInstructions = $order->get_meta( Main::ORDER_META_KEY_PAYMENT_INSTRUCTIONS, true );
		if ( $paymentInstructions ) {
			if ( $order->get_payment_method() !== Prepayment::GATEWAY_ID ) {
				return;
			}
			echo '<div id="unzer-payment-instructions" style="margin:20px 0;">' . wp_kses_post( $paymentInstructions ) . '</div>';
		}
	}
}
