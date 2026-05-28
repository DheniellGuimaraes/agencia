<?php
class Alcateia_Delivery_Test_Rest_Health extends WP_UnitTestCase {
	public function test_health_report_has_cache_version() {
		$report = Alcateia_Delivery_Observability::health_report();
		$this->assertArrayHasKey( 'cache_version', $report );
	}
}
