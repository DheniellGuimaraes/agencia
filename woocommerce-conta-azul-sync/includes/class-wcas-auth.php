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

		return add_query_arg( $args, (string) WCAS_Utils::get_setting( 'auth_url', WCAS_Utils::AUTHORIZATION_URL ) );
	}

	/**
	 * Process OAuth callback request.
	 */
	public function maybe_handle_callback(): void {
		if ( ! is_admin() || ! isset( $_GET['page'] ) || 'wcas-conta-azul' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para conectar a Conta Azul.', 'woocommerce-conta-azul-sync' ) );
		}
		if ( isset( $_GET['error'] ) ) {
			WCAS_Logger::log( 'auth_error', 'OAuth callback retornou erro da Conta Azul.', null, array( 'error' => sanitize_text_field( wp_unslash( $_GET['error'] ) ), 'description' => sanitize_text_field( wp_unslash( $_GET['error_description'] ?? '' ) ) ) );
			wp_safe_redirect( add_query_arg( array( 'page' => 'wcas-conta-azul', 'wcas_message' => 'auth_failed' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( ! isset( $_GET['code'], $_GET['state'] ) ) {
			return;
		}

		$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
		$state_user_id = get_transient( self::STATE_TRANSIENT_PREFIX . $state );
		if ( false === $state_user_id || (int) $state_user_id !== get_current_user_id() ) {
			WCAS_Logger::log( 'auth_error', 'OAuth callback rejeitado por state inválido.' );
			wp_safe_redirect( add_query_arg( array( 'page' => 'wcas-conta-azul', 'wcas_message' => 'invalid_state' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		delete_transient( self::STATE_TRANSIENT_PREFIX . $state );

		$result = $this->exchange_code( sanitize_text_field( wp_unslash( $_GET['code'] ) ) );
		$message = is_wp_error( $result ) ? 'auth_failed' : 'auth_success';
		wp_safe_redirect( add_query_arg( array( 'page' => 'wcas-conta-azul', 'wcas_message' => $message ), admin_url( 'admin.php' ) ) );
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
		if ( '' === $client_id || '' === $client_secret ) {
			return new WP_Error( 'wcas_missing_credentials', __( 'Client ID e Client Secret são obrigatórios.', 'woocommerce-conta-azul-sync' ) );
		}

		$response = wp_remote_post(
			(string) WCAS_Utils::get_setting( 'token_url', WCAS_Utils::TOKEN_URL ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			WCAS_Logger::log( 'auth_error', $response->get_error_message(), null, array( 'body' => $body ) );
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = WCAS_Utils::decode_json( wp_remote_retrieve_body( $response ) );
		if ( $status < 200 || $status >= 300 || empty( $data['access_token'] ) || empty( $data['refresh_token'] ) ) {
			WCAS_Logger::log( 'auth_error', 'Falha ao obter token OAuth2.', null, array( 'status' => $status, 'response' => $data ) );
			return new WP_Error( 'wcas_token_error', __( 'Falha ao obter token OAuth2 da Conta Azul.', 'woocommerce-conta-azul-sync' ), $data );
		}

		$this->save_tokens( $data );
		WCAS_Logger::log( 'auth', 'Token OAuth2 Conta Azul atualizado.' );
		return $data;
	}
}
