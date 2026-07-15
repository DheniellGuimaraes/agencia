<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alcateia_Delivery_DB {
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'alcateia_delivery_rates';
	}

	public static function history_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'alcateia_delivery_rate_history';
	}

	public static function activate() {
		self::create_tables();
		if ( 0 === (int) self::count_rules() ) {
			Alcateia_Delivery_Importer::import_default_csv();
		}
	}

	public static function deactivate() {}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		$history = self::history_table_name();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			priority INT UNSIGNED NOT NULL DEFAULT 100,
			weight_from DECIMAL(10,3) NOT NULL DEFAULT 0,
			weight_to DECIMAL(10,3) NOT NULL DEFAULT 0,
			qty_from INT UNSIGNED NOT NULL DEFAULT 0,
			qty_to INT UNSIGNED NOT NULL DEFAULT 999999,
			subtotal_from DECIMAL(12,2) NOT NULL DEFAULT 0,
			subtotal_to DECIMAL(12,2) NOT NULL DEFAULT 999999,
			zip_from VARCHAR(10) NOT NULL DEFAULT '',
			zip_to VARCHAR(10) NOT NULL DEFAULT '',
			state CHAR(2) NOT NULL DEFAULT '',
			region VARCHAR(50) NOT NULL,
			region_label VARCHAR(120) NOT NULL,
			product_ids TEXT NULL,
			category_ids TEXT NULL,
			shipping_class_ids TEXT NULL,
			shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
			delivery_days INT UNSIGNED NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_rule_lookup (active, region, state, priority, weight_from, weight_to, qty_from, qty_to),
			KEY idx_zip (zip_from, zip_to),
			KEY idx_subtotal (subtotal_from, subtotal_to)
		) {$charset};";

		$history_sql = "CREATE TABLE {$history} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rate_id BIGINT UNSIGNED NOT NULL,
			action_type VARCHAR(30) NOT NULL,
			changed_by BIGINT UNSIGNED NOT NULL,
			payload LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_rate (rate_id),
			KEY idx_action (action_type)
		) {$charset};";
		dbDelta( $sql );
		dbDelta( $history_sql );
	}

	public static function count_rules() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}
}
