<?php
class Alcateia_Delivery_Test_Rule_Engine extends WP_UnitTestCase {
	public function test_engine_returns_false_without_rule() {
		$engine = new Alcateia_Delivery_Rule_Engine( array() );
		$this->assertFalse( $engine->calculate( array( 'qty' => 1 ) ) );
	}
}
