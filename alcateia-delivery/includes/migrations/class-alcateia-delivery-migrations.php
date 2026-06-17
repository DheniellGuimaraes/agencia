<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Alcateia_Delivery_Migrations {
	const VERSION_OPTION = 'alcateia_delivery_schema_version';
	public static function current_version() { return '1.2.0'; }
	public static function run() {
		$installed = (string) get_option( self::VERSION_OPTION, '1.0.0' );
		if ( version_compare( $installed, '1.2.0', '<' ) ) {
			Alcateia_Delivery_DB::create_tables();
			update_option( self::VERSION_OPTION, self::current_version(), false );
		}
	}
}
