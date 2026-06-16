<?php
/**
 * Internal logger for plugin events.
 *
 * @package AlcateiaLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores a small bounded log in a WordPress option. */
class Alcateia_Logger {
	public const OPTION = 'alcateia_labels_logs';
	private const LIMIT = 100;

	/** Add an event to the internal log. */
	public static function log( string $event, string $message = '', array $context = array() ): void {
		$logs = self::get_logs();
		array_unshift(
			$logs,
			array(
				'time'    => current_time( 'mysql' ),
				'event'   => sanitize_key( $event ),
				'message' => sanitize_text_field( $message ),
				'context' => self::sanitize_context( $context ),
			)
		);
		$logs = array_slice( $logs, 0, self::LIMIT );
		update_option( self::OPTION, $logs, false );
	}

	/** Return logs newest first. */
	public static function get_logs(): array {
		$logs = get_option( self::OPTION, array() );
		return is_array( $logs ) ? $logs : array();
	}

	/** Clear internal logs. */
	public static function clear(): void {
		delete_option( self::OPTION );
	}

	/** Sanitize context values for safe storage/display. */
	private static function sanitize_context( array $context ): array {
		$clean = array();
		foreach ( $context as $key => $value ) {
			if ( is_scalar( $value ) || null === $value ) {
				$clean[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
			}
		}
		return $clean;
	}
}
