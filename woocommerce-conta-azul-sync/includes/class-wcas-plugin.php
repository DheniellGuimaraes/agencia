<?php
/**
 * Main plugin bootstrap.
 *
 * @package WooCommerceContaAzulSync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
class WCAS_Plugin {
	private static ?WCAS_Plugin $instance = null;
	private WCAS_Auth $auth;

	/**
	 * Singleton.
	 */
	public static function instance(): WCAS_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Init plugin.
	 */
	public function init(): void {
		load_plugin_textdomain( WCAS_TEXT_DOMAIN, false, dirname( plugin_basename( WCAS_PLUGIN_FILE ) ) . '/languages' );

		$this->auth = new WCAS_Auth();
		add_action( 'admin_post_wcas_oauth_callback', array( $this->auth, 'handle_admin_post_callback' ) );
		$this->auth->maybe_handle_callback();

		$client    = new WCAS_API_Client( $this->auth );
		$customers = new WCAS_Customers( $client );
		$products  = new WCAS_Products( $client );
		$orders    = new WCAS_Orders( $client, $customers, $products );
		$admin     = new WCAS_Admin( $this->auth );

		$admin->init();
		if ( class_exists( 'WooCommerce' ) ) {
			$orders->init();
		} else {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		}
	}

	/**
	 * Activation routine.
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( plugin_basename( WCAS_PLUGIN_FILE ) );
			wp_die( esc_html__( 'WooCommerce Conta Azul Sync requer PHP 8.1 ou superior.', 'woocommerce-conta-azul-sync' ) );
		}
		if ( false === get_option( WCAS_Utils::OPTION_SETTINGS, false ) ) {
			WCAS_Utils::update_option_no_autoload( WCAS_Utils::OPTION_SETTINGS, WCAS_Utils::defaults() );
		}
		WCAS_Logger::create_table();
	}

	/**
	 * Deactivation routine.
	 */
	public static function deactivate(): void {
		// Keep settings, tokens and logs for safe reactivation.
	}

	/**
	 * Declare WooCommerce HPOS compatibility.
	 */
	public static function declare_hpos_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCAS_PLUGIN_FILE, true );
		}
	}

	/**
	 * Admin notice if WooCommerce is not active.
	 */
	public function woocommerce_missing_notice(): void {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'WooCommerce Conta Azul Sync está ativo, mas precisa do WooCommerce para sincronizar pedidos.', 'woocommerce-conta-azul-sync' ) . '</p></div>';
		}
	}
}
