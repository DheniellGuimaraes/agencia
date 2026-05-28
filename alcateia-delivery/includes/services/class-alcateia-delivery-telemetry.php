<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Alcateia_Delivery_Telemetry {
	const OPT_IN = 'alcateia_delivery_telemetry_opt_in';
	public static function is_enabled() { return 'yes' === get_option( self::OPT_IN, 'no' ); }
	public static function payload() {
		global $wpdb;
		return array(
			'wp_version' => get_bloginfo( 'version' ),
			'wc_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'not-installed',
			'rules_count' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Alcateia_Delivery_DB::table_name() ),
			'critical_errors' => (int) get_option( 'alcateia_delivery_critical_errors', 0 ),
		);
	}
}
