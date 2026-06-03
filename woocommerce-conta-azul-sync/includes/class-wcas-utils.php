<?php
/**
 * Shared utility helpers.
 *
 * @package WooCommerceContaAzulSync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Utility functions for settings, formatting and endpoint configuration.
 */
class WCAS_Utils {
	public const OPTION_SETTINGS = 'wcas_settings';
	public const OPTION_TOKENS   = 'wcas_tokens';
	public const OPTION_OAUTH_DEBUG = 'wcas_oauth_debug';
	public const NONCE_ACTION    = 'wcas_admin_action';
	public const SECRET_PREFIX   = 'wcas:v1:';

	/**
	 * New Conta Azul API defaults confirmed in public docs. Resource paths remain configurable.
	 * TODO: Validate resource paths in the Conta Azul developer portal before production use.
	 */
	public const AUTHORIZATION_URL = 'https://auth.contaazul.com/login';
	public const TOKEN_URL         = 'https://auth.contaazul.com/oauth2/token';
	public const API_BASE_URL      = 'https://api-v2.contaazul.com/v1';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'                 => 'no',
			'client_id'               => '',
			'client_secret'           => '',
			'redirect_uri'            => self::get_oauth_callback_url(),
			'environment'             => 'production',
			'api_base_url'            => self::API_BASE_URL,
			'auth_url'                => self::AUTHORIZATION_URL,
			'token_url'               => self::TOKEN_URL,
			'auto_sync_orders'        => 'yes',
			'sync_products'           => 'yes',
			'auto_create_customer'    => 'yes',
			'register_sale'           => 'yes',
			'register_receivable'     => 'no',
			'detailed_log'            => 'no',
			'customer_search_path'    => '/customers',
			'customer_create_path'    => '/customers',
			'product_search_path'     => '/products',
			'product_create_path'     => '/products',
			'sale_create_path'        => '/sales',
			'sale_cancel_path'        => '/sales/{id}/cancel',
			'receivable_create_path'  => '/financial/receivables',
		);
	}


	/**
	 * Dedicated OAuth callback URL that must be registered in Conta Azul.
	 */
	public static function get_oauth_callback_url(): string {
		return admin_url( 'admin-post.php?action=wcas_oauth_callback' );
	}

	/**
	 * Normalize legacy callbacks to the dedicated admin-post OAuth callback.
	 */
	public static function normalize_oauth_redirect_uri( string $redirect_uri ): string {
		$redirect_uri = esc_url_raw( trim( $redirect_uri ) );
		if ( '' === $redirect_uri || false !== strpos( $redirect_uri, 'admin.php?page=wcas-conta-azul' ) || false !== strpos( $redirect_uri, '/wp-json/wcas/v1/oauth/callback' ) ) {
			return self::get_oauth_callback_url();
		}
		return $redirect_uri;
	}

	/**
	 * Migrate old admin.php callback settings to the dedicated admin-post callback.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	private static function maybe_migrate_redirect_uri( array $settings ): array {
		$old_redirect = (string) ( $settings['redirect_uri'] ?? '' );
		$new_redirect = self::normalize_oauth_redirect_uri( $old_redirect );
		if ( $new_redirect !== $old_redirect ) {
			$settings['redirect_uri'] = $new_redirect;
			self::update_option_no_autoload( self::OPTION_SETTINGS, $settings );
			if ( class_exists( 'WCAS_Logger' ) ) {
				WCAS_Logger::diagnostic(
					'oauth',
					'callback_endpoint_changed',
					'success',
					'Redirect URI OAuth migrado para o callback dedicado admin-post.php?action=wcas_oauth_callback.',
					array(
						'old_redirect_uri' => $old_redirect,
						'new_redirect_uri' => $new_redirect,
						'callback_type'    => 'admin_post',
						'admin_post_action' => 'wcas_oauth_callback',
					)
				);
			}
		}
		return $settings;
	}

	/**
	 * Return all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$settings = get_option( self::OPTION_SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$settings = self::maybe_migrate_redirect_uri( $settings );
		if ( ! empty( $settings['client_secret'] ) ) {
			$settings['client_secret'] = self::decrypt_secret( (string) $settings['client_secret'] );
		}
		return wp_parse_args( $settings, self::defaults() );
	}

	/**
	 * Read one setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get_setting( string $key, mixed $default = null ): mixed {
		$settings = self::get_settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Save sanitized settings while preserving secrets when password fields are blank.
	 *
	 * @param array<string, mixed> $input Raw input.
	 */
	public static function save_settings( array $input ): void {
		$current = self::get_settings();
		$boolean = array( 'enabled', 'auto_sync_orders', 'sync_products', 'auto_create_customer', 'register_sale', 'register_receivable', 'detailed_log' );
		$clean   = self::defaults();

		foreach ( $boolean as $key ) {
			$clean[ $key ] = isset( $input[ $key ] ) ? 'yes' : 'no';
		}

		$clean['client_id']    = sanitize_text_field( $input['client_id'] ?? '' );
		$secret                = sanitize_text_field( $input['client_secret'] ?? '' );
		$clean['client_secret'] = self::encrypt_secret( '' !== $secret ? $secret : (string) $current['client_secret'] );
		$clean['redirect_uri']  = self::normalize_oauth_redirect_uri( esc_url_raw( $input['redirect_uri'] ?? $current['redirect_uri'] ) );
		$clean['environment']   = in_array( $input['environment'] ?? 'production', array( 'production', 'development' ), true ) ? $input['environment'] : 'production';
		$clean['api_base_url']  = self::sanitize_url_with_fallback( (string) ( $input['api_base_url'] ?? self::API_BASE_URL ), self::API_BASE_URL );
		$clean['auth_url']      = self::sanitize_url_with_fallback( (string) ( $input['auth_url'] ?? self::AUTHORIZATION_URL ), self::AUTHORIZATION_URL );
		$clean['token_url']     = self::sanitize_url_with_fallback( (string) ( $input['token_url'] ?? self::TOKEN_URL ), self::TOKEN_URL );

		foreach ( array( 'customer_search_path', 'customer_create_path', 'product_search_path', 'product_create_path', 'sale_create_path', 'sale_cancel_path', 'receivable_create_path' ) as $key ) {
			$clean[ $key ] = self::sanitize_path( (string) ( $input[ $key ] ?? $current[ $key ] ) );
		}

		self::update_option_no_autoload( self::OPTION_SETTINGS, $clean );
	}

	/**
	 * Update an option and force autoload=no both on first save and subsequent saves.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value Option value.
	 */
	public static function update_option_no_autoload( string $option, mixed $value ): void {
		if ( false === get_option( $option, false ) ) {
			add_option( $option, $value, '', false );
			return;
		}
		update_option( $option, $value, false );
	}

	/**
	 * Sanitize URL and fall back to a known safe default when empty/invalid.
	 */
	public static function sanitize_url_with_fallback( string $url, string $fallback ): string {
		$clean = esc_url_raw( trim( $url ) );
		return '' !== $clean ? untrailingslashit( $clean ) : $fallback;
	}

	/**
	 * Sanitize API path.
	 */
	public static function sanitize_path( string $path ): string {
		$path = sanitize_text_field( $path );
		$path = '/' . ltrim( $path, '/' );
		return untrailingslashit( $path );
	}

	/**
	 * Return yes/no as boolean.
	 */
	public static function is_enabled( string $key ): bool {
		return 'yes' === self::get_setting( $key, 'no' );
	}

	/**
	 * Best-effort document lookup from common WooCommerce Brazilian fields.
	 */
	public static function get_order_document( WC_Order $order ): string {
		$keys = array( '_billing_cpf', '_billing_cnpj', 'billing_cpf', 'billing_cnpj', '_cpf', '_cnpj' );
		foreach ( $keys as $key ) {
			$value = (string) $order->get_meta( $key, true );
			if ( '' !== $value ) {
				return preg_replace( '/\D+/', '', $value ) ?: $value;
			}
		}
		return '';
	}

	/**
	 * Mask sensitive values before logging.
	 *
	 * @param mixed $value Value to mask.
	 * @return mixed
	 */
	public static function mask_sensitive( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			$masked = array();
			foreach ( $value as $key => $item ) {
				$key_string = (string) $key;
				if ( 'authorization_url' === $key_string ) {
					$masked[ $key ] = is_scalar( $item ) ? self::redact_url_query( (string) $item ) : '';
				} elseif ( preg_match( '/client_id|state|code/i', $key_string ) ) {
					$masked[ $key ] = self::mask_identifier( is_scalar( $item ) ? (string) $item : '' );
				} elseif ( preg_match( '/access_token|refresh_token|secret|authorization|password|cpf|cnpj|document|email|phone|telefone/i', $key_string ) ) {
					$masked[ $key ] = self::mask_scalar( $item );
				} else {
					$masked[ $key ] = self::mask_sensitive( $item );
				}
			}
			return $masked;
		}
		if ( is_string( $value ) ) {
			return self::redact_sensitive_text( $value );
		}
		return $value;
	}

	/**
	 * Redact sensitive tokens/secrets from arbitrary strings such as raw HTTP bodies.
	 */
	public static function redact_sensitive_text( string $value ): string {
		$patterns = array(
			'/(access_token|refresh_token|client_secret|id_token)([\"\']?\s*[:=]\s*[\"\']?)([^\"\'&\s,}]+)/i',
			'/(Authorization\s*[:=]\s*)(Bearer|Basic)\s+[^\s,}]+/i',
		);
		$replacements = array( '$1$2***', '$1$2 ***' );
		return preg_replace( $patterns, $replacements, $value ) ?? $value;
	}

	/**
	 * Return a query-string map from a URL for diagnostics. Values are masked later.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_url_query_args( string $url ): array {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
			return array();
		}
		$query = array();
		wp_parse_str( (string) $parts['query'], $query );
		return is_array( $query ) ? $query : array();
	}


	/**
	 * Mask an identifier preserving only the first and last 4 characters.
	 */
	public static function mask_identifier( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( strlen( $value ) <= 8 ) {
			return substr( $value, 0, 1 ) . '***' . substr( $value, -1 );
		}
		return substr( $value, 0, 4 ) . '***' . substr( $value, -4 );
	}

	/**
	 * Redact sensitive OAuth query parameters from a URL for safe logs.
	 */
	public static function redact_url_query( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return '';
		}
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			wp_parse_str( $parts['query'], $query );
			foreach ( $query as $key => $value ) {
				if ( 'client_id' === $key ) {
					$query[ $key ] = self::mask_identifier( (string) $value );
				} elseif ( in_array( $key, array( 'state', 'code', 'client_secret', 'access_token', 'refresh_token' ), true ) ) {
					$query[ $key ] = '***';
				}
			}
		}

		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
		$host   = $parts['host'] ?? '';
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path   = $parts['path'] ?? '';
		return $scheme . $host . $port . $path . ( empty( $query ) ? '' : '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) );
	}

	/**
	 * Mask one scalar value.
	 *
	 * @param mixed $value Value to mask.
	 * @return mixed
	 */
	private static function mask_scalar( mixed $value ): mixed {
		return is_scalar( $value ) && '' !== (string) $value ? '***' : $value;
	}

	/**
	 * Encrypt a secret using WordPress salts when OpenSSL is available.
	 */
	public static function encrypt_secret( string $secret ): string {
		if ( '' === $secret || str_starts_with( $secret, self::SECRET_PREFIX ) || ! function_exists( 'openssl_encrypt' ) ) {
			return $secret;
		}
		try {
			$iv = random_bytes( 16 );
		} catch ( Exception $exception ) {
			return $secret;
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$encrypted = openssl_encrypt( $secret, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $encrypted ) {
			return $secret;
		}
		return self::SECRET_PREFIX . base64_encode( $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored secret. Plain values are returned for backward compatibility.
	 */
	public static function decrypt_secret( string $secret ): string {
		if ( '' === $secret || ! str_starts_with( $secret, self::SECRET_PREFIX ) || ! function_exists( 'openssl_decrypt' ) ) {
			return $secret;
		}
		$payload = base64_decode( substr( $secret, strlen( self::SECRET_PREFIX ) ), true );
		if ( false === $payload || strlen( $payload ) <= 16 ) {
			return '';
		}
		$iv = substr( $payload, 0, 16 );
		$ciphertext = substr( $payload, 16 );
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$decrypted = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return false === $decrypted ? '' : $decrypted;
	}


	/**
	 * Merge and persist OAuth debug information for the admin dashboard.
	 *
	 * @param array<string, mixed> $data Debug data.
	 */
	public static function update_oauth_debug( array $data ): void {
		$current = get_option( self::OPTION_OAUTH_DEBUG, array() );
		$current = is_array( $current ) ? $current : array();
		self::update_option_no_autoload( self::OPTION_OAUTH_DEBUG, array_merge( $current, self::mask_sensitive( $data ) ) );
	}

	/**
	 * Read OAuth debug information.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_oauth_debug(): array {
		$debug = get_option( self::OPTION_OAUTH_DEBUG, array() );
		return is_array( $debug ) ? $debug : array();
	}

	/**
	 * Create a compact JSON summary for logs/debug cards.
	 *
	 * @param mixed $value Value to summarize.
	 */
	public static function summarize( mixed $value, int $max_length = 500 ): string {
		$value = self::mask_sensitive( $value );
		$summary = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
		$summary = wp_strip_all_tags( $summary );
		return wp_html_excerpt( $summary, $max_length, '…' );
	}

	/**
	 * Decode JSON safely.
	 *
	 * @return array<string, mixed>
	 */
	public static function decode_json( string $body ): array {
		$decoded = json_decode( $body, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
