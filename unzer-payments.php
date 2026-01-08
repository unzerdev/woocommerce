<?php
/**
 * Plugin Name: Unzer Payments
 * Plugin URI:
 * Description: Official Unzer Plugin
 * Author: Unzer
 * Author URI: https://www.unzer.com
 * Version: 2.0.1
 * License: Apache-2.0
 * Requires at least: 4.5
 * Tested up to: 6.9
 * WC requires at least: 6.0
 * WC tested up to: 10.4
 * Text Domain: unzer-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'UNZER_VERSION', '2.0.1' );
define( 'UNZER_PLUGIN_TYPE_STRING', 'Unzer Payments' );
define( 'UNZER_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'UNZER_PLUGIN_PATH', __DIR__ . '/' );
define( 'UNZER_PLUGIN_NAME', 'unzer-payments' );

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'unzer-payments', false, basename( __DIR__ ) . '/languages' );
	}
);

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="error"><p><strong>' . esc_html__( 'Unzer requires WooCommerce to be installed and active.', 'unzer-payments' ) . '</strong></p></div>';
				}
			);
			return;
		}
		require_once UNZER_PLUGIN_PATH . 'vendor/autoload.php';

		spl_autoload_register(
			function ( $class ) {
				$prefix   = 'UnzerPayments\\';
				$base_dir = UNZER_PLUGIN_PATH . 'includes/';

				$len = strlen( $prefix );
				if ( strncmp( $prefix, $class, $len ) !== 0 ) {
					return;
				}

				$relative_class = substr( $class, $len );
				$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

				if ( file_exists( $file ) ) {
					require_once $file;
				}
			}
		);

		$unzer = \UnzerPayments\Main::getInstance();
		$unzer->init();
	}
);

