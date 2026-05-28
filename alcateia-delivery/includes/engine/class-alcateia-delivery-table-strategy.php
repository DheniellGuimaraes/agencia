<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Alcateia_Delivery_Table_Strategy implements Alcateia_Delivery_Strategy {
	public function supports( $context ) { return true; }
	public function resolve( $context ) { return Alcateia_Delivery_Shipping_Method::calculate_estimate( $context ); }
}
