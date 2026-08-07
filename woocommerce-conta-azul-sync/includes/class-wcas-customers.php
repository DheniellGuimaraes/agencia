<?php
/**
 * Customer synchronization.
 *
 * @package WooCommerceContaAzulSync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Customer sync service.
 */
class WCAS_Customers {
	private WCAS_API_Client $client;

	public function __construct( WCAS_API_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Ensure order customer exists in Conta Azul and return ID.
	 */
	public function sync_from_order( WC_Order $order ): string|WP_Error {
		$existing = (string) $order->get_meta( '_wcas_customer_id', true );
		if ( '' !== $existing ) {
			return $existing;
		}

		$user_id = $order->get_user_id();
		if ( $user_id > 0 ) {
			$user_customer_id = (string) get_user_meta( $user_id, '_wcas_customer_id', true );
			if ( '' !== $user_customer_id ) {
				$order->update_meta_data( '_wcas_customer_id', $user_customer_id );
				$order->save();
				return $user_customer_id;
			}
		}

		$document = WCAS_Utils::get_order_document( $order );
		$email    = $order->get_billing_email();
		$found    = $this->find_customer( $document, $email );
		if ( is_wp_error( $found ) ) {
			return $found;
		}
		$customer_id = $found;

		if ( '' === $customer_id ) {
			if ( ! WCAS_Utils::is_enabled( 'auto_create_customer' ) ) {
				return new WP_Error( 'wcas_customer_missing', __( 'Cliente não encontrado na Conta Azul e criação automática desativada.', 'woocommerce-conta-azul-sync' ) );
			}
			$created = $this->create_customer( $order );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$customer_id = $created;
			WCAS_Logger::log( 'customer_created', 'Cliente criado na Conta Azul.', $order->get_id(), array( 'customer_id' => $customer_id ) );
		}

		$order->update_meta_data( '_wcas_customer_id', $customer_id );
		$order->save();
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, '_wcas_customer_id', $customer_id );
		}
		return $customer_id;
	}

	/**
	 * Find customer by document or email.
	 */
	private function find_customer( string $document, string $email ): string|WP_Error {
		$query = array_filter( array( 'document' => $document, 'email' => $email ) );
		if ( empty( $query ) ) {
			return '';
		}

		$response = $this->client->request( 'GET', (string) WCAS_Utils::get_setting( 'customer_search_path' ), array(), $query );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response[0] ) && is_array( $response[0] ) ) {
			return $this->client->extract_id( $response[0] );
		}
		if ( isset( $response['items'][0] ) && is_array( $response['items'][0] ) ) {
			return $this->client->extract_id( $response['items'][0] );
		}
		return $this->client->extract_id( $response );
	}

	/**
	 * Create customer.
	 */
	private function create_customer( WC_Order $order ): string|WP_Error {
		$payload = array(
			'name'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ?: $order->get_formatted_billing_full_name(),
			'email'    => $order->get_billing_email(),
			'phone'    => $order->get_billing_phone(),
			'document' => WCAS_Utils::get_order_document( $order ),
			'address'  => array(
				'line1'      => $order->get_billing_address_1(),
				'line2'      => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postalCode' => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
			),
			'external_id' => 'wc-customer-' . $order->get_customer_id(),
		);

		$response = $this->client->request( 'POST', (string) WCAS_Utils::get_setting( 'customer_create_path' ), $payload );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$id = $this->client->extract_id( $response );
		return '' !== $id ? $id : new WP_Error( 'wcas_customer_id_missing', __( 'ID do cliente não retornado pela Conta Azul.', 'woocommerce-conta-azul-sync' ), $response );
	}
}
