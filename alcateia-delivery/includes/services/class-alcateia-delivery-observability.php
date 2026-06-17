<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Alcateia_Delivery_Observability {
	public static function health_report() {
		global $wpdb;
		$table = Alcateia_Delivery_DB::table_name();
		$size = $wpdb->get_var( $wpdb->prepare( 'SELECT ROUND((DATA_LENGTH+INDEX_LENGTH)/1024,2) FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s', DB_NAME, $table ) );
		return array(
			'rest_api' => rest_url( 'alcateia-delivery/v1/rates' ),
			'ajax' => admin_url( 'admin-ajax.php' ),
			'cache_version' => Alcateia_Delivery_Plugin::cache_version(),
			'db_table' => $table,
			'table_size_kb' => $size,
			'last_calc' => get_option( 'alcateia_delivery_last_calc', '' ),
			'last_import' => get_option( 'alcateia_delivery_last_import', '' ),
			'maintenance_mode' => get_option( 'alcateia_delivery_maintenance_mode', 'no' ),
		);
	}
}
