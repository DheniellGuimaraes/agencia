<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$settings = get_option( 'mpap_settings', array() );
if ( is_array( $settings ) && ! empty( $settings['remove_data_on_uninstall'] ) ) {
    delete_option( 'mpap_settings' );
    delete_option( 'mpap_credentials' );
    delete_option( 'mpap_tokens' );
    delete_option( 'mpap_managed_connection' );
    delete_option( 'mpap_oauth_status' );
    delete_option( 'mpap_last_cron_sync' );
    global $wpdb;
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mpap_logs" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mpap_webhooks" );
}
