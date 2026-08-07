<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MPAP_Auth {
    const CREDENTIAL_OPTION     = 'mpap_credentials';
    const TOKEN_OPTION          = 'mpap_tokens';
    const MANAGED_OPTION        = 'mpap_managed_connection';
    const OAUTH_STATUS_OPTION   = 'mpap_oauth_status';
    const STATE_PREFIX          = 'mpap_oauth_state_';
    const MANAGED_STATE_PREFIX  = 'mpap_managed_oauth_state_';

    public function hooks() {
        add_action( 'admin_post_mpap_oauth_start', array( $this, 'start' ) );
        add_action( 'admin_post_mpap_oauth_callback', array( $this, 'callback' ) );
        add_action( 'admin_post_mpap_disconnect', array( $this, 'disconnect' ) );
        add_action( 'admin_post_mpap_refresh_token', array( $this, 'manual_refresh' ) );
        add_action( 'admin_post_mpap_managed_oauth_start', array( $this, 'managed_start' ) );
        add_action( 'admin_post_mpap_managed_oauth_callback', array( $this, 'managed_callback' ) );
        add_action( 'admin_post_mpap_managed_disconnect', array( $this, 'managed_disconnect' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_rest_routes() {
        register_rest_route(
            'mpap/v1',
            '/oauth/callback',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'callback' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function connection_mode() {
        return function_exists( 'mpap_connection_mode' ) ? mpap_connection_mode() : 'manual';
    }

    public function using_managed() {
        return 'managed' === $this->connection_mode();
    }

    public function save_credentials( $client_id, $client_secret = '' ) {
        $old = $this->credentials( false );
        $data = array(
            'client_id'     => sanitize_text_field( $client_id ),
            'client_secret' => '' !== (string) $client_secret ? MPAP_Crypto::encrypt( sanitize_text_field( $client_secret ) ) : ( $old['client_secret'] ?? '' ),
            'updated_at'    => current_time( 'mysql' ),
        );
        update_option( self::CREDENTIAL_OPTION, $data, false );
        MPAP_Logger::info( 'oauth', 'Credenciais Mercado Livre salvas.', array( 'client_id' => $this->mask( $client_id ), 'secret_informed' => '' !== (string) $client_secret ), array( 'event' => 'credentials_saved' ) );
    }

    public function credentials( $decrypted = true ) {
        $data = get_option( self::CREDENTIAL_OPTION, array() );
        $data = is_array( $data ) ? wp_parse_args( $data, array( 'client_id' => '', 'client_secret' => '', 'updated_at' => '' ) ) : array( 'client_id' => '', 'client_secret' => '', 'updated_at' => '' );
        if ( $decrypted ) {
            $data['client_secret'] = $data['client_secret'] ? MPAP_Crypto::decrypt( $data['client_secret'] ) : '';
        }
        return $data;
    }

    public function mask( $value ) {
        return mpap_mask_secret( $value );
    }

    public function tokens() {
        return MPAP_Crypto::decrypt_json( get_option( self::TOKEN_OPTION, '' ) );
    }

    public function oauth_status() {
        $data = get_option( self::OAUTH_STATUS_OPTION, array() );
        $data = is_array( $data ) ? $data : array();
        return wp_parse_args(
            $data,
            array(
                'status'            => 'idle',
                'message'           => 'OAuth ainda não iniciado.',
                'updated_at'        => '',
                'started_at'        => '',
                'callback_at'       => '',
                'state_hash'        => '',
                'redirect_uri'      => mpap_oauth_redirect_uri(),
                'authorization_url' => '',
                'pkce_enabled'      => (bool) mpap_get_settings( 'pkce_enabled', 0 ),
                'last_error'        => '',
                'last_http_status'  => '',
            )
        );
    }

    private function save_oauth_status( $status, $message, array $data = array() ) {
        $current = $this->oauth_status();
        $payload = wp_parse_args(
            $data,
            array(
                'status'            => sanitize_key( $status ),
                'message'           => sanitize_text_field( $message ),
                'updated_at'        => current_time( 'mysql' ),
                'started_at'        => $current['started_at'],
                'callback_at'       => $current['callback_at'],
                'state_hash'        => $current['state_hash'],
                'redirect_uri'      => mpap_oauth_redirect_uri(),
                'authorization_url' => $current['authorization_url'],
                'pkce_enabled'      => $current['pkce_enabled'],
                'last_error'        => '',
                'last_http_status'  => '',
            )
        );
        $payload = mpap_sanitize_log_context( $payload );
        update_option( self::OAUTH_STATUS_OPTION, $payload, false );
        return $payload;
    }

    private function save_tokens( array $tokens ) {
        $current = $this->tokens();
        $expires = isset( $tokens['expires_in'] ) ? absint( $tokens['expires_in'] ) : 0;
        $data = array(
            'access_token'  => (string) ( $tokens['access_token'] ?? '' ),
            'refresh_token' => (string) ( $tokens['refresh_token'] ?? ( $current['refresh_token'] ?? '' ) ),
            'token_type'    => (string) ( $tokens['token_type'] ?? 'Bearer' ),
            'expires_in'    => $expires,
            'expires_at'    => $expires ? time() + $expires : 0,
            'scope'         => (string) ( $tokens['scope'] ?? '' ),
            'user_id'       => (string) ( $tokens['user_id'] ?? ( $current['user_id'] ?? '' ) ),
            'updated_at'    => current_time( 'mysql' ),
        );
        update_option( self::TOKEN_OPTION, MPAP_Crypto::encrypt_json( $data ), false );
        return $data;
    }

    public function managed_connection() {
        $data = MPAP_Crypto::decrypt_json( get_option( self::MANAGED_OPTION, '' ) );
        $data = is_array( $data ) ? $data : array();
        return wp_parse_args(
            $data,
            array(
                'connection_token' => '',
                'connection_id'    => '',
                'service_url'      => '',
                'seller_user_id'   => '',
                'seller_nickname'  => '',
                'scope'            => '',
                'expires_at'       => 0,
                'connected_at'     => '',
                'updated_at'       => '',
            )
        );
    }

    private function save_managed_connection( array $connection ) {
        $current = $this->managed_connection();
        $expires = isset( $connection['expires_in'] ) ? time() + absint( $connection['expires_in'] ) : absint( $connection['expires_at'] ?? ( $current['expires_at'] ?? 0 ) );
        $service_url = ! empty( $connection['service_url'] ) ? untrailingslashit( esc_url_raw( $connection['service_url'] ) ) : mpap_managed_service_url();
        $data = array(
            'connection_token' => (string) ( $connection['connection_token'] ?? $connection['token'] ?? $current['connection_token'] ?? '' ),
            'connection_id'    => sanitize_text_field( $connection['connection_id'] ?? $current['connection_id'] ?? '' ),
            'service_url'      => $service_url,
            'seller_user_id'   => sanitize_text_field( $connection['seller_user_id'] ?? $connection['user_id'] ?? $current['seller_user_id'] ?? '' ),
            'seller_nickname'  => sanitize_text_field( $connection['seller_nickname'] ?? $connection['nickname'] ?? $current['seller_nickname'] ?? '' ),
            'scope'            => sanitize_text_field( $connection['scope'] ?? $current['scope'] ?? '' ),
            'expires_at'       => $expires,
            'connected_at'     => $current['connected_at'] ?: current_time( 'mysql' ),
            'updated_at'       => current_time( 'mysql' ),
        );
        update_option( self::MANAGED_OPTION, MPAP_Crypto::encrypt_json( $data ), false );
        return $data;
    }

    public function managed_connected() {
        $connection = $this->managed_connection();
        return ! empty( $connection['connection_token'] );
    }

    public function connected() {
        if ( $this->using_managed() ) {
            return $this->managed_connected();
        }
        $tokens = $this->tokens();
        return ! empty( $tokens['access_token'] );
    }

    public function expiring( $window = 300 ) {
        if ( $this->using_managed() ) {
            $connection = $this->managed_connection();
            return ! empty( $connection['expires_at'] ) && (int) $connection['expires_at'] <= time() + absint( $window );
        }
        $tokens = $this->tokens();
        return ! empty( $tokens['expires_at'] ) && (int) $tokens['expires_at'] <= time() + absint( $window );
    }

    public function build_authorization_url( $state = '', $store_state = false, $force_pkce = null ) {
        $credentials = $this->credentials();
        if ( empty( $credentials['client_id'] ) ) {
            return new WP_Error( 'mpap_missing_client_id', 'Client ID ausente.' );
        }
        $state = $state ?: wp_generate_password( 32, false, false );
        $use_pkce = null === $force_pkce ? (bool) mpap_get_settings( 'pkce_enabled', 0 ) : (bool) $force_pkce;
        $data  = array( 'created' => time(), 'redirect_uri' => mpap_oauth_redirect_uri(), 'state_hash' => mpap_hash_for_log( $state ), 'pkce_enabled' => $use_pkce );
        $args  = array(
            'response_type' => 'code',
            'client_id'     => $credentials['client_id'],
            'redirect_uri'  => mpap_oauth_redirect_uri(),
            'state'         => $state,
        );

        if ( $use_pkce ) {
            $verifier = wp_generate_password( 64, false, false );
            $challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
            $args['code_challenge']        = $challenge;
            $args['code_challenge_method'] = 'S256';
            $data['code_verifier']         = $verifier;
        }

        if ( $store_state ) {
            set_transient( self::STATE_PREFIX . $state, $data, 10 * MINUTE_IN_SECONDS );
        }

        $auth_base = untrailingslashit( mpap_get_settings( 'auth_base' ) );
        if ( ! mpap_validate_url_host( $auth_base, mpap_allowed_auth_hosts() ) ) {
            return new WP_Error( 'mpap_invalid_auth_host', 'Auth Host inválido. Use um host oficial do Mercado Livre.' );
        }
        return $auth_base . '/authorization?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
    }

    public function start() {
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_die( 'Permissão negada.' );
        }
        check_admin_referer( 'mpap_oauth_start' );
        $credentials = $this->credentials();
        if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
            MPAP_Logger::warning( 'oauth', 'Tentativa de conexão sem credenciais completas.', array( 'client_id' => $this->mask( $credentials['client_id'] ?? '' ), 'has_secret' => ! empty( $credentials['client_secret'] ) ), array( 'event' => 'oauth_start_blocked' ) );
            wp_safe_redirect( add_query_arg( 'mpap_notice', 'missing_credentials', admin_url( 'admin.php?page=mpap-settings' ) ) );
            exit;
        }

        $state = wp_generate_password( 32, false, false );
        $force_pkce = ( isset( $_GET['mpap_pkce'] ) && '0' === sanitize_text_field( wp_unslash( $_GET['mpap_pkce'] ) ) ) ? false : null;
        $auth_url = $this->build_authorization_url( $state, true, $force_pkce );
        if ( is_wp_error( $auth_url ) ) {
            MPAP_Logger::error( 'oauth', 'OAuth bloqueado por configuração inválida.', array( 'error' => $auth_url->get_error_message(), 'auth_base' => mpap_get_settings( 'auth_base' ) ), array( 'event' => 'oauth_start_invalid_config' ) );
            wp_safe_redirect( add_query_arg( 'mpap_notice', 'invalid_auth_host', admin_url( 'admin.php?page=mpap-settings' ) ) );
            exit;
        }

        $pkce_active = false !== strpos( (string) $auth_url, 'code_challenge=' );
        $status_payload = $this->save_oauth_status(
            'started',
            'OAuth iniciado e aguardando retorno do Mercado Livre.',
            array(
                'started_at'        => current_time( 'mysql' ),
                'callback_at'       => '',
                'state_hash'        => mpap_hash_for_log( $state ),
                'redirect_uri'      => mpap_oauth_redirect_uri(),
                'authorization_url' => $auth_url,
                'pkce_enabled'      => $pkce_active,
                'last_error'        => '',
                'last_http_status'  => '',
            )
        );

        MPAP_Logger::info(
            'oauth',
            'OAuth iniciado: redirecionando para autorização Mercado Livre.',
            array(
                'client_id'         => $this->mask( $credentials['client_id'] ),
                'redirect_uri'      => mpap_oauth_redirect_uri(),
                'legacy_redirect'   => function_exists( 'mpap_legacy_oauth_redirect_uri' ) ? mpap_legacy_oauth_redirect_uri() : '',
                'auth_base'         => mpap_get_settings( 'auth_base' ),
                'pkce_enabled'      => $pkce_active,
                'authorization_url' => $auth_url,
                'state_hash'        => $status_payload['state_hash'],
                'observacao'        => 'Se o Mercado Livre exibir “O aplicativo não está pronto para se conectar”, o erro ocorre no portal Mercado Livre antes do callback. Confira App ID, Redirect URI exata, permissões e estado da aplicação no painel Developers.',
            ),
            array( 'event' => 'oauth_start', 'url' => '/authorization' )
        );

        wp_redirect( $auth_url );
        exit;
    }

    public function managed_start() {
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_die( 'Permissão negada.' );
        }
        check_admin_referer( 'mpap_managed_oauth_start' );

        $service_url = mpap_managed_service_url();
        if ( ! mpap_validate_https_url( $service_url ) ) {
            MPAP_Logger::error( 'connect', 'Privilege Connect não configurado.', array( 'service_url' => $service_url ), array( 'event' => 'managed_start_missing_service' ) );
            wp_safe_redirect( add_query_arg( 'mpap_notice', 'missing_managed_service', admin_url( 'admin.php?page=mpap-settings' ) ) );
            exit;
        }

        $state = wp_generate_password( 40, false, false );
        $state_data = array(
            'created'      => time(),
            'service_url'  => $service_url,
            'site_url'     => home_url( '/' ),
            'callback_url' => mpap_managed_oauth_callback_uri(),
        );
        set_transient( self::MANAGED_STATE_PREFIX . $state, $state_data, 15 * MINUTE_IN_SECONDS );

        $params = array(
            'state'          => $state,
            'site_url'       => home_url( '/' ),
            'site_name'      => get_bloginfo( 'name' ),
            'callback_url'   => mpap_managed_oauth_callback_uri(),
            'webhook_url'    => mpap_webhook_url( true ),
            'plugin_version' => MPAP_VERSION,
            'platform'       => 'wordpress',
            'source'         => 'mpap',
        );
        $connect_url = $service_url . '/v1/mercadolivre/connect?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );

        MPAP_Logger::info(
            'connect',
            'Privilege Connect iniciado: redirecionando para broker OAuth.',
            array(
                'service_url' => $service_url,
                'site_url'    => home_url( '/' ),
                'callback'    => mpap_managed_oauth_callback_uri(),
                'webhook'     => mpap_webhook_url( true ),
                'connect_url' => $connect_url,
                'observacao'  => 'Neste modo o Client ID e o Client Secret ficam no broker; o WordPress recebe apenas um token de conexão do serviço.',
            ),
            array( 'event' => 'managed_oauth_start', 'url' => '/v1/mercadolivre/connect' )
        );

        wp_safe_redirect( $connect_url );
        exit;
    }

    public function managed_callback() {
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_die( 'Permissão negada.' );
        }

        $state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
        $code = '';
        if ( isset( $_GET['code'] ) ) {
            $code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        } elseif ( isset( $_GET['install_code'] ) ) {
            $code = sanitize_text_field( wp_unslash( $_GET['install_code'] ) );
        }

        $incoming = array(
            'state_present'       => (bool) $state,
            'install_code_present'=> (bool) $code,
            'error'               => isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '',
            'error_description'   => isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : '',
            'query'               => $_GET,
        );
        MPAP_Logger::info( 'connect', 'Callback Privilege Connect recebido.', $incoming, array( 'event' => 'managed_callback' ) );

        if ( ! empty( $incoming['error'] ) ) {
            MPAP_Logger::error( 'connect', 'Broker retornou erro no callback.', $incoming, array( 'event' => 'managed_callback_error' ) );
            wp_safe_redirect( add_query_arg( 'mpap_notice', 'managed_callback_error', admin_url( 'admin.php?page=mpap-settings' ) ) );
            exit;
        }

        $state_data = $state ? get_transient( self::MANAGED_STATE_PREFIX . $state ) : false;
        if ( $state ) {
            delete_transient( self::MANAGED_STATE_PREFIX . $state );
        }
        if ( ! $state || ! $code || ! is_array( $state_data ) ) {
            MPAP_Logger::error( 'connect', 'Callback Privilege Connect inválido ou expirado.', array( 'state_present' => (bool) $state, 'code_present' => (bool) $code, 'state_found' => is_array( $state_data ) ), array( 'event' => 'managed_invalid_state' ) );
            wp_safe_redirect( add_query_arg( 'mpap_notice', 'managed_invalid_state', admin_url( 'admin.php?page=mpap-settings' ) ) );
            exit;
        }

        $service_url = untrailingslashit( $state_data['service_url'] ?? mpap_managed_service_url() );
        $exchange = $this->managed_exchange_code( $service_url, $code, $state, $state_data );
        if ( is_wp_error( $exchange ) ) {
            MPAP_Logger::error( 'connect', 'Falha ao trocar install_code por token de conexão.', array( 'error' => $exchange->get_error_message(), 'data' => $exchange->get_error_data() ), array( 'event' => 'managed_exchange_failed' ) );
            wp_safe_redirect( add_query_arg( 'mpap_notice', 'managed_exchange_failed', admin_url( 'admin.php?page=mpap-settings' ) ) );
            exit;
        }

        $saved = $this->save_managed_connection( $exchange );
        MPAP_Logger::info(
            'connect',
            'Conta conectada via Privilege Connect.',
            array(
                'connection_id'   => $saved['connection_id'],
                'seller_user_id'  => $saved['seller_user_id'],
                'seller_nickname' => $saved['seller_nickname'],
                'service_url'     => $saved['service_url'],
                'expires_at'      => $saved['expires_at'],
            ),
            array( 'event' => 'managed_connected' )
        );
        wp_safe_redirect( add_query_arg( 'mpap_notice', 'managed_connected', admin_url( 'admin.php?page=mpap-dashboard' ) ) );
        exit;
    }

    private function managed_exchange_code( $service_url, $code, $state, array $state_data ) {
        if ( ! mpap_validate_https_url( $service_url ) ) {
            return new WP_Error( 'mpap_invalid_managed_service', 'URL do broker inválida.' );
        }

        $url = $service_url . '/v1/wordpress/exchange';
        $request_id = 'connect-' . wp_generate_uuid4();
        $payload = array(
            'install_code'   => $code,
            'state'          => $state,
            'site_url'       => home_url( '/' ),
            'site_name'      => get_bloginfo( 'name' ),
            'admin_email'    => get_option( 'admin_email' ),
            'callback_url'   => mpap_managed_oauth_callback_uri(),
            'webhook_url'    => mpap_webhook_url( true ),
            'plugin_version' => MPAP_VERSION,
            'wp_version'     => get_bloginfo( 'version' ),
            'origin'         => $state_data['site_url'] ?? home_url( '/' ),
        );

        MPAP_Logger::info( 'connect', 'Solicitando troca de install_code no broker.', array( 'request_id' => $request_id, 'url' => $url, 'payload' => $payload ), array( 'event' => 'managed_exchange_request', 'request_id' => $request_id, 'method' => 'POST', 'url' => '/v1/wordpress/exchange' ) );
        $started = microtime( true );
        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 45,
                'headers' => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent'   => 'MPAP/' . MPAP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
                ),
                'body' => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            )
        );
        $duration = (int) round( ( microtime( true ) - $started ) * 1000 );

        if ( is_wp_error( $response ) ) {
            MPAP_Logger::error( 'connect', 'Erro de transporte ao falar com o broker.', array( 'request_id' => $request_id, 'error' => $response->get_error_message() ), array( 'event' => 'managed_exchange_transport_error', 'request_id' => $request_id, 'method' => 'POST', 'url' => '/v1/wordpress/exchange', 'duration_ms' => $duration ) );
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $raw    = wp_remote_retrieve_body( $response );
        $data   = json_decode( $raw, true );
        $data   = is_array( $data ) ? $data : array( 'raw' => $raw );
        MPAP_Logger::log(
            ( $status >= 200 && $status < 300 ) ? 'info' : 'error',
            'connect',
            'Resposta do broker recebida.',
            array( 'request_id' => $request_id, 'status' => $status, 'response' => $data ),
            array( 'event' => 'managed_exchange_response', 'request_id' => $request_id, 'method' => 'POST', 'url' => '/v1/wordpress/exchange', 'http_status' => $status, 'duration_ms' => $duration )
        );

        if ( isset( $data['ok'] ) && true === (bool) $data['ok'] && isset( $data['data'] ) && is_array( $data['data'] ) ) {
            $data = $data['data'];
        }

        if ( $status < 200 || $status >= 300 || empty( $data['connection_token'] ) ) {
            return new WP_Error( 'mpap_managed_exchange_error', $data['message'] ?? $data['error'] ?? 'Broker não retornou connection_token.', array( 'status' => $status, 'response' => $data, 'request_id' => $request_id ) );
        }
        $data['service_url'] = $service_url;
        return $data;
    }

    public function callback() {
        $is_rest_callback = defined( 'REST_REQUEST' ) && REST_REQUEST;
        if ( ! $is_rest_callback && ! current_user_can( mpap_capability() ) ) {
            wp_die( 'Permissão negada.' );
        }

        $transport = $is_rest_callback ? 'rest_clean_callback' : 'admin_post_legacy_callback';
        $incoming = array(
            'state'             => isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '',
            'code_present'      => isset( $_GET['code'] ),
            'error'             => isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '',
            'error_description' => isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : '',
            'transport'         => $transport,
            'query'             => $_GET,
        );
        MPAP_Logger::info( 'oauth', 'Callback OAuth recebido.', $incoming, array( 'event' => 'oauth_callback' ) );
        $this->save_oauth_status(
            'callback_received',
            'Callback OAuth recebido pelo WordPress.',
            array(
                'callback_at'      => current_time( 'mysql' ),
                'state_hash'       => $incoming['state'] ? mpap_hash_for_log( $incoming['state'] ) : '',
                'last_error'       => $incoming['error'],
                'last_http_status' => '',
            )
        );

        if ( ! empty( $incoming['error'] ) ) {
            $this->save_oauth_status( 'callback_error', 'Mercado Livre retornou erro no callback OAuth.', array( 'last_error' => $incoming['error'] . ' ' . $incoming['error_description'] ) );
            MPAP_Logger::error( 'oauth', 'Mercado Livre retornou erro no callback OAuth.', $incoming, array( 'event' => 'oauth_callback_error' ) );
            wp_safe_redirect( add_query_arg( 'mpap_notice', 'oauth_callback_error', admin_url( 'admin.php?page=mpap-settings' ) ) );
            exit;
        }

        $state = $incoming['state'];
        $code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
        $data  = $state ? get_transient( self::STATE_PREFIX . $state ) : false;
        if ( $state ) {
            delete_transient( self::STATE_PREFIX . $state );
        }

        if ( ! $state || ! $code || ! is_array( $data ) ) {
            $last_status = $this->oauth_status();
            $this->save_oauth_status( 'invalid_state', 'Callback OAuth inválido: state expirado, ausente ou code ausente.', array( 'last_error' => 'state_present=' . ( $state ? '1' : '0' ) . '; code_present=' . ( $code ? '1' : '0' ) . '; state_found=' . ( is_array( $data ) ? '1' : '0' ), 'state_hash' => $state ? mpap_hash_for_log( $state ) : ( $last_status['state_hash'] ?? '' ) ) );
            MPAP_Logger::error( 'oauth', 'Callback OAuth inválido: state expirado, ausente ou code ausente.', array( 'state_present' => (bool) $state, 'code_present' => (bool) $code, 'state_found' => is_array( $data ), 'last_oauth_status' => $last_status ), array( 'event' => 'oauth_invalid_state' ) );
            wp_safe_redirect( add_query_arg( 'mpap_notice', 'invalid_state', admin_url( 'admin.php?page=mpap-settings' ) ) );
            exit;
        }

        $credentials = $this->credentials();
        $body = array(
            'grant_type'    => 'authorization_code',
            'client_id'     => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'code'          => $code,
            'redirect_uri'  => mpap_oauth_redirect_uri(),
        );
        if ( ! empty( $data['code_verifier'] ) ) {
            $body['code_verifier'] = $data['code_verifier'];
        }

        $this->save_oauth_status( 'token_exchange_started', 'Callback validado. Solicitando access_token ao Mercado Livre.', array( 'state_hash' => mpap_hash_for_log( $state ), 'redirect_uri' => mpap_oauth_redirect_uri() ) );
        $response = $this->token_request( $body, 'authorization_code' );
        if ( is_wp_error( $response ) ) {
            $error_data = $response->get_error_data();
            $this->save_oauth_status( 'token_failed', 'Falha ao obter token OAuth.', array( 'last_error' => $response->get_error_message(), 'last_http_status' => is_array( $error_data ) && isset( $error_data['status'] ) ? $error_data['status'] : '' ) );
            MPAP_Logger::error( 'oauth', 'Falha ao obter token OAuth.', array( 'error' => $response->get_error_message(), 'data' => $error_data ), array( 'event' => 'oauth_token_failed' ) );
            wp_safe_redirect( add_query_arg( 'mpap_notice', 'token_failed', admin_url( 'admin.php?page=mpap-settings' ) ) );
            exit;
        }

        $saved = $this->save_tokens( $response );
        $this->save_oauth_status( 'connected', 'Conta Mercado Livre conectada com sucesso.', array( 'last_error' => '', 'last_http_status' => '', 'updated_at' => current_time( 'mysql' ) ) );
        MPAP_Logger::info( 'oauth', 'Conta Mercado Livre conectada.', array( 'user_id' => $response['user_id'] ?? '', 'expires_at' => $saved['expires_at'] ), array( 'event' => 'oauth_connected' ) );
        wp_safe_redirect( add_query_arg( 'mpap_notice', 'connected', admin_url( 'admin.php?page=mpap-dashboard' ) ) );
        exit;
    }

    private function token_request( array $body, $grant_event = 'token' ) {
        $api_base = untrailingslashit( mpap_get_settings( 'api_base' ) );
        if ( ! mpap_validate_url_host( $api_base, mpap_allowed_api_hosts() ) ) {
            return new WP_Error( 'mpap_invalid_api_host', 'Host de API inválido. Use https://api.mercadolibre.com.' );
        }
        $url = $api_base . '/oauth/token';
        $request_id = 'oauth-' . wp_generate_uuid4();
        $safe_body = mpap_sanitize_log_context( $body );
        MPAP_Logger::info( 'oauth', 'Solicitando token OAuth.', array( 'request_id' => $request_id, 'url' => $url, 'body' => $safe_body ), array( 'event' => 'oauth_token_request', 'request_id' => $request_id, 'method' => 'POST', 'url' => '/oauth/token' ) );

        $started = microtime( true );
        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'User-Agent'   => 'MPAP/' . MPAP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
                ),
                'body'    => $body,
            )
        );
        $duration = (int) round( ( microtime( true ) - $started ) * 1000 );

        if ( is_wp_error( $response ) ) {
            MPAP_Logger::error( 'oauth', 'Erro de transporte no token OAuth.', array( 'request_id' => $request_id, 'error' => $response->get_error_message() ), array( 'event' => 'oauth_transport_error', 'request_id' => $request_id, 'method' => 'POST', 'url' => '/oauth/token', 'duration_ms' => $duration ) );
            return $response;
        }
        $status = (int) wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $data   = json_decode( $raw_body, true );
        $data   = is_array( $data ) ? $data : array( 'raw' => $raw_body );
        $safe_response = mpap_sanitize_log_context( $data );
        $level = ( $status >= 200 && $status < 300 ) ? 'info' : 'error';
        MPAP_Logger::log( $level, 'oauth', 'Resposta do token OAuth recebida.', array( 'request_id' => $request_id, 'status' => $status, 'response' => $safe_response, 'response_headers' => mpap_remote_headers_to_array( $response ) ), array( 'event' => 'oauth_token_response', 'request_id' => $request_id, 'method' => 'POST', 'url' => '/oauth/token', 'http_status' => $status, 'duration_ms' => $duration ) );

        if ( $status < 200 || $status >= 300 ) {
            return new WP_Error( 'mpap_oauth_error', $data['message'] ?? $data['error_description'] ?? $data['error'] ?? 'Erro OAuth Mercado Livre.', array( 'status' => $status, 'response' => $data, 'request_id' => $request_id, 'grant' => $grant_event ) );
        }
        return $data;
    }

    public function refresh( $force = false ) {
        if ( $this->using_managed() ) {
            $connection = $this->managed_connection();
            if ( empty( $connection['connection_token'] ) ) {
                MPAP_Logger::warning( 'connect', 'Conexão gerenciada ausente. Reconexão necessária.', array(), array( 'event' => 'managed_refresh_missing' ) );
                return new WP_Error( 'mpap_missing_managed_connection', 'Conexão Privilege Connect ausente. Reconecte a conta.' );
            }
            MPAP_Logger::debug( 'connect', 'Refresh gerenciado ignorado localmente; tokens Mercado Livre ficam no broker.', array( 'connection_id' => $connection['connection_id'] ), array( 'event' => 'managed_refresh_noop' ) );
            return $connection['connection_token'];
        }

        $tokens = $this->tokens();
        if ( empty( $tokens['refresh_token'] ) ) {
            MPAP_Logger::warning( 'oauth', 'Refresh token ausente. Reconexão necessária.', array(), array( 'event' => 'oauth_refresh_missing' ) );
            return new WP_Error( 'mpap_missing_refresh_token', 'Refresh token ausente. Reconecte a conta.' );
        }
        if ( ! $force && ! $this->expiring( 300 ) ) {
            return $tokens['access_token'] ?? '';
        }

        $credentials = $this->credentials();
        $response = $this->token_request(
            array(
                'grant_type'    => 'refresh_token',
                'client_id'     => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $tokens['refresh_token'],
            ),
            'refresh_token'
        );
        if ( is_wp_error( $response ) ) {
            MPAP_Logger::error( 'oauth', 'Falha ao renovar token.', array( 'error' => $response->get_error_message(), 'data' => $response->get_error_data() ), array( 'event' => 'oauth_refresh_failed' ) );
            return $response;
        }
        $saved = $this->save_tokens( $response );
        $this->save_oauth_status( 'connected', 'Token OAuth renovado automaticamente.', array( 'last_error' => '', 'last_http_status' => '' ) );
        MPAP_Logger::info( 'oauth', 'Token renovado automaticamente.', array( 'expires_at' => $saved['expires_at'] ), array( 'event' => 'oauth_refreshed' ) );
        return $saved['access_token'];
    }

    public function access_token() {
        if ( $this->using_managed() ) {
            $connection = $this->managed_connection();
            return $connection['connection_token'] ?? '';
        }
        $tokens = $this->tokens();
        if ( empty( $tokens['access_token'] ) ) {
            return '';
        }
        if ( $this->expiring( 300 ) ) {
            return $this->refresh( true );
        }
        return $tokens['access_token'];
    }

    public function manual_refresh() {
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_die( 'Permissão negada.' );
        }
        check_admin_referer( 'mpap_refresh_token' );
        $result = $this->refresh( true );
        $notice = is_wp_error( $result ) ? 'refresh_failed' : 'token_refreshed';
        wp_safe_redirect( add_query_arg( 'mpap_notice', $notice, admin_url( 'admin.php?page=mpap-settings' ) ) );
        exit;
    }

    private function revoke_managed_connection() {
        $connection = $this->managed_connection();
        if ( empty( $connection['connection_token'] ) || empty( $connection['service_url'] ) ) {
            return;
        }
        $url = untrailingslashit( $connection['service_url'] ) . '/v1/wordpress/disconnect';
        $request_id = 'connect-' . wp_generate_uuid4();
        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $connection['connection_token'],
                    'User-Agent'    => 'MPAP/' . MPAP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
                ),
                'body' => wp_json_encode( array( 'site_url' => home_url( '/' ), 'connection_id' => $connection['connection_id'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            )
        );
        $status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
        MPAP_Logger::info( 'connect', 'Solicitação de desconexão enviada ao broker.', array( 'request_id' => $request_id, 'status' => $status, 'transport_error' => is_wp_error( $response ) ? $response->get_error_message() : '' ), array( 'event' => 'managed_disconnect_remote', 'request_id' => $request_id, 'method' => 'POST', 'url' => '/v1/wordpress/disconnect', 'http_status' => $status ) );
    }

    public function managed_disconnect() {
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_die( 'Permissão negada.' );
        }
        check_admin_referer( 'mpap_managed_disconnect' );
        $this->revoke_managed_connection();
        delete_option( self::MANAGED_OPTION );
        MPAP_Logger::info( 'connect', 'Conta desconectada do Privilege Connect.', array(), array( 'event' => 'managed_disconnected' ) );
        wp_safe_redirect( add_query_arg( 'mpap_notice', 'disconnected', admin_url( 'admin.php?page=mpap-settings' ) ) );
        exit;
    }

    public function disconnect() {
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_die( 'Permissão negada.' );
        }
        check_admin_referer( 'mpap_disconnect' );
        if ( $this->using_managed() ) {
            $this->revoke_managed_connection();
            delete_option( self::MANAGED_OPTION );
            MPAP_Logger::info( 'connect', 'Conta desconectada do Privilege Connect.', array(), array( 'event' => 'managed_disconnected' ) );
        } else {
            delete_option( self::TOKEN_OPTION );
            $this->save_oauth_status( 'disconnected', 'Conta Mercado Livre desconectada manualmente.', array( 'authorization_url' => '', 'last_error' => '', 'last_http_status' => '' ) );
            MPAP_Logger::info( 'oauth', 'Conta Mercado Livre desconectada.', array(), array( 'event' => 'oauth_disconnected' ) );
        }
        wp_safe_redirect( add_query_arg( 'mpap_notice', 'disconnected', admin_url( 'admin.php?page=mpap-settings' ) ) );
        exit;
    }
}
