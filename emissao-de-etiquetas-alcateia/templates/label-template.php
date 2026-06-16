<?php
/**
 * Printable 10 x 15 cm label template.
 *
 * Variables: $orders, $format.
 *
 * @package AlcateiaLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html__( 'Etiquetas Alcateia', 'emissao-de-etiquetas-alcateia' ); ?></title>
	<style>
		@page { size: 100mm 150mm; margin: 0; }
		* { box-sizing: border-box; }
		body { margin: 0; color: #111827; font-family: DejaVu Sans, Arial, sans-serif; background: #f4f4f5; }
		.alcateia-print-toolbar { position: sticky; top: 0; z-index: 10; padding: 12px; text-align: center; background: #111827; }
		.alcateia-print-toolbar button { border: 0; border-radius: 999px; padding: 10px 18px; color: #111827; background: #f8fafc; font-weight: 700; cursor: pointer; }
		.alcateia-label { width: 100mm; min-height: 150mm; page-break-after: always; margin: 0 auto; padding: 5.5mm; background: #fff; border: 1px solid #e5e7eb; overflow: hidden; }
		.alcateia-label:last-child { page-break-after: auto; }
		.alcateia-label-header { text-align: center; border-bottom: 2px solid #111827; padding-bottom: 3mm; margin-bottom: 3mm; }
		.alcateia-logo { max-width: 48mm; max-height: 17mm; object-fit: contain; }
		.alcateia-label-title { margin: 2mm 0 0; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; }
		.alcateia-block { border: 1px solid #d1d5db; border-radius: 2.5mm; padding: 2.2mm; margin-bottom: 2.2mm; background: #fafafa; }
		.alcateia-block-title { margin: 0 0 1.5mm; font-size: 8px; letter-spacing: 1px; text-transform: uppercase; color: #111827; font-weight: 800; }
		.alcateia-row { margin-bottom: 1.8mm; }
		.alcateia-row strong { display: block; font-size: 8px; letter-spacing: .8px; color: #4b5563; text-transform: uppercase; }
		.alcateia-row span, .alcateia-row div { font-size: 11.5px; line-height: 1.32; }
		.alcateia-two-cols { display: table; width: 100%; table-layout: fixed; gap: 2mm; }
		.alcateia-two-cols .alcateia-row { display: table-cell; width: 50%; padding-right: 2mm; }
		.alcateia-products { width: 100%; border-collapse: collapse; margin-top: 1mm; font-size: 10px; }
		.alcateia-products th, .alcateia-products td { border-bottom: 1px solid #e5e7eb; padding: 1.4mm 0; text-align: left; vertical-align: top; }
		.alcateia-products th:last-child, .alcateia-products td:last-child { width: 14mm; text-align: right; }
		.alcateia-tracking-highlight { background: #111827; color: #fff; border-radius: 2.5mm; padding: 2.3mm; margin: 2.2mm 0; border: 2px solid #000; }
		.alcateia-tracking-highlight strong { color: #cbd5e1; }
		.alcateia-barcode { margin: 4mm 0 2mm; text-align: center; }
		.alcateia-barcode-bars { height: 14mm; display: inline-flex; align-items: stretch; gap: 1px; padding: 2mm; border: 1px solid #111827; }
		.alcateia-barcode-bars i { display: block; width: 2px; background: #111827; }
		.alcateia-footer { margin-top: 2mm; padding-top: 1.5mm; border-top: 1px solid #d1d5db; text-align: center; font-weight: 700; font-size: 10px; color: #4b5563; }
		@media print { body { background: #fff; } .alcateia-print-toolbar { display: none; } .alcateia-label { border: 0; margin: 0; } }
	</style>
</head>
<body>
	<div class="alcateia-print-toolbar">
		<button type="button" onclick="window.print()"><?php echo esc_html__( 'Imprimir etiqueta', 'emissao-de-etiquetas-alcateia' ); ?></button>
	</div>
	<?php foreach ( $orders as $order ) : ?>
		<?php
		$shipping_address = $order->get_formatted_shipping_address();
		if ( empty( $shipping_address ) ) {
			$shipping_address = $order->get_formatted_billing_address();
		}
		$order_number = (string) $order->get_order_number();
		$notes        = trim( (string) $order->get_customer_note() );
		$tracking     = class_exists( 'Alcateia_Tracking' ) ? Alcateia_Tracking::get_tracking_data( $order ) : array( 'code' => '', 'carrier' => '' );
		?>
		<section class="alcateia-label alcateia-label-<?php echo esc_attr( $format ); ?>">
			<header class="alcateia-label-header">
				<img class="alcateia-logo" src="<?php echo esc_url( ALCATEIA_LABELS_LOGO_URL ); ?>" alt="<?php echo esc_attr__( 'Alcateia Editorial', 'emissao-de-etiquetas-alcateia' ); ?>">
				<p class="alcateia-label-title"><?php echo esc_html__( 'Etiqueta interna de expedição', 'emissao-de-etiquetas-alcateia' ); ?></p>
			</header>

			<div class="alcateia-block">
				<p class="alcateia-block-title"><?php echo esc_html__( 'Destinatário', 'emissao-de-etiquetas-alcateia' ); ?></p>
				<div class="alcateia-two-cols">
					<div class="alcateia-row"><strong><?php echo esc_html__( 'Pedido', 'emissao-de-etiquetas-alcateia' ); ?></strong><span>#<?php echo esc_html( $order_number ); ?></span></div>
					<div class="alcateia-row"><strong><?php echo esc_html__( 'Data', 'emissao-de-etiquetas-alcateia' ); ?></strong><span><?php echo esc_html( $order->get_date_created() ? wc_format_datetime( $order->get_date_created(), 'd/m/Y H:i' ) : '-' ); ?></span></div>
				</div>
				<div class="alcateia-row"><strong><?php echo esc_html__( 'Cliente', 'emissao-de-etiquetas-alcateia' ); ?></strong><span><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></span></div>
				<div class="alcateia-row"><strong><?php echo esc_html__( 'Endereço de entrega', 'emissao-de-etiquetas-alcateia' ); ?></strong><div><?php echo wp_kses_post( $shipping_address ); ?></div></div>
				<div class="alcateia-two-cols">
					<div class="alcateia-row"><strong><?php echo esc_html__( 'Telefone', 'emissao-de-etiquetas-alcateia' ); ?></strong><span><?php echo esc_html( $order->get_billing_phone() ?: '-' ); ?></span></div>
					<div class="alcateia-row"><strong><?php echo esc_html__( 'E-mail', 'emissao-de-etiquetas-alcateia' ); ?></strong><span><?php echo esc_html( $order->get_billing_email() ?: '-' ); ?></span></div>
				</div>
			</div>

			<?php if ( ! empty( $tracking['code'] ) ) : ?>
				<div class="alcateia-tracking-highlight"><div class="alcateia-row"><strong><?php echo esc_html__( 'Rastreio:', 'emissao-de-etiquetas-alcateia' ); ?></strong><span><?php echo esc_html( $tracking['code'] ); ?></span></div><div class="alcateia-row"><strong><?php echo esc_html__( 'Transportadora:', 'emissao-de-etiquetas-alcateia' ); ?></strong><span><?php echo esc_html( $tracking['carrier'] ); ?></span></div></div>
			<?php endif; ?>

			<div class="alcateia-block">
				<p class="alcateia-block-title"><?php echo esc_html__( 'Produtos', 'emissao-de-etiquetas-alcateia' ); ?></p>
				<table class="alcateia-products">
					<thead><tr><th><?php echo esc_html__( 'Item', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Qtd.', 'emissao-de-etiquetas-alcateia' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $order->get_items() as $item ) : ?>
							<tr><td><?php echo esc_html( $item->get_name() ); ?></td><td><?php echo esc_html( (string) $item->get_quantity() ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( '' !== $notes ) : ?>
				<div class="alcateia-row"><strong><?php echo esc_html__( 'Observações', 'emissao-de-etiquetas-alcateia' ); ?></strong><div><?php echo esc_html( $notes ); ?></div></div>
			<?php endif; ?>

			<div class="alcateia-barcode" aria-label="<?php echo esc_attr( 'Pedido ' . $order_number ); ?>">
				<div class="alcateia-barcode-bars">
					<?php foreach ( str_split( preg_replace( '/\D+/', '', $order_number ) ?: (string) $order->get_id() ) as $digit ) : ?>
						<i style="height:<?php echo esc_attr( 18 + ( (int) $digit * 2 ) ); ?>px"></i><i style="height:28px;width:<?php echo esc_attr( 1 + ( (int) $digit % 3 ) ); ?>px"></i>
					<?php endforeach; ?>
				</div>
				<div>#<?php echo esc_html( $order_number ); ?></div>
			</div>

			<footer class="alcateia-footer"><?php echo esc_html__( 'Alcateia Editorial', 'emissao-de-etiquetas-alcateia' ); ?></footer>
		</section>
	<?php endforeach; ?>
</body>
</html>
