<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Alcateia_Delivery_Addons_Loader {
	public function load() {
		$addons = (array) get_option( 'alcateia_delivery_enabled_addons', array() );
		foreach ( glob( ALCATEIA_DELIVERY_PATH . 'addons/*/bootstrap.php' ) as $file ) {
			$slug = basename( dirname( $file ) );
			if ( in_array( $slug, $addons, true ) ) { require_once $file; }
		}
	}
}
