<?php
/**
 * OAuth2 authentication with Conta Azul.
 *
 * @package WooCommerceContaAzulSync
 */

defined( 'ABSPATH' ) || exit;

/**
 * OAuth2 flow manager.
 */
class WCAS_Auth {
	private const STATE_TRANSIENT_PREFIX = 'wcas_oauth_state_';
	private const TOKEN_REFRESH_MARGIN   = 300;

	/**
	 * Build authorization URL.
	 */
	public function get_authorization_url(): string {
		$state = wp_generate_password( 32, false, false );
		set_transient( self::STATE_TRANSIENT_PREFIX . $state, get_current_user_id(), 10 * MINUTE_IN_SECONDS );
		$redirect_uri = WCAS_Utils::normalize_oauth_redirect_uri( (string) WCAS_Utils::get_setting( 'redirect_uri' ) );

		$args = array(
			'response_type' => 'code',
			'client_id'     => WCAS_Utils::get_setting( 'client_id' ),
			'redirect_uri'  => $redirect_uri,
			'state'         => $state,
			'scope'         => 'openid profile aws.cognito.signin.user.admin',
		);

		$url = add_query_arg( $args, (string) WCAS_Utils::get_setting( 'auth_url', WCAS_Utils::AUTHORIZATION_URL ) );
		WCAS_Logger::oauth_trace(
			'generate_authorization_url',
			'sent',
			'success',
			'URL de autorização OAuth2 gerada e state temporário criado.',
			array(
				'timestamp'           => current_time( 'mysql' ),
				'url_complete'        => $url,
				'authorization_url'   => WCAS_Utils::redact_url_query( $url ),
				'query_string_sent'   => $args,
				'client_id'           => (string) WCAS_Utils::get_setting( 'client_id' ),
				'redirect_uri'        => $redirect_uri,
				'response_type'       => $args['response_type'],
				'state'               => $state,
				'scope'               => $args['scope'],
				'state_created'       => true,
				'http_method'         => 'GET',
				'headers_sent_to_ca'  => array( 'Client-Secret' => 'not_sent', 'Authorization' => 'not_sent' ),
			)
		);

		return $url;
	}


	/**
	 * Dedicated admin-post OAuth callback endpoint.
	 */
	public function handle_admin_post_callback(): void {
		$this->process_oauth_callback( 'admin_post' );
	}

