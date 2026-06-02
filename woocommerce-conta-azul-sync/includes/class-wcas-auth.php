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

		$args = array(
			'response_type' => 'code',
			'client_id'     => WCAS_Utils::get_setting( 'client_id' ),
			'redirect_uri'  => WCAS_Utils::get_setting( 'redirect_uri' ),
			'state'         => $state,
			'scope'         => 'openid profile aws.cognito.signin.user.admin',
		);

		$url = add_query_arg( $args, (string) WCAS_Utils::get_setting( 'auth_url', WCAS_Utils::AUTHORIZATION_URL ) );
		WCAS_Logger::diagnostic(
			'oauth',
			'generate_authorization_url',
			'success',
			'URL de autorização OAuth2 gerada e state temporário criado.',
			array(
				'client_id'         => (string) WCAS_Utils::get_setting( 'client_id' ),
				'redirect_uri'      => (string) WCAS_Utils::get_setting( 'redirect_uri' ),
				'authorization_url' => WCAS_Utils::redact_url_query( $url ),
				'state_created'     => true,
				'scope'             => $args['scope'],
			)
		);

		return $url;
	}

	/**
	 * Process OAuth callback request.
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

		$received_code  = $has_code ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$received_state = $has_state ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$received_error = $has_error ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		$error_description = isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : '';

		WCAS_Utils::update_oauth_debug(
			array(
				'last_callback_at'       => current_time( 'mysql' ),
				'last_state_received'    => WCAS_Utils::mask_identifier( $received_state ),
				'last_code_received'     => WCAS_Utils::mask_identifier( $received_code ),
				'last_error_received'    => $received_error,
				'last_error_description' => $error_description,
			)
		);

		WCAS_Logger::diagnostic(
			'oauth',
			'callback_received',
			'started',
			'Callback OAuth2 recebido com parâmetros de query.',
			array(
				'code_present'  => $has_code,
				'state_present' => $has_state,
				'error_present' => $has_error,
				'code'          => $received_code,
				'state'         => $received_state,
				'error'         => $received_error,
			)
		);

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			WCAS_Logger::diagnostic( 'oauth', 'callback_capability', 'error', 'Callback OAuth2 bloqueado por falta de capability manage_woocommerce.' );
			wp_die( esc_html__( 'Você não tem permissão para conectar a Conta Azul.', 'woocommerce-conta-azul-sync' ) );
		}

		if ( $has_error ) {
			WCAS_Logger::diagnostic(
				'oauth',
				'callback_error',
				'error',
				'OAuth callback retornou erro da Conta Azul.',
				array(
					'error'             => $received_error,
					'error_description' => $error_description,
				)
			);
			wp_safe_redirect( add_query_arg( array( 'page' => 'wcas-conta-azul', 'wcas_message' => 'auth_failed', 'tab' => 'logs' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( ! $has_code || ! $has_state ) {
			WCAS_Logger::diagnostic( 'oauth', 'callback_missing_parameters', 'error', 'Callback OAuth2 sem code ou state obrigatório.', array( 'code_present' => $has_code, 'state_present' => $has_state ) );
			wp_safe_redirect( add_query_arg( array( 'page' => 'wcas-conta-azul', 'wcas_message' => 'auth_failed', 'tab' => 'logs' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		WCAS_Logger::diagnostic(
			'oauth',
			'authorization_code_received',
			'success',
			'Authorization code recebido no callback OAuth2.',
			array(
				'code'          => $received_code,
				'code_present'  => true,
				'state_present' => true,
			)
		);

		$state_user_id = get_transient( self::STATE_TRANSIENT_PREFIX . $received_state );
		$state_valid   = false !== $state_user_id && (int) $state_user_id === get_current_user_id();
		WCAS_Logger::diagnostic(
			'oauth',
			'validate_state',
			$state_valid ? 'success' : 'error',
			$state_valid ? 'State OAuth2 validado com sucesso.' : 'OAuth callback rejeitado por state inválido ou usuário divergente.',
			array(
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

		WCAS_Logger::diagnostic( 'oauth', 'token_exchange_started', 'started', 'Iniciando troca do authorization code por tokens OAuth2.', array( 'token_url' => (string) WCAS_Utils::get_setting( 'token_url', WCAS_Utils::TOKEN_URL ), 'method' => 'POST', 'code' => $received_code ) );
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
			WCAS_Logger::diagnostic( 'oauth', $action_prefix . '_failed', 'error', 'Client ID ou Client Secret ausente antes da requisição ao token endpoint.', array( 'grant_type' => $grant_type ) );
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

		WCAS_Logger::diagnostic(
			'oauth',
			$action_prefix . '_started',
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
			WCAS_Logger::diagnostic(
				'oauth',
				$action_prefix . '_failed',
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
		WCAS_Logger::diagnostic(
			'oauth',
			'token_endpoint_response',
			$status >= 200 && $status < 300 ? 'success' : 'error',
			'Resposta recebida do token endpoint da Conta Azul.',
			array(
				'endpoint'     => $token_url,
				'method'       => 'POST',
				'grant_type'   => $grant_type,
				'body_summary' => $response_summary,
			),
			$status
		);

		if ( $status < 200 || $status >= 300 || empty( $data['access_token'] ) || empty( $data['refresh_token'] ) ) {
			WCAS_Logger::diagnostic(
				'oauth',
				$action_prefix . '_failed',
				'error',
				'Falha ao obter token OAuth2 da Conta Azul.',
				array(
					'grant_type'   => $grant_type,
					'response'     => $data,
					'body_summary' => $response_summary,
				),
				$status
			);
			return new WP_Error( 'wcas_token_error', __( 'Falha ao obter token OAuth2 da Conta Azul.', 'woocommerce-conta-azul-sync' ), $data );
		}

		$this->save_tokens( $data );
		WCAS_Logger::diagnostic( 'oauth', $action_prefix . '_success', 'success', 'Token OAuth2 recebido com sucesso da Conta Azul.', array( 'grant_type' => $grant_type, 'expires_in' => (int) ( $data['expires_in'] ?? 0 ) ), $status );
		WCAS_Logger::diagnostic( 'oauth', 'oauth_connected', 'success', 'access_token e refresh_token persistidos com segurança.', array( 'expires_in' => (int) ( $data['expires_in'] ?? 0 ), 'token_type' => $data['token_type'] ?? 'Bearer' ), $status );
		return $data;
	}

}
