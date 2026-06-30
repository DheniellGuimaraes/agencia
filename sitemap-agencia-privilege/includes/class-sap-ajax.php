<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SAP_Ajax {
	public static function init() {
		$actions = array( 'start', 'process', 'finalize', 'clean', 'permissions', 'status', 'delete' );
		foreach ( $actions as $action ) { add_action( 'wp_ajax_sap_' . $action, array( __CLASS__, $action ) ); }
	}

	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => __( 'Permissão insuficiente.', 'sitemap-agencia-privilege' ) ), 403 ); }
		check_ajax_referer( SAP_NONCE_ACTION, 'nonce' );
	}

	public static function start() {
		self::guard();
		$settings = SAP_Settings::sanitize( $_POST );
		$state = SAP_Generator::start( $settings );
		if ( is_wp_error( $state ) ) { wp_send_json_error( array( 'message' => $state->get_error_message() ) ); }
		wp_send_json_success( self::payload( $state, __( 'Geração iniciada.', 'sitemap-agencia-privilege' ) ) );
	}

	public static function process() {
		self::guard();
		$state = SAP_Generator::process_next();
		$finished = empty( $state['tasks'][ (int) $state['task_index'] ] );
		wp_send_json_success( self::payload( $state, $finished ? __( 'Lotes concluídos. Finalize o índice.', 'sitemap-agencia-privilege' ) : __( 'Lote processado.', 'sitemap-agencia-privilege' ), $finished ) );
	}

	public static function finalize() {
		self::guard();
		$state = SAP_Generator::finalize();
		wp_send_json_success( self::payload( $state, __( 'Sitemap index criado com sucesso.', 'sitemap-agencia-privilege' ), true ) );
	}

	public static function clean() { self::guard(); wp_send_json_success( array( 'deleted' => SAP_Files::delete_generated(), 'files' => SAP_Files::list_files(), 'message' => __( 'Arquivos antigos removidos.', 'sitemap-agencia-privilege' ) ) ); }
	public static function permissions() { self::guard(); wp_send_json_success( array( 'diagnostics' => SAP_Files::diagnostics(), 'message' => __( 'Diagnóstico atualizado.', 'sitemap-agencia-privilege' ) ) ); }
	public static function status() { self::guard(); $settings = SAP_Settings::get(); wp_send_json_success( array( 'diagnostics' => SAP_Files::diagnostics(), 'files' => SAP_Files::list_files(), 'estimated' => SAP_Generator::estimate_total( $settings ), 'last_run' => get_option( SAP_OPTION_LAST_RUN, '' ), 'main_url' => SAP_Files::main_url() ) ); }
	public static function delete() { self::guard(); wp_send_json_success( array( 'deleted' => SAP_Files::delete_generated(), 'files' => SAP_Files::list_files(), 'message' => __( 'Sitemaps físicos apagados.', 'sitemap-agencia-privilege' ) ) ); }

	private static function payload( $state, $message, $finished = false ) {
		$generated = SAP_Files::list_files();
		$current = '';
		if ( isset( $state['tasks'][ (int) $state['task_index'] ] ) ) { $task = $state['tasks'][ (int) $state['task_index'] ]; $current = $task['name']; }
		return array( 'processed' => (int) ( $state['processed'] ?? 0 ), 'last_id' => isset( $task['last_id'] ) ? (int) $task['last_id'] : 0, 'current_file' => $current, 'generated_files' => $generated, 'finished' => (bool) $finished, 'estimated' => (int) ( $state['estimated'] ?? 0 ), 'message' => $message, 'main_url' => SAP_Files::main_url() );
	}
}