	/**
	 * Legacy callback support for older installations that still have admin.php configured.
	 */
	public function maybe_handle_callback(): void {
		if ( ! is_admin() || ! isset( $_GET['page'] ) || 'wcas-conta-azul' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		$has_code  = isset( $_GET['code'] );
		$has_state = isset( $_GET['state'] );
		$has_error = isset( $_GET['error'] );
		if ( ! $has_code && ! $has_state && ! $has_error ) {
			return;
		}

		WCAS_Logger::oauth_trace( 'legacy_callback_received', 'received', 'warning', 'Callback OAuth recebido pelo endpoint legado admin.php?page=wcas-conta-azul; processe e atualize a Redirect URI para admin-post.php?action=wcas_oauth_callback.' );
		$this->process_oauth_callback( 'legacy_admin_page' );
	}

	/**
	 * Shared OAuth callback processor used by the dedicated endpoint and legacy compatibility path.
	 */
	private function process_oauth_callback( string $callback_type ): void {
		$has_code  = isset( $_GET['code'] );
		$has_state = isset( $_GET['state'] );
		$has_error = isset( $_GET['error'] );

		$received_code  = $has_code ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$received_state = $has_state ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$received_error = $has_error ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		$error_description = isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : '';

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		$get_params  = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_params = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_body = file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw_body = is_string( $raw_body ) ? $raw_body : '';

		WCAS_Utils::update_oauth_debug(
			array(
				'last_callback_at'       => current_time( 'mysql' ),
				'last_callback_type'     => $callback_type,
				'last_request_uri'       => $request_uri,
				'last_state_received'    => WCAS_Utils::mask_identifier( $received_state ),
				'last_code_received'     => WCAS_Utils::mask_identifier( $received_code ),
				'last_error_received'    => $received_error,
				'last_error_description' => $error_description,
			)
		);

		WCAS_Logger::oauth_trace(
			'callback_received',
			$has_error ? 'error' : 'received',
			'started',
			'Callback OAuth2 recebido com REQUEST_URI, GET, POST e body capturados imediatamente.',
			array(
				'timestamp'             => current_time( 'mysql' ),
				'callback_type'         => $callback_type,
				'callback_triggered'    => $callback_type,
				'return_url'            => $request_uri,
				'url_return'            => $request_uri,
				'http_method'           => $request_method,
				'http_status'           => null,
				'query_string_received' => is_array( $get_params ) ? $get_params : array(),
				'get'                   => is_array( $get_params ) ? $get_params : array(),
				'post'                  => is_array( $post_params ) ? $post_params : array(),
				'body_complete'         => $raw_body,
				'code_present'          => $has_code,
				'state_present'         => $has_state,
				'error_present'         => $has_error,
				'code'                  => $received_code,
				'state'                 => $received_state,
				'error'                 => $received_error,
				'error_description'     => $error_description,
				'nonce_validation'      => 'not_applicable_oauth_state_used',
			)
		);

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			WCAS_Logger::oauth_trace( 'callback_capability', 'error', 'error', 'Callback OAuth2 bloqueado por falta de capability manage_woocommerce.', array( 'callback_type' => $callback_type, 'capability_validation' => 'failed', 'nonce_validation' => 'not_applicable_oauth_state_used' ) );
			wp_die( esc_html__( 'Você não tem permissão para conectar a Conta Azul.', 'woocommerce-conta-azul-sync' ) );
		}
		WCAS_Logger::oauth_trace( 'callback_capability', 'received', 'success', 'Capability manage_woocommerce validada no callback OAuth2.', array( 'callback_type' => $callback_type, 'capability_validation' => 'passed', 'nonce_validation' => 'not_applicable_oauth_state_used' ) );

		if ( $has_error ) {
			WCAS_Logger::oauth_trace(
				'callback_error',
				'error',
				'error',
				'OAuth callback retornou erro da Conta Azul.',
				array(
					'callback_type'     => $callback_type,
					'error'             => $received_error,
					'error_description' => $error_description,
				)
			);
			wp_safe_redirect( add_query_arg( array( 'page' => 'wcas-conta-azul', 'wcas_message' => 'auth_failed', 'tab' => 'logs' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( ! $has_code || ! $has_state ) {
			WCAS_Logger::oauth_trace( 'callback_missing_parameters', 'error', 'error', 'Callback OAuth2 sem code ou state obrigatório.', array( 'callback_type' => $callback_type, 'code_present' => $has_code, 'state_present' => $has_state, 'required_parameters' => array( 'code', 'state' ) ) );
			wp_safe_redirect( add_query_arg( array( 'page' => 'wcas-conta-azul', 'wcas_message' => 'auth_failed', 'tab' => 'logs' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		WCAS_Logger::oauth_trace(
			'authorization_code_received',
			'received',
			'success',
			'Authorization code recebido no callback OAuth2.',
			array(
				'callback_type' => $callback_type,
				'code'          => $received_code,
				'code_present'  => true,
				'state_present' => true,
			)
		);

		$state_user_id = get_transient( self::STATE_TRANSIENT_PREFIX . $received_state );
		$state_valid   = false !== $state_user_id && (int) $state_user_id === get_current_user_id();
		WCAS_Logger::oauth_trace(
			'validate_state',
			$state_valid ? 'received' : 'error',
			$state_valid ? 'success' : 'error',
			$state_valid ? 'State OAuth2 validado com sucesso.' : 'OAuth callback rejeitado por state inválido ou usuário divergente.',
			array(
				'callback_type'         => $callback_type,
				'state_received'        => $received_state,
				'state_stored'          => false !== $state_user_id ? 'exists' : 'missing',
				'state_stored_user_id'  => false !== $state_user_id ? (int) $state_user_id : null,
				'current_user_id'       => get_current_user_id(),
				'validation_result'     => $state_valid ? 'valid' : 'invalid',
			)
		);
		WCAS_Utils::update_oauth_debug(
			array(
				'last_state_validation' => $state_valid ? 'valid' : 'invalid',
				'last_state_stored'     => false !== $state_user_id ? 'exists' : 'missing',
			)
		);

		if ( ! $state_valid ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'wcas-conta-azul', 'wcas_message' => 'invalid_state', 'tab' => 'logs' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		delete_transient( self::STATE_TRANSIENT_PREFIX . $received_state );

		WCAS_Logger::oauth_trace( 'token_exchange_started', 'token', 'started', 'Iniciando troca do authorization code por tokens OAuth2.', array( 'callback_type' => $callback_type, 'token_url' => (string) WCAS_Utils::get_setting( 'token_url', WCAS_Utils::TOKEN_URL ), 'method' => 'POST', 'http_method' => 'POST', 'code' => $received_code, 'redirect_uri' => WCAS_Utils::get_setting( 'redirect_uri' ) ) );
		$result = $this->exchange_code( $received_code );
		$message = is_wp_error( $result ) ? 'auth_failed' : 'auth_success';
		wp_safe_redirect( add_query_arg( array( 'page' => 'wcas-conta-azul', 'wcas_message' => $message, 'tab' => 'logs' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function exchange_code( string $code ): array|WP_Error {
		return $this->token_request(
			array(
				'code'         => $code,
				'grant_type'   => 'authorization_code',
				'redirect_uri' => WCAS_Utils::get_setting( 'redirect_uri' ),
			)
		);
	}

	/**
	 * Return valid access token, refreshing when needed.
	 */
	public function get_access_token(): string|WP_Error {
		$tokens = $this->get_tokens();
		if ( empty( $tokens['access_token'] ) || empty( $tokens['refresh_token'] ) ) {
			return new WP_Error( 'wcas_not_connected', __( 'Conta Azul não conectada.', 'woocommerce-conta-azul-sync' ) );
		}
		if ( time() + self::TOKEN_REFRESH_MARGIN >= (int) ( $tokens['expires_at'] ?? 0 ) ) {
			$refreshed = $this->refresh_token();
			if ( is_wp_error( $refreshed ) ) {
				return $refreshed;
			}
			$tokens = $this->get_tokens();
		}
		return (string) $tokens['access_token'];
	}

	/**
	 * Refresh OAuth token.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function refresh_token(): array|WP_Error {
		$tokens = $this->get_tokens();
		if ( empty( $tokens['refresh_token'] ) ) {
			return new WP_Error( 'wcas_missing_refresh_token', __( 'Refresh token ausente.', 'woocommerce-conta-azul-sync' ) );
		}
		return $this->token_request(
			array(
				'refresh_token' => $tokens['refresh_token'],
				'grant_type'    => 'refresh_token',
			)
		);
	}

	/**
	 * Persist tokens securely enough for WordPress options and never autoload them.
	 *
	 * @param array<string, mixed> $tokens Token payload.
	 */
	private function save_tokens( array $tokens ): void {
		$data = array(
			'access_token'  => WCAS_Utils::encrypt_secret( sanitize_text_field( $tokens['access_token'] ?? '' ) ),
			'refresh_token' => WCAS_Utils::encrypt_secret( sanitize_text_field( $tokens['refresh_token'] ?? '' ) ),
			'token_type'    => sanitize_text_field( $tokens['token_type'] ?? 'Bearer' ),
			'expires_at'    => time() + max( 60, (int) ( $tokens['expires_in'] ?? 3600 ) ),
			'connected_at'  => current_time( 'mysql' ),
		);
		WCAS_Utils::update_option_no_autoload( WCAS_Utils::OPTION_TOKENS, $data );
	}

	/**
	 * Get saved tokens.
	 *
	 * @return array<string, mixed>
	 */
	public function get_tokens(): array {
		$tokens = get_option( WCAS_Utils::OPTION_TOKENS, array() );
		if ( ! is_array( $tokens ) ) {
			return array();
		}
		foreach ( array( 'access_token', 'refresh_token' ) as $key ) {
			if ( ! empty( $tokens[ $key ] ) ) {
				$tokens[ $key ] = WCAS_Utils::decrypt_secret( (string) $tokens[ $key ] );
			}
		}
		return $tokens;
	}

	/**
	 * Remove tokens.
	 */
	public function disconnect(): void {
		delete_option( WCAS_Utils::OPTION_TOKENS );
		WCAS_Logger::log( 'auth', 'Conta Azul desconectada manualmente.' );
	}

	/**
	 * Is connected?
	 */
	public function is_connected(): bool {
		$tokens = $this->get_tokens();
		return ! empty( $tokens['access_token'] ) && ! empty( $tokens['refresh_token'] );
	}

	/**
	 * Execute token endpoint request.
	 *
	 * @param array<string, mixed> $body Form data.
	 * @return array<string, mixed>|WP_Error
	 */
	private function token_request( array $body ): array|WP_Error {
		$client_id     = (string) WCAS_Utils::get_setting( 'client_id' );
		$client_secret = (string) WCAS_Utils::get_setting( 'client_secret' );
		$grant_type    = (string) ( $body['grant_type'] ?? '' );
		$token_url     = (string) WCAS_Utils::get_setting( 'token_url', WCAS_Utils::TOKEN_URL );
		$action_prefix = 'authorization_code' === $grant_type ? 'token_exchange' : 'token_refresh';

		if ( '' === $client_id || '' === $client_secret ) {
			WCAS_Logger::oauth_trace( $action_prefix . '_failed', 'error', 'error', 'Client ID ou Client Secret ausente antes da requisição ao token endpoint.', array( 'grant_type' => $grant_type ) );
			return new WP_Error( 'wcas_missing_credentials', __( 'Client ID e Client Secret são obrigatórios.', 'woocommerce-conta-azul-sync' ) );
		}

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'Content-Type'  => 'application/x-www-form-urlencoded',
		);

		$request_context = array(
			'endpoint' => $token_url,
			'method'   => 'POST',
			'headers'  => $headers,
			'body'     => $body,
		);

		WCAS_Logger::oauth_trace(
			$action_prefix . '_started',
			'token',
			'started',
			'Iniciando requisição ao token endpoint da Conta Azul.',
			$request_context
		);
		WCAS_Utils::update_oauth_debug(
			array(
				'last_token_endpoint' => $token_url,
				'last_token_method'   => 'POST',
			)
		);

		$response = wp_remote_post(
			$token_url,
			array(
				'timeout' => 20,
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			WCAS_Utils::update_oauth_debug(
				array(
					'last_token_http_status' => 'wp_error',
					'last_token_response'    => $response->get_error_message(),
				)
			);
			WCAS_Logger::oauth_trace(
				$action_prefix . '_failed',
				'error',
				'error',
				'WP_Error ao chamar token endpoint: ' . $response->get_error_message(),
				array_merge( $request_context, array( 'wp_error' => $response->get_error_message() ) )
			);
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$data   = WCAS_Utils::decode_json( $raw_body );
		$response_summary = ! empty( $data ) ? WCAS_Utils::summarize( $data ) : WCAS_Utils::summarize( $raw_body );
		WCAS_Utils::update_oauth_debug(
			array(
				'last_token_http_status' => $status,
				'last_token_response'    => $response_summary,
			)
		);
		WCAS_Logger::oauth_trace(
			'token_endpoint_response',
			$status >= 200 && $status < 300 ? 'token' : 'error',
			$status >= 200 && $status < 300 ? 'success' : 'error',
			'Resposta recebida do token endpoint da Conta Azul.',
			array(
				'endpoint'               => $token_url,
				'method'                 => 'POST',
				'http_method'            => 'POST',
				'grant_type'             => $grant_type,
				'body_summary'           => $response_summary,
				'body_complete'          => $raw_body,
				'headers_sent'           => $headers,
				'token_endpoint_status'  => $status,
			),
			$status
		);

		if ( $status < 200 || $status >= 300 || empty( $data['access_token'] ) || empty( $data['refresh_token'] ) ) {
			WCAS_Logger::oauth_trace(
				$action_prefix . '_failed',
				'error',
				'error',
				'Falha ao obter token OAuth2 da Conta Azul.',
				array(
					'grant_type'    => $grant_type,
					'response'      => $data,
					'body_summary'  => $response_summary,
					'body_complete' => $raw_body,
				),
				$status
			);
			return new WP_Error( 'wcas_token_error', __( 'Falha ao obter token OAuth2 da Conta Azul.', 'woocommerce-conta-azul-sync' ), $data );
		}

		$this->save_tokens( $data );
		WCAS_Logger::oauth_trace( $action_prefix . '_success', 'token', 'success', 'Token OAuth2 recebido com sucesso da Conta Azul.', array( 'grant_type' => $grant_type, 'expires_in' => (int) ( $data['expires_in'] ?? 0 ) ), $status );
		WCAS_Logger::oauth_trace( 'oauth_connected', 'token', 'success', 'access_token e refresh_token persistidos com segurança.', array( 'expires_in' => (int) ( $data['expires_in'] ?? 0 ), 'token_type' => $data['token_type'] ?? 'Bearer' ), $status );
		return $data;
	}

}
