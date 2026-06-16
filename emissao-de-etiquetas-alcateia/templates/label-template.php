<?php
/**
 * Premium printable 10 x 15 cm label template.
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
		body { margin: 0; color: #101010; font-family: DejaVu Sans, Arial, sans-serif; background: #eef0f3; }
		.alcateia-print-toolbar { position: sticky; top: 0; z-index: 10; padding: 12px; text-align: center; background: #111827; }
		.alcateia-print-toolbar button { border: 0; border-radius: 999px; padding: 10px 18px; color: #111827; background: #f8fafc; font-weight: 800; cursor: pointer; }
		.alcateia-label { width: 100mm; min-height: 150mm; page-break-after: always; margin: 0 auto; padding: 4.6mm; background: #fff; border: 1px solid #d7d7d7; overflow: hidden; }
		.alcateia-label:last-child { page-break-after: auto; }
		.alcateia-shell { min-height: 140.8mm; border: 1.6px solid #111; border-radius: 4mm; padding: 3.2mm; position: relative; }
		.alcateia-topbar { width: 100%; border-bottom: 2.4px solid #111; padding-bottom: 2.8mm; margin-bottom: 2.8mm; text-align: center; }
		.alcateia-brand { text-align: center; }
		.alcateia-logo { max-width: 48mm; max-height: 16mm; object-fit: contain; display: inline-block; }
		.alcateia-meta-grid { display: table; width: 100%; table-layout: fixed; margin-bottom: 2.5mm; }
		.alcateia-meta { display: table-cell; border: 1px solid #111; padding: 1.7mm; vertical-align: top; }
		.alcateia-meta + .alcateia-meta { border-left: 0; }
		.alcateia-label-small { display: block; margin-bottom: .8mm; font-size: 7px; color: #555; font-weight: 900; letter-spacing: .9px; text-transform: uppercase; }
		.alcateia-label-value { display: block; font-size: 11px; font-weight: 900; line-height: 1.15; }
		.alcateia-section { border: 1.4px solid #111; border-radius: 2.5mm; margin-bottom: 2.4mm; overflow: hidden; background: #fff; }
		.alcateia-section-title { margin: 0; padding: 1.3mm 2mm; color: #fff; background: #111; font-size: 7.8px; font-weight: 900; letter-spacing: 1.3px; text-transform: uppercase; }
		.alcateia-section-body { padding: 2mm; }
		.alcateia-recipient-name { margin: 0 0 1.2mm; font-size: 15px; font-weight: 900; line-height: 1.12; text-transform: uppercase; }
		.alcateia-address { font-size: 11.2px; line-height: 1.28; font-weight: 700; }
		.alcateia-contact-row { display: table; width: 100%; table-layout: fixed; margin-top: 1.8mm; padding-top: 1.5mm; border-top: 1px dashed #999; }
		.alcateia-contact { display: table-cell; padding-right: 1.5mm; font-size: 8.4px; line-height: 1.25; word-break: break-word; }
		.alcateia-tracking { border: 2.2px solid #111; background: #f4f4f4; }
		.alcateia-tracking .alcateia-section-title { background: #000; }
		.alcateia-tracking-code { display: block; margin: .8mm 0 1.2mm; font-size: 17px; font-weight: 900; letter-spacing: 1.1px; line-height: 1.05; }
		.alcateia-carrier { display: inline-block; border: 1px solid #111; border-radius: 999px; padding: .8mm 1.8mm; font-size: 8.5px; font-weight: 900; background: #fff; }
		.alcateia-products { width: 100%; border-collapse: collapse; font-size: 9.2px; }
		.alcateia-products th { padding: 1.1mm 0; border-bottom: 1.4px solid #111; font-size: 7px; letter-spacing: .8px; text-transform: uppercase; text-align: left; }
		.alcateia-products td { padding: 1.1mm 0; border-bottom: 1px solid #e2e2e2; vertical-align: top; line-height: 1.2; }
		.alcateia-products th:last-child, .alcateia-products td:last-child { width: 13mm; text-align: right; font-weight: 900; }
		.alcateia-notes { margin-top: 1.5mm; padding-top: 1.5mm; border-top: 1px dashed #999; font-size: 8.8px; line-height: 1.25; }
		.alcateia-bottom { position: absolute; left: 3.2mm; right: 3.2mm; bottom: 3mm; }
		.alcateia-bottom-grid { display: table; width: 100%; table-layout: fixed; margin-bottom: 1.4mm; }
		.alcateia-barcode, .alcateia-site-qr { display: table-cell; vertical-align: bottom; text-align: center; }
		.alcateia-barcode { width: 66%; }
		.alcateia-barcode-bars { height: 12mm; display: inline-flex; align-items: stretch; gap: 1px; padding: 1.5mm 2mm; border: 1.4px solid #111; background: #fff; }
		.alcateia-barcode-bars i { display: block; width: 2px; background: #111; }
		.alcateia-order-number { margin-top: .8mm; font-size: 10px; font-weight: 900; letter-spacing: 1px; }
		.alcateia-site-qr { width: 34%; }
		.alcateia-site-qr img { display: inline-block; width: 18mm; height: 18mm; border: 1px solid #111; padding: 1mm; background: #fff; }
		.alcateia-site-qr span { display: block; margin-top: .6mm; font-size: 6.5px; font-weight: 900; letter-spacing: .35px; color: #333; }
		.alcateia-footer { display: table; width: 100%; border-top: 1px solid #111; padding-top: 1.3mm; font-size: 7.8px; color: #555; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; }
		.alcateia-footer span { display: table-cell; }
		.alcateia-footer span:last-child { text-align: right; }
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
		$barcode_seed = preg_replace( '/\D+/', '', $order_number ) ?: (string) $order->get_id();
		?>
		<section class="alcateia-label alcateia-label-<?php echo esc_attr( $format ); ?>">
			<div class="alcateia-shell">
				<header class="alcateia-topbar">
					<div class="alcateia-brand"><img class="alcateia-logo" src="<?php echo esc_url( ALCATEIA_LABELS_LOGO_URL ); ?>" alt="<?php echo esc_attr__( 'Alcateia Editorial', 'emissao-de-etiquetas-alcateia' ); ?>"></div>
				</header>

				<div class="alcateia-meta-grid">
					<div class="alcateia-meta"><span class="alcateia-label-small"><?php echo esc_html__( 'Pedido', 'emissao-de-etiquetas-alcateia' ); ?></span><span class="alcateia-label-value">#<?php echo esc_html( $order_number ); ?></span></div>
					<div class="alcateia-meta"><span class="alcateia-label-small"><?php echo esc_html__( 'Data', 'emissao-de-etiquetas-alcateia' ); ?></span><span class="alcateia-label-value"><?php echo esc_html( $order->get_date_created() ? wc_format_datetime( $order->get_date_created(), 'd/m/Y H:i' ) : '-' ); ?></span></div>
					<div class="alcateia-meta"><span class="alcateia-label-small"><?php echo esc_html__( 'Volumes', 'emissao-de-etiquetas-alcateia' ); ?></span><span class="alcateia-label-value">1/1</span></div>
				</div>

				<section class="alcateia-section">
					<h2 class="alcateia-section-title"><?php echo esc_html__( 'Destinatário', 'emissao-de-etiquetas-alcateia' ); ?></h2>
					<div class="alcateia-section-body">
						<p class="alcateia-recipient-name"><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></p>
						<div class="alcateia-address"><?php echo wp_kses_post( $shipping_address ); ?></div>
						<div class="alcateia-contact-row">
							<div class="alcateia-contact"><span class="alcateia-label-small"><?php echo esc_html__( 'Telefone', 'emissao-de-etiquetas-alcateia' ); ?></span><?php echo esc_html( $order->get_billing_phone() ?: '-' ); ?></div>
							<div class="alcateia-contact"><span class="alcateia-label-small"><?php echo esc_html__( 'E-mail', 'emissao-de-etiquetas-alcateia' ); ?></span><?php echo esc_html( $order->get_billing_email() ?: '-' ); ?></div>
						</div>
					</div>
				</section>

				<?php if ( ! empty( $tracking['code'] ) ) : ?>
					<section class="alcateia-section alcateia-tracking">
						<h2 class="alcateia-section-title"><?php echo esc_html__( 'Rastreio', 'emissao-de-etiquetas-alcateia' ); ?></h2>
						<div class="alcateia-section-body"><span class="alcateia-tracking-code"><?php echo esc_html( $tracking['code'] ); ?></span><span class="alcateia-carrier"><?php echo esc_html( $tracking['carrier'] ); ?></span></div>
					</section>
				<?php endif; ?>

				<section class="alcateia-section">
					<h2 class="alcateia-section-title"><?php echo esc_html__( 'Produtos para conferência', 'emissao-de-etiquetas-alcateia' ); ?></h2>
					<div class="alcateia-section-body">
						<table class="alcateia-products">
							<thead><tr><th><?php echo esc_html__( 'Item', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Qtd.', 'emissao-de-etiquetas-alcateia' ); ?></th></tr></thead>
							<tbody>
								<?php foreach ( $order->get_items() as $item ) : ?>
									<tr><td><?php echo esc_html( $item->get_name() ); ?></td><td><?php echo esc_html( (string) $item->get_quantity() ); ?></td></tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php if ( '' !== $notes ) : ?>
							<div class="alcateia-notes"><strong><?php echo esc_html__( 'Observações:', 'emissao-de-etiquetas-alcateia' ); ?></strong> <?php echo esc_html( $notes ); ?></div>
						<?php endif; ?>
					</div>
				</section>

				<div class="alcateia-bottom">
					<div class="alcateia-bottom-grid">
						<div class="alcateia-barcode" aria-label="<?php echo esc_attr( 'Pedido ' . $order_number ); ?>">
							<div class="alcateia-barcode-bars">
								<?php foreach ( str_split( $barcode_seed ) as $digit ) : ?>
									<i style="height:<?php echo esc_attr( 16 + ( (int) $digit * 2 ) ); ?>px"></i><i style="height:28px;width:<?php echo esc_attr( 1 + ( (int) $digit % 3 ) ); ?>px"></i>
								<?php endforeach; ?>
							</div>
							<div class="alcateia-order-number">#<?php echo esc_html( $order_number ); ?></div>
						</div>
						<div class="alcateia-site-qr">
							<img src="<?php echo esc_url( 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=1&data=https%3A%2F%2Falcateiaeditorial.com.br' ); ?>" alt="<?php echo esc_attr__( 'QR Code Alcateia Editorial', 'emissao-de-etiquetas-alcateia' ); ?>">
							<span><?php echo esc_html__( 'alcateiaeditorial.com.br', 'emissao-de-etiquetas-alcateia' ); ?></span>
						</div>
					</div>
					<footer class="alcateia-footer"><span><?php echo esc_html__( 'Alcateia Editorial', 'emissao-de-etiquetas-alcateia' ); ?></span><span><?php echo esc_html__( 'Pedido WooCommerce', 'emissao-de-etiquetas-alcateia' ); ?></span></footer>
				</div>
			</div>
		</section>
	<?php endforeach; ?>
</body>
</html>
