<?php
/**
 * Plugin Name: WooCommerce Conta Azul Sync
 * Plugin URI: https://developers.contaazul.com/guide
 * Description: Integra WooCommerce com Conta Azul Pro usando a nova API OAuth2 da Conta Azul.
 * Version: 1.0.0
 * Author: Agência
 * Text Domain: woocommerce-conta-azul-sync
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 *
 * @package WooCommerceContaAzulSync
 */

defined( 'ABSPATH' ) || exit;

define( 'WCAS_VERSION', '1.0.0' );
define( 'WCAS_PLUGIN_FILE', __FILE__ );
define( 'WCAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCAS_TEXT_DOMAIN', 'woocommerce-conta-azul-sync' );

require_once WCAS_PLUGIN_DIR . 'includes/class-wcas-utils.php';
require_once WCAS_PLUGIN_DIR . 'includes/class-wcas-logger.php';
require_once WCAS_PLUGIN_DIR . 'includes/class-wcas-auth.php';
require_once WCAS_PLUGIN_DIR . 'includes/class-wcas-api-client.php';
require_once WCAS_PLUGIN_DIR . 'includes/class-wcas-customers.php';
require_once WCAS_PLUGIN_DIR . 'includes/class-wcas-products.php';
require_once WCAS_PLUGIN_DIR . 'includes/class-wcas-orders.php';
require_once WCAS_PLUGIN_DIR . 'includes/class-wcas-admin.php';
require_once WCAS_PLUGIN_DIR . 'includes/class-wcas-plugin.php';

add_action( 'before_woocommerce_init', array( 'WCAS_Plugin', 'declare_hpos_compatibility' ) );

register_activation_hook( __FILE__, array( 'WCAS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCAS_Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		WCAS_Plugin::instance()->init();
	}
);
