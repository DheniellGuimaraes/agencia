<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Alcateia_Delivery_License_Manager {
	const OPTION_KEY = 'alcateia_delivery_license';
	public static function get_status() { return get_option( self::OPTION_KEY, array( 'key' => '', 'status' => 'inactive', 'expires_at' => '', 'domain' => '', 'last_check' => '' ) ); }
	public static function activate( $key ) {
		$data = self::get_status();
		$data['key'] = sanitize_text_field( $key );
		$data['status'] = 'active';
		$data['domain'] = wp_parse_url( home_url(), PHP_URL_HOST );
		$data['last_check'] = gmdate( 'c' );
		update_option( self::OPTION_KEY, $data, false );
		return $data;
	}
	public static function deactivate() { $data = self::get_status(); $data['status'] = 'inactive'; update_option( self::OPTION_KEY, $data, false ); return $data; }
	public static function validate_remote() { $data = self::get_status(); $data['last_check'] = gmdate( 'c' ); update_option( self::OPTION_KEY, $data, false ); return $data; }
}
