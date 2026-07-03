<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MPAP_Logger {
    const MAX_CONTEXT_BYTES = 262144;

    public static function log( $level, $source, $message, $context = array(), $meta = array() ) {
        global $wpdb;

        $level  = sanitize_key( $level ?: 'info' );
        $source = sanitize_key( $source ?: 'system' );
        $event  = isset( $meta['event'] ) ? sanitize_key( $meta['event'] ) : $source;
        $context = mpap_sanitize_log_context( is_array( $context ) ? $context : array( 'value' => $context ) );
        $context_json = mpap_json_encode_pretty( $context );
        if ( strlen( $context_json ) > self::MAX_CONTEXT_BYTES ) {
            $context_json = substr( $context_json, 0, self::MAX_CONTEXT_BYTES ) . "\n... [contexto truncado]";
        }

        $row = array(
            'level'          => $level,
            'source'         => $source,
            'event'          => $event,
            'message'        => sanitize_text_field( $message ),
            'correlation_id' => sanitize_text_field( $meta['correlation_id'] ?? self::correlation_id() ),
            'request_id'     => sanitize_text_field( $meta['request_id'] ?? '' ),
            'http_status'    => isset( $meta['http_status'] ) ? absint( $meta['http_status'] ) : null,
            'method'         => sanitize_text_field( $meta['method'] ?? '' ),
            'url'            => mpap_truncate( sanitize_text_field( $meta['url'] ?? '' ), 255 ),
            'duration_ms'    => isset( $meta['duration_ms'] ) ? absint( $meta['duration_ms'] ) : null,
            'context'        => $context_json,
            'created_at'     => current_time( 'mysql' ),
        );

        $inserted = false;
        $tables   = function_exists( 'mpap_tables' ) ? mpap_tables() : array();
        if ( ! empty( $tables['logs'] ) && isset( $wpdb ) && $wpdb instanceof wpdb ) {
            $inserted = false !== $wpdb->insert(
                $tables['logs'],
                $row,
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
            );
        }

        if ( function_exists( 'wc_get_logger' ) ) {
            try {
                wc_get_logger()->log( $level, $message . ' ' . $context_json, array( 'source' => 'mpap-' . $source ) );
            } catch ( Exception $e ) {
                // Não interrompe o fluxo se o logger do WooCommerce falhar.
            }
        }

        if ( ! $inserted && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[MPAP][' . strtoupper( $level ) . '][' . $source . '] ' . $message . ' ' . $context_json );
        }

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public static function debug( $source, $message, $context = array(), $meta = array() ) {
        if ( ! mpap_get_settings( 'debug_mode', 1 ) ) {
            return 0;
        }
        return self::log( 'debug', $source, $message, $context, $meta );
    }

    public static function info( $source, $message, $context = array(), $meta = array() ) {
        return self::log( 'info', $source, $message, $context, $meta );
    }

    public static function warning( $source, $message, $context = array(), $meta = array() ) {
        return self::log( 'warning', $source, $message, $context, $meta );
    }

    public static function error( $source, $message, $context = array(), $meta = array() ) {
        return self::log( 'error', $source, $message, $context, $meta );
    }

    public static function correlation_id() {
        static $id = null;
        if ( null === $id ) {
            $id = isset( $_SERVER['HTTP_X_REQUEST_ID'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUEST_ID'] ) ) : '';
            if ( ! $id ) {
                $id = 'mpap-' . wp_generate_uuid4();
            }
        }
        return $id;
    }

    public static function recent( $limit = 100, $filters = array() ) {
        global $wpdb;
        $tables = mpap_tables();
        $limit = max( 1, min( 1000, absint( $limit ) ) );
        $where = array( '1=1' );
        $args  = array();

        if ( ! empty( $filters['level'] ) ) {
            $where[] = 'level = %s';
            $args[] = sanitize_key( $filters['level'] );
        }
        if ( ! empty( $filters['source'] ) ) {
            $where[] = 'source = %s';
            $args[] = sanitize_key( $filters['source'] );
        }
        if ( ! empty( $filters['search'] ) ) {
            $where[] = '(message LIKE %s OR context LIKE %s OR url LIKE %s OR correlation_id LIKE %s)';
            $like = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $sql = "SELECT * FROM {$tables['logs']} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d';
        $args[] = $limit;
        return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
    }

    public static function counts() {
        global $wpdb;
        $tables = mpap_tables();
        $rows = $wpdb->get_results( "SELECT level, COUNT(*) total FROM {$tables['logs']} GROUP BY level", ARRAY_A );
        $out = array( 'debug' => 0, 'info' => 0, 'warning' => 0, 'error' => 0 );
        foreach ( (array) $rows as $row ) {
            $out[ $row['level'] ] = (int) $row['total'];
        }
        return $out;
    }

    public static function sources() {
        global $wpdb;
        $tables = mpap_tables();
        $rows = $wpdb->get_col( "SELECT DISTINCT source FROM {$tables['logs']} WHERE source <> '' ORDER BY source ASC" );
        return array_filter( array_map( 'sanitize_key', (array) $rows ) );
    }

    public static function clear( $filters = array() ) {
        global $wpdb;
        $tables = mpap_tables();
        if ( empty( $filters ) ) {
            $wpdb->query( "TRUNCATE TABLE {$tables['logs']}" );
            return;
        }
        $where = array();
        $args = array();
        if ( ! empty( $filters['older_than_days'] ) ) {
            $where[] = 'created_at < %s';
            $args[] = gmdate( 'Y-m-d H:i:s', time() - absint( $filters['older_than_days'] ) * DAY_IN_SECONDS );
        }
        if ( $where ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$tables['logs']} WHERE " . implode( ' AND ', $where ), $args ) );
        }
    }

    public static function prune() {
        $days = absint( mpap_get_settings( 'log_retention_days', 30 ) );
        if ( $days > 0 ) {
            self::clear( array( 'older_than_days' => $days ) );
        }
    }

    public static function export_json() {
        return self::recent( 1000 );
    }
}
