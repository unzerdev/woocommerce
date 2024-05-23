<?php

namespace UnzerPayments\Traits;

use UnzerPayments\Gateways\AbstractGateway;
use UnzerPayments\Main;
use UnzerPayments\Services\PaymentService;
use UnzerSDK\Resources\PaymentTypes\BasePaymentType;
use UnzerSDK\Resources\PaymentTypes\Card;
use UnzerSDK\Resources\PaymentTypes\Paypal;
use UnzerSDK\Resources\PaymentTypes\SepaDirectDebit;

trait SavePaymentInstrumentTrait {

	public function isSaveInstruments(): bool {
		return $this->get_option( AbstractGateway::SETTINGS_KEY_SAVE_INSTRUMENTS ) === 'yes' && is_user_logged_in();
	}

	public function renderSavedInstrumentsSelection( $originalForm ) {
		if ( $this->isSaveInstruments() ) {
			$originalForm .= '<div class="unzer-save-new-payment-instrument">
                                   <label>
                                        <input type="checkbox" name="unzer-save-payment-instrument-' . $this->id . '" value="1" class="unzer-save-payment-instrument-checkbox" ' . ( WC()->session->get( 'save_payment_instrument' ) ? 'checked ' : '' ) . '/>
                                        <span class="label">' . esc_html__( 'Save this for my next purchase', 'unzer-payments' ) . '</span>
                                   </label>
                              </div>';
		}
		if ( ! $this->isSaveInstruments() || empty( $this->getSavedPaymentInstruments() ) ) {
			return $originalForm;
		} else {
			$html        = '<div class="unzer-saved-payment-instruments-container">';
			$instruments = $this->getSavedPaymentInstruments();
			$isFirst     = true;
			foreach ( $instruments as $instrument ) {
				$html   .= '<div class="saved-payment-instrument"><label><input type="radio" name="' . static::GATEWAY_ID . '_payment_instrument" class="unzer-payment-instrument-radio" value="' . $instrument['id'] . '" ' . ( $isFirst ? 'checked ' : '' ) . '/><span class="label">' . $instrument['label'] . '</span></label></div>';
				$isFirst = false;
			}
			$html .= '<div class="saved-payment-instrument new-instrument"><label><input type="radio" name="' . static::GATEWAY_ID . '_payment_instrument" value="" class="unzer-payment-instrument-radio unzer-payment-instrument-new-radio" /><span class="label">' . __( 'Use another one', 'unzer-payments' ) . '</span></label></div><div class="unzer-payment-instrument-new-form" style="display: none;">' . $originalForm . '</div>';
			$html .= '</div>';
			return $html;
		}
	}

	public function maybeSavePaymentInstrument( $paymentInstrumentId ) {
		if ( ! $this->isSaveInstruments() ) {
			return;
		}
		$paymentService    = new PaymentService();
		$unzerService      = $paymentService->getUnzerManager( $this );
		$paymentInstrument = $unzerService->getResourceService()->fetchPaymentType( $paymentInstrumentId );

		$user                 = wp_get_current_user();
		$existingPaymentMeans = get_user_meta( $user->ID, Main::USER_META_KEY_PAYMENT_INSTRUMENTS, true );
		if ( empty( $existingPaymentMeans ) || ! is_array( $existingPaymentMeans ) ) {
			$existingPaymentMeans = array();
		}
		if ( empty( $existingPaymentMeans[ $this->paymentTypeResource ] ) ) {
			$existingPaymentMeans[ $this->paymentTypeResource ] = array();
		}
		$existingPaymentMeans[ $this->paymentTypeResource ][ $paymentInstrumentId ] = array(
			'id'    => $paymentInstrumentId,
			'label' => $this->getLabelForPaymentInstrument( $paymentInstrument ),
		);
		update_user_meta( $user->ID, Main::USER_META_KEY_PAYMENT_INSTRUMENTS, $existingPaymentMeans );
	}

	public function getLabelForPaymentInstrument( BasePaymentType $paymentInstrument ): string {
		if ( $paymentInstrument instanceof Card ) {
			return $paymentInstrument->getNumber() . ' (' . $paymentInstrument->getExpiryDate() . ')';
		} elseif ( $paymentInstrument instanceof Paypal ) {
			return $paymentInstrument->getEmail();
		} elseif ( $paymentInstrument instanceof SepaDirectDebit ) {
			return $paymentInstrument->getIban();
		} else {
			return $paymentInstrument->getId();
		}
	}

	public function getSavedPaymentInstruments(): array {
		if ( ! is_user_logged_in() ) {
			return array();
		}
		$user                 = wp_get_current_user();
		$existingPaymentMeans = get_user_meta( $user->ID, Main::USER_META_KEY_PAYMENT_INSTRUMENTS, true );
		if ( empty( $existingPaymentMeans ) || ! is_array( $existingPaymentMeans ) || empty( $existingPaymentMeans[ $this->paymentTypeResource ] ) ) {
			return array();
		} else {
			return $existingPaymentMeans[ $this->paymentTypeResource ];
		}
	}

	public function deleteAllSavedPaymentInstruments() {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->usermeta . ' WHERE meta_key = %s', Main::USER_META_KEY_PAYMENT_INSTRUMENTS ) );
		foreach ( $results as $result ) {
			$data = unserialize( $result->meta_value );
			if ( is_array( $data ) && ! empty( $data[ $this->paymentTypeResource ] ) ) {
				unset( $data[ $this->paymentTypeResource ] );
				$data = serialize( $data );
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->usermeta . ' SET meta_value = %s WHERE umeta_id = %d', $data, $result->umeta_id ) );
			}
		}
	}
}
