<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Alcateia_Delivery_Plugin {
	private static $instance;
	public static function instance() { return self::$instance ?? ( self::$instance = new self() ); }
	private function __construct() {
		Alcateia_Delivery_Migrations::run();
		( new Alcateia_Delivery_Addons_Loader() )->load();
		( new Alcateia_Delivery_Update_Manager() )->hooks();
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
		add_action( 'wp_ajax_alcateia_delivery_simulate', array( $this, 'ajax_simulate' ) );
		add_action( 'wp_ajax_nopriv_alcateia_delivery_simulate', array( $this, 'ajax_simulate' ) );
		( new Alcateia_Delivery_Admin() )->hooks();
		( new Alcateia_Delivery_Shortcode() )->hooks();
	}
	public static function cache_version() { return (string) get_option( 'alcateia_delivery_cache_version', '1' ); }
	public static function flush_cache() { update_option( 'alcateia_delivery_cache_version', (string) time() ); }
	public static function log( $message, $context = array(), $level = 'info' ) { if ( function_exists( 'wc_get_logger' ) ) { wc_get_logger()->log( $level, $message . ' ' . wp_json_encode( $context ), array( 'source' => 'alcateia-delivery' ) ); } }
	public function ajax_simulate() {
		check_ajax_referer( 'alcateia_delivery_simulate', 'nonce' );
		if ( 'yes' === get_option( 'alcateia_delivery_maintenance_mode', 'no' ) ) { wp_send_json_error( array( 'message' => 'Simulador em manutenção.' ) ); }
		$qty = max( 1, absint( $_POST['qty'] ?? 1 ) );
		$weight = max( 0.01, (float) ( $_POST['weight'] ?? 0.3 ) );
		$region = sanitize_text_field( wp_unslash( $_POST['region'] ?? 'R4' ) );
		$result = ( new Alcateia_Delivery_Rule_Engine() )->calculate( compact( 'qty', 'weight', 'region' ) );
		update_option( 'alcateia_delivery_last_calc', gmdate( 'c' ), false );
		wp_send_json_success( $result ?: array( 'message' => 'Sem cobertura para os dados informados.' ) );
	}
	public function register_rest() {
		register_rest_route( 'alcateia-delivery/v1', '/health', array( 'methods' => 'GET', 'callback' => function(){ return rest_ensure_response( Alcateia_Delivery_Observability::health_report() ); }, 'permission_callback' => array( $this, 'rest_can_manage' ) ) );
		register_rest_route( 'alcateia-delivery/v1', '/license', array( 'methods' => 'POST', 'callback' => array( $this, 'rest_license' ), 'permission_callback' => array( $this, 'rest_can_manage' ) ) );
		register_rest_route( 'alcateia-delivery/v1', '/rates', array( array( 'methods' => 'GET', 'callback' => array( $this, 'rest_rates' ), 'permission_callback' => array( $this, 'rest_can_manage' ) ), array( 'methods' => 'POST', 'callback' => array( $this, 'rest_rates_upsert' ), 'permission_callback' => array( $this, 'rest_can_manage' ) ) ) );
		register_rest_route( 'alcateia-delivery/v1', '/calculate', array( 'methods' => 'POST', 'callback' => array( $this, 'rest_calculate' ), 'permission_callback' => array( $this, 'rest_calculate_permission' ) ) );
	}
	public function rest_license( WP_REST_Request $request ) { $action = sanitize_text_field( $request->get_param( 'action' ) ); if ( 'activate' === $action ) { return Alcateia_Delivery_License_Manager::activate( $request->get_param( 'key' ) ); } if ( 'deactivate' === $action ) { return Alcateia_Delivery_License_Manager::deactivate(); } return Alcateia_Delivery_License_Manager::validate_remote(); }
	public function rest_can_manage() { return current_user_can( 'manage_woocommerce' ); }
	public function rest_rates() { global $wpdb; return rest_ensure_response( $wpdb->get_results( 'SELECT * FROM ' . Alcateia_Delivery_DB::table_name(), ARRAY_A ) ); }
	public function rest_rates_upsert( WP_REST_Request $r ) { $rows = (array) $r->get_param( 'rows' ); return rest_ensure_response( Alcateia_Delivery_Importer::import_csv( $this->array_to_tmp_csv( $rows ), false ) ); }
	public function rest_calculate_permission( WP_REST_Request $request ) { return wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) || current_user_can( 'read' ); }
	public function rest_calculate( WP_REST_Request $r ) { $qty=max(1,(int)$r['qty']); $weight=max(0.01,(float)$r['weight']); $region=sanitize_text_field($r['region']); return rest_ensure_response( ( new Alcateia_Delivery_Rule_Engine() )->calculate( array( 'qty'=>$qty,'weight'=>$weight,'region'=>$region ) ) ); }
	private function array_to_tmp_csv( $rows ) { $tmp = wp_tempnam(); $h=fopen($tmp,'w'); fputcsv($h,array('weight_from','weight_to','qty_from','qty_to','region','region_label','shipping_cost','delivery_days','active')); foreach($rows as $row){fputcsv($h,$row);} fclose($h); return $tmp; }
	public function declare_hpos() { if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) { \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ALCATEIA_DELIVERY_FILE, true ); } }
	public function register_shipping( $methods ) { $methods['alcateia_delivery'] = 'Alcateia_Delivery_Shipping_Method'; return $methods; }
}
