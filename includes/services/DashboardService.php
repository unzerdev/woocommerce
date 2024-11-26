<?php

namespace UnzerPayments\Services;

use UnzerPayments\Controllers\AdminController;
use UnzerPayments\Util;

class DashboardService {

	const OPTION_KEY_NOTIFICATIONS = 'unzer_notifications';

	public function getNotifications(): array {
		return get_option( self::OPTION_KEY_NOTIFICATIONS, array() );
	}

	public function addError( $messageType, $parameters = array() ) {
		$errors             = $this->getNotifications();
		$errors[ uniqid() ] = array(
			'type'        => 'error',
			'messageType' => $messageType,
			'parameters'  => $parameters,
		);
		update_option( self::OPTION_KEY_NOTIFICATIONS, $errors );
	}

	public function showNotifications() {
		$notifications = $this->getNotifications();
		foreach ( $notifications as $notificationId => $notification ) {
			$message = isset( $notification['message'] ) ? $notification['message'] : '';
			switch ( $notification['messageType'] ) {
				case 'chargeback':
					$message = sprintf( __( 'A chargeback has been received. Please check the payment details for order %s', 'unzer-payments' ), $notification['parameters'][0] );
					break;
				case 'apple_pay_id_file':
					$message = __( 'Could not create apple-developer-merchantid-domain-association file', 'unzer-payments' );
					break;
			}
			echo '<div class="notice notice-' . esc_attr( $notification['type'] ) . '">
                    <p>' . wp_kses_post( $message ) . '</p>
                    <p><button href="#" class="button button-small button-primary dismiss-unzer-notification" data-id="' . esc_attr( $notificationId ) . '" data-url="' . esc_url( WC()->api_request_url( AdminController::NOTIFICATION_SLUG ) ) . '" data-nonce="' . esc_attr( Util::getNonce() ) . '">' . esc_html__( 'Dismiss this notification', 'unzer-payments' ) . '</button></p>
                  </div>';
		}
	}

	public function removeNotification( $key ) {
		$notifications = $this->getNotifications();
		if ( isset( $notifications[ $key ] ) ) {
			unset( $notifications[ $key ] );
			update_option( self::OPTION_KEY_NOTIFICATIONS, $notifications );
		}
	}
}
