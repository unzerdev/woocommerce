<?php

namespace UnzerPayments\Gateways;

use Exception;
use UnzerPayments\Gateways\Blocks\CardBlock;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Traits\SavePaymentInstrumentTrait;
use UnzerPayments\Util;
use UnzerSDK\Constants\RecurrenceTypes;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Charge;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Card extends AbstractGateway {



	use SavePaymentInstrumentTrait;

	const GATEWAY_ID            = 'unzer_card';
	const BLOCK_CLASS           = CardBlock::class;
	public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\Card::class;
	public $method_title        = 'Unzer Credit Card';
	public $method_description;
	public $title       = 'Credit Card';
	public $description = 'Use any credit card';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);


	public function has_fields() {
		return true;
	}


	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}
		Util::getNonceField();
		$form = '
            <input type="hidden" id="unzer-card-id" name="unzer-card-id" value=""/>
                <div class="unzer-ui-container"></div>
            <template class="unzer-ui-template">
                    <unzer-payment
                        id="unzer-card-payment-component"
                        publicKey="' . esc_attr( $this->get_public_key() ) . '"
                        locale="' . esc_attr( get_locale() ) . '"
                        ' . ( $this->get_option( 'allow_ctp' ) === 'yes' ? '' : 'disableCTP' ) . '
                    >
                        <unzer-card id="unzer-card"></unzer-card>
                    </unzer-payment>
            </template>';
		echo wp_kses( $this->renderSavedInstrumentsSelection( $form ), $this->get_allowed_html_tags() );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'          => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer Card Payments', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'            => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Credit Card', 'unzer-payments' ),
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
					'title'       => __( 'Save card for registered customers', 'unzer-payments' ),
					'label'       => __( '&nbsp;', 'unzer-payments' ),
					'type'        => 'select',
					'description' => '',
					'default'     => 'no',
					'options'     => array(
						'no'  => __( 'No', 'unzer-payments' ),
						'yes' => __( 'Yes', 'unzer-payments' ),
					),
				),
				'allow_ctp'        => array(
					'title'       => __( 'Offer Click To Pay', 'unzer-payments' ),
					'label'       => __( '&nbsp;', 'unzer-payments' ),
					'type'        => 'select',
					'description' => '',
					'default'     => 'no',
					'options'     => array(
						'no'  => __( 'No', 'unzer-payments' ),
						'yes' => __( 'Yes', 'unzer-payments' ),
					),
				),
				/*
				'capture_trigger_order_status' => [
					'title' => __('Capture status', 'unzer-payments'),
					'label' => '',
					'type' => 'select',
					'description' => __('When this status is assigned to an order, the funds will be captured', 'unzer-payments'),
					'options' => array_merge(['' => ''], wc_get_order_statuses()),
				],
				*/
			)
		);
	}

	public function process_payment( $order_id ) {
		$this->logger->debug( 'start payment for #' . $order_id . ' with ' . self::GATEWAY_ID );
		$return = array(
			'result' => 'success',
		);

		// for saved payment instruments
		$selectedSavedPaymentInstrument = Util::getNonceCheckedPostValue( static::GATEWAY_ID . '_payment_instrument' );
		$isSavedPaymentInstrument       = ! empty( $selectedSavedPaymentInstrument );
		$cardId                         = $isSavedPaymentInstrument ? $selectedSavedPaymentInstrument : Util::getNonceCheckedPostValue( 'unzer-card-id' );
		$savePaymentInstrument          = ! empty( Util::getNonceCheckedPostValue( 'unzer-save-payment-instrument-' . $this->id ) );

		WC()->session->set( 'save_payment_instrument', $savePaymentInstrument );
		$transactionEditorFunction = null;
		if ( $savePaymentInstrument || $isSavedPaymentInstrument ) {
			/**
			 * @param Charge|Authorization $transaction
			 * @return void
			 */
			$transactionEditorFunction = function ( $transaction ) {
				$transaction->setRecurrenceType( RecurrenceTypes::ONE_CLICK );
			};
		}

		if ( $this->get_option( 'transaction_type' ) === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE ) {
			$transaction = ( new PaymentService() )->performAuthorizationForOrder( $order_id, $this, $cardId, $transactionEditorFunction );
		} else {
			$transaction = ( new PaymentService() )->performChargeForOrder( $order_id, $this, $cardId, $transactionEditorFunction );
		}
		$this->before_payment_redirect( $order_id );
		if ( $transaction->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $transaction->getPayment()->getRedirectUrl();
		} elseif ( $transaction->isSuccess() ) {
			$return['redirect'] = $this->get_confirm_url( $order_id );
		}
		return $return;
	}


	/**
	 * @param WC_Order $order
	 * @param float    $amount
	 * @throws Exception
	 */
	public function capture( WC_Order $order, $amount = null ) {
	}
}
