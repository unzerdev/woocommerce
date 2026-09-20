<?php

namespace UnzerPayments\Traits;

use Exception;
use UnzerPayments\Gateways\SubscriptionGateway;
use UnzerSDK\Resources\PaymentTypes\BasePaymentType;
use WC_Payment_Token;
use WC_Payment_Tokens;
use WC_Order;

trait SavePaymentTokenTrait {

	/**
	 * @throws Exception
	 */
	public function getPaymentTypeIdFromToken( WC_Order $order ): string {
		$token = $this->getActivePaymentToken( $order );

		if ( $token !== null ) {
			return $token->get_token();
		}

		throw new Exception( 'Order has no saved payment method.' );
	}

	public function getActivePaymentToken( WC_Order $order ): ?WC_Payment_Token {
		$tokenIds = $order->get_payment_tokens();
		$tokenId  = end( $tokenIds );

		return $tokenId ? WC_Payment_Tokens::get( $tokenId ) : null;
	}

	public function addPaymentToken(
		WC_Order $order,
		WC_Payment_Token $token
	): void {
		$activeToken = $this->getActivePaymentToken( $order );

		if ( $activeToken !== null && $activeToken->get_id() === $token->get_id() ) {
			return;
		}

		$order->add_payment_token( $token );
	}

	public function getCustomerPaymentToken( int $userId, string $token, string $gatewayId = ''	): ?WC_Payment_Token {
		foreach ( WC_Payment_Tokens::get_customer_tokens( $userId, $gatewayId ) as $t ) {
			if ( $t->get_token() === $token ) {
				return $t;
			}
		}

		return null;
	}

	public function getOrCreateCustomerPaymentToken(
		int $userId,
		BasePaymentType $paymentType,
		SubscriptionGateway $gateway,
	): WC_Payment_Token {
		$token = $this->getCustomerPaymentToken( $userId, $paymentType->getId(), $gateway->id );

		if ( $token === null ) {
			$token = $gateway->create_payment_token( $paymentType );
			$token->set_user_id( $userId );
			$token->save();
		}

		return $token;
	}
}
