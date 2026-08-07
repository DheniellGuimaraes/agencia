<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mpap_default_settings() {
    return array(
        'site_id'                    => 'MLB',
        'connection_mode'            => 'manual',
        'managed_service_url'        => '',
        'auth_base'                  => 'https://auth.mercadolivre.com.br',
        'api_base'                   => 'https://api.mercadolibre.com',
        'currency_id'                => 'BRL',
        'default_category_id'        => '',
        'default_category_name'      => '',
        'default_category_path'      => '',
        'default_category_domain'    => '',
        'default_category_query'     => '',
        'listing_type_id'            => 'gold_special',
        'condition'                  => 'new',
        'logistic_type'              => 'not_specified',
        'sync_interval_minutes'      => 15,
        'sync_batch_size'            => 20,
        'auto_sync_on_save'          => 0,
        'include_description'        => 1,
        'enable_order_import'        => 1,
        'require_webhook_secret'     => 1,
        'webhook_secret'             => '',
        'price_adjustment_type'      => 'none',
        'price_adjustment_value'     => '0',
        'stock_strategy'             => 'wc_to_ml',
        'pkce_enabled'               => 0,
        'debug_mode'                 => 1,
        'log_http_bodies'            => 1,
        'log_retention_days'         => 30,
        'diagnostic_rest_probe'      => 0,
        'remove_data_on_uninstall'   => 0,
        'auto_remove_manufacturing_time' => 1,
        'auto_remove_manufacturing_time_dry_run' => 1,
        'interest_free_installments_done' => 0,
    );
}

function mpap_get_settings( $key = null, $default = null ) {
    $settings = wp_parse_args( get_option( 'mpap_settings', array() ), mpap_default_settings() );
    if ( null === $key ) {
        return $settings;
    }
    return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}

function mpap_update_settings( array $settings ) {
    $merged = wp_parse_args( $settings, mpap_get_settings() );
    $merged['sync_interval_minutes'] = max( 5, min( 1440, absint( $merged['sync_interval_minutes'] ) ) );
    $merged['sync_batch_size']       = max( 1, min( 100, absint( $merged['sync_batch_size'] ) ) );
    $merged['log_retention_days']    = max( 1, min( 365, absint( $merged['log_retention_days'] ) ) );
    $merged['connection_mode']        = in_array( $merged['connection_mode'], array( 'manual', 'managed' ), true ) ? $merged['connection_mode'] : 'manual';
    $merged['managed_service_url']    = untrailingslashit( trim( (string) $merged['managed_service_url'] ) );
    update_option( 'mpap_settings', $merged, false );
    return $merged;
}

function mpap_has_wc() {
    return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
}

function mpap_capability() {
    return current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
}

function mpap_legacy_oauth_redirect_uri() {
    return admin_url( 'admin-post.php?action=mpap_oauth_callback' );
}

function mpap_oauth_redirect_uri() {
    return rest_url( 'mpap/v1/oauth/callback' );
}

function mpap_oauth_start_uri() {
    return wp_nonce_url( admin_url( 'admin-post.php?action=mpap_oauth_start' ), 'mpap_oauth_start' );
}

function mpap_managed_oauth_start_uri() {
    return wp_nonce_url( admin_url( 'admin-post.php?action=mpap_managed_oauth_start' ), 'mpap_managed_oauth_start' );
}

function mpap_oauth_start_no_pkce_uri() {
    return wp_nonce_url( add_query_arg( 'mpap_pkce', '0', admin_url( 'admin-post.php?action=mpap_oauth_start' ) ), 'mpap_oauth_start' );
}

function mpap_managed_oauth_callback_uri() {
    return admin_url( 'admin-post.php?action=mpap_managed_oauth_callback' );
}

function mpap_connection_mode() {
    $mode = (string) mpap_get_settings( 'connection_mode', 'manual' );
    return in_array( $mode, array( 'manual', 'managed' ), true ) ? $mode : 'manual';
}

function mpap_using_managed_connection() {
    return 'managed' === mpap_connection_mode();
}

function mpap_managed_service_url() {
    return untrailingslashit( trim( (string) mpap_get_settings( 'managed_service_url', '' ) ) );
}

function mpap_validate_https_url( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) {
        return false;
    }
    $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
    $host   = wp_parse_url( $url, PHP_URL_HOST );
    return 'https' === strtolower( (string) $scheme ) && ! empty( $host );
}

function mpap_webhook_url( $with_secret = false ) {
    $url = rest_url( 'mpap/v1/notifications' );
    if ( $with_secret && mpap_get_settings( 'require_webhook_secret', 1 ) && mpap_get_settings( 'webhook_secret' ) ) {
        $url = add_query_arg( 'mpap_secret', mpap_get_settings( 'webhook_secret' ), $url );
    }
    return $url;
}

function mpap_health_url() {
    return rest_url( 'mpap/v1/health' );
}

function mpap_tables() {
    global $wpdb;
    return array(
        'logs'     => $wpdb->prefix . 'mpap_logs',
        'webhooks' => $wpdb->prefix . 'mpap_webhooks',
    );
}

function mpap_allowed_auth_hosts() {
    return array(
        'auth.mercadolivre.com.br',
        'auth.mercadolibre.com.ar',
        'auth.mercadolibre.com.mx',
        'auth.mercadolibre.cl',
        'auth.mercadolibre.com.co',
        'auth.mercadolibre.com.pe',
        'auth.mercadolibre.com.uy',
        'auth.mercadolibre.com.ec',
        'auth.mercadolibre.com.ve',
        'global-selling.mercadolibre.com',
    );
}

function mpap_allowed_api_hosts() {
    return array(
        'api.mercadolibre.com',
    );
}

