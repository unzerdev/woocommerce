<?php

namespace UnzerPayments\Gateways\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;
use UnzerPayments\Controllers\CheckoutController;
use UnzerPayments\Gateways\AbstractGateway;
use UnzerPayments\Gateways\Alipay;
use UnzerPayments\Gateways\Bancontact;
use UnzerPayments\Gateways\Eps;
use UnzerPayments\Gateways\Ideal;
use UnzerPayments\Gateways\Klarna;
use UnzerPayments\Gateways\Paypal;
use UnzerPayments\Gateways\PostFinanceCard;
use UnzerPayments\Gateways\PostFinanceEfinance;
use UnzerPayments\Gateways\Prepayment;
use UnzerPayments\Gateways\Przelewy24;
use UnzerPayments\Gateways\Twint;
use UnzerPayments\Gateways\WeChatPay;
use UnzerPayments\Gateways\Wero;
use UnzerPayments\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractBlock extends AbstractPaymentMethodType {


	/**
	 * Payment methods without UI elements
	 */
	public const SIMPLE_PAYMENT_METHODS = array(
		Alipay::GATEWAY_ID,
		Bancontact::GATEWAY_ID,
		Eps::GATEWAY_ID,
		Ideal::GATEWAY_ID,
		Klarna::GATEWAY_ID,
		Paypal::GATEWAY_ID,
		PostFinanceCard::GATEWAY_ID,
		PostFinanceEfinance::GATEWAY_ID,
		Prepayment::GATEWAY_ID,
		Przelewy24::GATEWAY_ID,
		Twint::GATEWAY_ID,
		WeChatPay::GATEWAY_ID,
		Wero::GATEWAY_ID,
	);

	public function get_payment_method_data() {
		$gatewayClass = static::GATEWAY_CLASS;
		/** @var AbstractGateway $gateway */
		$gateway = new $gatewayClass();
		return array(
			'id'                 => static::GATEWAY_ID,
			'title'              => $gateway->title,
			'description'        => $gateway->description,
			'allowedCountries'   => $gateway->allowedCountries,
			'allowedCurrencies'  => $gateway->allowedCurrencies,
			'publicKey'          => $gateway->get_public_key(),
			'locale'             => get_locale(),
			'nonce'              => Util::getNonce(),
			'paymentComponentId' => str_replace( '_', '-', static::GATEWAY_ID ) . '-payment-component',
			'getCustomerDataUrl' => WC()->api_request_url( CheckoutController::GET_UNZER_CUSTOMER_SLUG ),
		);
	}

	public function get_name() {
		return $this->name;
	}

	public function is_active() {
		$gatewayClass = static::GATEWAY_CLASS;
		/** @var AbstractGateway $gateway */
		$gateway = new $gatewayClass();
		return $gateway->is_enabled();
	}

	public function get_script_data() {
		return $this->get_payment_method_data();
	}

	protected function get_identifier() {
		$identifier = 'unzer_simple';
		if ( ! in_array( static::GATEWAY_ID, self::SIMPLE_PAYMENT_METHODS, true ) ) {
			$identifier = static::GATEWAY_ID;
		}
		return $identifier;
	}

	public function get_payment_method_script_handles() {
		return array( $this->get_identifier() . '-block-checkout' );
	}


	public function initialize() {
		if ( ! $this->should_enqueue_assets() || is_admin() ) {
			return;
		}
		// TODO replace when minimum WP version is 6.5 (wp_enqueue_script_module)
		add_filter(
			'script_loader_tag',
			function ( $tag, $handle, $src ) {
				// if not your script, do nothing and return original $tag
				if ( 'unzer_ui_v2_js' !== $handle ) {
					return $tag;
				}
				// change the script tag by adding type="module" and return it.
				$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
				return $tag;
			},
			10,
			3
		);
		wp_enqueue_script( 'unzer_ui_v2_js', 'https://static.test.unzer.com/v2/ui-components/index.js', array(), UNZER_VERSION, array( 'in_footer' => true ) ); // https://static-v2.unzer.com/v2/ui-components/index.js
		wp_register_script( 'unzer_global-block-checkout', UNZER_PLUGIN_URL . '/assets/build/unzer_global.js', array( 'wc-blocks-registry' ), UNZER_VERSION, array( 'in_footer' => true ) );
		wp_enqueue_script( 'unzer_global-block-checkout' );
		$this->settings           = array(
			'title' => $this->get_setting( 'title' ),
		);
		$identifier               = $this->get_identifier();
		$script_dependencies_path = UNZER_PLUGIN_PATH . 'assets/build/' . $identifier . '.asset.php';
		$script_url               = UNZER_PLUGIN_URL . '/assets/build/' . $identifier . '.js';
		$style_path               = UNZER_PLUGIN_PATH . 'assets/build/' . $identifier . '.css';
		$style_url                = UNZER_PLUGIN_URL . '/assets/build/' . $identifier . '.css';
		$asset_handle             = $identifier . '-block-checkout';

		$script_dependencies = require $script_dependencies_path;
		wp_register_script( $asset_handle, $script_url, $script_dependencies['dependencies'], $script_dependencies['version'], array( 'in_footer' => true ) );
		if ( file_exists( $style_path ) ) {
			wp_register_style( $asset_handle, $style_url, array(), UNZER_VERSION );
		}

		wp_set_script_translations( $asset_handle, 'unzer-payments' );

		wp_enqueue_script( $asset_handle );
		if ( wp_style_is( $asset_handle, 'registered' ) ) {
			wp_enqueue_style( $asset_handle );
		}
	}

	private function should_enqueue_assets(): bool {
		$should_enqueue = ! is_order_received_page() && ! is_checkout_pay_page();

		$is_cart_block_in_use     = class_exists( CartCheckoutUtils::class ) && CartCheckoutUtils::is_cart_block_default();
		$is_checkout_block_in_use = class_exists( CartCheckoutUtils::class ) && CartCheckoutUtils::is_checkout_block_default();

		return $should_enqueue && ( wp_is_block_theme() || $is_cart_block_in_use || $is_checkout_block_in_use );
	}
}
