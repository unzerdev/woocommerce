<?php

namespace UnzerPayments;

use Automattic\WooCommerce\Utilities\OrderUtil;

class Util {



	public const NONCE_NAME  = 'unzer_nonce';
	protected static $nonces = array();

	public static function safeCompareAmount( $amount1, $amount2 ): bool {
		return number_format( $amount1, 2 ) === number_format( $amount2, 2 );
	}

	public static function round( $amount, $precision = 2 ): float {
		return round( $amount, $precision );
	}

	public static function isHPOS(): bool {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}
		if ( ! method_exists( OrderUtil::class, 'custom_orders_table_usage_is_enabled' ) ) {
			return false;
		}
		return OrderUtil::custom_orders_table_usage_is_enabled();
	}

	public static function getNonce( string $action = '' ): string {
		$action = $action ?: self::NONCE_NAME;
		if ( ! isset( self::$nonces[ $action ] ) ) {
			self::$nonces[ $action ] = wp_create_nonce( $action );
		}
		return self::$nonces[ $action ];
	}

	public static function getNonceField( $doPrint = true, string $action = '' ) {
		$action        = $action ?: self::NONCE_NAME;
		$nameAttribute = esc_attr( $action );
		$html          = '<input type="hidden" class="nonce-input--' . $nameAttribute . '" name="' . $nameAttribute . '" value="' . esc_attr( self::getNonce( $action ) ) . '" />';
		if ( $doPrint ) {
			// this is safe because the html is static and attributes are escaped
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            //@codingStandardsIgnoreLine
            echo $html;
		} else {
			return $html;
		}
	}

	public static function getNonceCheckedPostValue( string $key, $skipSanitize = false ): ?string {
		if ( ! empty( $_POST[ $key ] ) ) {
			// our own nonce:
			if ( isset( $_POST[ self::NONCE_NAME ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_NAME ) ) {
				return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
			// woocommerce nonce:
			if ( isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'update-order-review' ) ) {
				$value = wp_unslash( $_POST[ $key ] );
				return $skipSanitize ? $value : sanitize_text_field( $value );
			}
		}
		return null;
	}

	public static function getNonceCheckedBillingData(): array {
		$postData = self::getNonceCheckedPostValue( 'post_data', true );
		if ( ! empty( $postData ) ) {
			parse_str( $postData, $params );
			$params = array_map( 'sanitize_text_field', $params );
			return $params;
		}
		return array();
	}


	public static function escape_array_html( $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				// Recursively escape the value
				$data[ $key ] = self::escape_array_html( $value );
			}
		} elseif ( is_scalar( $data ) ) {
			// Escape the string
			$data = wp_kses_post( (string) $data );
		}

		return $data;
	}
}
