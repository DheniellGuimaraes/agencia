<?php
/**
 * Admin integration for Alcateia labels.
 *
 * @package AlcateiaLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles menus, buttons and bulk actions.
 */
class Alcateia_Labels_Admin {
	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'render_order_button' ) );
		add_action( 'admin_post_alcateia_generate_label', array( __CLASS__, 'handle_single_label' ) );
		add_action( 'admin_post_alcateia_generate_labels_bulk', array( __CLASS__, 'handle_bulk_label' ) );
		add_action( 'admin_post_alcateia_bulk_tool', array( __CLASS__, 'handle_bulk_tool' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_bulk_notices' ) );
		add_action( 'admin_post_alcateia_clear_logs', array( __CLASS__, 'handle_clear_logs' ) );

		add_filter( 'bulk_actions-edit-shop_order', array( __CLASS__, 'register_legacy_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( __CLASS__, 'handle_legacy_bulk_action' ), 10, 3 );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'register_hpos_bulk_action' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'handle_hpos_bulk_action' ), 10, 3 );
	}


	/**
	 * Render bulk tracking result notices.
	 */
	public static function render_bulk_notices(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_GET['alcateia_tracking_bulk_sent'] ) ) {
			return;
		}
		$sent    = absint( $_GET['alcateia_tracking_bulk_sent'] );
		$skipped = absint( $_GET['alcateia_tracking_bulk_skipped'] ?? 0 );
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sprintf( __( 'Rastreamento Alcateia: %1$d e-mail(s) enviados, %2$d pedido(s) ignorados por falta de rastreio, duplicidade ou erro.', 'emissao-de-etiquetas-alcateia' ), $sent, $skipped ) ) . '</p></div>';
	}

	/**
	 * Create admin menu.
	 */
	public static function register_menu(): void {
		add_menu_page(
			esc_html__( 'Etiquetas Alcateia', 'emissao-de-etiquetas-alcateia' ),
			esc_html__( 'Etiquetas Alcateia', 'emissao-de-etiquetas-alcateia' ),
			'manage_woocommerce',
			'alcateia-labels',
			array( __CLASS__, 'render_admin_page' ),
			'dashicons-tag',
			56
		);
	}

	/**
	 * Load admin assets only where useful.
	 */
	public static function enqueue_assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_order_screen = $screen && in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );
		if ( 'toplevel_page_alcateia-labels' !== $hook && ! $is_order_screen ) {
			return;
		}

		wp_enqueue_style( 'alcateia-labels-admin', ALCATEIA_LABELS_URL . 'assets/css/admin.css', array(), ALCATEIA_LABELS_VERSION );
		wp_enqueue_script( 'alcateia-labels-admin', ALCATEIA_LABELS_URL . 'assets/js/admin.js', array(), ALCATEIA_LABELS_VERSION, true );
	}

	/**
	 * Render plugin dashboard.
	 */
	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'emissao-de-etiquetas-alcateia' ) );
		}

		$tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'dashboard' ) );
		$cards = array(
			array( 'icon' => '📦', 'title' => __( 'Pedidos recentes', 'emissao-de-etiquetas-alcateia' ), 'text' => __( 'Acompanhe pedidos novos e prepare a expedição com agilidade.', 'emissao-de-etiquetas-alcateia' ) ),
			array( 'icon' => '🏷️', 'title' => __( 'Gerar etiqueta individual', 'emissao-de-etiquetas-alcateia' ), 'text' => __( 'Abra um pedido WooCommerce e gere uma etiqueta interna em PDF ou HTML imprimível.', 'emissao-de-etiquetas-alcateia' ) ),
			array( 'icon' => '🧾', 'title' => __( 'Gerar etiquetas em lote', 'emissao-de-etiquetas-alcateia' ), 'text' => __( 'Selecione vários pedidos na listagem e gere um arquivo único com uma etiqueta por página.', 'emissao-de-etiquetas-alcateia' ) ),
			array( 'icon' => '⚙️', 'title' => __( 'Configurações', 'emissao-de-etiquetas-alcateia' ), 'text' => __( 'Defina remetente, mensagens e envio automático de rastreio.', 'emissao-de-etiquetas-alcateia' ) ),
		);
		?>
		<div class="alcateia-admin-wrap">
			<div class="alcateia-aurora alcateia-aurora-one"></div>
			<div class="alcateia-aurora alcateia-aurora-two"></div>
			<section class="alcateia-hero alcateia-glass">
				<img class="alcateia-hero-logo" src="<?php echo esc_url( ALCATEIA_LABELS_LOGO_URL ); ?>" alt="<?php echo esc_attr__( 'Alcateia Editorial', 'emissao-de-etiquetas-alcateia' ); ?>">
				<p class="alcateia-kicker"><?php echo esc_html__( 'WooCommerce • Expedição interna', 'emissao-de-etiquetas-alcateia' ); ?></p>
				<h1><?php echo esc_html__( 'Emissão de Etiquetas Alcateia', 'emissao-de-etiquetas-alcateia' ); ?></h1>
				<p class="alcateia-subtitle"><?php echo esc_html__( 'Gere etiquetas internas de expedição com rapidez, organização e visual profissional.', 'emissao-de-etiquetas-alcateia' ); ?></p>
			</section>
			<?php self::render_tabs( $tab ); ?>
			<?php if ( 'diagnostico' === $tab ) : ?>
				<?php self::render_diagnostics(); ?>
			<?php elseif ( 'ajuda' === $tab ) : ?>
				<?php self::render_help(); ?>
			<?php else : ?>
				<div class="alcateia-card-grid">
					<?php foreach ( $cards as $card ) : ?>
						<article class="alcateia-card alcateia-glass">
							<span class="alcateia-card-icon"><?php echo esc_html( $card['icon'] ); ?></span>
							<h2><?php echo esc_html( $card['title'] ); ?></h2>
							<p><?php echo esc_html( $card['text'] ); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
				<div class="alcateia-quick-actions alcateia-glass">
					<h2><?php echo esc_html__( 'Ações rápidas', 'emissao-de-etiquetas-alcateia' ); ?></h2>
					<p><?php echo esc_html__( 'Acesse os pedidos para cadastrar e enviar códigos de rastreio aos clientes.', 'emissao-de-etiquetas-alcateia' ); ?></p>
					<a class="button button-primary alcateia-tracking-cta" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>"><?php echo esc_html__( 'ENVIAR CÓDIGO DE RASTREIO', 'emissao-de-etiquetas-alcateia' ); ?></a>
				</div>
				<?php Alcateia_Settings::render(); ?>
			<?php endif; ?>
		</div>
		<?php
	}


	/** Render admin page tabs. */
	private static function render_tabs( string $active ): void {
		$tabs = array(
			'dashboard'   => __( 'Configurações', 'emissao-de-etiquetas-alcateia' ),
			'diagnostico' => __( 'Diagnóstico', 'emissao-de-etiquetas-alcateia' ),
			'ajuda'       => __( 'Ajuda', 'emissao-de-etiquetas-alcateia' ),
		);
		echo '<nav class="alcateia-tabs">';
		foreach ( $tabs as $tab => $label ) {
			$url = add_query_arg( array( 'page' => 'alcateia-labels', 'tab' => $tab ), admin_url( 'admin.php' ) );
			echo '<a class="' . esc_attr( $active === $tab ? 'is-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/** Render diagnostics panel. */
	private static function render_diagnostics(): void {
		$uploads = wp_upload_dir();
		$logs    = Alcateia_Logger::get_logs();
		$rows    = array(
			__( 'Versão do plugin', 'emissao-de-etiquetas-alcateia' )      => ALCATEIA_LABELS_VERSION,
			__( 'Versão do WordPress', 'emissao-de-etiquetas-alcateia' )   => get_bloginfo( 'version' ),
			__( 'Versão do WooCommerce', 'emissao-de-etiquetas-alcateia' ) => defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Indisponível', 'emissao-de-etiquetas-alcateia' ),
			__( 'Versão do PHP', 'emissao-de-etiquetas-alcateia' )         => PHP_VERSION,
			__( 'Dompdf', 'emissao-de-etiquetas-alcateia' )                => Alcateia_Labels_Generator::is_dompdf_available() ? __( 'Disponível', 'emissao-de-etiquetas-alcateia' ) : __( 'Indisponível', 'emissao-de-etiquetas-alcateia' ),
			__( 'HPOS', 'emissao-de-etiquetas-alcateia' )                  => self::is_hpos_enabled() ? __( 'Ativo', 'emissao-de-etiquetas-alcateia' ) : __( 'Inativo', 'emissao-de-etiquetas-alcateia' ),
			__( 'Permissão manage_woocommerce', 'emissao-de-etiquetas-alcateia' ) => current_user_can( 'manage_woocommerce' ) ? __( 'Sim', 'emissao-de-etiquetas-alcateia' ) : __( 'Não', 'emissao-de-etiquetas-alcateia' ),
			__( 'Diretório de uploads', 'emissao-de-etiquetas-alcateia' )   => empty( $uploads['error'] ) && wp_is_writable( $uploads['basedir'] ) ? __( 'Gravável', 'emissao-de-etiquetas-alcateia' ) : __( 'Sem escrita ou indisponível', 'emissao-de-etiquetas-alcateia' ),
			__( 'Diretório do plugin', 'emissao-de-etiquetas-alcateia' )    => is_readable( ALCATEIA_LABELS_PATH ) ? __( 'Legível', 'emissao-de-etiquetas-alcateia' ) : __( 'Indisponível', 'emissao-de-etiquetas-alcateia' ),
		);
		$clear_url = wp_nonce_url( admin_url( 'admin-post.php?action=alcateia_clear_logs' ), 'alcateia_clear_logs' );
		?>
		<section class="alcateia-panel alcateia-glass"><h2><?php echo esc_html__( 'Diagnóstico', 'emissao-de-etiquetas-alcateia' ); ?></h2><table class="alcateia-diagnostics"><tbody><?php foreach ( $rows as $label => $value ) : ?><tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( (string) $value ); ?></td></tr><?php endforeach; ?></tbody></table></section>
		<section class="alcateia-panel alcateia-glass"><h2><?php echo esc_html__( 'Últimos erros e eventos registrados', 'emissao-de-etiquetas-alcateia' ); ?></h2><p><a class="button" href="<?php echo esc_url( $clear_url ); ?>"><?php echo esc_html__( 'Limpar logs', 'emissao-de-etiquetas-alcateia' ); ?></a></p><?php if ( empty( $logs ) ) : ?><p><?php echo esc_html__( 'Nenhum log registrado.', 'emissao-de-etiquetas-alcateia' ); ?></p><?php else : ?><table class="alcateia-diagnostics"><thead><tr><th><?php echo esc_html__( 'Data', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Evento', 'emissao-de-etiquetas-alcateia' ); ?></th><th><?php echo esc_html__( 'Mensagem', 'emissao-de-etiquetas-alcateia' ); ?></th></tr></thead><tbody><?php foreach ( $logs as $log ) : ?><tr><td><?php echo esc_html( $log['time'] ?? '' ); ?></td><td><?php echo esc_html( $log['event'] ?? '' ); ?></td><td><?php echo esc_html( $log['message'] ?? '' ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
		<?php
	}

	/** Render help page. */
	private static function render_help(): void {
		$items = array(
			__( 'Etiqueta individual: abra um pedido WooCommerce e clique em Gerar Etiqueta Alcateia ou Imprimir etiqueta.', 'emissao-de-etiquetas-alcateia' ),
			__( 'Etiquetas em lote: selecione pedidos na listagem e use a ação em massa Gerar Etiquetas Alcateia.', 'emissao-de-etiquetas-alcateia' ),
			__( 'Rastreio: abra o pedido, preencha o painel Rastreamento Alcateia e salve.', 'emissao-de-etiquetas-alcateia' ),
			__( 'Enviar rastreio: após cadastrar o código, use o botão Enviar rastreio ao cliente; reenvios exigem ação explícita.', 'emissao-de-etiquetas-alcateia' ),
			__( 'Picking list: selecione pedidos e use a ação em massa Gerar picking list Alcateia.', 'emissao-de-etiquetas-alcateia' ),
			__( 'Romaneio: selecione pedidos e use a ação em massa Gerar romaneio Alcateia.', 'emissao-de-etiquetas-alcateia' ),
			__( 'Etiqueta interna não é etiqueta oficial dos Correios ou de transportadora; ela serve para separação, embalagem e expedição interna.', 'emissao-de-etiquetas-alcateia' ),
		);
		echo '<section class="alcateia-panel alcateia-glass"><h2>' . esc_html__( 'Ajuda', 'emissao-de-etiquetas-alcateia' ) . '</h2><ol>';
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ol></section>';
	}

	/** Clear logs endpoint. */
	public static function handle_clear_logs(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'alcateia_clear_logs' ) ) {
			Alcateia_Logger::log( 'permission_error', __( 'Tentativa inválida de limpar logs.', 'emissao-de-etiquetas-alcateia' ) );
			wp_die( esc_html__( 'Solicitação inválida.', 'emissao-de-etiquetas-alcateia' ) );
		}
		Alcateia_Logger::clear();
		wp_safe_redirect( add_query_arg( array( 'page' => 'alcateia-labels', 'tab' => 'diagnostico', 'logs_cleared' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Detect if WooCommerce HPOS is enabled. */
	private static function is_hpos_enabled(): bool {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}

	/**
	 * Add button inside order admin page.
	 */
	public static function render_order_button( WC_Order $order ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=alcateia_generate_label&order_id=' . absint( $order->get_id() ) ),
			'alcateia_generate_label_' . absint( $order->get_id() )
		);

		$print_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=alcateia_generate_label&print_html=1&order_id=' . absint( $order->get_id() ) ),
			'alcateia_generate_label_' . absint( $order->get_id() )
		);

		echo '<p class="alcateia-order-action"><a class="button button-primary alcateia-order-button" target="_blank" href="' . esc_url( $url ) . '">' . esc_html__( 'Gerar Etiqueta Alcateia', 'emissao-de-etiquetas-alcateia' ) . '</a> <a class="button" target="_blank" href="' . esc_url( $print_url ) . '">' . esc_html__( 'Imprimir etiqueta', 'emissao-de-etiquetas-alcateia' ) . '</a></p>';
	}

	/**
	 * Single label request.
	 */
	public static function handle_single_label(): void {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'alcateia_generate_label_' . $order_id ) ) {
			Alcateia_Logger::log( 'permission_error', __( 'Solicitação inválida para gerar etiqueta.', 'emissao-de-etiquetas-alcateia' ) );
			wp_die( esc_html__( 'Solicitação inválida.', 'emissao-de-etiquetas-alcateia' ) );
		}

		$force_html = ! empty( $_GET['print_html'] );
		Alcateia_Labels_Generator::output( array( $order_id ), 'thermal', $force_html );
	}

	/**
	 * Register bulk action in legacy orders list.
	 */
	public static function register_legacy_bulk_action( array $actions ): array {
		$actions['alcateia_generate_labels'] = __( 'Gerar Etiquetas Alcateia', 'emissao-de-etiquetas-alcateia' );
		$actions['alcateia_send_tracking'] = __( 'Enviar rastreio Alcateia', 'emissao-de-etiquetas-alcateia' );
		$actions['alcateia_picking_list'] = __( 'Gerar picking list Alcateia', 'emissao-de-etiquetas-alcateia' );
		$actions['alcateia_manifest'] = __( 'Gerar romaneio Alcateia', 'emissao-de-etiquetas-alcateia' );
		return $actions;
	}

	/**
	 * Register bulk action in HPOS orders list.
	 */
	public static function register_hpos_bulk_action( array $actions ): array {
		return self::register_legacy_bulk_action( $actions );
	}

	/**
	 * Handle legacy bulk action.
	 */
	public static function handle_legacy_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		return self::bulk_redirect_or_passthrough( $redirect_to, $action, $post_ids );
	}

	/**
	 * Handle HPOS bulk action.
	 */
	public static function handle_hpos_bulk_action( string $redirect_to, string $action, array $order_ids ): string {
		return self::bulk_redirect_or_passthrough( $redirect_to, $action, $order_ids );
	}

	/**
	 * Redirect selected orders to a nonce-protected admin-post endpoint.
	 */
	private static function bulk_redirect_or_passthrough( string $redirect_to, string $action, array $order_ids ): string {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect_to;
		}

		$ids = array_filter( array_map( 'absint', $order_ids ) );
		$tools = array(
			'alcateia_generate_labels' => 'labels',
			'alcateia_send_tracking'   => 'tracking',
			'alcateia_picking_list'    => 'picking',
			'alcateia_manifest'        => 'manifest',
		);
		if ( ! isset( $tools[ $action ] ) ) {
			return $redirect_to;
		}

		$url = add_query_arg(
			array(
				'action'    => 'alcateia_bulk_tool',
				'tool'      => $tools[ $action ],
				'order_ids' => implode( ',', $ids ),
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, 'alcateia_bulk_tool' );
	}

	/**
	 * Bulk label request.
	 */
	public static function handle_bulk_label(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'alcateia_generate_labels_bulk' ) ) {
			Alcateia_Logger::log( 'permission_error', __( 'Solicitação inválida de geração de etiquetas em lote.', 'emissao-de-etiquetas-alcateia' ) );
			wp_die( esc_html__( 'Solicitação inválida.', 'emissao-de-etiquetas-alcateia' ) );
		}
		$order_ids = isset( $_GET['order_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['order_ids'] ) ) : '';
		$ids       = array_filter( array_map( 'absint', explode( ',', $order_ids ) ) );
		Alcateia_Labels_Generator::output( $ids );
	}

	/**
	 * Dispatch bulk tools: labels, tracking e-mails, picking list and manifest.
	 */
	public static function handle_bulk_tool(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'alcateia_bulk_tool' ) ) {
			Alcateia_Logger::log( 'permission_error', __( 'Solicitação inválida de ferramenta em lote.', 'emissao-de-etiquetas-alcateia' ) );
			wp_die( esc_html__( 'Solicitação inválida.', 'emissao-de-etiquetas-alcateia' ) );
		}

		$order_ids = isset( $_GET['order_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['order_ids'] ) ) : '';
		$ids       = array_filter( array_map( 'absint', explode( ',', $order_ids ) ) );
		$tool      = sanitize_key( wp_unslash( $_GET['tool'] ?? 'labels' ) );

		if ( 'tracking' === $tool ) {
			$sent = 0;
			$skipped = 0;
			foreach ( $ids as $id ) {
				if ( Alcateia_Tracking::send_tracking_email( $id, true ) ) {
					$sent++;
				} else {
					$skipped++;
				}
			}
			wp_safe_redirect( add_query_arg( array( 'alcateia_tracking_bulk_sent' => $sent, 'alcateia_tracking_bulk_skipped' => $skipped ), wp_get_referer() ?: admin_url( 'edit.php?post_type=shop_order' ) ) );
			exit;
		}

		if ( 'picking' === $tool ) {
			Alcateia_Logger::log( 'picking_list', __( 'Picking list gerada.', 'emissao-de-etiquetas-alcateia' ), array( 'orders' => implode( ',', $ids ) ) );
			Alcateia_Picking_List::output( $ids );
		}
		if ( 'manifest' === $tool ) {
			Alcateia_Logger::log( 'manifest', __( 'Romaneio gerado.', 'emissao-de-etiquetas-alcateia' ), array( 'orders' => implode( ',', $ids ) ) );
			Alcateia_Manifest::output( $ids );
		}

		Alcateia_Labels_Generator::output( $ids );
	}
}
