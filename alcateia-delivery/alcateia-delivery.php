<?php
/**
 * Plugin Name: Alcateia Delivery
 * Description: Método de frete personalizado para WooCommerce com tabela editável para Entrega Express.
 * Version: 1.4.1
 * Author: Alcateia Editorial
 * Text Domain: alcateia-delivery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ALCATEIA_DELIVERY_VERSION', '1.4.1' );
define( 'ALCATEIA_DELIVERY_FILE', __FILE__ );
define( 'ALCATEIA_DELIVERY_PATH', plugin_dir_path( __FILE__ ) );
define( 'ALCATEIA_DELIVERY_URL', plugin_dir_url( __FILE__ ) );

require_once ALCATEIA_DELIVERY_PATH . 'includes/class-alcateia-delivery-db.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/migrations/class-alcateia-delivery-migrations.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/services/class-alcateia-delivery-license-manager.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/services/class-alcateia-delivery-update-manager.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/services/class-alcateia-delivery-telemetry.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/services/class-alcateia-delivery-observability.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/services/class-alcateia-delivery-addons-loader.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/engine/interface-alcateia-delivery-strategy.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/engine/class-alcateia-delivery-table-strategy.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/engine/class-alcateia-delivery-rule-engine.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/class-alcateia-delivery-importer.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/class-alcateia-delivery-admin.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/class-alcateia-delivery-shortcode.php';
require_once ALCATEIA_DELIVERY_PATH . 'includes/class-alcateia-delivery-plugin.php';

register_activation_hook( __FILE__, array( 'Alcateia_Delivery_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Alcateia_Delivery_DB', 'deactivate' ) );

add_action(
	'plugins_loaded',
	function() {
		if ( class_exists( 'WooCommerce' ) ) {
			require_once ALCATEIA_DELIVERY_PATH . 'includes/class-alcateia-delivery-shipping-method.php';
			Alcateia_Delivery_Plugin::instance();
			return;
		}

		add_action(
			'admin_notices',
			function() {
				if ( current_user_can( 'activate_plugins' ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Alcateia Delivery requer WooCommerce ativo para funcionar.', 'alcateia-delivery' ) . '</p></div>';
				}
			}
		);
	}
);
