<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MPAP_API {
    private $auth;

    public function __construct( MPAP_Auth $auth ) {
        $this->auth = $auth;
    }

    public function request( $method, $path, $body = null, $query = array(), $auth_required = true, $retry = true ) {
        if ( $auth_required && method_exists( $this->auth, 'using_managed' ) && $this->auth->using_managed() ) {
            return $this->managed_request( $method, $path, $body, $query );
        }

        $base = untrailingslashit( mpap_get_settings( 'api_base' ) );
        if ( ! mpap_validate_url_host( $base, mpap_allowed_api_hosts() ) ) {
            MPAP_Logger::error( 'api', 'Host de API inválido bloqueado por segurança.', array( 'api_base' => $base ) );
            return new WP_Error( 'mpap_invalid_api_host', 'Host de API inválido. Use https://api.mercadolibre.com.' );
        }

        $url = $base . '/' . ltrim( $path, '/' );
        if ( ! empty( $query ) ) {
            $url = add_query_arg( $query, $url );
        }

        $request_id = 'api-' . wp_generate_uuid4();
        $headers = array(
            'Accept'     => 'application/json',
            'User-Agent' => 'MPAP/' . MPAP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
        );
        if ( $auth_required ) {
            $access = $this->auth->access_token();
            if ( is_wp_error( $access ) ) {
                return $access;
            }
            if ( ! $access ) {
                MPAP_Logger::warning( 'api', 'Chamada bloqueada: access token ausente.', array( 'method' => $method, 'path' => $path ), array( 'request_id' => $request_id, 'method' => $method, 'url' => $path ) );
                return new WP_Error( 'mpap_no_access_token', 'Conecte sua conta Mercado Livre antes de chamar a API.' );
            }
            $headers['Authorization'] = 'Bearer ' . $access;
        }

        $args = array(
            'method'      => strtoupper( $method ),
            'timeout'     => 45,
            'redirection' => 0,
            'headers'     => $headers,
        );
        if ( null !== $body ) {
            $headers['Content-Type'] = 'application/json';
            $args['headers'] = $headers;
            $args['body'] = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        }

        $log_context = array(
            'request_id' => $request_id,
            'method'     => strtoupper( $method ),
            'url'        => $url,
            'path'       => $path,
            'query'      => $query,
            'auth'       => $auth_required ? 'bearer' : 'public',
            'headers'    => mpap_sanitize_log_context( $headers ),
        );
        if ( mpap_get_settings( 'log_http_bodies', 1 ) && null !== $body ) {
            $log_context['request_body'] = $body;
        }
        MPAP_Logger::debug( 'api', 'HTTP request Mercado Livre iniciado.', $log_context, array( 'request_id' => $request_id, 'method' => strtoupper( $method ), 'url' => $path, 'event' => 'http_request' ) );

        $started = microtime( true );
        $response = wp_remote_request( $url, $args );
        $duration = (int) round( ( microtime( true ) - $started ) * 1000 );

        if ( is_wp_error( $response ) ) {
            MPAP_Logger::error(
                'api',
                'Falha de conexão com a API Mercado Livre.',
                array_merge( $log_context, array( 'error' => $response->get_error_message(), 'error_code' => $response->get_error_code() ) ),
                array( 'request_id' => $request_id, 'method' => strtoupper( $method ), 'url' => $path, 'duration_ms' => $duration, 'event' => 'http_transport_error' )
            );
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $raw    = wp_remote_retrieve_body( $response );
        $data   = json_decode( $raw, true );
        $data   = is_array( $data ) ? $data : array( 'raw' => $raw );

        $response_context = array_merge( $log_context, array( 'status' => $status ) );
        if ( mpap_get_settings( 'log_http_bodies', 1 ) || $status >= 400 ) {
            $response_context['response'] = $data;
            $response_context['response_headers'] = mpap_remote_headers_to_array( $response );
        }
        MPAP_Logger::debug(
            'api',
            'HTTP response Mercado Livre recebido.',
            $response_context,
            array( 'request_id' => $request_id, 'method' => strtoupper( $method ), 'url' => $path, 'http_status' => $status, 'duration_ms' => $duration, 'event' => 'http_response' )
        );

        if ( 401 === $status && $auth_required && $retry ) {
            MPAP_Logger::warning( 'api', 'API retornou 401. Tentando renovar token e repetir uma vez.', array( 'path' => $path, 'request_id' => $request_id ), array( 'request_id' => $request_id, 'method' => strtoupper( $method ), 'url' => $path, 'http_status' => 401 ) );
            $refreshed = $this->auth->refresh( true );
            if ( ! is_wp_error( $refreshed ) ) {
                return $this->request( $method, $path, $body, $query, $auth_required, false );
            }
        }

        if ( 429 === $status && $retry ) {
            $retry_after = 2;
            $headers_array = mpap_remote_headers_to_array( $response );
            if ( ! empty( $headers_array['retry-after'] ) ) {
                $retry_after = max( 1, min( 10, absint( $headers_array['retry-after'] ) ) );
            }
            MPAP_Logger::warning( 'api', 'API retornou 429. Aguardando backoff e repetindo uma vez.', array( 'path' => $path, 'retry_after' => $retry_after, 'request_id' => $request_id ), array( 'request_id' => $request_id, 'method' => strtoupper( $method ), 'url' => $path, 'http_status' => 429 ) );
            sleep( $retry_after );
            return $this->request( $method, $path, $body, $query, $auth_required, false );
        }

        if ( 403 === $status ) {
            MPAP_Logger::warning( 'api', 'API retornou 403. Permissão insuficiente ou recurso bloqueado; não haverá retry infinito.', array( 'path' => $path, 'request_id' => $request_id, 'response' => $data ), array( 'request_id' => $request_id, 'method' => strtoupper( $method ), 'url' => $path, 'http_status' => 403 ) );
        }

        if ( $status >= 200 && $status < 300 ) {
            return $data;
        }

        MPAP_Logger::error( 'api', 'API Mercado Livre retornou erro.', array( 'method' => $method, 'path' => $path, 'url' => $url, 'status' => $status, 'response' => $data, 'response_headers' => mpap_remote_headers_to_array( $response ) ), array( 'request_id' => $request_id, 'method' => strtoupper( $method ), 'url' => $path, 'http_status' => $status, 'duration_ms' => $duration, 'event' => 'http_api_error' ) );
        return new WP_Error( 'mpap_api_error', $data['message'] ?? $data['error'] ?? 'Erro retornado pela API Mercado Livre.', array( 'status' => $status, 'response' => $data, 'request_id' => $request_id ) );
    }


    private function managed_request( $method, $path, $body = null, $query = array() ) {
        $connection = $this->auth->managed_connection();
        if ( empty( $connection['connection_token'] ) ) {
            MPAP_Logger::warning( 'managed_api', 'Chamada bloqueada: token de conexão Privilege Connect ausente.', array( 'method' => $method, 'path' => $path ), array( 'event' => 'managed_no_token', 'method' => strtoupper( $method ), 'url' => $path ) );
            return new WP_Error( 'mpap_no_managed_connection', 'Conecte a conta pelo Privilege Connect antes de chamar a API.' );
        }

        $service_url = ! empty( $connection['service_url'] ) ? untrailingslashit( $connection['service_url'] ) : mpap_managed_service_url();
        if ( ! mpap_validate_https_url( $service_url ) ) {
            MPAP_Logger::error( 'managed_api', 'URL do broker inválida.', array( 'service_url' => $service_url ), array( 'event' => 'managed_invalid_service' ) );
            return new WP_Error( 'mpap_invalid_managed_service', 'URL do broker Privilege Connect inválida.' );
        }

        $request_id = 'proxy-' . wp_generate_uuid4();
        $url = $service_url . '/v1/ml/proxy';
        $payload = array(
            'request_id'     => $request_id,
            'method'         => strtoupper( $method ),
            'path'           => '/' . ltrim( $path, '/' ),
            'query'          => is_array( $query ) ? $query : array(),
            'body'           => $body,
            'site_url'       => home_url( '/' ),
            'plugin_version' => MPAP_VERSION,
        );

        $log_context = array(
            'request_id'    => $request_id,
            'service_url'   => $service_url,
            'proxy_url'     => $url,
            'method'        => strtoupper( $method ),
            'path'          => $payload['path'],
            'query'         => $payload['query'],
            'connection_id' => $connection['connection_id'] ?? '',
        );
        if ( mpap_get_settings( 'log_http_bodies', 1 ) && null !== $body ) {
            $log_context['request_body'] = $body;
        }
        MPAP_Logger::debug( 'managed_api', 'HTTP proxy Privilege Connect iniciado.', $log_context, array( 'event' => 'managed_proxy_request', 'request_id' => $request_id, 'method' => strtoupper( $method ), 'url' => $payload['path'] ) );

        $started = microtime( true );
        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 60,
                'headers' => array(
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $connection['connection_token'],
                    'User-Agent'    => 'MPAP/' . MPAP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
                ),
                'body' => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            )
        );
        $duration = (int) round( ( microtime( true ) - $started ) * 1000 );

        if ( is_wp_error( $response ) ) {
            MPAP_Logger::error( 'managed_api', 'Falha de transporte no proxy Privilege Connect.', array_merge( $log_context, array( 'error' => $response->get_error_message() ) ), array( 'event' => 'managed_proxy_transport_error', 'request_id' => $request_id, 'method' => strtoupper( $method ), 'url' => $payload['path'], 'duration_ms' => $duration ) );
            return $response;
        }

        $http_status = (int) wp_remote_retrieve_response_code( $response );
        $raw = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );
        $data = is_array( $data ) ? $data : array( 'raw' => $raw );

        $response_context = array_merge( $log_context, array( 'http_status' => $http_status ) );
        if ( mpap_get_settings( 'log_http_bodies', 1 ) || $http_status >= 400 ) {
            $response_context['response'] = $data;
        }
        MPAP_Logger::log(
            ( $http_status >= 200 && $http_status < 300 ) ? 'debug' : 'error',
            'managed_api',
            'HTTP proxy Privilege Connect respondeu.',
            $response_context,
            array( 'event' => 'managed_proxy_response', 'request_id' => $request_id, 'method' => strtoupper( $method ), 'url' => $payload['path'], 'http_status' => $http_status, 'duration_ms' => $duration )
        );

        if ( isset( $data['ok'] ) && false === (bool) $data['ok'] ) {
            return new WP_Error( 'mpap_managed_proxy_error', $data['message'] ?? $data['error'] ?? 'Broker retornou erro.', array( 'status' => $http_status, 'response' => $data, 'request_id' => $request_id ) );
        }

        if ( isset( $data['status'] ) && isset( $data['body'] ) ) {
            $ml_status = absint( $data['status'] );
            if ( $ml_status >= 200 && $ml_status < 300 ) {
                return is_array( $data['body'] ) ? $data['body'] : array( 'raw' => $data['body'] );
            }
            return new WP_Error( 'mpap_managed_ml_error', $data['body']['message'] ?? $data['body']['error'] ?? 'API Mercado Livre retornou erro via broker.', array( 'status' => $ml_status, 'response' => $data['body'], 'request_id' => $request_id ) );
        }

        if ( $http_status >= 200 && $http_status < 300 ) {
            if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
                return $data['data'];
            }
            return $data;
        }

        return new WP_Error( 'mpap_managed_http_error', $data['message'] ?? $data['error'] ?? 'Broker Privilege Connect retornou erro HTTP.', array( 'status' => $http_status, 'response' => $data, 'request_id' => $request_id ) );
    }

    public function get_user() {
        return $this->request( 'GET', '/users/me' );
    }

    public function get_site( $site_id = '' ) {
        $site_id = $site_id ?: mpap_get_settings( 'site_id', 'MLB' );
        return $this->request( 'GET', '/sites/' . rawurlencode( $site_id ), null, array(), false );
    }

    public function get_listing_types( $site_id = '' ) {
        $site_id = $site_id ?: mpap_get_settings( 'site_id', 'MLB' );
        return $this->request( 'GET', '/sites/' . rawurlencode( $site_id ) . '/listing_types', null, array(), false );
    }

    public function create_item( array $payload ) {
        return $this->request( 'POST', '/items', $payload );
    }

    public function update_item( $item_id, array $payload ) {
        return $this->request( 'PUT', '/items/' . rawurlencode( $item_id ), $payload );
    }

    public function update_pictures( $item_id, array $pictures ) {
        return $this->request( 'PUT', '/items/' . rawurlencode( $item_id ) . '/pictures', array( 'pictures' => $pictures ) );
    }

    public function update_description( $item_id, $plain_text ) {
        return $this->request( 'PUT', '/items/' . rawurlencode( $item_id ) . '/description', array( 'plain_text' => mpap_plain_text( $plain_text ) ) );
    }

    public function get_item( $item_id ) {
        return $this->request( 'GET', '/items/' . rawurlencode( $item_id ) );
    }

    public function get_order( $order_id ) {
        return $this->request( 'GET', '/orders/' . rawurlencode( $order_id ) );
    }

    public function get_shipment( $shipment_id ) {
        return $this->request( 'GET', '/shipments/' . rawurlencode( $shipment_id ) );
    }

    public function predict_category( $title, $limit = 8 ) {
        $title = trim( mpap_plain_text( $title, 180 ) );
        if ( '' === $title ) {
            return new WP_Error( 'mpap_empty_category_query', 'Informe um termo ou título de produto para consultar a categoria.' );
        }
        $limit = max( 1, min( 20, absint( $limit ) ) );
        return $this->request(
            'GET',
            '/sites/' . rawurlencode( mpap_get_settings( 'site_id', 'MLB' ) ) . '/domain_discovery/search',
            null,
            array( 'q' => $title, 'limit' => $limit ),
            true
        );
    }

    public function get_category( $category_id ) {
        $category_id = strtoupper( preg_replace( '/[^A-Z0-9_\-]/', '', (string) $category_id ) );
        if ( '' === $category_id ) {
            return new WP_Error( 'mpap_empty_category_id', 'Informe um ID de categoria Mercado Livre.' );
        }
        return $this->request( 'GET', '/categories/' . rawurlencode( $category_id ), null, array(), true );
    }

    public function get_category_attributes( $category_id ) {
        $category_id = strtoupper( preg_replace( '/[^A-Z0-9_\-]/', '', (string) $category_id ) );
        if ( '' === $category_id ) {
            return new WP_Error( 'mpap_empty_category_id', 'Informe um ID de categoria Mercado Livre.' );
        }
        return $this->request( 'GET', '/categories/' . rawurlencode( $category_id ) . '/attributes', null, array(), true );
    }

    public function get_category_listing_types( $category_id ) {
        $category_id = strtoupper( preg_replace( '/[^A-Z0-9_\-]/', '', (string) $category_id ) );
        if ( '' === $category_id ) {
            return new WP_Error( 'mpap_empty_category_id', 'Informe um ID de categoria Mercado Livre.' );
        }
        return $this->request( 'GET', '/categories/' . rawurlencode( $category_id ) . '/listing_types', null, array(), true );
    }
}
