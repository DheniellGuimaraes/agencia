<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MPAP_Webhooks {
    private $products;
    private $orders;

    public function __construct( MPAP_Product_Sync $products, MPAP_Order_Importer $orders ) {
        $this->products = $products;
        $this->orders   = $orders;
    }

    public function hooks() {
        add_action( 'rest_api_init', array( $this, 'routes' ) );
    }

    public function routes() {
        register_rest_route(
            'mpap/v1',
            '/notifications',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'receive' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            'mpap/v1',
            '/health',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'health' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function health() {
        return rest_ensure_response( array( 'ok' => true, 'plugin' => 'Mercado Pago Agência Privilege', 'version' => MPAP_VERSION, 'time' => current_time( 'mysql' ) ) );
    }

    private function valid_secret( WP_REST_Request $request ) {
        if ( ! mpap_get_settings( 'require_webhook_secret', 1 ) ) {
            return true;
        }
        $expected = (string) mpap_get_settings( 'webhook_secret', '' );
        if ( ! $expected ) {
            return false;
        }
        $provided = (string) $request->get_param( 'mpap_secret' );
        if ( ! $provided ) {
            $provided = (string) $request->get_header( 'x-mpap-secret' );
        }
        return $provided && hash_equals( $expected, $provided );
    }

    public function receive( WP_REST_Request $request ) {
        if ( ! $this->valid_secret( $request ) ) {
            MPAP_Logger::warning( 'webhooks', 'Webhook rejeitado por segredo inválido.', array( 'headers' => $request->get_headers(), 'params' => $request->get_params() ), array( 'event' => 'webhook_invalid_secret' ) );
            return new WP_Error( 'mpap_invalid_webhook_secret', 'Webhook secret inválido.', array( 'status' => 403 ) );
        }

        $payload = $request->get_json_params();
        if ( ! is_array( $payload ) ) {
            $payload = $request->get_params();
        }

        $topic = sanitize_text_field( $payload['topic'] ?? $payload['type'] ?? '' );
        $resource = sanitize_text_field( $payload['resource'] ?? '' );
        $this->store( $topic, $resource, $payload );
        MPAP_Logger::info( 'webhooks', 'Webhook recebido.', array( 'topic' => $topic, 'resource' => $resource, 'payload' => $payload ), array( 'event' => 'webhook_received' ) );

        $result = null;
        if ( in_array( $topic, array( 'orders_v2', 'orders' ), true ) ) {
            $result = $this->orders->import_from_resource( $resource );
        } elseif ( in_array( $topic, array( 'items', 'stock', 'items_prices' ), true ) ) {
            $result = $this->products->process_item_notification( $resource );
        }

        if ( is_wp_error( $result ) ) {
            MPAP_Logger::log( 'error', 'webhooks', 'Erro ao processar webhook.', array( 'topic' => $topic, 'resource' => $resource, 'error' => $result->get_error_message() ) );
            return rest_ensure_response( array( 'received' => true, 'processed' => false, 'message' => $result->get_error_message() ) );
        }

        MPAP_Logger::info( 'webhooks', 'Webhook processado.', array( 'topic' => $topic, 'resource' => $resource, 'result' => $result ), array( 'event' => 'webhook_processed' ) );
        return rest_ensure_response( array( 'received' => true, 'processed' => true ) );
    }

    private function store( $topic, $resource, array $payload ) {
        global $wpdb;
        $tables = mpap_tables();
        $wpdb->insert(
            $tables['webhooks'],
            array(
                'topic'          => sanitize_text_field( $topic ),
                'resource'       => sanitize_text_field( $resource ),
                'user_id'        => sanitize_text_field( $payload['user_id'] ?? '' ),
                'application_id' => sanitize_text_field( $payload['application_id'] ?? '' ),
                'status'         => 'received',
                'payload'        => wp_json_encode( $payload ),
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    public static function stats() {
        global $wpdb;
        $tables = mpap_tables();
        $received = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['webhooks']}" );
        $topics = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT topic) FROM {$tables['webhooks']} WHERE topic <> ''" );
        return array( 'received' => $received, 'topics' => $topics );
    }

    public static function recent( $limit = 50 ) {
        global $wpdb;
        $tables = mpap_tables();
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tables['webhooks']} ORDER BY id DESC LIMIT %d", absint( $limit ) ), ARRAY_A );
    }
}
