<?php
/**
 * Plugin Name: Unzer Payments
 * Plugin URI: https://github.com/unzerdev/woocommerce
 * Description: Official Unzer Plugin
 * Author: Unzer
 * Author URI: https://www.unzer.com
 * Version: 1.0.0
 * Requires at least: 4.5
 * Tested up to: 6.1
 * WC requires at least: 6.0
 * WC tested up to: 7.2
 * Text Domain: unzer-payments
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Required minimums and constants
 */
define('UNZER_VERSION', '1.0.0');
define('UNZER_PLUGIN_TYPE_STRING', 'Unzer Payments');
define('UNZER_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('UNZER_PLUGIN_PATH', __DIR__ . '/');
define('UNZER_PLUGIN_NAME', 'unzer-payments');

add_action('init', function () {
    load_plugin_textdomain(UNZER_PLUGIN_NAME, false, __DIR__ . '/languages');
});

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>' . __('Unzer requires WooCommerce to be installed and active.', UNZER_PLUGIN_NAME) . '</strong></p></div>';
        });
        return;
    }
    require_once UNZER_PLUGIN_PATH . 'vendor/autoload.php';
    require_once UNZER_PLUGIN_PATH . 'includes/Main.php';
    require_once UNZER_PLUGIN_PATH . 'includes/services/OrderService.php';
    require_once UNZER_PLUGIN_PATH . 'includes/services/LogService.php';
    require_once UNZER_PLUGIN_PATH . 'includes/services/PaymentService.php';
    require_once UNZER_PLUGIN_PATH . 'includes/services/ShopService.php';
    require_once UNZER_PLUGIN_PATH . 'includes/services/WebhookManagementService.php';
    require_once UNZER_PLUGIN_PATH . 'includes/controllers/CheckoutController.php';
    require_once UNZER_PLUGIN_PATH . 'includes/controllers/AdminController.php';
    require_once UNZER_PLUGIN_PATH . 'includes/controllers/WebhookController.php';
    require_once UNZER_PLUGIN_PATH . 'includes/gateways/AbstractGateway.php';
    foreach (glob(UNZER_PLUGIN_PATH . 'includes/gateways/*.php') as $gateway) {
        require_once $gateway;
    }

    $unzer = \UnzerPayments\Main::getInstance();
    $unzer->init();
});
