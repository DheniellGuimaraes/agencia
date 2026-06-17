<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Alcateia_Delivery_Update_Manager {
	public function hooks() { add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_updates' ) ); }
	public function check_updates( $transient ) {
		if ( empty( $transient->checked ) ) { return $transient; }
		$endpoint = apply_filters( 'alcateia_delivery_update_endpoint', '' );
		if ( empty( $endpoint ) ) { return $transient; }
		// Estrutura preparada para integração remota futura.
		return $transient;
	}
}
