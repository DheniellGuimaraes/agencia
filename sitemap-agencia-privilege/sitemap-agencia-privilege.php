<?php
/**
 * Plugin Name: Sitemap Agencia Privilége
 * Plugin URI: https://agenciaprivilege.com/
 * Description: Gerador premium de sitemaps XML físicos em lotes para WordPress.
 * Version: 1.0.0
 * Author: Agencia Privilége
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: sitemap-agencia-privilege
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAP_VERSION', '1.0.0' );
define( 'SAP_PLUGIN_FILE', __FILE__ );
define( 'SAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAP_OPTION_SETTINGS', 'sap_sitemap_settings' );
define( 'SAP_OPTION_STATE', 'sap_sitemap_generation_state' );
define( 'SAP_OPTION_LAST_RUN', 'sap_sitemap_last_run' );
define( 'SAP_NONCE_ACTION', 'sap_sitemap_nonce' );

require_once SAP_PLUGIN_DIR . 'includes/class-sap-settings.php';
require_once SAP_PLUGIN_DIR . 'includes/class-sap-files.php';
require_once SAP_PLUGIN_DIR . 'includes/class-sap-generator.php';
require_once SAP_PLUGIN_DIR . 'includes/class-sap-ajax.php';
require_once SAP_PLUGIN_DIR . 'includes/class-sap-admin.php';

final class SAP_Plugin {
	public static function init() {
		SAP_Admin::init();
		SAP_Ajax::init();
	}

	public static function activate() {
		SAP_Settings::ensure_defaults();
		SAP_Files::ensure_directory();
	}
}

register_activation_hook( __FILE__, array( 'SAP_Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'SAP_Plugin', 'init' ) );
