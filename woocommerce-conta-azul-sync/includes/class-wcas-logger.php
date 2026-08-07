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
	private const OAUTH_TRACE_FILE = 'logs/oauth-full-trace.log';
	private const OAUTH_TRACE_MAX_ENTRIES = 500;

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
	 * Record an OAuth full-trace event in the DB logger and in logs/oauth-full-trace.log.
	 *
	 * @param string               $action Action/event name.
	 * @param string               $phase Trace phase: sent, received, error, token, system.
	 * @param string               $result Result label.
	 * @param string               $message Technical message.
	 * @param array<string, mixed> $context Safe diagnostic context.
	 * @param int|null             $http_status Optional HTTP status.
	 */
	public static function oauth_trace( string $action, string $phase, string $result, string $message, array $context = array(), ?int $http_status = null ): void {
		$context = array_merge( array( 'trace_phase' => sanitize_key( $phase ) ), $context );
		self::diagnostic( 'oauth', $action, $result, $message, $context, $http_status );
		self::append_oauth_trace_file(
			array(
				'timestamp'   => current_time( 'mysql' ),
				'type'        => 'oauth',
				'phase'       => sanitize_key( $phase ),
				'action'      => $action,
				'result'      => $result,
				'message'     => $message,
				'http_status' => $http_status,
				'context'     => $context,
			)
		);
	}

	/**
	 * Fetch OAuth full trace entries from the file log.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_oauth_trace( int $limit = 500 ): array {
		$file = self::oauth_trace_path();
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			return array();
		}
		$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! is_array( $lines ) ) {
			return array();
		}
		$lines = array_slice( $lines, -1 * max( 1, min( self::OAUTH_TRACE_MAX_ENTRIES, $limit ) ) );
		$entries = array();
		foreach ( array_reverse( $lines ) as $line ) {
			$decoded = json_decode( $line, true );
			if ( is_array( $decoded ) ) {
				$entries[] = $decoded;
			}
		}
		return $entries;
	}

	/**
	 * Remove OAuth full-trace file contents.
	 */
	public static function clear_oauth_trace(): void {
		$file = self::oauth_trace_path();
		if ( ! self::ensure_oauth_trace_directory() || ( file_exists( $file ) && ! is_writable( $file ) ) ) {
			return;
		}
		file_put_contents( $file, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Append and rotate the OAuth trace file.
	 *
	 * @param array<string, mixed> $entry Entry data.
	 */
	private static function append_oauth_trace_file( array $entry ): void {
		if ( ! self::ensure_oauth_trace_directory() ) {
			return;
		}
		$file = self::oauth_trace_path();
		if ( file_exists( $file ) && ! is_writable( $file ) ) {
			return;
		}
		$entry = WCAS_Utils::mask_sensitive( $entry );
		$line = wp_json_encode( $entry );
		if ( ! is_string( $line ) ) {
			return;
		}
		$lines = file_exists( $file ) ? file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array();
		$lines = is_array( $lines ) ? array_slice( $lines, -1 * ( self::OAUTH_TRACE_MAX_ENTRIES - 1 ) ) : array();
		$lines[] = $line;
		file_put_contents( $file, implode( PHP_EOL, $lines ) . PHP_EOL, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * OAuth trace file path.
	 */
	private static function oauth_trace_path(): string {
		return trailingslashit( WCAS_PLUGIN_DIR ) . self::OAUTH_TRACE_FILE;
	}

	/**
	 * Ensure log directory exists and is not directly browsable on Apache.
	 */
	private static function ensure_oauth_trace_directory(): bool {
		$directory = dirname( self::oauth_trace_path() );
		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}
		if ( ! is_dir( $directory ) || ! is_writable( $directory ) ) {
			return false;
		}
		$htaccess = trailingslashit( $directory ) . '.htaccess';
		$index = trailingslashit( $directory ) . 'index.php';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return true;
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
		self::clear_oauth_trace();
	}
}
