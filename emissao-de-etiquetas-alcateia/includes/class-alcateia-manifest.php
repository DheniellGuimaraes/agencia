<?php
/**
 * Simple shipping manifest generator.
 *
 * @package AlcateiaLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Generates order manifests. */
class Alcateia_Manifest {
	/** Output manifest HTML. */
	public static function output( array $order_ids ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'emissao-de-etiquetas-alcateia' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		?>
		<!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>"><title><?php echo esc_html__( 'Romaneio Alcateia', 'emissao-de-etiquetas-alcateia' ); ?></title><style><?php self::styles(); ?></style></head><body>
		<div class="toolbar"><button onclick="window.print()"><?php echo esc_html__( 'Imprimir romaneio', 'emissao-de-etiquetas-alcateia' ); ?></button></div>
		<main class="page"><h1><?php echo esc_html__( 'Romaneio de expedição', 'emissao-de-etiquetas-alcateia' ); ?></h1><p><?php echo esc_html( sprintf( __( 'Gerado em %s', 'emissao-de-etiquetas-alcateia' ), current_time( 'd/m/Y H:i' ) ) ); ?></p>
		<table><thead><tr><th><?php echo esc_html__( 'Pedido', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Cliente', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Cidade', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Estado', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Transportadora', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Rastreio', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Status', 'emissao-de-etiquetas-alcateia' ); ?></th></tr></thead><tbody>
		<?php foreach ( array_filter( array_map( 'absint', $order_ids ) ) as $order_id ) : $order = wc_get_order( $order_id ); if ( ! $order instanceof WC_Order ) { continue; } $tracking = Alcateia_Tracking::get_tracking_data( $order ); ?>
			<tr><td>#<?php echo esc_html( $order->get_order_number() ); ?></td><td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td><td><?php echo esc_html( $order->get_shipping_city() ?: $order->get_billing_city() ); ?></td><td><?php echo esc_html( $order->get_shipping_state() ?: $order->get_billing_state() ); ?></td><td><?php echo esc_html( $tracking['carrier'] ?: '-' ); ?></td><td><?php echo esc_html( $tracking['code'] ?: '-' ); ?></td><td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table></main></body></html>
		<?php
		exit;
	}

	/** CSS for print view. */
	private static function styles(): void {
		echo 'body{margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827}.toolbar{position:sticky;top:0;background:#111827;padding:12px;text-align:center}.toolbar button{border:0;border-radius:999px;padding:10px 18px;font-weight:700}.page{max-width:1200px;margin:24px auto;background:#fff;padding:32px;border-radius:18px}h1{margin-top:0}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}th{background:#f9fafb}@media print{.toolbar{display:none}.page{margin:0;max-width:none;border-radius:0}}';
	}
}
