<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Alcateia_Delivery_Deadline {
	const BASE_DAYS_OPTION = 'alcateia_delivery_deadline_base_days';
	const EXTRA_DAYS_OPTION = 'alcateia_delivery_deadline_extra_days';
	const LEGACY_SETTINGS_OPTION = 'alcateia_delivery_settings';
	const LEGACY_DEFAULT_DAYS_OPTION = 'alcateia_delivery_default_days';
	const LEGACY_EXTRA_DAYS_OPTION = 'alcateia_delivery_extra_days';

	public static function get_settings() {
		$legacy = wp_parse_args( (array) get_option( self::LEGACY_SETTINGS_OPTION, array() ), array( 'default_days' => 7, 'extra_days' => 0 ) );
		$base_days = get_option( self::BASE_DAYS_OPTION, get_option( self::LEGACY_DEFAULT_DAYS_OPTION, $legacy['default_days'] ) );
		$extra_days = get_option( self::EXTRA_DAYS_OPTION, get_option( self::LEGACY_EXTRA_DAYS_OPTION, $legacy['extra_days'] ) );

		return array(
			'base_days'  => max( 1, absint( $base_days ) ),
			'extra_days' => max( 0, absint( $extra_days ) ),
		);
	}

	public static function save_settings( $base_days, $extra_days ) {
		$settings = array(
			'base_days'  => max( 1, absint( $base_days ) ),
			'extra_days' => max( 0, absint( $extra_days ) ),
		);
		$legacy = array( 'default_days' => $settings['base_days'], 'extra_days' => $settings['extra_days'] );

		update_option( self::BASE_DAYS_OPTION, $settings['base_days'], false );
		update_option( self::EXTRA_DAYS_OPTION, $settings['extra_days'], false );
		update_option( self::LEGACY_DEFAULT_DAYS_OPTION, $settings['base_days'], false );
		update_option( self::LEGACY_EXTRA_DAYS_OPTION, $settings['extra_days'], false );
		update_option( self::LEGACY_SETTINGS_OPTION, $legacy, false );

		return $settings;
	}

	public static function total_days() {
		$settings = self::get_settings();
		return max( 1, $settings['base_days'] + $settings['extra_days'] );
	}

	public static function label_text( $title = 'Entrega Express' ) {
		return $title . ' — Receba em até ' . self::total_days() . ' dias úteis';
	}

	public static function replace_days_in_label( $label ) {
		$replacement = 'Receba em até ' . self::total_days() . ' dias úteis';
		if ( preg_match( '/Receba em até \d+ dias úteis/u', $label ) ) {
			return preg_replace( '/Receba em até \d+ dias úteis/u', $replacement, $label );
		}
		return $label;
	}
}
