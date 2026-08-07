<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MPAP_Diagnostics {
    private $auth;
    private $api;

    public function __construct( MPAP_Auth $auth, MPAP_API $api ) {
        $this->auth = $auth;
        $this->api  = $api;
    }

    public function hooks() {
        add_action( 'wp_ajax_mpap_run_diagnostics', array( $this, 'ajax_run' ) );
        add_action( 'wp_ajax_mpap_test_public_api', array( $this, 'ajax_test_public_api' ) );
        add_action( 'wp_ajax_mpap_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_mpap_test_oauth_readiness', array( $this, 'ajax_test_oauth_readiness' ) );
        add_action( 'wp_ajax_mpap_test_log', array( $this, 'ajax_test_log' ) );
    }

    public function run() {
        $settings    = mpap_get_settings();
        $credentials = $this->auth->credentials();
        $tokens      = $this->auth->tokens();
        $managed     = $this->auth->managed_connection();
        $oauth       = $this->auth->oauth_status();
        $mode        = $this->auth->using_managed() ? 'managed' : 'manual';
        $checks      = array();

        $checks[] = $this->check( 'WooCommerce', mpap_has_wc(), mpap_has_wc() ? 'WooCommerce ativo.' : 'WooCommerce não está ativo. A sincronização de produtos e pedidos ficará indisponível.' );
        $checks[] = $this->check( 'HTTPS do site', is_ssl() || 0 === strpos( home_url(), 'https://' ), ( is_ssl() || 0 === strpos( home_url(), 'https://' ) ) ? 'Site usando HTTPS.' : 'Ative HTTPS. APIs e webhooks de produção exigem uma URL pública segura.' );
        $checks[] = $this->check( 'REST API', ! empty( rest_url() ), 'Health endpoint: ' . mpap_health_url() );
        $checks[] = $this->check( 'Permalinks', '' !== get_option( 'permalink_structure' ), '' !== get_option( 'permalink_structure' ) ? 'Permalinks amigáveis ativos.' : 'Ative links permanentes para reduzir problemas com REST/webhooks.' );
        $checks[] = $this->check( 'Modo de conexão', true, 'Modo ativo: ' . ( 'managed' === $mode ? 'Privilege Connect sem credenciais no WordPress' : 'Manual com credenciais locais' ) );

        if ( 'managed' === $mode ) {
            $checks[] = $this->check( 'Broker Privilege Connect', mpap_validate_https_url( $settings['managed_service_url'] ), $settings['managed_service_url'] ? $settings['managed_service_url'] : 'Informe a URL HTTPS do broker.' );
            $checks[] = $this->check( 'Token de conexão broker', ! empty( $managed['connection_token'] ), ! empty( $managed['connection_token'] ) ? 'Conectado ao broker. Connection ID: ' . ( $managed['connection_id'] ?: '—' ) : 'Clique em Conectar sem credenciais para autorizar pelo broker.', true );
            $checks[] = $this->check( 'Callback Connect', true, mpap_managed_oauth_callback_uri() . ' — o broker deve redirecionar para essa URL com install_code/code e state.' );
        } else {
            $checks[] = $this->check( 'Client ID', ! empty( $credentials['client_id'] ), ! empty( $credentials['client_id'] ) ? 'Client ID informado: ' . $this->auth->mask( $credentials['client_id'] ) : 'Informe o App ID/Client ID da aplicação Mercado Livre Developers.' );
            $checks[] = $this->check( 'Client Secret', ! empty( $credentials['client_secret'] ), ! empty( $credentials['client_secret'] ) ? 'Client Secret armazenado criptografado.' : 'Informe o Client Secret da aplicação Mercado Livre Developers.' );
            $checks[] = $this->check( 'Redirect URI limpa', true, mpap_oauth_redirect_uri() . ' — cadastre exatamente essa URL no app Mercado Livre. Esta versão evita query string fixa no callback.' );
        $checks[] = $this->check( 'Redirect URI antiga', true, mpap_legacy_oauth_redirect_uri() . ' — mantenha apenas como referência; não use se o Mercado Livre continuar mostrando aplicativo não pronto.' );
            $checks[] = $this->check( 'PKCE manual', ! mpap_get_settings( 'pkce_enabled', 0 ), mpap_get_settings( 'pkce_enabled', 0 ) ? 'PKCE está ativo. Se o Mercado Livre exibir “aplicativo não está pronto”, teste o botão Conectar sem PKCE.' : 'PKCE desativado no OAuth manual. Fluxo mais compatível para diagnóstico.', true );
        }

        $checks[] = $this->check( 'Auth Host', mpap_validate_url_host( $settings['auth_base'], mpap_allowed_auth_hosts() ), $settings['auth_base'] );
        $checks[] = $this->check( 'API Host', mpap_validate_url_host( $settings['api_base'], mpap_allowed_api_hosts() ), $settings['api_base'] );
        $category_id = strtoupper( trim( (string) ( $settings['default_category_id'] ?? '' ) ) );
        $category_ok = (bool) preg_match( '/^ML[A-Z0-9]+$/', $category_id );
        $category_msg = $category_ok ? ( $category_id . ( ! empty( $settings['default_category_path'] ) ? ' — ' . $settings['default_category_path'] : '' ) ) : 'Categoria padrão ausente ou inválida. Use a aba Categorias ML e aplique um ID no formato MLB... antes de sincronizar produtos.';
        $checks[] = $this->check( 'Categoria padrão ML', $category_ok, $category_msg, true );
        $checks[] = $this->check( 'Webhook URL', true, mpap_webhook_url( true ) . ' — cadastre essa URL nos tópicos orders_v2, items e stock.' );
        $checks[] = $this->check( 'WP-Cron sync', (bool) wp_next_scheduled( 'mpap_cron_sync' ), wp_next_scheduled( 'mpap_cron_sync' ) ? 'Próxima execução: ' . mpap_datetime( wp_next_scheduled( 'mpap_cron_sync' ) ) : 'Cron ainda não agendado. Reative o plugin ou salve configurações.', true );
        $checks[] = $this->check( 'WP-Cron token', (bool) wp_next_scheduled( 'mpap_cron_refresh_token' ), wp_next_scheduled( 'mpap_cron_refresh_token' ) ? 'Próxima execução: ' . mpap_datetime( wp_next_scheduled( 'mpap_cron_refresh_token' ) ) : 'Cron de token ainda não agendado.', true );

        if ( 'managed' === $mode ) {
            $checks[] = $this->check( 'Conexão Privilege Connect', ! empty( $managed['connection_token'] ), ! empty( $managed['connection_token'] ) ? 'Conectado. Vendedor: ' . ( $managed['seller_nickname'] ?: $managed['seller_user_id'] ) : 'Ainda não conectado ao broker.', true );
        } else {
            $has_ready_credentials = ! empty( $credentials['client_id'] ) && ! empty( $credentials['client_secret'] );
            $checks[] = $this->check( 'Token OAuth', ! empty( $tokens['access_token'] ), ! empty( $tokens['access_token'] ) ? 'Conectado. Expira em: ' . mpap_datetime( $tokens['expires_at'] ?? 0 ) : 'Ainda não conectado. Isto é esperado antes do callback OAuth.', $has_ready_credentials );
            $checks[] = $this->check( 'Último OAuth', true, $this->format_oauth_status( $oauth ) );
            if ( $has_ready_credentials && empty( $tokens['access_token'] ) && 'idle' === ( $oauth['status'] ?? 'idle' ) ) {
                $checks[] = $this->check( 'Próxima ação OAuth', false, 'Credenciais prontas. Agora clique em “Conectar sem PKCE” ou “Conectar manualmente”; se não aparecer log oauth_start depois do clique, o botão/nonce não foi executado.', true );
            }
            if ( ! empty( $credentials['client_id'] ) ) {
                $preview = $this->auth->build_authorization_url( 'DIAGNOSTICO-STATE', false, false );
                $checks[] = $this->check( 'URL de autorização sem PKCE', ! is_wp_error( $preview ), is_wp_error( $preview ) ? $preview->get_error_message() : $preview );
            }
        }

        $summary = array(
            'total'    => count( $checks ),
            'ok'       => count( array_filter( $checks, static function ( $item ) { return 'ok' === $item['status']; } ) ),
            'warning'  => count( array_filter( $checks, static function ( $item ) { return 'warning' === $item['status']; } ) ),
            'error'    => count( array_filter( $checks, static function ( $item ) { return 'error' === $item['status']; } ) ),
            'datetime' => current_time( 'mysql' ),
        );

        MPAP_Logger::info( 'diagnostics', 'Diagnóstico local executado.', array( 'summary' => $summary, 'checks' => $checks, 'oauth_status' => $oauth ), array( 'event' => 'diagnostics_run' ) );
        return array( 'summary' => $summary, 'checks' => $checks, 'oauth_status' => $oauth );
    }

    private function format_oauth_status( array $oauth ) {
        $parts = array();
        $parts[] = 'Status: ' . ( $oauth['status'] ?: 'idle' );
        $parts[] = 'Mensagem: ' . ( $oauth['message'] ?: '—' );
        if ( ! empty( $oauth['started_at'] ) ) {
            $parts[] = 'Iniciado em: ' . mpap_datetime( $oauth['started_at'] );
        }
        if ( ! empty( $oauth['callback_at'] ) ) {
            $parts[] = 'Callback em: ' . mpap_datetime( $oauth['callback_at'] );
        }
        if ( ! empty( $oauth['last_error'] ) ) {
            $parts[] = 'Último erro: ' . $oauth['last_error'];
        }
        return implode( ' | ', $parts );
    }

    private function check( $label, $ok, $message, $warning = false ) {
        return array(
            'label'   => $label,
            'status'  => $ok ? 'ok' : ( $warning ? 'warning' : 'error' ),
            'message' => (string) $message,
        );
    }

    public function oauth_readiness_probe() {
        $credentials = $this->auth->credentials();
        $oauth       = $this->auth->oauth_status();
        $preview     = ! empty( $credentials['client_id'] ) ? $this->auth->build_authorization_url( 'DIAGNOSTICO-STATE', false, false ) : new WP_Error( 'mpap_missing_client_id', 'Client ID ausente.' );
        $checks      = array(
            $this->check( 'Client ID', ! empty( $credentials['client_id'] ), ! empty( $credentials['client_id'] ) ? $this->auth->mask( $credentials['client_id'] ) : 'Ausente.' ),
            $this->check( 'Client Secret', ! empty( $credentials['client_secret'] ), ! empty( $credentials['client_secret'] ) ? 'Armazenado criptografado.' : 'Ausente.' ),
            $this->check( 'Redirect URI', true, mpap_oauth_redirect_uri() ),
            $this->check( 'URL OAuth sem PKCE', ! is_wp_error( $preview ), is_wp_error( $preview ) ? $preview->get_error_message() : $preview ),
            $this->check( 'Último OAuth', true, $this->format_oauth_status( $oauth ) ),
        );
        $has_error = (bool) count( array_filter( $checks, static function ( $item ) { return 'error' === $item['status']; } ) );
        $result = array(
            'ready'             => ! $has_error,
            'checks'            => $checks,
            'redirect_uri'      => mpap_oauth_redirect_uri(),
            'authorization_url' => is_wp_error( $preview ) ? '' : $preview,
            'oauth_status'      => $oauth,
            'advice'            => 'Se a tela do Mercado Livre parar em “aplicativo não está pronto”, o callback não chega ao WordPress. Nesse caso confira a Redirect URI exata, estado/permissões da aplicação e teste também o botão Conectar sem PKCE.',
        );
        MPAP_Logger::info( 'diagnostics', 'Validação local OAuth executada.', $result, array( 'event' => $has_error ? 'oauth_readiness_failed' : 'oauth_readiness_ok' ) );
        return $result;
    }

    public function public_api_probe() {
        $api_base = untrailingslashit( mpap_get_settings( 'api_base' ) );
        if ( ! mpap_validate_url_host( $api_base, mpap_allowed_api_hosts() ) ) {
            $error = new WP_Error( 'mpap_invalid_api_host', 'Host de API inválido. Use https://api.mercadolibre.com.' );
            MPAP_Logger::error( 'diagnostics', 'Teste opcional de API pública bloqueado por host inválido.', array( 'api_base' => $api_base ), array( 'event' => 'public_api_probe_invalid_host' ) );
            return $error;
        }

        $site_id = strtoupper( preg_replace( '/[^A-Z0-9_\-]/', '', (string) mpap_get_settings( 'site_id', 'MLB' ) ) );
        $site_id = $site_id ?: 'MLB';
        $url = $api_base . '/sites/' . rawurlencode( $site_id );
        $request_id = 'diag-api-' . wp_generate_uuid4();
        $headers = array(
            'Accept'     => 'application/json',
            'User-Agent' => 'MPAP/' . MPAP_VERSION . '; WordPress/' . get_bloginfo( 'version' ) . '; DiagnosticsOnly/1',
        );
        $context = array(
            'request_id' => $request_id,
            'method'     => 'GET',
            'url'        => $url,
            'path'       => '/sites/' . $site_id,
            'auth'       => 'public_optional',
            'headers'    => $headers,
        );
        MPAP_Logger::debug( 'diagnostics', 'Teste opcional de API pública iniciado.', $context, array( 'event' => 'public_api_probe_request', 'request_id' => $request_id, 'method' => 'GET', 'url' => '/sites/' . $site_id ) );

        $started = microtime( true );
        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 20,
                'redirection' => 0,
                'headers'     => $headers,
            )
        );
        $duration = (int) round( ( microtime( true ) - $started ) * 1000 );

        if ( is_wp_error( $response ) ) {
            MPAP_Logger::error( 'diagnostics', 'Falha de transporte no teste opcional de API pública.', array_merge( $context, array( 'error' => $response->get_error_message(), 'error_code' => $response->get_error_code() ) ), array( 'event' => 'public_api_probe_transport_error', 'request_id' => $request_id, 'method' => 'GET', 'url' => '/sites/' . $site_id, 'duration_ms' => $duration ) );
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $raw = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );
        $data = is_array( $data ) ? $data : array( 'raw' => $raw );
        $headers_response = mpap_remote_headers_to_array( $response );
        $policy_agent = 403 === $status && isset( $data['code'] ) && 'PA_UNAUTHORIZED_RESULT_FROM_POLICIES' === $data['code'];

        $response_context = array_merge(
            $context,
            array(
                'status'           => $status,
                'response'         => $data,
                'response_headers' => $headers_response,
            )
        );

        if ( $status >= 200 && $status < 300 ) {
            MPAP_Logger::info( 'diagnostics', 'Teste opcional de API pública concluído.', $response_context, array( 'event' => 'public_api_probe_ok', 'request_id' => $request_id, 'method' => 'GET', 'url' => '/sites/' . $site_id, 'http_status' => $status, 'duration_ms' => $duration ) );
            return array( 'ok' => true, 'warning' => false, 'message' => 'API pública respondeu.', 'result' => $data );
        }

        if ( $policy_agent ) {
            $message = 'A API foi alcançada, mas o endpoint público foi bloqueado pelo PolicyAgent. Isso não prova falha de OAuth, não impede a conexão e não será contado como erro do plugin.';
            MPAP_Logger::warning( 'diagnostics', $message, $response_context, array( 'event' => 'public_api_probe_policy_warning', 'request_id' => $request_id, 'method' => 'GET', 'url' => '/sites/' . $site_id, 'http_status' => $status, 'duration_ms' => $duration ) );
            return array( 'ok' => true, 'warning' => true, 'message' => $message, 'data' => array( 'status' => $status, 'response' => $data, 'response_headers' => $headers_response, 'request_id' => $request_id ) );
        }

        MPAP_Logger::error( 'diagnostics', 'Teste opcional de API pública falhou.', $response_context, array( 'event' => 'public_api_probe_failed', 'request_id' => $request_id, 'method' => 'GET', 'url' => '/sites/' . $site_id, 'http_status' => $status, 'duration_ms' => $duration ) );
        return new WP_Error( 'mpap_public_api_probe_failed', $data['message'] ?? $data['error'] ?? 'Teste opcional de API pública falhou.', array( 'status' => $status, 'response' => $data, 'response_headers' => $headers_response, 'request_id' => $request_id ) );
    }

    public function connection_probe() {
        $result = $this->api->get_user();
        if ( is_wp_error( $result ) ) {
            MPAP_Logger::error( 'diagnostics', 'Teste de conexão autenticada falhou.', array( 'error' => $result->get_error_message(), 'data' => $result->get_error_data() ), array( 'event' => 'auth_probe_failed' ) );
            return $result;
        }
        MPAP_Logger::info( 'diagnostics', 'Teste de conexão autenticada concluído.', array( 'user_id' => $result['id'] ?? '', 'nickname' => $result['nickname'] ?? '' ), array( 'event' => 'auth_probe_ok' ) );
        return $result;
    }

    public function ajax_run() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }
        wp_send_json_success( $this->run() );
    }

    public function ajax_test_public_api() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }
        $result = $this->public_api_probe();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'data' => $result->get_error_data() ), 400 );
        }
        wp_send_json_success( array( 'message' => ! empty( $result['warning'] ) ? 'API alcançada com aviso.' : 'API respondeu.', 'result' => $result ) );
    }

    public function ajax_test_oauth_readiness() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }
        $result = $this->oauth_readiness_probe();
        if ( empty( $result['ready'] ) ) {
            wp_send_json_error( array( 'message' => 'OAuth local ainda não está pronto.', 'result' => $result ), 400 );
        }
        wp_send_json_success( array( 'message' => 'OAuth local validado.', 'result' => $result ) );
    }

    public function ajax_test_connection() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }
        $result = $this->connection_probe();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'data' => $result->get_error_data() ), 400 );
        }
        wp_send_json_success( array( 'message' => 'Conexão autenticada respondeu.', 'result' => $result ) );
    }

    public function ajax_test_log() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }
        $id = MPAP_Logger::info( 'diagnostics', 'Log de teste gerado pelo painel.', array( 'user_id' => get_current_user_id(), 'site' => home_url() ), array( 'event' => 'manual_test_log' ) );
        wp_send_json_success( array( 'message' => 'Log de teste gravado.', 'log_id' => $id ) );
    }
}
