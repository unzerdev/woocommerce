<?php

namespace UnzerPayments\Tokens;

use WC_Payment_Token;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaypalPaymentToken extends WC_Payment_Token {

	public const TYPE = 'UnzerPaypal';

	protected $type = self::TYPE;

	protected $extra_data = array(
		'email' => '',
	);

	public static function get_token_class( string $class, string $type ): string {
		return self::TYPE === $type ? self::class : $class;
	}

	public function get_display_name( $deprecated = '' ) {
		return $this->get_email();
	}

	public function get_email( $context = 'view' ) {
		return $this->get_prop( 'email', $context );
	}

	public function set_email( $email ) {
		$this->set_prop( 'email', $email );
	}

	public function validate() {
		if ( false === parent::validate() ) {
			return false;
		}

		if ( ! $this->get_email( 'edit' ) ) {
			return false;
		}

		return true;
	}
}
