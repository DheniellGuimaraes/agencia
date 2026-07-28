<?php
/**
 * Picking list generator.
 *
 * @package AlcateiaLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Generates product separation lists from orders. */
class Alcateia_Picking_List {
	/** Output picking list HTML. */
	public static function output( array $order_ids ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'emissao-de-etiquetas-alcateia' ) );
		}

		$products = array();
		foreach ( array_filter( array_map( 'absint', $order_ids ) ) as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				$key     = $product ? (string) $product->get_id() : sanitize_key( $item->get_name() );
				if ( ! isset( $products[ $key ] ) ) {
					$products[ $key ] = array(
						'name'   => $item->get_name(),
						'sku'    => $product ? $product->get_sku() : '',
						'qty'    => 0,
						'orders' => array(),
					);
				}
				$products[ $key ]['qty']     += (int) $item->get_quantity();
				$products[ $key ]['orders'][] = '#' . $order->get_order_number();
			}
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		?>
		<!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>"><title><?php echo esc_html__( 'Lista de separação Alcateia', 'emissao-de-etiquetas-alcateia' ); ?></title><style><?php self::styles(); ?></style></head><body>
		<div class="toolbar"><button onclick="window.print()"><?php echo esc_html__( 'Imprimir picking list', 'emissao-de-etiquetas-alcateia' ); ?></button></div>
		<main class="page"><h1><?php echo esc_html__( 'Lista de separação', 'emissao-de-etiquetas-alcateia' ); ?></h1><p><?php echo esc_html( sprintf( __( 'Gerada em %s', 'emissao-de-etiquetas-alcateia' ), current_time( 'd/m/Y H:i' ) ) ); ?></p>
		<table><thead><tr><th><?php echo esc_html__( 'Produto', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'SKU', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Quantidade total', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Pedidos relacionados', 'emissao-de-etiquetas-alcateia' ); ?></th></tr></thead><tbody>
		<?php foreach ( $products as $product ) : ?><tr><td><?php echo esc_html( $product['name'] ); ?></td><td><?php echo esc_html( $product['sku'] ?: '-' ); ?></td><td><?php echo esc_html( (string) $product['qty'] ); ?></td><td><?php echo esc_html( implode( ', ', array_unique( $product['orders'] ) ) ); ?></td></tr><?php endforeach; ?>
		</tbody></table></main></body></html>
		<?php
		exit;
	}

	/** CSS for print view. */
	private static function styles(): void {
		echo 'body{margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827}.toolbar{position:sticky;top:0;background:#111827;padding:12px;text-align:center}.toolbar button{border:0;border-radius:999px;padding:10px 18px;font-weight:700}.page{max-width:1100px;margin:24px auto;background:#fff;padding:32px;border-radius:18px}h1{margin-top:0}table{width:100%;border-collapse:collapse}th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left}th{background:#f9fafb}@media print{.toolbar{display:none}.page{margin:0;max-width:none;border-radius:0}}';
	}
}
