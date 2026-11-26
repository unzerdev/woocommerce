<?php

namespace UnzerPayments\Controllers;

use UnzerPayments\Gateways\Card;
use UnzerPayments\Gateways\DirectDebit;
use UnzerPayments\Gateways\Paypal;
use UnzerPayments\Main;
use UnzerPayments\Util;

class AccountController {

	const DELETE_PAYMENT_INSTRUMENT_URL_SLUG = 'delete_payment_instrument';

	public function deletePaymentInstrument() {
		$instrumentId = Util::getNonceCheckedPostValue( 'instrument' );
		if ( empty( $instrumentId ) || ! is_user_logged_in() ) {
			return;
		}

		$user                 = wp_get_current_user();
		$existingPaymentMeans = get_user_meta( $user->ID, Main::USER_META_KEY_PAYMENT_INSTRUMENTS, true );
		if ( empty( $existingPaymentMeans ) || ! is_array( $existingPaymentMeans ) ) {
			return;
		}

		foreach ( $existingPaymentMeans as $gatewayClass => $instruments ) {
			if ( isset( $instruments[ $instrumentId ] ) ) {
				unset( $existingPaymentMeans[ $gatewayClass ][ $instrumentId ] );
			}
		}
		update_user_meta( $user->ID, Main::USER_META_KEY_PAYMENT_INSTRUMENTS, $existingPaymentMeans );
	}

	public function accountPaymentInstruments() {
		$gateways  = array(
			Card::class,
			Paypal::class,
			DirectDebit::class,
		);
		$html      = '';
		$deleteUrl = WC()->api_request_url( self::DELETE_PAYMENT_INSTRUMENT_URL_SLUG );
		$nonce     = Util::getNonce();

		foreach ( $gateways as $gateway ) {
			/** @var Card|Paypal|DirectDebit $gatewayObject */
			$gatewayObject    = new $gateway();
			$savedInstruments = $gatewayObject->getSavedPaymentInstruments();
			if ( $savedInstruments && $gatewayObject->isSaveInstruments() ) {
				$html .= '<div class="unzer-payment-mean"><b>' . $gatewayObject->get_title() . '</b></div><ul>';
				foreach ( $savedInstruments as $savedInstrument ) {
					$html .= '<li>' . esc_html( $savedInstrument['label'] ) . ' <a href="#" class="unzer-delete-instrument" data-instrument-id="' . esc_attr( $savedInstrument['id'] ) . '" data-delete-url="' . esc_url( $deleteUrl ) . '" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html__( 'Delete', 'unzer-payments' ) . '</a></li>';
				}
				$html .= '</ul>';
			}
		}
		if ( $html ) {
			wp_enqueue_script( 'unzer_account_js', UNZER_PLUGIN_URL . '/assets/js/account.js', array(), UNZER_VERSION, array( 'in_footer' => true ) );
			$html = '<h2>' . esc_html__( 'Your saved payment means', 'unzer-payments' ) . '</h2>' . $html;
		}
		echo wp_kses(
			$html,
			array(
				'h2'  => array(),
				'div' => array( 'class' => array() ),
				'b'   => array(),
				'ul'  => array(),
				'li'  => array(),
				'a'   => array(
					'href'               => array(),
					'class'              => array(),
					'data-instrument-id' => array(),
					'data-delete-url'    => array(),
					'data-nonce'         => array(),
				),
			)
		);
	}
}