function mpap_validate_url_host( $url, array $allowed_hosts ) {
    $host = wp_parse_url( $url, PHP_URL_HOST );
    $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
    if ( ! $host || ! in_array( strtolower( $host ), array_map( 'strtolower', $allowed_hosts ), true ) ) {
        return false;
    }
    return 'https' === strtolower( (string) $scheme );
}

function mpap_allow_redirect_hosts( $hosts ) {
    $hosts = array_merge( (array) $hosts, mpap_allowed_auth_hosts() );
    $managed = mpap_managed_service_url();
    $managed_host = $managed ? wp_parse_url( $managed, PHP_URL_HOST ) : '';
    if ( $managed_host ) {
        $hosts[] = strtolower( $managed_host );
    }
    return array_unique( array_filter( $hosts ) );
}
add_filter( 'allowed_redirect_hosts', 'mpap_allow_redirect_hosts' );

function mpap_plain_text( $text, $limit = 60000 ) {
    $text = trim( wp_strip_all_tags( (string) $text ) );
    if ( function_exists( 'mb_substr' ) ) {
        return mb_substr( $text, 0, $limit );
    }
    return substr( $text, 0, $limit );
}

function mpap_trim_title( $title, $limit = 60 ) {
    $title = mpap_plain_text( $title, 255 );
    if ( function_exists( 'mb_substr' ) ) {
        return mb_substr( $title, 0, $limit );
    }
    return substr( $title, 0, $limit );
}

function mpap_datetime( $value ) {
    if ( ! $value ) {
        return '—';
    }
    if ( is_numeric( $value ) ) {
        return date_i18n( 'd/m/Y H:i:s', (int) $value );
    }
    $timestamp = strtotime( (string) $value );
    return $timestamp ? date_i18n( 'd/m/Y H:i:s', $timestamp ) : '—';
}

function mpap_price_with_adjustment( $price ) {
    $price = (float) $price;
    $type  = (string) mpap_get_settings( 'price_adjustment_type', 'none' );
    $value = (float) mpap_get_settings( 'price_adjustment_value', 0 );

    if ( 'percent' === $type ) {
        $price = $price + ( $price * ( $value / 100 ) );
    } elseif ( 'fixed' === $type ) {
        $price = $price + $value;
    }

    return round( max( 0, $price ), 2 );
}

function mpap_register_cron_schedules( $schedules ) {
    $minutes = max( 5, min( 1440, absint( mpap_get_settings( 'sync_interval_minutes', 15 ) ) ) );
    $schedules['mpap_custom_interval'] = array(
        'interval' => $minutes * MINUTE_IN_SECONDS,
        'display'  => sprintf( 'MPAP a cada %d minutos', $minutes ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'mpap_register_cron_schedules' );

function mpap_mask_secret( $value, $visible = 4 ) {
    $value = (string) $value;
    if ( '' === $value ) {
        return '';
    }
    if ( strlen( $value ) <= $visible ) {
        return str_repeat( '•', max( 4, strlen( $value ) ) );
    }
    return str_repeat( '•', max( 6, strlen( $value ) - $visible ) ) . substr( $value, - $visible );
}

function mpap_sanitize_log_context( $value, $depth = 0 ) {
    if ( $depth > 7 ) {
        return '[max-depth]';
    }
    $secret_keys = array( 'access_token', 'refresh_token', 'client_secret', 'authorization', 'token', 'code_verifier', 'code_challenge', 'mpap_secret', 'secret', 'password' );
    if ( is_array( $value ) ) {
        $out = array();
        foreach ( $value as $key => $item ) {
            $key_string = is_scalar( $key ) ? (string) $key : '';
            $lower      = strtolower( $key_string );
            $public_url_keys = array( 'authorization_url', 'auth_url', 'redirect_uri', 'redirect_url', 'callback_url', 'webhook_url', 'url' );
            $is_secret  = false;
            if ( ! in_array( $lower, $public_url_keys, true ) ) {
                foreach ( $secret_keys as $secret_key ) {
                    if ( false !== strpos( $lower, $secret_key ) ) {
                        $is_secret = true;
                        break;
                    }
                }
            }
            $out[ $key ] = $is_secret ? mpap_mask_secret( is_scalar( $item ) ? (string) $item : wp_json_encode( $item ) ) : mpap_sanitize_log_context( $item, $depth + 1 );
        }
        return $out;
    }
    if ( is_object( $value ) ) {
        return mpap_sanitize_log_context( (array) $value, $depth + 1 );
    }
    if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
        return $value;
    }
    $string = (string) $value;
    $string = preg_replace( '/(access_token|refresh_token|client_secret|code_verifier|mpap_secret)=([^&\s]+)/i', '$1=••••••', $string );
    if ( strlen( $string ) > 20000 ) {
        $string = substr( $string, 0, 20000 ) . '... [truncado]';
    }
    return $string;
}


function mpap_remote_headers_to_array( $response ) {
    $headers = wp_remote_retrieve_headers( $response );
    if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
        $headers = $headers->getAll();
    } elseif ( $headers instanceof Traversable ) {
        $headers = iterator_to_array( $headers );
    } elseif ( is_object( $headers ) ) {
        $headers = (array) $headers;
    }
    return is_array( $headers ) ? mpap_sanitize_log_context( $headers ) : array();
}

function mpap_hash_for_log( $value ) {
    return substr( hash( 'sha256', (string) $value ), 0, 16 );
}

function mpap_json_encode_pretty( $value ) {
    $json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    return false === $json ? '' : $json;
}

function mpap_truncate( $value, $limit = 191 ) {
    $value = (string) $value;
    if ( function_exists( 'mb_substr' ) ) {
        return mb_substr( $value, 0, $limit );
    }
    return substr( $value, 0, $limit );
}
