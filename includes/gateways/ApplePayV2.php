<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Gateways\Blocks\ApplePayBlock;
use UnzerPayments\Services\ExpressCheckoutService;
use UnzerPayments\Services\PaymentService;
use UnzerPayments\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ApplePayV2 extends AbstractGateway {

	const GATEWAY_ID          = 'unzer_apple_pay_v2';
	const BLOCK_CLASS         = ApplePayBlock::class;
	public $allowedCurrencies = array( 'AUD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'NOK', 'PLN', 'SEK', 'USD', 'HUF', 'RON', 'BGN', 'HRK', 'ISK' );
	public $method_title      = 'Unzer Apple Pay';
	public $method_description;
	public $title       = 'Apple Pay';
	public $description = '';
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
		} else {
			add_action(
				'wp_enqueue_scripts',
				function () {
					wp_add_inline_style( 'woocommerce_unzer_css', '.payment_box.payment_method_unzer_apple_pay_v2{display:none !important;}' );
				}
			);
		}
		Util::getNonceField();
		$form = '
		    <input type="hidden" id="unzer-apple-pay-v2-id" name="unzer-apple-pay-v2-id" value=""/>
		    <input type="hidden" id="unzer-apple-pay-v2-amount" name="unzer-apple-pay-v2-amount" value="' . esc_attr( $this->get_amount() ) . '"/>
            <template class="unzer-apple-pay-ui-template">
                <unzer-payment
                        id="unzer-apple-pay-payment-component"
                        publicKey="' . esc_attr( $this->get_public_key() ) . '"
                        locale="' . esc_attr( get_locale() ) . '">
                    <unzer-apple-pay></unzer-apple-pay>
                </unzer-payment>
                <unzer-checkout id="unzer-apple-pay-checkout-component"></unzer-checkout>
            </template>     
        ';
		echo wp_kses( $form, $this->get_allowed_html_tags() );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'          => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer Apple Pay', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'            => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Apple Pay', 'unzer-payments' ),
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
                AbstractGateway::SETTINGS_KEY_EXPRESS_OPTION => array(
                    'title'       => __( 'Offer Apple Pay Express Checkout', 'unzer-payments' ),
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
		$this->logger->debug( 'start payment for #' . $order_id . ' with ' . self::GATEWAY_ID );
		$return = array(
			'result' => 'success',
		);

        $applePayId = Util::getNonceCheckedPostValue( 'unzer-apple-pay-v2-id' );
        $this->logger->debug( 'fetched applePayId ' . $applePayId, array(  ) );
        try {
            if (!empty(WC()->session->get(ExpressCheckoutService::SESSION_APPLEPAY_PAYMENT_TYPE_ID))) {
                $applePayId = (WC()->session->get(ExpressCheckoutService::SESSION_APPLEPAY_PAYMENT_TYPE_ID));

                $client = ( new PaymentService() )->getUnzerManagerForOrder($order_id);
                $paymentType = $client->fetchPaymentType(WC()->session->get(ExpressCheckoutService::SESSION_APPLEPAY_PAYMENT_TYPE_ID));
                $this->logger->debug( 'fetched applePayId from Session ' . $applePayId, array( 'paymentType' => $paymentType ) );
            }
        } catch (\Throwable) {
        }

		if ( $this->get_option( 'transaction_type' ) === AbstractGateway::TRANSACTION_TYPE_AUTHORIZE ) {
            $this->logger->debug( 'apple pay authorization for order ' . $order_id, array( $applePayId ) );
			$transaction = ( new PaymentService() )->performAuthorizationForOrder( $order_id, $this, $applePayId );
		} else {
            $this->logger->debug( 'apple pay charge for order ' . $order_id, array( $applePayId ) );
			$transaction = ( new PaymentService() )->performChargeForOrder( $order_id, $this, $applePayId );
		}

		$this->logger->debug( 'apple pay charge/authorization for order ' . $order_id, array( $transaction->expose() ) );

		$this->before_payment_redirect( $order_id );

		if ( $transaction->getPayment()->getRedirectUrl() ) {
			$return['redirect'] = $transaction->getPayment()->getRedirectUrl();
		} else {
			$return['redirect'] = $this->get_confirm_url( $order_id );
		}
		return $return;
	}

    public function getPublicOptions() {
        return array(
            'countryCode' => 'DE',
            'allowedCardNetworks' => ['masterCard', 'visa'],
            'merchantCapabilities' => ['supports3DS'],
            'shopName' => get_bloginfo('name'),
        );
    }

}
