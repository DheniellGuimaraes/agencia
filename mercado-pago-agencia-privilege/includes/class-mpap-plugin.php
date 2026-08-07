<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MPAP_Plugin {
    private static $instance;
    public $auth;
    public $api;
    public $products;
    public $quality;
    public $orders;
    public $webhooks;
    public $diagnostics;
    public $admin;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->auth        = new MPAP_Auth();
        $this->api         = new MPAP_API( $this->auth );
        $this->quality     = new MPAP_Quality( $this->api );
        $this->products    = new MPAP_Product_Sync( $this->api, $this->quality );
        $this->orders      = new MPAP_Order_Importer( $this->api, $this->products );
        $this->webhooks    = new MPAP_Webhooks( $this->products, $this->orders );
        $this->diagnostics = new MPAP_Diagnostics( $this->auth, $this->api );
        $this->admin       = new MPAP_Admin( $this->auth, $this->api, $this->products, $this->orders, $this->diagnostics, $this->quality );
    }

    public function hooks() {
        load_plugin_textdomain( MPAP_TEXT_DOMAIN, false, dirname( MPAP_BASENAME ) . '/languages' );
        $this->maybe_upgrade();
        $this->auth->hooks();
        $this->quality->hooks();
        $this->products->hooks();
        $this->orders->hooks();
        $this->webhooks->hooks();
        $this->diagnostics->hooks();
        add_action( 'mpap_cron_sync', array( $this, 'cron_sync' ) );
        add_action( 'mpap_cron_refresh_token', array( $this, 'cron_refresh_token' ) );
        add_action( 'mpap_cron_prune_logs', array( $this, 'cron_prune_logs' ) );
        if ( is_admin() ) {
            $this->admin->hooks();
        }
    }

    public function maybe_upgrade() {
        $installed = get_option( 'mpap_db_version', '' );
        if ( MPAP_DB_VERSION !== $installed ) {
            self::activate();
        }
    }

    public function cron_sync() {
        MPAP_Logger::debug( 'cron', 'Cron de sincronização iniciado.', array( 'connected' => $this->auth->connected(), 'woocommerce' => mpap_has_wc() ), array( 'event' => 'cron_sync_start' ) );
        if ( $this->auth->connected() && mpap_has_wc() ) {
            $result = $this->products->sync_all( mpap_get_settings( 'sync_batch_size', 20 ) );
            MPAP_Logger::info( 'cron', 'Cron de sincronização concluído.', $result, array( 'event' => 'cron_sync_done' ) );
        }
    }

    public function cron_refresh_token() {
        if ( $this->auth->connected() && $this->auth->expiring( HOUR_IN_SECONDS ) ) {
            $this->auth->refresh( true );
        }
    }

    public function cron_prune_logs() {
        MPAP_Logger::prune();
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $tables = mpap_tables();
        $charset = $wpdb->get_charset_collate();

        dbDelta(
            "CREATE TABLE {$tables['logs']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                level varchar(20) NOT NULL DEFAULT 'info',
                source varchar(80) NOT NULL DEFAULT 'system',
                event varchar(120) NOT NULL DEFAULT '',
                message text NOT NULL,
                correlation_id varchar(80) NULL,
                request_id varchar(80) NULL,
                http_status int(11) NULL,
                method varchar(12) NULL,
                url varchar(255) NULL,
                duration_ms int(11) NULL,
                context longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY level (level),
                KEY source (source),
                KEY event (event),
                KEY correlation_id (correlation_id),
                KEY request_id (request_id),
                KEY http_status (http_status),
                KEY created_at (created_at)
            ) $charset;"
        );

        dbDelta(
            "CREATE TABLE {$tables['webhooks']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                topic varchar(120) NOT NULL DEFAULT '',
                resource varchar(255) NOT NULL DEFAULT '',
                user_id varchar(80) NULL,
                application_id varchar(80) NULL,
                status varchar(40) NOT NULL DEFAULT 'received',
                payload longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY topic (topic),
                KEY resource (resource(191)),
                KEY status (status),
                KEY created_at (created_at)
            ) $charset;"
        );

        $settings = mpap_get_settings();
        if ( empty( $settings['webhook_secret'] ) ) {
            $settings['webhook_secret'] = wp_generate_password( 40, false, false );
            mpap_update_settings( $settings );
        }

        self::schedule_events();
        update_option( 'mpap_db_version', MPAP_DB_VERSION, false );
        MPAP_Logger::info( 'system', 'Plugin ativado/atualizado.', array( 'version' => MPAP_VERSION, 'db_version' => MPAP_DB_VERSION ), array( 'event' => 'plugin_activate' ) );
    }

    public static function schedule_events() {
        if ( ! wp_next_scheduled( 'mpap_cron_sync' ) ) {
            wp_schedule_event( time() + 300, 'mpap_custom_interval', 'mpap_cron_sync' );
        }
        if ( ! wp_next_scheduled( 'mpap_cron_refresh_token' ) ) {
            wp_schedule_event( time() + 600, 'hourly', 'mpap_cron_refresh_token' );
        }
        if ( ! wp_next_scheduled( 'mpap_cron_prune_logs' ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'mpap_cron_prune_logs' );
        }
    }

    public static function deactivate() {
        foreach ( array( 'mpap_cron_sync', 'mpap_cron_refresh_token', 'mpap_cron_prune_logs' ) as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            while ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
                $timestamp = wp_next_scheduled( $hook );
            }
        }
        MPAP_Logger::info( 'system', 'Plugin desativado.', array( 'version' => MPAP_VERSION ), array( 'event' => 'plugin_deactivate' ) );
    }
}
