<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Alcateia_Delivery_Rule_Engine {
	private $strategies = array();
	public function __construct( $strategies = array() ) { $this->strategies = $strategies ?: array( new Alcateia_Delivery_Table_Strategy() ); }
	public function calculate( $context ) {
		foreach ( $this->strategies as $strategy ) {
			if ( $strategy->supports( $context ) ) {
				$result = $strategy->resolve( $context );
				if ( ! empty( $result ) ) { return $result; }
			}
		}
		return false;
	}
}
