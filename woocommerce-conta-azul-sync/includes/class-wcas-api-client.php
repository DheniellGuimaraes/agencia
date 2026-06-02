<?php
/**
 * Conta Azul API client abstraction.
 *
 * @package WooCommerceContaAzulSync
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST API client.
 */
class WCAS_API_Client {
	/**
	 * Auth manager.
	 */
	private WCAS_Auth $auth;

	/**
	 * Constructor.
	 */
	public function __construct( WCAS_Auth $auth ) {
		$this->auth = $auth;
	}

	/**
	 * Request Conta Azul API.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path API path, configurable because final endpoints may change.
	 * @param array<string, mixed> $data Request data.
	 * @param array<string, mixed> $query Query args.
	 * @return array<string, mixed>|WP_Error
	 */
	public function request( string $method, string $path, array $data = array(), array $query = array(), bool $allow_retry = true ): array|WP_Error {
		$token = $this->auth->get_access_token();
		if ( is_wp_error( $token ) ) {
			WCAS_Logger::log( 'auth_error', $token->get_error_message() );
			return $token;
		}

		$url = trailingslashit( (string) WCAS_Utils::get_setting( 'api_base_url', WCAS_Utils::API_BASE_URL ) ) . ltrim( $path, '/' );
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( ! empty( $data ) && in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		if ( WCAS_Utils::is_enabled( 'detailed_log' ) ) {
			WCAS_Logger::log( 'request', 'Requisição enviada à Conta Azul.', null, array( 'method' => $method, 'path' => $path, 'query' => $query, 'body' => $data ) );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			WCAS_Logger::log( 'http_error', $response->get_error_message(), null, array( 'method' => $method, 'path' => $path ) );
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$parsed = WCAS_Utils::decode_json( $body );

		if ( WCAS_Utils::is_enabled( 'detailed_log' ) ) {
			WCAS_Logger::log( 'response', 'Resposta recebida da Conta Azul.', null, array( 'status' => $status, 'body' => $parsed ) );
		}

		if ( 401 === $status && $allow_retry ) {
			$refreshed = $this->auth->refresh_token();
			if ( ! is_wp_error( $refreshed ) ) {
				return $this->request( $method, $path, $data, $query, false );
			}
			return $refreshed;
		}

		if ( $status < 200 || $status >= 300 ) {
			WCAS_Logger::log( 'http_error', 'Erro HTTP retornado pela Conta Azul.', null, array( 'status' => $status, 'response' => $parsed ) );
			return new WP_Error( 'wcas_api_error', sprintf( 'Conta Azul HTTP %d', $status ), $parsed );
		}

		return ! empty( $parsed ) ? $parsed : array( 'raw_body' => $body, 'status' => $status );
	}

	/**
	 * Extract an ID from a Conta Azul response using common conventions.
	 *
	 * @param array<string, mixed> $response API response.
	 */
	public function extract_id( array $response ): string {
		foreach ( array( 'id', 'uuid', 'external_id' ) as $key ) {
			if ( ! empty( $response[ $key ] ) ) {
				return (string) $response[ $key ];
			}
		}
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			return $this->extract_id( $response['data'] );
		}
		return '';
	}
}
