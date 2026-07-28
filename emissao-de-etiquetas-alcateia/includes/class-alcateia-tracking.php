<?php
/**
 * Manual tracking module.
 *
 * @package AlcateiaLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Adds tracking fields, columns, filters and email delivery. */
class Alcateia_Tracking {
	/** Carrier options. */
	public static function carriers(): array {
		return array(
			'Correios'               => __( 'Correios', 'emissao-de-etiquetas-alcateia' ),
			'Jadlog'                 => __( 'Jadlog', 'emissao-de-etiquetas-alcateia' ),
			'Loggi'                  => __( 'Loggi', 'emissao-de-etiquetas-alcateia' ),
			'Azul Cargo'             => __( 'Azul Cargo', 'emissao-de-etiquetas-alcateia' ),
			'Total Express'          => __( 'Total Express', 'emissao-de-etiquetas-alcateia' ),
			'Transportadora própria' => __( 'Transportadora própria', 'emissao-de-etiquetas-alcateia' ),
			'Outro'                  => __( 'Outro', 'emissao-de-etiquetas-alcateia' ),
		);
	}

	/** Register hooks. */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_post_alcateia_send_tracking', array( __CLASS__, 'handle_single_send' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'maybe_auto_send' ), 10, 4 );

		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_legacy_column' ), 30 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_legacy_column' ), 10, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_legacy_column' ), 30 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_hpos_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_legacy_filter' ) );
		add_filter( 'request', array( __CLASS__, 'filter_legacy_orders' ) );
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( __CLASS__, 'render_hpos_filter' ) );
		add_filter( 'woocommerce_order_query_args', array( __CLASS__, 'filter_hpos_orders' ) );
	}

	/** Add order metabox. */
	public static function add_meta_box(): void {
		add_meta_box( 'alcateia-tracking', __( 'Rastreamento Alcateia', 'emissao-de-etiquetas-alcateia' ), array( __CLASS__, 'render_meta_box' ), 'shop_order', 'side', 'high' );
		add_meta_box( 'alcateia-tracking', __( 'Rastreamento Alcateia', 'emissao-de-etiquetas-alcateia' ), array( __CLASS__, 'render_meta_box' ), 'woocommerce_page_wc-orders', 'side', 'high' );
	}

	/** Render order tracking fields. */
	public static function render_meta_box( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID ?? 0 );
		if ( ! $order instanceof WC_Order || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$tracking = self::get_tracking_data( $order );
		wp_nonce_field( 'alcateia_save_tracking_' . $order->get_id(), 'alcateia_tracking_nonce' );
		?>
		<div class="alcateia-tracking-box">
			<p><label for="alcateia_tracking_code"><strong><?php echo esc_html__( 'Código de rastreio', 'emissao-de-etiquetas-alcateia' ); ?></strong></label><input class="widefat" id="alcateia_tracking_code" name="alcateia_tracking_code" type="text" value="<?php echo esc_attr( $tracking['code'] ); ?>"></p>
			<p><label for="alcateia_tracking_carrier"><strong><?php echo esc_html__( 'Transportadora', 'emissao-de-etiquetas-alcateia' ); ?></strong></label><select class="widefat" id="alcateia_tracking_carrier" name="alcateia_tracking_carrier"><?php foreach ( self::carriers() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $tracking['carrier'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
			<p><label for="alcateia_tracking_url"><strong><?php echo esc_html__( 'Link de rastreamento', 'emissao-de-etiquetas-alcateia' ); ?></strong></label><input class="widefat" id="alcateia_tracking_url" name="alcateia_tracking_url" type="url" value="<?php echo esc_url( $tracking['url'] ); ?>"></p>
			<p><label for="alcateia_tracking_shipped_date"><strong><?php echo esc_html__( 'Data de envio', 'emissao-de-etiquetas-alcateia' ); ?></strong></label><input class="widefat" id="alcateia_tracking_shipped_date" name="alcateia_tracking_shipped_date" type="date" value="<?php echo esc_attr( $tracking['shipped_date'] ); ?>"></p>
			<p><label for="alcateia_tracking_notes"><strong><?php echo esc_html__( 'Observações internas', 'emissao-de-etiquetas-alcateia' ); ?></strong></label><textarea class="widefat" id="alcateia_tracking_notes" name="alcateia_tracking_notes" rows="3"><?php echo esc_textarea( $tracking['notes'] ); ?></textarea></p>
			<p class="alcateia-tracking-status"><?php self::render_badge( $order ); ?></p>
			<?php if ( $tracking['sent'] ) : ?>
				<p class="description"><?php echo esc_html__( 'Rastreio já enviado. Use o botão abaixo apenas se quiser reenviar.', 'emissao-de-etiquetas-alcateia' ); ?></p>
			<?php else : ?>
				<p class="description alcateia-send-hint"><?php echo esc_html__( 'Preencha o código e clique em Enviar agora.', 'emissao-de-etiquetas-alcateia' ); ?></p>
			<?php endif; ?>
			<p>
				<button type="submit" class="button button-primary widefat alcateia-send-now" name="<?php echo esc_attr( $tracking['sent'] ? 'alcateia_resend_after_save' : 'alcateia_send_after_save' ); ?>" value="1" data-default-label="<?php echo esc_attr__( 'Enviar agora', 'emissao-de-etiquetas-alcateia' ); ?>" data-ready-label="<?php echo esc_attr__( 'Enviar agora', 'emissao-de-etiquetas-alcateia' ); ?>">
					<?php echo esc_html__( 'Enviar agora', 'emissao-de-etiquetas-alcateia' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/** Save tracking fields. */
	public static function save_meta_box( int $order_id, $post = null ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['alcateia_tracking_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alcateia_tracking_nonce'] ) ), 'alcateia_save_tracking_' . $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$old_code = (string) $order->get_meta( ALCATEIA_TRACKING_CODE_META );
		$data = array(
			ALCATEIA_TRACKING_CODE_META         => sanitize_text_field( wp_unslash( $_POST['alcateia_tracking_code'] ?? '' ) ),
			ALCATEIA_TRACKING_CARRIER_META      => self::sanitize_carrier( wp_unslash( $_POST['alcateia_tracking_carrier'] ?? 'Outro' ) ),
			ALCATEIA_TRACKING_URL_META          => esc_url_raw( wp_unslash( $_POST['alcateia_tracking_url'] ?? '' ) ),
			ALCATEIA_TRACKING_SHIPPED_DATE_META => sanitize_text_field( wp_unslash( $_POST['alcateia_tracking_shipped_date'] ?? '' ) ),
			ALCATEIA_TRACKING_NOTES_META        => sanitize_textarea_field( wp_unslash( $_POST['alcateia_tracking_notes'] ?? '' ) ),
		);

		foreach ( $data as $key => $value ) {
			$order->update_meta_data( $key, $value );
		}
		$order->save();

		if ( '' === $old_code && '' !== $data[ ALCATEIA_TRACKING_CODE_META ] ) {
			$order->add_order_note( __( 'Código de rastreio cadastrado.', 'emissao-de-etiquetas-alcateia' ), false, true );
			Alcateia_Logger::log( 'tracking_created', __( 'Código de rastreio cadastrado.', 'emissao-de-etiquetas-alcateia' ), array( 'order_id' => $order_id ) );
		} elseif ( '' !== $old_code && $old_code !== $data[ ALCATEIA_TRACKING_CODE_META ] ) {
			$order->delete_meta_data( ALCATEIA_TRACKING_SENT_META );
			$order->delete_meta_data( ALCATEIA_TRACKING_SENT_AT_META );
			$order->save();
			$order->add_order_note( __( 'Código de rastreio atualizado.', 'emissao-de-etiquetas-alcateia' ), false, true );
			Alcateia_Logger::log( 'tracking_updated', __( 'Código de rastreio atualizado.', 'emissao-de-etiquetas-alcateia' ), array( 'order_id' => $order_id ) );
		}

		if ( isset( $_POST['alcateia_send_after_save'] ) || isset( $_POST['alcateia_resend_after_save'] ) ) {
			$force_resend = isset( $_POST['alcateia_resend_after_save'] );
			self::send_tracking_email( $order_id, ! $force_resend );
		}
	}

	/** Get tracking data. */
	public static function get_tracking_data( WC_Order $order ): array {
		return array(
			'code'         => (string) $order->get_meta( ALCATEIA_TRACKING_CODE_META ),
			'carrier'      => (string) $order->get_meta( ALCATEIA_TRACKING_CARRIER_META ) ?: 'Correios',
			'url'          => (string) $order->get_meta( ALCATEIA_TRACKING_URL_META ),
			'shipped_date' => (string) $order->get_meta( ALCATEIA_TRACKING_SHIPPED_DATE_META ),
			'notes'        => (string) $order->get_meta( ALCATEIA_TRACKING_NOTES_META ),
			'sent'         => 'yes' === $order->get_meta( ALCATEIA_TRACKING_SENT_META ),
			'sent_at'      => (string) $order->get_meta( ALCATEIA_TRACKING_SENT_AT_META ),
		);
	}

	/** Sanitize carrier. */
	private static function sanitize_carrier( string $carrier ): string {
		$carrier = sanitize_text_field( $carrier );
		return array_key_exists( $carrier, self::carriers() ) ? $carrier : 'Outro';
	}

	/** Build single send URL. */
	public static function send_url( int $order_id, bool $resend = false ): string {
		$url = admin_url( 'admin-post.php?action=alcateia_send_tracking&order_id=' . absint( $order_id ) );
		if ( $resend ) {
			$url = add_query_arg( 'resend', '1', $url );
		}
		return wp_nonce_url( $url, 'alcateia_send_tracking_' . absint( $order_id ) );
	}

	/** Single send endpoint. */
	public static function handle_single_send(): void {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'alcateia_send_tracking_' . $order_id ) ) {
			Alcateia_Logger::log( 'permission_error', __( 'Solicitação inválida para envio de rastreio.', 'emissao-de-etiquetas-alcateia' ) );
			wp_die( esc_html__( 'Solicitação inválida.', 'emissao-de-etiquetas-alcateia' ) );
		}

		$force_resend = ! empty( $_GET['resend'] );
		$result       = self::send_tracking_email( $order_id, ! $force_resend );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'post.php?post=' . $order_id . '&action=edit&alcateia_tracking_sent=' . ( $result ? '1' : '0' ) ) );
		exit;
	}

	/** Send tracking email. */
	public static function send_tracking_email( int $order_id, bool $skip_if_sent = true ): bool {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$tracking = self::get_tracking_data( $order );
		if ( '' === $tracking['code'] ) {
			$order->add_order_note( __( 'Tentativa de envio sem código de rastreio.', 'emissao-de-etiquetas-alcateia' ), false, true );
			Alcateia_Logger::log( 'tracking_missing', __( 'Tentativa de envio sem código de rastreio.', 'emissao-de-etiquetas-alcateia' ), array( 'order_id' => $order_id ) );
			return false;
		}
		if ( $skip_if_sent && $tracking['sent'] ) {
			Alcateia_Logger::log( 'tracking_duplicate_blocked', __( 'Envio duplicado bloqueado.', 'emissao-de-etiquetas-alcateia' ), array( 'order_id' => $order_id ) );
			return false;
		}

		$settings = Alcateia_Settings::get();
		$to       = $order->get_billing_email();
		$subject  = __( 'Seu pedido Alcateia foi enviado', 'emissao-de-etiquetas-alcateia' );
		$message  = self::email_html( $order, $tracking, $settings );
		$headers  = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $settings['sender_email'] ) ) {
			$headers[] = 'From: ' . sanitize_text_field( $settings['sender_name'] ) . ' <' . sanitize_email( $settings['sender_email'] ) . '>';
		}

		$mailer = WC()->mailer();
		$sent   = $mailer ? $mailer->send( $to, $subject, $message, $headers, array() ) : wp_mail( $to, $subject, $message, $headers );

		if ( $sent ) {
			$now = current_time( 'mysql' );
			$order->update_meta_data( ALCATEIA_TRACKING_SENT_META, 'yes' );
			$order->update_meta_data( ALCATEIA_TRACKING_SENT_AT_META, $now );
			$order->save();
			$order->add_order_note( sprintf( /* translators: %s: date/time */ __( 'Código de rastreio enviado ao cliente em %s.', 'emissao-de-etiquetas-alcateia' ), $now ), false, true );
			Alcateia_Logger::log( 'tracking_email_sent', __( 'E-mail de rastreio enviado.', 'emissao-de-etiquetas-alcateia' ), array( 'order_id' => $order_id ) );
			return true;
		}

		$order->add_order_note( __( 'Erro no envio de e-mail de rastreio.', 'emissao-de-etiquetas-alcateia' ), false, true );
		Alcateia_Logger::log( 'mail_error', __( 'Erro no envio de e-mail de rastreio.', 'emissao-de-etiquetas-alcateia' ), array( 'order_id' => $order_id ) );
		return false;
	}

	/** Build email HTML. */
	private static function email_html( WC_Order $order, array $tracking, array $settings ): string {
		$customer = $order->get_billing_first_name() ?: $order->get_formatted_billing_full_name();
		ob_start();
		?>
		<div style="font-family:Arial,sans-serif;background:#f6f7fb;padding:24px;color:#1f2937">
			<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden">
				<tr><td style="background:#111827;padding:24px;text-align:center"><img src="<?php echo esc_url( ALCATEIA_LABELS_LOGO_URL ); ?>" alt="<?php echo esc_attr__( 'Alcateia Editorial', 'emissao-de-etiquetas-alcateia' ); ?>" style="max-width:170px;height:auto"><p style="margin:12px 0 0;color:#f9fafb;font-size:18px;font-weight:700"><?php echo esc_html__( 'Alcateia Editorial', 'emissao-de-etiquetas-alcateia' ); ?></p></td></tr>
				<tr><td style="padding:28px">
					<p style="font-size:16px;line-height:1.6"><?php echo esc_html( sprintf( __( 'Olá, %s.', 'emissao-de-etiquetas-alcateia' ), $customer ) ); ?></p>
					<p style="font-size:16px;line-height:1.6"><?php echo esc_html( sprintf( __( 'Seu pedido #%s foi enviado pela Alcateia Editorial.', 'emissao-de-etiquetas-alcateia' ), $order->get_order_number() ) ); ?></p>
					<p style="font-size:15px;line-height:1.6;color:#4b5563"><?php echo esc_html( $settings['default_message'] ); ?></p>
					<div style="margin:22px 0;padding:18px;border-radius:12px;background:#f9fafb;border:1px solid #e5e7eb"><p style="margin:0 0 8px;font-size:13px;color:#6b7280;text-transform:uppercase;font-weight:700"><?php echo esc_html__( 'Código de rastreio', 'emissao-de-etiquetas-alcateia' ); ?></p><p style="margin:0;font-size:24px;letter-spacing:1px;font-weight:800;color:#111827"><?php echo esc_html( $tracking['code'] ); ?></p><p style="margin:12px 0 0;font-size:14px;color:#374151"><strong><?php echo esc_html__( 'Transportadora:', 'emissao-de-etiquetas-alcateia' ); ?></strong> <?php echo esc_html( $tracking['carrier'] ); ?></p></div>
					<?php if ( $tracking['url'] ) : ?><p style="text-align:center;margin:26px 0"><a style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;border-radius:999px;padding:13px 22px;font-weight:700" href="<?php echo esc_url( $tracking['url'] ); ?>"><?php echo esc_html__( 'Acompanhar entrega', 'emissao-de-etiquetas-alcateia' ); ?></a></p><?php endif; ?>
					<p style="font-size:15px;line-height:1.6"><?php echo esc_html__( 'Obrigado por comprar com a Alcateia Editorial.', 'emissao-de-etiquetas-alcateia' ); ?></p>
				</td></tr>
				<tr><td style="padding:16px;text-align:center;background:#f9fafb;color:#6b7280;font-size:12px"><?php echo esc_html( $settings['email_footer'] ); ?></td></tr>
			</table>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Auto send tracking when configured status is reached. */
	public static function maybe_auto_send( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
		$settings = Alcateia_Settings::get();
		if ( 'yes' !== $settings['auto_send'] || $new_status !== $settings['auto_send_status'] ) {
			return;
		}
		self::send_tracking_email( $order_id, true );
	}

	/** Add tracking column. */
	public static function add_legacy_column( array $columns ): array {
		$columns['alcateia_tracking'] = __( 'Rastreio Alcateia', 'emissao-de-etiquetas-alcateia' );
		return $columns;
	}

	/** Render legacy column. */
	public static function render_legacy_column( string $column, int $post_id ): void {
		if ( 'alcateia_tracking' === $column ) {
			$order = wc_get_order( $post_id );
			if ( $order instanceof WC_Order ) {
				self::render_badge( $order );
			}
		}
	}

	/** Render HPOS column. */
	public static function render_hpos_column( string $column, WC_Order $order ): void {
		if ( 'alcateia_tracking' === $column ) {
			self::render_badge( $order );
		}
	}

	/** Render status badge. */
	public static function render_badge( WC_Order $order ): void {
		$tracking = self::get_tracking_data( $order );
		if ( $tracking['sent'] ) {
			echo '<span class="alcateia-badge alcateia-badge-sent">' . esc_html__( 'Rastreio enviado', 'emissao-de-etiquetas-alcateia' ) . '</span>';
		} elseif ( $tracking['code'] ) {
			echo '<span class="alcateia-badge alcateia-badge-ready">' . esc_html__( 'Rastreio cadastrado', 'emissao-de-etiquetas-alcateia' ) . '</span>';
		} else {
			echo '<span class="alcateia-badge alcateia-badge-empty">' . esc_html__( 'Sem rastreio', 'emissao-de-etiquetas-alcateia' ) . '</span>';
		}
	}

	/** Render legacy filter. */
	public static function render_legacy_filter(): void {
		global $typenow;
		if ( 'shop_order' === $typenow ) {
			self::render_filter_select();
		}
	}

	/** Render HPOS filter. */
	public static function render_hpos_filter(): void {
		self::render_filter_select();
	}

	/** Filter select markup. */
	private static function render_filter_select(): void {
		$value = sanitize_key( wp_unslash( $_GET['alcateia_tracking_filter'] ?? '' ) );
		?>
		<select name="alcateia_tracking_filter" id="alcateia_tracking_filter">
			<option value=""><?php echo esc_html__( 'Todos os rastreios Alcateia', 'emissao-de-etiquetas-alcateia' ); ?></option>
			<option value="missing" <?php selected( $value, 'missing' ); ?>><?php echo esc_html__( 'Pedidos sem rastreio', 'emissao-de-etiquetas-alcateia' ); ?></option>
			<option value="has" <?php selected( $value, 'has' ); ?>><?php echo esc_html__( 'Pedidos com rastreio', 'emissao-de-etiquetas-alcateia' ); ?></option>
			<option value="sent" <?php selected( $value, 'sent' ); ?>><?php echo esc_html__( 'Pedidos com rastreio enviado', 'emissao-de-etiquetas-alcateia' ); ?></option>
		</select>
		<?php
	}

	/** Apply legacy filter. */
	public static function filter_legacy_orders( array $vars ): array {
		if ( 'shop_order' !== ( $vars['post_type'] ?? '' ) || empty( $_GET['alcateia_tracking_filter'] ) ) {
			return $vars;
		}
		$vars['meta_query'] = self::tracking_meta_query( sanitize_key( wp_unslash( $_GET['alcateia_tracking_filter'] ) ) );
		return $vars;
	}

	/** Apply HPOS filter. */
	public static function filter_hpos_orders( array $args ): array {
		if ( empty( $_GET['alcateia_tracking_filter'] ) ) {
			return $args;
		}
		$args['meta_query'] = self::tracking_meta_query( sanitize_key( wp_unslash( $_GET['alcateia_tracking_filter'] ) ) );
		return $args;
	}

	/** Build meta query for tracking filter. */
	private static function tracking_meta_query( string $filter ): array {
		if ( 'has' === $filter ) {
			return array( array( 'key' => ALCATEIA_TRACKING_CODE_META, 'value' => '', 'compare' => '!=' ) );
		}
		if ( 'sent' === $filter ) {
			return array( array( 'key' => ALCATEIA_TRACKING_SENT_META, 'value' => 'yes' ) );
		}
		if ( 'missing' === $filter ) {
			return array( 'relation' => 'OR', array( 'key' => ALCATEIA_TRACKING_CODE_META, 'compare' => 'NOT EXISTS' ), array( 'key' => ALCATEIA_TRACKING_CODE_META, 'value' => '' ) );
		}
		return array();
	}
}
