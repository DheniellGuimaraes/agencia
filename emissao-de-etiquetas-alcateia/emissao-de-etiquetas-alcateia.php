<?php
/**
 * Plugin Name: Emissão de Etiquetas Alcateia
 * Plugin URI: https://alcateiaeditorial.com.br/
 * Description: Gere etiquetas internas, rastreamento, picking lists e romaneios para pedidos WooCommerce.
 * Version: 1.2.0
 * Author: Alcateia Editorial
 * Author URI: https://alcateiaeditorial.com.br/
 * Text Domain: emissao-de-etiquetas-alcateia
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 *
 * @package AlcateiaLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ALCATEIA_LABELS_VERSION', '1.2.0' );
define( 'ALCATEIA_LABELS_FILE', __FILE__ );
define( 'ALCATEIA_LABELS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ALCATEIA_LABELS_URL', plugin_dir_url( __FILE__ ) );
define( 'ALCATEIA_LABELS_LOGO_URL', 'https://alcateiaeditorial.com.br/wp-content/uploads/2026/05/logo-alcateia.png' );

define( 'ALCATEIA_TRACKING_CODE_META', '_alcateia_tracking_code' );
define( 'ALCATEIA_TRACKING_CARRIER_META', '_alcateia_tracking_carrier' );
define( 'ALCATEIA_TRACKING_URL_META', '_alcateia_tracking_url' );
define( 'ALCATEIA_TRACKING_SHIPPED_DATE_META', '_alcateia_tracking_shipped_date' );
define( 'ALCATEIA_TRACKING_NOTES_META', '_alcateia_tracking_notes' );
define( 'ALCATEIA_TRACKING_SENT_META', '_alcateia_tracking_sent' );
define( 'ALCATEIA_TRACKING_SENT_AT_META', '_alcateia_tracking_sent_at' );
define( 'ALCATEIA_SETTINGS_OPTION', 'alcateia_labels_settings' );

/**
 * Main bootstrap class.
 */
final class Alcateia_Labels_Plugin {
	/** Register plugin hooks. */
	public static function init(): void {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_woocommerce_compatibility' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'load' ) );
		add_action( 'admin_notices', array( __CLASS__, 'woocommerce_notice' ) );
	}

	/** Declare compatibility with WooCommerce custom order tables (HPOS). */
	public static function declare_woocommerce_compatibility(): void {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ALCATEIA_LABELS_FILE, true );
		}
	}

	/** Load modules when WooCommerce is available. */
	public static function load(): void {
		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		$classes = array(
			'includes/class-alcateia-logger.php',
			'includes/class-alcateia-settings.php',
			'includes/class-alcateia-tracking.php',
			'includes/class-alcateia-picking-list.php',
			'includes/class-alcateia-manifest.php',
			'includes/class-alcateia-labels-generator.php',
			'includes/class-alcateia-labels-admin.php',
		);

		foreach ( $classes as $class_file ) {
			require_once ALCATEIA_LABELS_PATH . $class_file;
		}

		Alcateia_Settings::init();
		Alcateia_Tracking::init();
		Alcateia_Labels_Admin::init();
	}

	/** Check WooCommerce availability. */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_order' );
	}

	/** Show dependency warning. */
	public static function woocommerce_notice(): void {
		if ( self::is_woocommerce_active() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'O plugin Emissão de Etiquetas Alcateia requer WooCommerce ativo para funcionar.', 'emissao-de-etiquetas-alcateia' ) . '</p></div>';
	}
}

Alcateia_Labels_Plugin::init();
