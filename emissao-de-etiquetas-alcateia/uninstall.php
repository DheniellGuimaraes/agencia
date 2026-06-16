<?php
/**
 * Uninstall cleanup for Emissão de Etiquetas Alcateia.
 *
 * This file intentionally removes only plugin-owned options. Order metadata is
 * preserved by default so stores do not lose tracking history after uninstall.
 *
 * @package AlcateiaLabels
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'alcateia_labels_settings' );
delete_option( 'alcateia_labels_logs' );
