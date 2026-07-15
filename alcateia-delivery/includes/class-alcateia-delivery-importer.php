<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alcateia_Delivery_Importer {
	public static function import_default_csv() {
		return self::import_csv( ALCATEIA_DELIVERY_PATH . 'data/default-rates.csv', true );
	}

	public static function import_csv( $path, $truncate = false, $preview = false ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'invalid_file', 'CSV inválido.' );
		}
		$rows = self::read_csv_rows( $path );
		if ( is_wp_error( $rows ) || $preview ) {
			return $rows;
		}
		return self::upsert_rows( $rows, $truncate );
	}

	public static function import_xlsx( $path, $preview = false ) {
		if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			return new WP_Error( 'xlsx_dependency', 'Instale phpoffice/phpspreadsheet para XLSX nativo.' );
		}
		$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $path )->getActiveSheet()->toArray( null, true, true, true );
		$header = array_shift( $sheet );
		$normalized_header = array_map( 'strtolower', array_values( $header ) );
		if ( $normalized_header !== self::expected_columns() ) {
			return new WP_Error( 'header', 'Cabeçalho XLSX inválido.' );
		}
		$rows = array();
		foreach ( $sheet as $line ) {
			$rows[] = self::normalize_row( array_values( $line ) );
		}
		if ( $preview ) {
			return array( 'rows' => $rows, 'errors' => array() );
		}
		return self::upsert_rows( $rows, false );
	}

	private static function expected_columns() {
		return array( 'weight_from', 'weight_to', 'qty_from', 'qty_to', 'region', 'region_label', 'shipping_cost', 'delivery_days', 'active' );
	}

	private static function read_csv_rows( $path ) {
		$h = fopen( $path, 'r' );
		$header = fgetcsv( $h );
		if ( self::expected_columns() !== $header ) { fclose( $h ); return new WP_Error( 'header', 'Cabeçalho CSV inválido.' ); }
		$rows = array();
		while ( ( $r = fgetcsv( $h ) ) !== false ) { $rows[] = self::normalize_row( $r ); }
		fclose( $h );
		return array( 'rows' => $rows, 'errors' => array() );
	}

	private static function normalize_row( $r ) {
		return array('weight_from'=>(float)$r[0],'weight_to'=>(float)$r[1],'qty_from'=>(int)$r[2],'qty_to'=>(int)$r[3],'region'=>sanitize_text_field($r[4]),'region_label'=>sanitize_text_field($r[5]),'shipping_cost'=>(float)$r[6],'delivery_days'=>(int)$r[7],'active'=>(int)$r[8]);
	}

	private static function upsert_rows( $payload, $truncate = false ) {
		global $wpdb;
		$table = Alcateia_Delivery_DB::table_name();
		$rows = $payload['rows'];
		if ( $truncate ) { $wpdb->query( "TRUNCATE TABLE {$table}" ); }
		foreach ( $rows as $row ) {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE region=%s AND weight_from=%f AND weight_to=%f AND qty_from=%d AND qty_to=%d LIMIT 1", $row['region'], $row['weight_from'], $row['weight_to'], $row['qty_from'], $row['qty_to'] ) );
			if ( $existing ) {
				$wpdb->update( $table, $row, array( 'id' => (int) $existing ) );
			} else {
				$wpdb->insert( $table, $row );
			}
		}
		Alcateia_Delivery_Plugin::flush_cache();
		Alcateia_Delivery_Plugin::log( 'Importação concluída', array( 'rows' => count( $rows ) ) );
		return true;
	}
}
