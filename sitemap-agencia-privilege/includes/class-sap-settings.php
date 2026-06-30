<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SAP_Settings {
	public static function defaults() {
		return array(
			'urls_per_file'      => 1000,
			'batch_size'         => 300,
			'batch_pause'        => 250,
			'include_types'      => array( 'page' ),
			'include_taxonomies' => array(),
			'exclude_ids'        => '',
			'exclude_noindex'    => 1,
			'clean_before'       => 1,
			'include_lastmod'    => 1,
			'include_changefreq' => 1,
			'include_priority'   => 1,
		);
	}

	public static function ensure_defaults() {
		if ( false === get_option( SAP_OPTION_SETTINGS, false ) ) {
			add_option( SAP_OPTION_SETTINGS, self::defaults(), '', false );
		}
	}

	public static function get() {
		$saved = get_option( SAP_OPTION_SETTINGS, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function sanitize( $input ) {
		$public_types = self::public_post_types();
		$public_taxes = self::public_taxonomies();
		$types        = isset( $input['include_types'] ) && is_array( $input['include_types'] ) ? array_map( 'sanitize_key', wp_unslash( $input['include_types'] ) ) : array();
		$taxes        = isset( $input['include_taxonomies'] ) && is_array( $input['include_taxonomies'] ) ? array_map( 'sanitize_key', wp_unslash( $input['include_taxonomies'] ) ) : array();

		$settings = array(
			'urls_per_file'      => isset( $input['urls_per_file'] ) ? max( 100, min( 50000, absint( $input['urls_per_file'] ) ) ) : 1000,
			'batch_size'         => isset( $input['batch_size'] ) ? max( 100, min( 1000, absint( $input['batch_size'] ) ) ) : 300,
			'batch_pause'        => isset( $input['batch_pause'] ) ? max( 0, min( 5000, absint( $input['batch_pause'] ) ) ) : 250,
			'include_types'      => array_values( array_intersect( $types, array_keys( $public_types ) ) ),
			'include_taxonomies' => array_values( array_intersect( $taxes, array_keys( $public_taxes ) ) ),
			'exclude_ids'        => isset( $input['exclude_ids'] ) ? sanitize_text_field( wp_unslash( $input['exclude_ids'] ) ) : '',
			'exclude_noindex'    => ! empty( $input['exclude_noindex'] ) ? 1 : 0,
			'clean_before'       => ! empty( $input['clean_before'] ) ? 1 : 0,
			'include_lastmod'    => ! empty( $input['include_lastmod'] ) ? 1 : 0,
			'include_changefreq' => ! empty( $input['include_changefreq'] ) ? 1 : 0,
			'include_priority'   => ! empty( $input['include_priority'] ) ? 1 : 0,
		);

		if ( empty( $settings['include_types'] ) && empty( $settings['include_taxonomies'] ) ) {
			$settings['include_types'] = array( 'page' );
		}

		return $settings;
	}

	public static function save( $settings ) {
		update_option( SAP_OPTION_SETTINGS, $settings, false );
	}

	public static function excluded_ids( $settings ) {
		$ids = preg_split( '/[\s,]+/', isset( $settings['exclude_ids'] ) ? $settings['exclude_ids'] : '' );
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	public static function public_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		return $types;
	}

	public static function public_taxonomies() {
		return get_taxonomies( array( 'public' => true ), 'objects' );
	}
}
