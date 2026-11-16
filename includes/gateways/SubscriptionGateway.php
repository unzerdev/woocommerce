<?php

namespace UnzerPayments\Gateways;

use Exception;
use Throwable;
use UnzerPayments\Controllers\SubscriptionController;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Traits\SavePaymentTokenTrait;
use UnzerSDK\Constants\RecurrenceTypes;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\PaymentTypes\BasePaymentType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Charge;
use WC_Order;
use WC_Payment_Token;
use WC_Payment_Tokens;
use WC_Subscription;
use WC_Subscriptions;

/**
 * Implements WooCommerce Subscriptions support shared by Unzer gateways.
 *
 * @see https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#step-1-registering-support-for-subscriptions
 */
abstract class SubscriptionGateway extends AbstractGateway {

	use SavePaymentTokenTrait;

	protected const PAYMENT_TOKEN_META_TABLE = 'wc_order_tokens';
	protected const PAYMENT_TOKEN_META_KEY = 'token';

	protected static array $registeredSubscriptionHooks = array();

	/** Creates a WooCommerce token from a recurring Unzer payment type. */
	abstract public function create_payment_token( BasePaymentType $paymentType ): WC_Payment_Token;

	/** Processes an order that does not contain a subscription. */
	abstract public function process_payment_for_order( WC_Order $order, string $paymentTypeId ): array;

	/** Processes the initial payment for an order containing a subscription. */
	abstract public function process_payment_for_subscription( WC_Order $order, string $paymentTypeId ): array;

	/** Checks whether WooCommerce Subscriptions 7.0 or later is active. */
	public function is_subscriptions_enabled(): bool {
		return class_exists( WC_Subscriptions::class )
			&& version_compare( WC_Subscriptions::$version, '7.0.0', '>=' );
	}

	/**
	 * Adds subscription capabilities and registers their hooks when Subscriptions is available.
	 *
	 * @see https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#register-support
	 */
	public function maybe_init_subscriptions(): void {
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		$supports = [
			'subscriptions',
			'subscription_amount_changes',
			'subscription_cancellation',
			'subscription_date_changes',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_payment_method_change',
			'subscription_payment_method_change_admin',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_delayed_change',
		];

		$this->supports = array_merge( $this->supports, $supports );

		if ( empty( self::$registeredSubscriptionHooks[ $this->id ] ) ) {
			self::$registeredSubscriptionHooks[ $this->id ] = true;
			$this->register_subscription_hooks();
		}
	}

