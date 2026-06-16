<?php
/**
 * Label HTML/PDF generation.
 *
 * @package AlcateiaLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates printable labels and optional Dompdf files.
 */
class Alcateia_Labels_Generator {
	/**
	 * Output PDF through Dompdf when available, otherwise HTML print view.
	 */
	public static function output( array $order_ids, string $format = 'thermal', bool $force_html = false ): void {
		$orders = self::get_orders( $order_ids );
		if ( empty( $orders ) ) {
			wp_die( esc_html__( 'Nenhum pedido válido encontrado.', 'emissao-de-etiquetas-alcateia' ) );
		}

		$html = self::render_html( $orders, $format );

		Alcateia_Logger::log( 'label_generation', __( 'Etiqueta gerada.', 'emissao-de-etiquetas-alcateia' ), array( 'orders' => implode( ',', array_map( static fn( WC_Order $order ): int => $order->get_id(), $orders ) ) ) );

		if ( ! $force_html && self::load_dompdf() && class_exists( '\Dompdf\Dompdf' ) ) {
			self::output_pdf( $html, count( $orders ) > 1 ? 'etiquetas-alcateia.pdf' : 'etiqueta-alcateia-' . $orders[0]->get_id() . '.pdf' );
		}

		self::output_printable_html( $html );
	}

	/**
	 * Get valid WooCommerce orders.
	 */
	private static function get_orders( array $order_ids ): array {
		$orders = array();
		foreach ( array_filter( array_map( 'absint', $order_ids ) ) as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				$orders[] = $order;
			}
		}
		return $orders;
	}

	/**
	 * Render label template.
	 */
	private static function render_html( array $orders, string $format ): string {
		ob_start();
		$template = ALCATEIA_LABELS_PATH . 'templates/label-template.php';
		include $template;
		return (string) ob_get_clean();
	}

	/** Check whether Dompdf is available. */
	public static function is_dompdf_available(): bool {
		return self::load_dompdf() && class_exists( '\\Dompdf\\Dompdf' );
	}

	/**
	 * Load Composer autoloader if bundled by the site/plugin.
	 */
	private static function load_dompdf(): bool {
		$autoloaders = array(
			ALCATEIA_LABELS_PATH . 'vendor/autoload.php',
			WP_CONTENT_DIR . '/vendor/autoload.php',
			ABSPATH . 'vendor/autoload.php',
		);

		foreach ( $autoloaders as $autoload ) {
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
				return true;
			}
		}

		return false;
	}

	/**
	 * Send PDF to browser.
	 */
	private static function output_pdf( string $html, string $filename ): void {
		$dompdf = new \Dompdf\Dompdf(
			array(
				'isRemoteEnabled'      => true,
				'isHtml5ParserEnabled' => true,
			)
		);
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->setPaper( array( 0, 0, 283.46, 425.20 ), 'portrait' ); // 10 x 15 cm in points.
		try {
			$dompdf->render();
		} catch ( Throwable $exception ) {
			Alcateia_Logger::log( 'pdf_error', $exception->getMessage() );
			self::output_printable_html( $html );
		}
		$dompdf->stream( sanitize_file_name( $filename ), array( 'Attachment' => false ) );
		exit;
	}

	/**
	 * HTML fallback with print button.
	 */
	private static function output_printable_html( string $html ): void {
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes dynamic data before output.
		exit;
	}
}
