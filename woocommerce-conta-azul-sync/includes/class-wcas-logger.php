<?php
/**
 * Logger and log table support.
 *
 * @package WooCommerceContaAzulSync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin logger.
 */
class WCAS_Logger {
	public const TABLE = 'wcas_logs';

	/**
	 * Create custom log table.
	 */
	public static function create_table(): void {
		global $wpdb;
		$table = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				type varchar(40) NOT NULL,
				order_id bigint(20) unsigned NULL,
				message text NOT NULL,
				context longtext NULL,
				PRIMARY KEY  (id),
				KEY type (type),
				KEY order_id (order_id),
				KEY created_at (created_at)
			) {$charset_collate};"
		);
	}

	/**
	 * Table name with prefix.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Log an event.
	 *
	 * @param string               $type Event type.
	 * @param string               $message Message.
	 * @param int|null             $order_id Order ID.
	 * @param array<string, mixed> $context Context.
	 */
	public static function log( string $type, string $message, ?int $order_id = null, array $context = array() ): void {
		global $wpdb;
		$context = WCAS_Utils::mask_sensitive( $context );
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at' => current_time( 'mysql' ),
				'type'       => sanitize_key( $type ),
				'order_id'   => $order_id,
				'message'    => sanitize_textarea_field( $message ),
				'context'    => wp_json_encode( $context ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info( $message, array( 'source' => 'woocommerce-conta-azul-sync', 'type' => $type, 'order_id' => $order_id, 'context' => $context ) );
		}
	}


	/**
	 * Log a structured diagnostic event for the admin dashboard.
	 *
	 * @param string               $type Event type/category.
	 * @param string               $action Action executed.
	 * @param string               $result Result label.
	 * @param string               $message Technical message.
	 * @param array<string, mixed> $context Additional safe context.
	 * @param int|null             $http_status Optional HTTP status.
	 */
	public static function diagnostic( string $type, string $action, string $result, string $message, array $context = array(), ?int $http_status = null ): void {
		$context = array_merge(
			array(
				'action'      => $action,
				'result'      => $result,
				'http_status' => $http_status,
			),
			$context
		);
		self::log( $type, $message, null, $context );
	}

	/**
	 * Fetch latest logs.
	 *
	 * @return array<int, object>
	 */
	public static function get_logs( int $limit = 100 ): array {
		global $wpdb;
		$limit = max( 1, min( 500, $limit ) );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY id DESC LIMIT %d', $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Clear logs.
	 */
	public static function clear(): void {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