	/** Registers one set of Subscriptions hooks for this gateway ID. */
	public function register_subscription_hooks(): void {
		add_action( 'woocommerce_api_payment_method_change_' . $this->id, array( new SubscriptionController( $this ), 'completePaymentMethodChange' ));
		add_action( 'woocommerce_subscription_payment_complete', array( $this, 'process_subscription_complete' ));
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'process_subscription_renewal' ), 10, 2);
		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array( $this, 'update_subscription_failing_payment_method' ), 10, 2);
		add_action( 'woocommerce_subscriptions_switch_completed', array( $this, 'update_subscription_payment_method_after_switch' ));
		add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', array( $this, 'update_subscription_payment_via_pay_shortcode' ), 10, 2);
		add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2);
		add_action( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 3 );
		add_filter(	'wcs_copy_payment_meta_to_order', array( $this, 'append_payment_meta_to_order' ), 10, 3);
		add_action( 'wcs_save_other_payment_meta', array( $this, 'save_subscription_payment_token_meta' ), 10, 4 );
		add_action(
			sprintf(
				'woocommerce_subscription_payment_meta_input_%s_%s_%s',
				$this->id,
				self::PAYMENT_TOKEN_META_TABLE,
				self::PAYMENT_TOKEN_META_KEY
			),
			array( $this, 'render_subscription_payment_meta_input' ),
			10,
			3
		);
	}

	/**
	 * Checks whether an order is handled as a subscription payment.
	 *
	 * @param mixed $order Order or order ID to inspect.
	 */
	public function is_payment_for_subscription( $order ): bool {
		if ( ! $this->is_subscriptions_enabled() ) {
			return false;
		}

		return wcs_order_contains_subscription( $order );
	}

	/**
	 * Checks whether the current request changes an existing subscription's payment method.
	 *
	 * @param mixed $order Order or subscription to inspect.
	 */
	public function is_payment_method_change( $order ): bool {
		if ( ! $this->is_subscriptions_enabled() ) {
			return false;
		}

		return wcs_is_subscription( $order ) && isset( $_POST['woocommerce_change_payment'] );
	}

	/**
	 * Starts recurring payment setup and returns its external or internal completion redirect.
	 *
	 * @see https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#5-1-supporting-subscriber-payment-method-changes
	 */
	public function process_payment_method_change( WC_Subscription $subscription, string $paymentTypeId ): array {
		$nonce = wp_create_nonce( "payment_method_change_{$this->id}_{$subscription->get_id()}_{$paymentTypeId}" );
		$params = array( 'id' => $subscription->get_id(), 'type' => $paymentTypeId,	'_wpnonce' => $nonce );
		$returnUrl = add_query_arg( $params, WC()->api_request_url( 'payment_method_change_' . $this->id ) );

		try {
			$unzer = ( new PaymentService() )->getUnzerManager( $this );
			$recurring = $unzer->activateRecurringPayment( $paymentTypeId, $returnUrl, RecurrenceTypes::SCHEDULED );

			if ( $recurring->isSuccess() && ! $recurring->getRedirectUrl() ) {
				return array(
					'result'   => 'success',
					'redirect' => $returnUrl,
				);
			}

			if ( $recurring->isPending() && $recurring->getRedirectUrl() ) {
				return array(
					'result'   => 'success',
					'redirect' => $recurring->getRedirectUrl(),
				);
			}

			throw new Exception( 'Recurring payment setup did not succeed.' );
		} catch ( Throwable $e ) {
			$message = $e instanceof UnzerApiException ? $e->getMerchantMessage() : $e->getMessage();

			$this->logger->error(
				'Payment method change failed',
				array(
					'message'        => $message,
					'subscriptionId' => $subscription->get_id(),
				),
			);

			wc_add_notice( __( 'Payment error', 'unzer-payments' ), 'error' );

			return array( 'result' => 'failure' );
		}
	}

	/**
	 * Stores the recurring payment token after the initial payment completes.
	 *
	 * @see https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#h-recording-payments
	 */
	public function process_subscription_complete( WC_Subscription $subscription ): void {
		$order = wc_get_order($subscription->get_last_order());

		try {
			if ( ! $order instanceof WC_Order ) {
				throw new Exception( 'Subscription has no last order' );
			}

			$transaction = ( new PaymentService() )->getChargeOrAuthorizationFromOrder( $order->get_id(), $this );
			$paymentType = $transaction?->getPayment()?->getPaymentType();

			if ( ! $paymentType instanceof BasePaymentType ) {
				throw new Exception( 'Order has no payment type for subscription' );
			}

			$this->update_subscription_payment_method($subscription, $paymentType);

		} catch ( Throwable $e ) {
			$message = $e instanceof UnzerApiException ? $e->getMerchantMessage() : $e->getMessage();

			$this->logger->error(
				'Subscription payment complete invalid',
				array(
					'message'        => $message,
					'subscriptionId' => $subscription->get_id(),
				)
			);
		}
	}

	/**
	 * Charges a scheduled subscription renewal with the subscription's saved token.
	 *
	 * @see https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#recurring-payments-by-extension
	 */
	public function process_subscription_renewal( float $renewalTotal, WC_Order $renewalOrder ): void {
        $message = '';

		try {
            $subscriptionId = $renewalOrder->get_meta('_subscription_renewal');
            $subscription = wcs_get_subscription($subscriptionId);

			if ( ! $subscription instanceof WC_Subscription ) {
				throw new Exception( 'Renewal order has no subscription.' );
			}

			/**
			 * @param Charge|Authorization $transaction
			 * @return void
			 */
			$transactionEditorFunction = function ( $transaction ): void {
				$transaction->setRecurrenceType( RecurrenceTypes::SCHEDULED );
			};

			$transaction = ( new PaymentService() )->performChargeForOrder(
				$renewalOrder->get_id(),
				$this,
				$this->getPaymentTypeIdFromToken( $subscription ),
				$transactionEditorFunction
			);

			if ( ! $transaction->isSuccess() ) {
				throw new Exception( 'Recurring payment transaction failed' );
			}

			( new OrderService() )->processPaymentStatus( $transaction, $renewalOrder );
		} catch ( Throwable $e ) {
			$message = $e instanceof UnzerApiException ? $e->getMerchantMessage() : $e->getMessage();

			$this->logger->error( 'Recurring payment failed', array(
					'message' => $message, 'orderId' => $renewalOrder->get_id(),
				)
			);
		}

        // https://woocommerce.com/document/subscriptions/failed-payment-retry/
        if ($renewalOrder->get_status() !== 'completed') {
            $renewalOrder->update_status('failed', $message);
        }
	}

	/**
	 * Saves a recurring payment token and assigns this gateway to a subscription.
	 *
	 * @see https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#5-1-supporting-subscriber-payment-method-changes
	 */
    public function update_subscription_payment_method( WC_Subscription $subscription, BasePaymentType $paymentType ): void {
		if ( ! method_exists( $paymentType, 'isRecurring' ) || ! $paymentType->isRecurring() ) {
			throw new Exception( 'Payment type is not recurring.' );
		}

		$token = $this->getOrCreateCustomerPaymentToken(
			$subscription->get_user_id(),
			$paymentType,
			$this,
		);

		$this->addPaymentToken( $subscription, $token );

		$subscription->set_payment_method( $this );
		$subscription->save();
	}

	/**
	 * Updates subscriptions created by a product switch with the switch order's payment token.
	 *
	 * @see https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#step-5-recurring-payment-method-changes
	 */
    public function update_subscription_payment_method_after_switch(WC_Order $order): void
    {
		if ($this->id !== $order->get_payment_method()) {
			return;
		}

		try {
			$transaction = ( new PaymentService() )->getChargeOrAuthorizationFromOrder( $order->get_id(), $this );
			$paymentType = $transaction?->getPayment()?->getPaymentType();

			if ( ! $paymentType instanceof BasePaymentType ) {
				throw new Exception( 'Order has no payment type for subscription' );
			}

			foreach (wcs_get_subscriptions_for_switch_order($order) as $subscription) {
				$this->update_subscription_payment_method($subscription, $paymentType);
			}

		} catch ( Throwable $e ) {
			$this->logger->warning(
				'Subscription payment method switch is invalid',
				array(
					'orderId'        => $order->get_id(),
					'subscriptionId' => $subscription->get_id(),
					'message'        => $e->getMessage(),
				)
			);
		}
    }

	/**
	 * Defers this gateway's update until recurring payment setup has produced a usable token.
	 *
	 * The completion endpoint then saves the token, updates the subscription, and applies a requested bulk update.
	 *
	 * @see https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#5-1-supporting-subscriber-payment-method-changes
	 */
	public function update_subscription_payment_via_pay_shortcode( bool $shouldUpdate, string $gatewayId ): bool {
		return $this->id === $gatewayId ? false : $shouldUpdate;
	}

	/**
	 * Saves a new payment token after a customer changes the method for a failed renewal.
	 *
	 * @see https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#5-2-updating-the-payment-method-after-a-failure
	 */
	public function update_subscription_failing_payment_method( WC_Subscription $subscription, WC_Order $order ): void {
		try {
			$transaction = ( new PaymentService() )->getChargeOrAuthorizationFromOrder( $order->get_id(), $this );
			$paymentType = $transaction?->getPayment()?->getPaymentType();

			if ( ! $paymentType instanceof BasePaymentType ) {
				throw new Exception( 'Order has no payment type for subscription' );
			}

			$this->update_subscription_payment_method( $subscription, $paymentType );

		} catch ( Throwable $e ) {
			$this->logger->warning(
				'Subscription payment type is invalid',
				array(
					'message'        => $e->getMessage(),
					'orderId'        => $order->get_id(),
					'subscriptionId' => $subscription->get_id(),
				)
			);
		}
	}

	/**
	 * Adds this gateway's saved-token field to subscription payment metadata.
	 *
	 * @param array<string, mixed> $paymentMeta Payment metadata indexed by gateway ID.
	 * @return array<string, mixed> Payment metadata including this gateway's token field.
	 */
    public function add_subscription_payment_meta(array $paymentMeta, WC_Subscription $subscription): array
    {
		$token = $this->getActivePaymentToken( $subscription );
		$tokenId = $this->id === $token?->get_gateway_id() ? $token->get_id() : 0;
		$paymentMeta[$this->id] = $this->create_payment_meta( $tokenId );

        return $paymentMeta;
    }

	/** Renders the saved payment token selector in the subscription editor. */
	public function render_subscription_payment_meta_input(
		WC_Subscription $subscription,
		string $fieldId,
		?string $fieldValue
	): void {
		$tokens = WC_Payment_Tokens::get_customer_tokens( $subscription->get_user_id(), $this->id );
		$options = array();
		$selected = null;

		foreach ( $tokens as $token ) {
			$tokenId = (string) $token->get_id();

			if ( $tokenId === $fieldValue ) {
				$selected = $tokenId;
			}

			$options[] = sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $tokenId ),
				selected( $tokenId, $fieldValue, false ),
				esc_html( $token->get_display_name() )
			);
		}

		printf( '<select id="%1$s" name="%1$s">', esc_attr( $fieldId ) );

		if ( ! $options ) {
			printf(
				'<option value="" selected disabled>%s</option>',
				esc_html__( 'No payment methods found for customer', 'unzer-payments' )
			);
		} elseif ( $fieldValue && $fieldValue !== $selected ) {
			printf(
				'<option value="" selected disabled>%s</option>',
				esc_html__( 'Please select a payment method', 'unzer-payments' )
			);
		}

		echo implode( '', $options ) . '</select>';
	}

	/**
	 * Validates that the selected token belongs to the subscription customer and this gateway.
	 *
	 * @param array<string, mixed> $paymentMeta Submitted subscription payment metadata.
	 * @throws Exception When the token is missing, invalid, or belongs to another customer.
	 */
	public function validate_subscription_payment_meta(
		string $gatewayId,
		array $paymentMeta,
		WC_Subscription $subscription
	): void {
		if ( $this->id !== $gatewayId ) {
			return;
		}

		$tokenId = $paymentMeta[ self::PAYMENT_TOKEN_META_TABLE ][ self::PAYMENT_TOKEN_META_KEY ]['value'] ?? '';

		if ( ! is_string( $tokenId ) || ! ctype_digit( $tokenId ) ) {
			throw new Exception( __( 'A saved payment method must be selected for this subscription.', 'unzer-payments' ) );
		}

		$token = WC_Payment_Tokens::get( (int) $tokenId );

		if (
			! $token instanceof WC_Payment_Token
			|| $token->get_user_id() !== $subscription->get_user_id()
			|| $token->get_gateway_id() !== $gatewayId
		) {
			throw new Exception( __( 'The selected saved payment method is invalid or does not belong to this customer.', 'unzer-payments' ) );
		}
	}

	/** Adds the selected saved payment token to the subscription. */
	public function save_subscription_payment_token_meta(
		WC_Subscription $subscription,
		string $table,
		string $metaKey,
		string $metaValue
	): void {
		if ( self::PAYMENT_TOKEN_META_TABLE !== $table || self::PAYMENT_TOKEN_META_KEY !== $metaKey ) {
			return;
		}

		$token = WC_Payment_Tokens::get( (int) $metaValue );

		if ( ! $token instanceof WC_Payment_Token ) {
			return;
		}

		$this->addPaymentToken( $subscription, $token );
	}

	/**
	 * Copies the subscription's active token to order payment metadata.
	 *
	 * @param array<string, mixed> $paymentMeta Existing order payment metadata.
	 * @return array<string, mixed> Payment metadata with the active token.
	 */
	public function append_payment_meta_to_order(
		array $paymentMeta,
		WC_Order $order,
		WC_Subscription $subscription
	): array {
		$token = $this->getActivePaymentToken( $subscription );

		return array_merge( $paymentMeta, $this->create_payment_meta( $token !== null ? $token->get_id() : 0 ) );
	}

	/**
	 * Builds the payment metadata shape for a saved payment token.
	 *
	 * @return array{wc_order_tokens: array{token: array{label: string, value: string}}}
	 */
	protected function create_payment_meta( int $tokenId ): array {
		return array(
			self::PAYMENT_TOKEN_META_TABLE => array(
				self::PAYMENT_TOKEN_META_KEY => array(
					'label' => __( 'Saved payment method', 'unzer-payments' ),
					'value' => (string) $tokenId,
				),
			),
		);
	}
}
