<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Controllers\CheckoutController;
use UnzerPayments\Gateways\Blocks\PaypalBlock;
use UnzerPayments\Main;
use UnzerPayments\Services\CustomerService;
use UnzerPayments\Services\ExpressCheckoutService;
use UnzerPayments\Services\OrderService;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Traits\SavePaymentInstrumentTrait;
use UnzerPayments\Util;
use UnzerSDK\Resources\PaymentTypes\Paypal as PaypalResource;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Charge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Paypal extends AbstractGateway {

    use SavePaymentInstrumentTrait;

	public $paymentTypeResource = PaypalResource::class;
	const GATEWAY_ID            = 'unzer_paypal';
	const BLOCK_CLASS           = PaypalBlock::class;
	public $method_title        = 'Unzer PayPal';
	public $method_description;
	public $title       = 'PayPal';
	public $description = '';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);

	public function has_fields() {
		return $this->isSaveInstruments();
	}

	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}
		Util::getNonceField();
		echo wp_kses_post( $this->renderSavedInstrumentsSelection( '' ) );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'          => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer PayPal', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'            => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'PayPal', 'unzer-payments' ),
				),
				'description'      => array(
					'title'       => __( 'Description', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'unzer-payments' ),
					'default'     => '',
				),
				'transaction_type' => array(
					'title'       => __( 'Charge or Authorize', 'unzer-payments' ),
					'label'       => '',
					'type'        => 'select',
					'description' => __( 'Choose "authorize", if you you want to charge the shopper at a later point of time', 'unzer-payments' ),
					'options'     => array(
						AbstractGateway::TRANSACTION_TYPE_AUTHORIZE => __( 'authorize', 'unzer-payments' ),
						AbstractGateway::TRANSACTION_TYPE_CHARGE => __( 'charge', 'unzer-payments' ),
					),
					'default'     => 'charge',
				),
				AbstractGateway::SETTINGS_KEY_SAVE_INSTRUMENTS => array(
					'title'       => __( 'Save PayPal account for registered customers', 'unzer-payments' ),
					'label'       => __( '&nbsp;', 'unzer-payments' ),
					'type'        => 'select',
					'description' => '',
					'default'     => 'no',
					'options'     => array(
						'no'  => __( 'No', 'unzer-payments' ),
						'yes' => __( 'Yes', 'unzer-payments' ),
					),
				),
                AbstractGateway::SETTINGS_KEY_EXPRESS_OPTION => array(
                    'title'       => __( 'Offer Paypal Express Checkout', 'unzer-payments' ),
                    'label'       => __( '&nbsp;', 'unzer-payments' ),
                    'type'        => 'select',
                    'description' => '',
                    'default'     => 'no',
                    'options'     => array(
                        'no'  => __( 'No', 'unzer-payments' ),
                        'yes' => __( 'Yes', 'unzer-payments' ),
                    ),
                ),
			)
		);
	}

	public function process_payment( $order_id ) {

        if (WC()->session->get(ExpressCheckoutService::SESSION_PAYPAL_PAYMENT_ID)) {
            try {
                return $this->payExpress(
                    WC()->session->get(ExpressCheckoutService::SESSION_PAYPAL_PAYMENT_ID),
                    $order_id
                );
            } catch (\Throwable $e) {
                $this->logger->error('Express CO failed: ' . $e->getMessage());
                WC()->session->set(ExpressCheckoutService::SESSION_PAYPAL_PAYMENT_ID, false);
                // continue with default paypal flow
            }
        }
		$return                 = array(
			'result' => 'success',
		);
		$savedPaymentInstrument = Util::getNonceCheckedPostValue( 'unzer_paypal_payment_instrument' );
		$paymentMean            = empty( $savedPaymentInstrument ) ? PaypalResource::class : $savedPaymentInstrument;

		$savePaymentInstrument = ! empty( Util::getNonceCheckedPostValue( 'unzer-save-payment-instrument-' . $this->id ) );
		WC()->session->set( 'save_payment_instrument', $savePaymentInstrument );
		$transactionEditorFunction = null;

		if ( $this->get_option( 'transaction_type' ) === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE ) {
			$transaction = ( new PaymentService() )->performAuthorizationForOrder( $order_id, $this, $paymentMean, $transactionEditorFunction );
		} else {
			$transaction = ( new PaymentService() )->performChargeForOrder( $order_id, $this, $paymentMean, $transactionEditorFunction );
		}

		$this->before_payment_redirect( $order_id );

		if ( $transaction->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $transaction->getPayment()->getRedirectUrl();
		} elseif ( $transaction->isSuccess() ) {
			$return['redirect'] = $this->get_confirm_url( $order_id );
		}
		return $return;
	}

    public function payExpress( $unzer_payment_id, $order_id)
    {
        $this->logger->debug('Paypal::payExpress()');
        ( new PaymentService() )->removeTransactionMetaData( $order_id );
        $order = wc_get_order( $order_id );
        $unzer = ( new PaymentService() )->getUnzerManager();
        $payment = $unzer->fetchPayment($unzer_payment_id);
        $basket = ( new OrderService())->getBasket($order_id);
        $basket->setId($payment->getBasket()->getId());
        $basket->setOrderId($order_id);
        $this->logger->debug('Update Basket with order ID');
        $unzer->updateBasket($basket);

        ( new CustomerService() )->updatePaypalExpressCustomer( $order,  $payment->getCustomer() );

        if (empty($payment->getCharges())) {
            $authorization = new Authorization(
                $basket->getTotalValueGross(),
                $basket->getCurrencyCode(),
                $this->get_confirm_url( $order_id )
            );
            $authorization->setOrderId($order_id);
            $this->logger->debug('Update Authorization');
            $transactionObject = $unzer->updateAuthorization($payment->getId(), $authorization);
            $order->update_meta_data( Main::ORDER_META_KEY_AUTHORIZATION_ID, $transactionObject->getId() );
        } else {
            $charge = new Charge(
                $basket->getTotalValueGross(),
                $basket->getCurrencyCode(),
                $this->get_confirm_url( $order_id )
            );
            $charge->setOrderId($order_id);
            $this->logger->debug('Update Charge');
            $transactionObject = $unzer->updateCharge($payment->getId(), $charge);
            $order->update_meta_data( Main::ORDER_META_KEY_CHARGE_ID, $transactionObject->getId() );
        }
        $order->update_meta_data( Main::ORDER_META_KEY_PAYMENT_ID, $transactionObject->getPayment()->getId() );
        $order->update_meta_data( Main::ORDER_META_KEY_PAYMENT_SHORT_ID, $transactionObject->getShortId() );
        $this->logger->debug('Save Meta Data');
        $order->save_meta_data();
        $this->logger->debug('Add order note');
        $order->add_order_note( __( 'Unzer Payment ID: ', 'unzer-payments' ) . $transactionObject->getPayment()->getId() );
        $this->before_payment_redirect( $order_id );
        $this->logger->debug('Return confirmation URL');
        $return                 = array(
            'result' => 'success',
            'redirect' => $this->get_confirm_url( $order_id )
        );
        return $return;
    }
}
