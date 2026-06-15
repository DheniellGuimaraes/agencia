<?php
/**
 * Order synchronization hooks and service.
 *
 * @package WooCommerceContaAzulSync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Orders sync service.
 */
class WCAS_Orders {
	private WCAS_API_Client $client;
	private WCAS_Customers $customers;
	private WCAS_Products $products;

	public function __construct( WCAS_API_Client $client, WCAS_Customers $customers, WCAS_Products $products ) {
		$this->client    = $client;
		$this->customers = $customers;
		$this->products  = $products;
	}

	/**
	 * Register WooCommerce hooks.
	 */
	public function init(): void {
		add_action( 'woocommerce_order_status_processing', array( $this, 'sync_order_by_id' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'sync_order_by_id' ), 20 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'handle_cancelled' ), 20 );
		add_action( 'woocommerce_refund_created', array( $this, 'handle_refund' ), 20, 2 );
	}

	/**
	 * Sync order by ID.
	 */
	public function sync_order_by_id( int $order_id ): void {
		if ( ! WCAS_Utils::is_enabled( 'enabled' ) || ! WCAS_Utils::is_enabled( 'auto_sync_orders' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$result = $this->sync_order( $order );
		if ( is_wp_error( $result ) ) {
			$this->mark_order( $order, 'error', $result->get_error_message() );
			WCAS_Logger::log( 'sync_error', $result->get_error_message(), $order_id, array( 'data' => $result->get_error_data() ) );
		}
	}

	/**
	 * Sync a WooCommerce order with Conta Azul.
	 */
	public function sync_order( WC_Order $order ): string|WP_Error {
		if ( (string) $order->get_meta( '_wcas_sale_id', true ) !== '' ) {
			WCAS_Logger::log( 'order_skipped', 'Pedido já sincronizado com a Conta Azul.', $order->get_id() );
			return (string) $order->get_meta( '_wcas_sale_id', true );
		}

		$customer_id = $this->customers->sync_from_order( $order );
		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}

		$items = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$remote_product_id = '';
			if ( WCAS_Utils::is_enabled( 'sync_products' ) ) {
				$remote_product_id = $this->products->sync_order_item( $item );
				if ( is_wp_error( $remote_product_id ) ) {
					return $remote_product_id;
				}
			}
			$items[] = array(
				'product_id' => $remote_product_id,
				'name'       => $item->get_name(),
				'sku'        => $item->get_product() ? $item->get_product()->get_sku() : '',
				'quantity'   => (float) $item->get_quantity(),
				'unit_price' => (float) $order->get_item_subtotal( $item, false, false ),
				'total'      => (float) $item->get_total(),
			);
		}

		$sale_id = '';
		if ( WCAS_Utils::is_enabled( 'register_sale' ) ) {
			$response = $this->client->request( 'POST', (string) WCAS_Utils::get_setting( 'sale_create_path' ), $this->build_sale_payload( $order, $customer_id, $items ) );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$sale_id = $this->client->extract_id( $response );
			if ( '' === $sale_id ) {
				return new WP_Error( 'wcas_sale_id_missing', __( 'ID da venda não retornado pela Conta Azul.', 'woocommerce-conta-azul-sync' ), $response );
			}
			$order->update_meta_data( '_wcas_sale_id', $sale_id );
		}

		if ( WCAS_Utils::is_enabled( 'register_receivable' ) ) {
			$receivable = $this->client->request( 'POST', (string) WCAS_Utils::get_setting( 'receivable_create_path' ), $this->build_receivable_payload( $order, $customer_id, $sale_id ) );
			if ( is_wp_error( $receivable ) ) {
				return $receivable;
			}
			$order->update_meta_data( '_wcas_receivable_id', $this->client->extract_id( $receivable ) );
		}

		$this->mark_order( $order, 'synced', '' );
		WCAS_Logger::log( 'order_synced', 'Pedido sincronizado com a Conta Azul.', $order->get_id(), array( 'sale_id' => $sale_id ) );
		return $sale_id;
	}

	/**
	 * Handle cancellation.
	 */
	public function handle_cancelled( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$sale_id = (string) $order->get_meta( '_wcas_sale_id', true );
		WCAS_Logger::log( 'order_cancelled', 'Pedido WooCommerce cancelado.', $order_id, array( 'sale_id' => $sale_id ) );
		if ( WCAS_Utils::is_enabled( 'enabled' ) && '' !== $sale_id ) {
			$path = str_replace( '{id}', rawurlencode( $sale_id ), (string) WCAS_Utils::get_setting( 'sale_cancel_path' ) );
			$result = $this->client->request( 'POST', $path, array( 'status' => 'cancelled', 'reason' => 'WooCommerce order cancelled' ) );
			if ( is_wp_error( $result ) ) {
				WCAS_Logger::log( 'sync_error', 'Falha ao cancelar/atualizar venda na Conta Azul: ' . $result->get_error_message(), $order_id );
			}
		}
	}

	/**
	 * Handle refund event.
	 */
	public function handle_refund( int $refund_id, array $args = array() ): void {
		$order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
		WCAS_Logger::log( 'refund_created', 'Reembolso criado no WooCommerce.', $order_id, array( 'refund_id' => $refund_id ) );
	}

	/**
	 * Build sale payload.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @return array<string, mixed>
	 */
	private function build_sale_payload( WC_Order $order, string $customer_id, array $items ): array {
		return array(
			'external_id'     => 'wc-order-' . $order->get_id(),
			'order_number'    => $order->get_order_number(),
			'customer_id'     => $customer_id,
			'ordered_at'      => $order->get_date_created() ? $order->get_date_created()->date( DATE_ATOM ) : gmdate( DATE_ATOM ),
			'payment_method'  => $order->get_payment_method_title(),
			'subtotal'        => (float) $order->get_subtotal(),
			'discount_total'  => (float) $order->get_discount_total(),
			'shipping_total'  => (float) $order->get_shipping_total(),
			'total'           => (float) $order->get_total(),
			'currency'        => $order->get_currency(),
			'items'           => $items,
			'notes'           => sprintf( 'Pedido WooCommerce #%s', $order->get_order_number() ),
		);
	}

	/**
	 * Build receivable payload.
	 *
	 * @return array<string, mixed>
	 */
	private function build_receivable_payload( WC_Order $order, string $customer_id, string $sale_id ): array {
		$paid = $order->is_paid();
		return array(
			'external_id'     => 'wc-receivable-' . $order->get_id(),
			'sale_id'         => $sale_id,
			'customer_id'     => $customer_id,
			'due_date'        => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : gmdate( 'Y-m-d' ),
			'amount'          => (float) $order->get_total(),
			'payment_method'  => $order->get_payment_method_title(),
			'status'          => $paid ? 'paid' : 'pending',
			'notes'           => sprintf( 'Conta a receber do pedido WooCommerce #%s', $order->get_order_number() ),
		);
	}

	/**
	 * Update order sync metadata.
	 */
	private function mark_order( WC_Order $order, string $status, string $error ): void {
		$order->update_meta_data( '_wcas_sync_status', $status );
		$order->update_meta_data( '_wcas_last_sync_at', current_time( 'mysql' ) );
		$order->update_meta_data( '_wcas_last_error', $error );
		$order->save();
	}
}
