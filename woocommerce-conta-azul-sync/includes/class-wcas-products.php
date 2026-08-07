<?php
/**
 * Product synchronization.
 *
 * @package WooCommerceContaAzulSync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product sync service.
 */
class WCAS_Products {
	private WCAS_API_Client $client;

	public function __construct( WCAS_API_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Ensure order line product exists in Conta Azul.
	 */
	public function sync_order_item( WC_Order_Item_Product $item ): string|WP_Error {
		$product = $item->get_product();
		if ( ! $product ) {
			return new WP_Error( 'wcas_missing_product', __( 'Produto WooCommerce não encontrado.', 'woocommerce-conta-azul-sync' ) );
		}

		$product_id = $product->get_id();
		$remote_id  = (string) get_post_meta( $product_id, '_wcas_product_id', true );
		if ( '' !== $remote_id ) {
			return $remote_id;
		}

		$remote_id = $this->find_product( $product );
		if ( is_wp_error( $remote_id ) ) {
			return $remote_id;
		}
		if ( '' === $remote_id ) {
			$remote_id = $this->create_product( $product );
			if ( is_wp_error( $remote_id ) ) {
				return $remote_id;
			}
			WCAS_Logger::log( 'product_created', 'Produto criado na Conta Azul.', null, array( 'product_id' => $product_id, 'conta_azul_id' => $remote_id ) );
		}

		update_post_meta( $product_id, '_wcas_product_id', $remote_id );
		return $remote_id;
	}

	/**
	 * Find product by SKU or external ID.
	 */
	private function find_product( WC_Product $product ): string|WP_Error {
		$query = array_filter(
			array(
				'sku'         => $product->get_sku(),
				'external_id' => 'wc-product-' . $product->get_id(),
			)
		);
		$response = $this->client->request( 'GET', (string) WCAS_Utils::get_setting( 'product_search_path' ), array(), $query );
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
	 * Create product.
	 */
	private function create_product( WC_Product $product ): string|WP_Error {
		$payload = array(
			'name'        => $product->get_name(),
			'sku'         => $product->get_sku(),
			'price'       => (float) wc_get_price_excluding_tax( $product ),
			'description' => wp_strip_all_tags( $product->get_description() ?: $product->get_short_description() ),
			'unit'        => 'UN',
			'active'      => 'publish' === get_post_status( $product->get_id() ),
			'external_id' => 'wc-product-' . $product->get_id(),
		);
		$response = $this->client->request( 'POST', (string) WCAS_Utils::get_setting( 'product_create_path' ), $payload );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$id = $this->client->extract_id( $response );
		return '' !== $id ? $id : new WP_Error( 'wcas_product_id_missing', __( 'ID do produto não retornado pela Conta Azul.', 'woocommerce-conta-azul-sync' ), $response );
	}
}
