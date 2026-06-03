<?php
/**
 * Admin screens.
 *
 * @package WooCommerceContaAzulSync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI manager.
 */
class WCAS_Admin {
	private WCAS_Auth $auth;

	public function __construct( WCAS_Auth $auth ) {
		$this->auth = $auth;
	}

	/**
	 * Register admin hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wcas_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_wcas_disconnect', array( $this, 'disconnect' ) );
		add_action( 'admin_post_wcas_clear_logs', array( $this, 'clear_logs' ) );
		add_action( 'admin_post_wcas_connect', array( $this, 'connect' ) );
		add_action( 'admin_post_wcas_connect_conta_azul', array( $this, 'connect' ) );
		add_action( 'admin_post_wcas_test_credentials', array( $this, 'test_credentials' ) );
		add_action( 'admin_post_wcas_test_oauth_url', array( $this, 'test_oauth_url' ) );
		add_action( 'admin_post_wcas_test_redirect_uri', array( $this, 'test_redirect_uri' ) );
		add_action( 'admin_post_wcas_test_permissions', array( $this, 'test_permissions' ) );
		add_action( 'admin_post_wcas_validate_callback', array( $this, 'validate_callback' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WCAS_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add WooCommerce submenu.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Conta Azul', 'woocommerce-conta-azul-sync' ),
			__( 'Conta Azul', 'woocommerce-conta-azul-sync' ),
			'manage_woocommerce',
			'wcas-conta-azul',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'wcas-conta-azul' ) ) {
			return;
		}
		wp_enqueue_style( 'wcas-admin', WCAS_PLUGIN_URL . 'assets/admin.css', array(), WCAS_VERSION );
		wp_enqueue_script( 'wcas-admin', WCAS_PLUGIN_URL . 'assets/admin.js', array(), WCAS_VERSION, true );
	}

	/**
	 * Settings link in plugins page.
	 *
	 * @param array<int, string> $links Links.
	 * @return array<int, string>
	 */
	public function plugin_action_links( array $links ): array {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wcas-conta-azul' ) ) . '">' . esc_html__( 'Configurações', 'woocommerce-conta-azul-sync' ) . '</a>';
		return $links;
	}

	/**
	 * Save settings action.
	 */
	public function save_settings(): void {
		$this->verify_action();
		$raw_settings = isset( $_POST['wcas'] ) && is_array( $_POST['wcas'] ) ? wp_unslash( $_POST['wcas'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		WCAS_Utils::save_settings( $raw_settings );
		WCAS_Logger::log( 'settings', 'Configurações da integração atualizadas.' );
		$this->redirect( 'settings_saved' );
	}

	/**
	 * Start OAuth connection.
	 */
	public function connect(): void {
		$this->verify_action();
		$settings = WCAS_Utils::get_settings();
		WCAS_Logger::oauth_trace(
			'connect_button_clicked',
			'sent',
			'started',
			'Botão Conectar Conta Azul acionado; nonce e capability aprovados.',
			array_merge(
				$this->build_oauth_diagnostic_context( $settings ),
				array(
					'timestamp'             => current_time( 'mysql' ),
					'http_method'           => 'POST',
					'nonce_validation'      => 'passed',
					'capability_validation' => 'passed',
				)
			)
		);

		if ( '' === (string) $settings['client_id'] || '' === (string) $settings['client_secret'] ) {
			WCAS_Logger::oauth_trace( 'connect_precheck', 'error', 'error', 'Client ID ou Client Secret ausente antes do redirect OAuth2.', $this->build_oauth_diagnostic_context( $settings ) );
			$this->redirect( 'missing_credentials', 'logs' );
		}

		try {
			$authorization_url = $this->auth->get_authorization_url();
			$redirect_host     = (string) wp_parse_url( $authorization_url, PHP_URL_HOST );
			$redirect_allowed  = str_starts_with( $authorization_url, 'https://auth.contaazul.com/' );

			if ( ! $redirect_allowed ) {
				WCAS_Logger::oauth_trace(
					'invalid_oauth_redirect_host',
					'error',
					'error',
					'Redirect OAuth externo bloqueado porque a URL não pertence ao host oficial auth.contaazul.com.',
					array(
						'authorization_url' => WCAS_Utils::redact_url_query( $authorization_url ),
						'redirect_method'   => 'blocked',
						'redirect_host'     => $redirect_host,
						'redirect_allowed'  => false,
					)
				);
				$this->redirect( 'auth_failed', 'logs' );
			}

			WCAS_Logger::oauth_trace(
				'external_oauth_redirect_started',
				'sent',
				'success',
				'Redirect externo OAuth para Conta Azul iniciado com wp_redirect.',
				array(
					'url_complete'        => $authorization_url,
					'authorization_url'   => WCAS_Utils::redact_url_query( $authorization_url ),
					'query_string_sent'   => WCAS_Utils::get_url_query_args( $authorization_url ),
					'headers_sent_to_ca'  => array( 'Location' => $authorization_url, 'Status' => '302 Found', 'Client-Secret' => 'not_sent' ),
					'timestamp'           => current_time( 'mysql' ),
					'redirect_method'     => 'wp_redirect',
					'redirect_host'     => $redirect_host,
					'redirect_allowed'  => true,
				)
			);
			wp_redirect( $authorization_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		} catch ( Throwable $throwable ) {
			WCAS_Logger::oauth_trace( 'connect_redirect', 'error', 'error', 'Erro antes do redirect OAuth2: ' . $throwable->getMessage(), $this->build_oauth_diagnostic_context( $settings ) );
			$this->redirect( 'auth_failed', 'logs' );
		}
	}

	/**
	 * Disconnect action.
	 */
	public function disconnect(): void {
		$this->verify_action();
		$this->auth->disconnect();
		$this->redirect( 'disconnected' );
	}

	/**
	 * Clear logs action.
	 */
	public function clear_logs(): void {
		$this->verify_action();
		WCAS_Logger::clear();
		$this->redirect( 'logs_cleared', 'logs' );
	}

	/** Test credentials presence. */
	public function test_credentials(): void {
		$this->verify_action();
		$settings = WCAS_Utils::get_settings();
		$ok = '' !== (string) $settings['client_id'] && '' !== (string) $settings['client_secret'];
		WCAS_Logger::diagnostic( 'config', 'test_credentials', $ok ? 'success' : 'warning', $ok ? 'Client ID e Client Secret existem. O secret não foi exibido ou gravado em texto puro.' : 'Client ID ou Client Secret ausente.', $this->build_oauth_diagnostic_context( $settings ) );
		$this->redirect( 'diagnostic_completed', 'logs' );
	}

	/** Test OAuth URL generation. */
	public function test_oauth_url(): void {
		$this->verify_action();
		$settings = WCAS_Utils::get_settings();
		try {
			$url = $this->auth->get_authorization_url();
			WCAS_Logger::diagnostic( 'oauth', 'test_oauth_url', 'success', 'URL OAuth2 gerada com response_type=code, client_id, redirect_uri, state e scope.', array_merge( $this->build_oauth_diagnostic_context( $settings ), array( 'authorization_url' => WCAS_Utils::redact_url_query( $url ) ) ) );
		} catch ( Throwable $throwable ) {
			WCAS_Logger::diagnostic( 'oauth', 'test_oauth_url', 'error', 'Falha ao gerar URL OAuth2: ' . $throwable->getMessage(), $this->build_oauth_diagnostic_context( $settings ) );
		}
		$this->redirect( 'diagnostic_completed', 'logs' );
	}

	/** Test Redirect URI. */
	public function test_redirect_uri(): void {
		$this->verify_action();
		$settings = WCAS_Utils::get_settings();
		$redirect_uri = (string) ( $settings['redirect_uri'] ?? '' );
		$valid = '' !== $redirect_uri && wp_http_validate_url( $redirect_uri );
		WCAS_Logger::diagnostic( 'config', 'test_redirect_uri', $valid ? 'success' : 'warning', $valid ? 'Redirect URI preenchida e válida para HTTP/HTTPS.' : 'Redirect URI ausente ou inválida.', array( 'redirect_uri' => $redirect_uri ) );
		$this->redirect( 'diagnostic_completed', 'logs' );
	}

	/** Test permissions. */
	public function test_permissions(): void {
		$this->verify_action();
		WCAS_Logger::diagnostic( 'system', 'test_permissions', 'success', 'Usuário atual passou na capability manage_woocommerce e no nonce do formulário.', array( 'capability' => 'manage_woocommerce', 'nonce' => 'passed', 'user_id' => get_current_user_id() ) );
		$this->redirect( 'diagnostic_completed', 'logs' );
	}


	/** Validate callback endpoint registration and URL. */
	public function validate_callback(): void {
		$this->verify_action();
		$settings = WCAS_Utils::get_settings();
		$diagnostic = $this->get_oauth_endpoint_diagnostics( $settings );
		WCAS_Logger::diagnostic(
			'oauth',
			'callback_url_generated',
			$diagnostic['callback_exists'] && $diagnostic['hook_registered'] ? 'success' : 'warning',
			'Validação de callback OAuth executada para identificar a URL que deve ser cadastrada no Portal da Conta Azul.',
			array(
				'callback'                  => $diagnostic['expected_return_url'],
				'type'                      => $diagnostic['callback_type'],
				'action_registered'         => 'admin_post_wcas_oauth_callback',
				'callback_handler'          => $diagnostic['callback_handler'],
				'namespace_rest_registered' => $diagnostic['rest_namespace'],
				'callback_exists'           => $diagnostic['callback_exists'],
				'hook_registered'           => $diagnostic['hook_registered'],
				'rest_route_registered'     => $diagnostic['rest_route_registered'],
				'url_accessible'            => $diagnostic['url_accessible'],
			)
		);
		$this->redirect( 'diagnostic_completed', 'logs' );
	}

	/**
	 * Render admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'woocommerce-conta-azul-sync' ) );
		}

		$settings = WCAS_Utils::get_settings();
		$tokens   = $this->auth->get_tokens();
		$logs     = WCAS_Logger::get_logs( 100 );
		$metrics  = $this->get_dashboard_metrics( $logs, $tokens, $settings );
		?>
		<div class="wrap wcas-wrap">
			<?php $this->render_notice(); ?>

			<section class="wcas-hero" aria-labelledby="wcas-page-title">
				<div class="wcas-hero__content">
					<p class="wcas-kicker"><?php esc_html_e( 'Integração WooCommerce + Conta Azul Pro', 'woocommerce-conta-azul-sync' ); ?></p>
					<h1 id="wcas-page-title"><?php esc_html_e( 'WooCommerce Conta Azul Sync', 'woocommerce-conta-azul-sync' ); ?></h1>
					<p class="wcas-hero__text"><?php esc_html_e( 'Painel premium para configurar OAuth2, sincronização, endpoints e logs com segurança.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
				<div class="wcas-hero__status" aria-live="polite">
					<span class="wcas-status-dot <?php echo esc_attr( $metrics['connection_class'] ); ?>"></span>
					<div>
						<strong><?php echo esc_html( $metrics['connection_label'] ); ?></strong>
						<span><?php echo esc_html( $metrics['token_label'] ); ?></span>
					</div>
				</div>
			</section>

			<?php $this->render_status_cards( $metrics ); ?>

			<nav class="wcas-tabs" aria-label="<?php esc_attr_e( 'Navegação do painel Conta Azul', 'woocommerce-conta-azul-sync' ); ?>">
				<?php foreach ( $this->get_tabs() as $tab_id => $label ) : ?>
					<a class="wcas-tab" href="#wcas-panel-<?php echo esc_attr( $tab_id ); ?>" data-wcas-tab="<?php echo esc_attr( $tab_id ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wcas-dashboard-form">
				<input type="hidden" name="action" value="wcas_save_settings" />
				<?php wp_nonce_field( WCAS_Utils::NONCE_ACTION ); ?>
				<?php $this->render_connection_panel( $settings, $tokens, $metrics ); ?>
				<?php $this->render_sync_panel( $settings, $metrics ); ?>
				<?php $this->render_endpoints_panel( $settings ); ?>
				<div class="wcas-save-bar">
					<?php submit_button( __( 'Salvar configurações', 'woocommerce-conta-azul-sync' ), 'primary wcas-button wcas-button--primary', 'submit', false ); ?>
					<span><?php esc_html_e( 'As alterações são salvas com nonce, sanitização e capability manage_woocommerce.', 'woocommerce-conta-azul-sync' ); ?></span>
				</div>
			</form>
			<?php $this->render_oauth_action_forms(); ?>

			<?php $this->render_logs_panel( $logs ); ?>
			<?php $this->render_oauth_trace_panel(); ?>
			<?php $this->render_tests_panel( $settings, $metrics ); ?>
			<?php $this->render_help_panel(); ?>
		</div>
		<?php
	}

	/**
	 * Dashboard tabs.
	 *
	 * @return array<string, string>
	 */
	private function get_tabs(): array {
		return array(
			'connection' => __( 'Conexão API', 'woocommerce-conta-azul-sync' ),
			'sync'       => __( 'Sincronização', 'woocommerce-conta-azul-sync' ),
			'endpoints'  => __( 'Endpoints', 'woocommerce-conta-azul-sync' ),
			'logs'       => __( 'Logs / Diagnóstico', 'woocommerce-conta-azul-sync' ),
			'oauth-trace'=> __( 'OAuth Full Trace', 'woocommerce-conta-azul-sync' ),
			'tests'      => __( 'Testes', 'woocommerce-conta-azul-sync' ),
			'help'       => __( 'Ajuda', 'woocommerce-conta-azul-sync' ),
		);
	}

	/**
	 * Dashboard metrics from safe local data.
	 *
	 * @param array<int, object>     $logs Logs.
	 * @param array<string, mixed>   $tokens Tokens.
	 * @param array<string, mixed>   $settings Settings.
	 * @return array<string, mixed>
	 */
	private function get_dashboard_metrics( array $logs, array $tokens, array $settings ): array {
		$connected     = $this->auth->is_connected();
		$expires_at    = (int) ( $tokens['expires_at'] ?? 0 );
		$token_expired = $connected && $expires_at > 0 && $expires_at <= time();
		$last_sync     = '';
		$errors        = 0;

		foreach ( $logs as $log ) {
			$type = (string) ( $log->type ?? '' );
			if ( '' === $last_sync && in_array( $type, array( 'order_synced', 'customer_created', 'product_created' ), true ) ) {
				$last_sync = (string) $log->created_at;
			}
			if ( str_contains( $type, 'error' ) ) {
				++$errors;
			}
		}

		return array(
			'connected'        => $connected,
			'token_expired'    => $token_expired,
			'connection_class' => $connected ? ( $token_expired ? 'wcas-is-warning' : 'wcas-is-success' ) : 'wcas-is-danger',
			'connection_label' => $connected ? __( 'Conta Azul conectada', 'woocommerce-conta-azul-sync' ) : __( 'Conta Azul desconectada', 'woocommerce-conta-azul-sync' ),
			'token_label'      => $connected ? ( $token_expired ? __( 'Token expirado', 'woocommerce-conta-azul-sync' ) : __( 'Token válido/renovável', 'woocommerce-conta-azul-sync' ) ) : __( 'Configure OAuth2 para conectar', 'woocommerce-conta-azul-sync' ),
			'sync_active'      => 'yes' === ( $settings['enabled'] ?? 'no' ) && 'yes' === ( $settings['auto_sync_orders'] ?? 'no' ),
			'last_sync'        => $last_sync,
			'errors'           => $errors,
		);
	}

	/** Render top status cards. */
	private function render_status_cards( array $metrics ): void {
		$cards = array(
			array( 'label' => __( 'Conexão', 'woocommerce-conta-azul-sync' ), 'value' => $metrics['connection_label'], 'hint' => $metrics['token_label'], 'class' => $metrics['connection_class'], 'icon' => '●' ),
			array( 'label' => __( 'Sincronização', 'woocommerce-conta-azul-sync' ), 'value' => $metrics['sync_active'] ? __( 'Ativa', 'woocommerce-conta-azul-sync' ) : __( 'Inativa', 'woocommerce-conta-azul-sync' ), 'hint' => __( 'Pedidos processing/completed', 'woocommerce-conta-azul-sync' ), 'class' => $metrics['sync_active'] ? 'wcas-is-success' : 'wcas-is-muted', 'icon' => '↻' ),
			array( 'label' => __( 'Último sync', 'woocommerce-conta-azul-sync' ), 'value' => $metrics['last_sync'] ?: __( 'Ainda não executado', 'woocommerce-conta-azul-sync' ), 'hint' => __( 'Baseado nos logs locais', 'woocommerce-conta-azul-sync' ), 'class' => $metrics['last_sync'] ? 'wcas-is-success' : 'wcas-is-muted', 'icon' => '⏱' ),
			array( 'label' => __( 'Erros recentes', 'woocommerce-conta-azul-sync' ), 'value' => (string) $metrics['errors'], 'hint' => __( 'Últimos 100 registros', 'woocommerce-conta-azul-sync' ), 'class' => $metrics['errors'] > 0 ? 'wcas-is-danger' : 'wcas-is-success', 'icon' => '!' ),
		);
		?>
		<div class="wcas-status-grid" aria-label="<?php esc_attr_e( 'Indicadores da integração', 'woocommerce-conta-azul-sync' ); ?>">
			<?php foreach ( $cards as $card ) : ?>
				<div class="wcas-status-card <?php echo esc_attr( $card['class'] ); ?>">
					<span class="wcas-status-card__icon" aria-hidden="true"><?php echo esc_html( $card['icon'] ); ?></span>
					<div>
						<span class="wcas-status-card__label"><?php echo esc_html( $card['label'] ); ?></span>
						<strong><?php echo esc_html( $card['value'] ); ?></strong>
						<small><?php echo esc_html( $card['hint'] ); ?></small>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/** Render connection settings panel. */
	private function render_connection_panel( array $settings, array $tokens, array $metrics ): void {
		?>
		<section class="wcas-panel wcas-card" id="wcas-panel-connection" data-wcas-panel="connection" aria-labelledby="wcas-connection-title">
			<div class="wcas-card__header">
				<div>
					<span class="wcas-eyebrow"><?php esc_html_e( 'OAuth2 seguro', 'woocommerce-conta-azul-sync' ); ?></span>
					<h2 id="wcas-connection-title"><?php esc_html_e( 'Conexão Conta Azul', 'woocommerce-conta-azul-sync' ); ?></h2>
					<p><?php esc_html_e( 'Configure credenciais, redirecionamento e ambiente sem expor tokens ou secrets.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
				<span class="wcas-badge <?php echo esc_attr( $metrics['connection_class'] ); ?>"><?php echo esc_html( $metrics['connection_label'] ); ?></span>
			</div>

			<div class="wcas-field-grid">
				<?php $this->checkbox_field( 'enabled', __( 'Ativar integração', 'woocommerce-conta-azul-sync' ), $settings, __( 'Habilita chamadas automáticas quando OAuth2 estiver conectado.', 'woocommerce-conta-azul-sync' ) ); ?>
				<?php $this->text_field( 'client_id', __( 'Client ID', 'woocommerce-conta-azul-sync' ), $settings ); ?>
				<?php $this->password_field( 'client_secret', __( 'Client Secret', 'woocommerce-conta-azul-sync' ), $settings ); ?>
				<div class="wcas-field wcas-field--readonly wcas-field--wide">
					<span class="wcas-field__label"><?php esc_html_e( 'Redirect URI automática', 'woocommerce-conta-azul-sync' ); ?></span>
					<code><?php echo esc_html( WCAS_Utils::get_oauth_callback_url() ); ?></code>
					<small><?php esc_html_e( 'Copie exatamente esta URL e cadastre no Portal do Desenvolvedor da Conta Azul. Este valor não é editável para evitar redirect_mismatch.', 'woocommerce-conta-azul-sync' ); ?></small>
				</div>
				<?php $this->select_field( 'environment', __( 'Ambiente', 'woocommerce-conta-azul-sync' ), $settings, array( 'production' => __( 'Produção', 'woocommerce-conta-azul-sync' ), 'development' => __( 'Desenvolvimento / conta teste', 'woocommerce-conta-azul-sync' ) ), __( 'A documentação informa que não há sandbox dedicado; use conta teste quando aplicável.', 'woocommerce-conta-azul-sync' ) ); ?>
				<div class="wcas-field wcas-field--readonly">
					<span class="wcas-field__label"><?php esc_html_e( 'Status do token', 'woocommerce-conta-azul-sync' ); ?></span>
					<strong><?php echo esc_html( $metrics['token_label'] ); ?></strong>
					<?php if ( ! empty( $tokens['expires_at'] ) ) : ?>
						<small><?php echo esc_html( sprintf( __( 'Expira em: %s', 'woocommerce-conta-azul-sync' ), wp_date( 'Y-m-d H:i:s', (int) $tokens['expires_at'] ) ) ); ?></small>
					<?php endif; ?>
				</div>
			</div>

			<div class="wcas-actions wcas-actions--hero">
				<button type="submit" form="wcas-connect-form" class="button button-primary wcas-button wcas-button--primary"><?php esc_html_e( 'Conectar Conta Azul', 'woocommerce-conta-azul-sync' ); ?></button>
				<button type="submit" form="wcas-disconnect-form" class="button wcas-button wcas-button--ghost"><?php esc_html_e( 'Desconectar', 'woocommerce-conta-azul-sync' ); ?></button>
			</div>
		</section>
		<?php
	}

	/** Render sync panel. */
	private function render_sync_panel( array $settings, array $metrics ): void {
		?>
		<section class="wcas-panel wcas-card" id="wcas-panel-sync" data-wcas-panel="sync" aria-labelledby="wcas-sync-title">
			<div class="wcas-card__header">
				<div>
					<span class="wcas-eyebrow"><?php esc_html_e( 'Automação WooCommerce', 'woocommerce-conta-azul-sync' ); ?></span>
					<h2 id="wcas-sync-title"><?php esc_html_e( 'Sincronização', 'woocommerce-conta-azul-sync' ); ?></h2>
					<p><?php esc_html_e( 'Controle quais entidades serão enviadas quando pedidos mudarem de status.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
				<span class="wcas-badge <?php echo $metrics['sync_active'] ? 'wcas-is-success' : 'wcas-is-muted'; ?>"><?php echo esc_html( $metrics['sync_active'] ? __( 'Sync ativa', 'woocommerce-conta-azul-sync' ) : __( 'Sync inativa', 'woocommerce-conta-azul-sync' ) ); ?></span>
			</div>
			<div class="wcas-toggle-grid">
				<?php $this->checkbox_field( 'auto_sync_orders', __( 'Sincronizar automaticamente pedidos', 'woocommerce-conta-azul-sync' ), $settings ); ?>
				<?php $this->checkbox_field( 'sync_products', __( 'Sincronizar produtos', 'woocommerce-conta-azul-sync' ), $settings ); ?>
				<?php $this->checkbox_field( 'auto_create_customer', __( 'Criar cliente automaticamente', 'woocommerce-conta-azul-sync' ), $settings ); ?>
				<?php $this->checkbox_field( 'register_sale', __( 'Registrar pedido como venda/receita', 'woocommerce-conta-azul-sync' ), $settings ); ?>
				<?php $this->checkbox_field( 'register_receivable', __( 'Registrar cobrança/conta a receber', 'woocommerce-conta-azul-sync' ), $settings ); ?>
				<?php $this->checkbox_field( 'detailed_log', __( 'Log detalhado', 'woocommerce-conta-azul-sync' ), $settings, __( 'Registra payloads com dados sensíveis mascarados.', 'woocommerce-conta-azul-sync' ) ); ?>
			</div>
		</section>
		<?php
	}

	/** Render endpoints panel. */
	private function render_endpoints_panel( array $settings ): void {
		?>
		<section class="wcas-panel wcas-card" id="wcas-panel-endpoints" data-wcas-panel="endpoints" aria-labelledby="wcas-endpoints-title">
			<div class="wcas-card__header">
				<div>
					<span class="wcas-eyebrow"><?php esc_html_e( 'Nova API Conta Azul', 'woocommerce-conta-azul-sync' ); ?></span>
					<h2 id="wcas-endpoints-title"><?php esc_html_e( 'Endpoints configuráveis', 'woocommerce-conta-azul-sync' ); ?></h2>
					<p><?php esc_html_e( 'TODO: valide os paths de recursos no Portal do Desenvolvedor antes de produção. Não há endpoints definitivos inventados no plugin.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
				<span class="wcas-badge wcas-is-warning"><?php esc_html_e( 'Validar paths', 'woocommerce-conta-azul-sync' ); ?></span>
			</div>
			<div class="wcas-field-grid">
				<?php $this->url_field( 'api_base_url', __( 'API Base URL', 'woocommerce-conta-azul-sync' ), $settings ); ?>
				<?php $this->url_field( 'auth_url', __( 'Authorization URL', 'woocommerce-conta-azul-sync' ), $settings ); ?>
				<?php $this->url_field( 'token_url', __( 'Token URL', 'woocommerce-conta-azul-sync' ), $settings ); ?>
				<?php foreach ( array( 'customer_search_path', 'customer_create_path', 'product_search_path', 'product_create_path', 'sale_create_path', 'sale_cancel_path', 'receivable_create_path' ) as $key ) : ?>
					<?php $this->text_field( $key, ucwords( str_replace( '_', ' ', $key ) ), $settings ); ?>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/** Render logs panel. */
	private function render_logs_panel( array $logs ): void {
		$types = array();
		foreach ( $logs as $log ) {
			$type = (string) ( $log->type ?? '' );
			if ( '' !== $type ) {
				$types[ $type ] = $type;
			}
		}
		ksort( $types );
		?>
		<section class="wcas-panel wcas-card" id="wcas-panel-logs" data-wcas-panel="logs" aria-labelledby="wcas-logs-title">
			<div class="wcas-card__header">
				<div>
					<span class="wcas-eyebrow"><?php esc_html_e( 'Auditoria segura', 'woocommerce-conta-azul-sync' ); ?></span>
					<h2 id="wcas-logs-title"><?php esc_html_e( 'Logs / Diagnóstico', 'woocommerce-conta-azul-sync' ); ?></h2>
					<p><?php esc_html_e( 'Diagnóstico detalhado do OAuth2, API, configuração, sistema e erros com dados sensíveis mascarados.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wcas_clear_logs" />
					<?php wp_nonce_field( WCAS_Utils::NONCE_ACTION ); ?>
					<?php submit_button( __( 'Limpar logs', 'woocommerce-conta-azul-sync' ), 'delete wcas-button wcas-button--ghost', 'submit', false ); ?>
				</form>
			</div>

			<?php $this->render_oauth_flow_inspector_card(); ?>
			<?php $this->render_oauth_debug_card(); ?>
			<?php $this->render_oauth_endpoints_card(); ?>

			<div class="wcas-log-filters" role="search" aria-label="<?php esc_attr_e( 'Filtros de logs', 'woocommerce-conta-azul-sync' ); ?>">
				<label for="wcas-log-search"><?php esc_html_e( 'Buscar', 'woocommerce-conta-azul-sync' ); ?></label>
				<input id="wcas-log-search" type="search" class="wcas-log-search" placeholder="<?php esc_attr_e( 'Mensagem, pedido ou contexto...', 'woocommerce-conta-azul-sync' ); ?>" />
				<label for="wcas-log-type"><?php esc_html_e( 'Tipo', 'woocommerce-conta-azul-sync' ); ?></label>
				<select id="wcas-log-type" class="wcas-log-type">
					<option value=""><?php esc_html_e( 'Todos', 'woocommerce-conta-azul-sync' ); ?></option>
					<?php foreach ( $types as $type ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<?php if ( empty( $logs ) ) : ?>
				<div class="wcas-empty-state">
					<strong><?php esc_html_e( 'Nenhum log ainda', 'woocommerce-conta-azul-sync' ); ?></strong>
					<p><?php esc_html_e( 'Conecte a Conta Azul ou execute uma sincronização para visualizar eventos aqui.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
			<?php else : ?>
				<div class="wcas-table-wrap">
					<table class="wcas-log-table widefat" aria-describedby="wcas-logs-title">
						<thead><tr><th><?php esc_html_e( 'Data e hora', 'woocommerce-conta-azul-sync' ); ?></th><th><?php esc_html_e( 'Tipo', 'woocommerce-conta-azul-sync' ); ?></th><th><?php esc_html_e( 'Ação executada', 'woocommerce-conta-azul-sync' ); ?></th><th><?php esc_html_e( 'Resultado', 'woocommerce-conta-azul-sync' ); ?></th><th><?php esc_html_e( 'Mensagem técnica', 'woocommerce-conta-azul-sync' ); ?></th><th><?php esc_html_e( 'Status HTTP', 'woocommerce-conta-azul-sync' ); ?></th><th><?php esc_html_e( 'Contexto resumido', 'woocommerce-conta-azul-sync' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<?php $context = $this->decode_log_context( (string) $log->context ); ?>
							<tr data-wcas-log-row data-wcas-type="<?php echo esc_attr( $log->type ); ?>">
								<td><?php echo esc_html( $log->created_at ); ?></td>
								<td><span class="wcas-log-chip"><?php echo esc_html( $this->get_log_type_label( (string) $log->type ) ); ?></span></td>
								<td><?php echo esc_html( (string) ( $context['action'] ?? '-' ) ); ?></td>
								<td><span class="wcas-result-chip <?php echo esc_attr( $this->get_result_class( (string) ( $context['result'] ?? '' ) ) ); ?>"><?php echo esc_html( (string) ( $context['result'] ?? '-' ) ); ?></span></td>
								<td><?php echo esc_html( $log->message ); ?></td>
								<td><?php echo isset( $context['http_status'] ) && '' !== (string) $context['http_status'] ? esc_html( (string) $context['http_status'] ) : '&mdash;'; ?></td>
								<td><code><?php echo esc_html( $this->format_log_context( (string) $log->context ) ); ?></code></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="wcas-empty-state wcas-empty-state--filtered" hidden>
					<strong><?php esc_html_e( 'Nenhum log encontrado', 'woocommerce-conta-azul-sync' ); ?></strong>
					<p><?php esc_html_e( 'Ajuste os filtros para visualizar outros registros.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/** Get the most recent OAuth trace entry by action. */
	private function get_latest_oauth_trace_context( array $trace_entries, string $action ): array {
		foreach ( $trace_entries as $entry ) {
			if ( $action === (string) ( $entry['action'] ?? '' ) ) {
				return isset( $entry['context'] ) && is_array( $entry['context'] ) ? $entry['context'] : array();
			}
		}
		return array();
	}

	/** Render Redirect URI exact-match diagnostic card. */
	private function render_redirect_uri_exact_match_card( array $trace_entries ): void {
		$generated_context = $this->get_latest_oauth_trace_context( $trace_entries, 'generate_authorization_url' );
		$redirect_context  = $this->get_latest_oauth_trace_context( $trace_entries, 'external_oauth_redirect_started' );
		$callback_url      = WCAS_Utils::get_oauth_callback_url();
		$sent_redirect    = (string) ( $generated_context['redirect_uri'] ?? '' );
		if ( '' === $sent_redirect && isset( $generated_context['query_string_sent']['redirect_uri'] ) ) {
			$sent_redirect = (string) $generated_context['query_string_sent']['redirect_uri'];
		}
		if ( '' === $sent_redirect ) {
			$sent_redirect = $callback_url;
		}
		$oauth_url = (string) ( $generated_context['authorization_url'] ?? '' );
		if ( '' === $oauth_url && ! empty( $redirect_context['authorization_url'] ) ) {
			$oauth_url = (string) $redirect_context['authorization_url'];
		}
		if ( '' === $oauth_url && ! empty( $generated_context['url_complete'] ) ) {
			$oauth_url = WCAS_Utils::redact_url_query( (string) $generated_context['url_complete'] );
		}
		$encoded_redirect = rawurlencode( $sent_redirect );
		?>
		<div class="wcas-redirect-match" data-wcas-redirect-match data-plugin-uri="<?php echo esc_attr( $callback_url ); ?>" data-sent-uri="<?php echo esc_attr( $sent_redirect ); ?>">
			<div class="wcas-redirect-match__header">
				<div>
					<span class="wcas-eyebrow"><?php esc_html_e( 'Redirect URI Exact Match', 'woocommerce-conta-azul-sync' ); ?></span>
					<strong><?php esc_html_e( 'Compare a URL cadastrada no Portal com a URL realmente enviada à Conta Azul', 'woocommerce-conta-azul-sync' ); ?></strong>
					<p><?php esc_html_e( 'Copie exatamente esta URL e cadastre no Portal do Desenvolvedor da Conta Azul.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
				<span class="wcas-badge wcas-is-warning"><?php esc_html_e( 'Anti redirect_mismatch', 'woocommerce-conta-azul-sync' ); ?></span>
			</div>
			<div class="wcas-redirect-match__grid">
				<div><span><?php esc_html_e( 'URL OAuth completa gerada antes do redirect', 'woocommerce-conta-azul-sync' ); ?></span><code><?php echo esc_html( '' !== $oauth_url ? $oauth_url : __( 'Ainda não gerada. Clique em Conectar Conta Azul.', 'woocommerce-conta-azul-sync' ) ); ?></code></div>
				<div><span><?php esc_html_e( 'redirect_uri enviada decodificada', 'woocommerce-conta-azul-sync' ); ?></span><code data-wcas-sent-uri><?php echo esc_html( $sent_redirect ); ?></code></div>
				<div><span><?php esc_html_e( 'redirect_uri enviada codificada com rawurlencode', 'woocommerce-conta-azul-sync' ); ?></span><code><?php echo esc_html( $encoded_redirect ); ?></code></div>
				<div><span><?php esc_html_e( 'URL para cadastrar no Portal da Conta Azul', 'woocommerce-conta-azul-sync' ); ?></span><code data-wcas-plugin-uri><?php echo esc_html( $callback_url ); ?></code></div>
			</div>
			<div class="wcas-redirect-match__manual">
				<label for="wcas-portal-redirect-uri"><?php esc_html_e( 'Redirect URI cadastrada no Portal Conta Azul', 'woocommerce-conta-azul-sync' ); ?></label>
				<textarea id="wcas-portal-redirect-uri" rows="3" data-wcas-portal-uri placeholder="https://seudominio.com/wp-admin/admin-post.php?action=wcas_oauth_callback"></textarea>
				<button type="button" class="button wcas-button wcas-button--primary" data-wcas-compare-uris><?php esc_html_e( 'Comparar URIs', 'woocommerce-conta-azul-sync' ); ?></button>
			</div>
			<div class="wcas-redirect-match__result" data-wcas-compare-result aria-live="polite">
				<?php esc_html_e( 'Cole a URI cadastrada no Portal e clique em Comparar URIs.', 'woocommerce-conta-azul-sync' ); ?>
			</div>
		</div>
		<?php
	}

	/** Render OAuth full trace panel. */
	private function render_oauth_trace_panel(): void {
		$trace_entries = WCAS_Logger::get_oauth_trace( 500 );
		$phases = array( 'sent' => __( 'Enviado', 'woocommerce-conta-azul-sync' ), 'received' => __( 'Recebido', 'woocommerce-conta-azul-sync' ), 'error' => __( 'Erro', 'woocommerce-conta-azul-sync' ), 'token' => __( 'Token', 'woocommerce-conta-azul-sync' ) );
		?>
		<section class="wcas-panel wcas-card" id="wcas-panel-oauth-trace" data-wcas-panel="oauth-trace" aria-labelledby="wcas-oauth-trace-title">
			<div class="wcas-card__header">
				<div>
					<span class="wcas-eyebrow"><?php esc_html_e( 'OAuth Full Trace', 'woocommerce-conta-azul-sync' ); ?></span>
					<h2 id="wcas-oauth-trace-title"><?php esc_html_e( 'Rastreamento linha por linha do OAuth2', 'woocommerce-conta-azul-sync' ); ?></h2>
					<p><?php esc_html_e( 'Mostra URL enviada, query strings, callback acionado, resposta da Conta Azul, token endpoint e erros como redirect_mismatch com dados sensíveis mascarados.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
				<span class="wcas-badge wcas-is-muted"><?php echo esc_html( sprintf( __( '%d eventos', 'woocommerce-conta-azul-sync' ), count( $trace_entries ) ) ); ?></span>
			</div>

			<?php $this->render_redirect_uri_exact_match_card( $trace_entries ); ?>

			<div class="wcas-log-filters" role="search" aria-label="<?php esc_attr_e( 'Filtros do OAuth Full Trace', 'woocommerce-conta-azul-sync' ); ?>">
				<label for="wcas-oauth-trace-search"><?php esc_html_e( 'Buscar', 'woocommerce-conta-azul-sync' ); ?></label>
				<input id="wcas-oauth-trace-search" type="search" class="wcas-log-search" placeholder="<?php esc_attr_e( 'redirect_uri, action, erro, callback...', 'woocommerce-conta-azul-sync' ); ?>" />
				<label for="wcas-oauth-trace-phase"><?php esc_html_e( 'Evento', 'woocommerce-conta-azul-sync' ); ?></label>
				<select id="wcas-oauth-trace-phase" class="wcas-log-type">
					<option value=""><?php esc_html_e( 'Todos', 'woocommerce-conta-azul-sync' ); ?></option>
					<?php foreach ( $phases as $phase => $label ) : ?>
						<option value="<?php echo esc_attr( $phase ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<?php if ( empty( $trace_entries ) ) : ?>
				<div class="wcas-empty-state">
					<strong><?php esc_html_e( 'Nenhum trace OAuth ainda', 'woocommerce-conta-azul-sync' ); ?></strong>
					<p><?php esc_html_e( 'Clique em Conectar Conta Azul para registrar a URL enviada, o callback recebido e a troca de token.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
			<?php else : ?>
				<div class="wcas-oauth-trace-list" aria-live="polite">
					<?php foreach ( $trace_entries as $entry ) : ?>
						<?php
						$context = isset( $entry['context'] ) && is_array( $entry['context'] ) ? $entry['context'] : array();
						$phase = sanitize_key( (string) ( $entry['phase'] ?? 'system' ) );
						$is_error = 'error' === $phase || false !== stripos( (string) ( $entry['message'] ?? '' ), 'redirect_mismatch' ) || false !== stripos( WCAS_Utils::summarize( $context, 1000 ), 'redirect_mismatch' );
						?>
						<article class="wcas-oauth-trace-row <?php echo $is_error ? 'wcas-oauth-trace-row--error' : ''; ?>" data-wcas-oauth-trace-row data-wcas-phase="<?php echo esc_attr( $phase ); ?>">
							<div class="wcas-oauth-trace-row__meta">
								<span><?php echo esc_html( (string) ( $entry['timestamp'] ?? '' ) ); ?></span>
								<span class="wcas-result-chip <?php echo esc_attr( $this->get_result_class( (string) ( $entry['result'] ?? '' ) ) ); ?>"><?php echo esc_html( (string) ( $entry['result'] ?? '-' ) ); ?></span>
								<span class="wcas-log-chip wcas-oauth-trace-phase--<?php echo esc_attr( $phase ); ?>"><?php echo esc_html( $phases[ $phase ] ?? $phase ); ?></span>
								<?php if ( ! empty( $entry['http_status'] ) ) : ?><span>HTTP <?php echo esc_html( (string) $entry['http_status'] ); ?></span><?php endif; ?>
							</div>
							<strong><?php echo esc_html( (string) ( $entry['action'] ?? '-' ) ); ?></strong>
							<p><?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?></p>
							<pre><?php echo esc_html( wp_json_encode( WCAS_Utils::mask_sensitive( $context ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
						</article>
					<?php endforeach; ?>
				</div>
				<div class="wcas-empty-state wcas-empty-state--trace-filtered" hidden>
					<strong><?php esc_html_e( 'Nenhum evento OAuth encontrado', 'woocommerce-conta-azul-sync' ); ?></strong>
					<p><?php esc_html_e( 'Ajuste o filtro para ver outros eventos do fluxo.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}



	/** Render OAuth Flow Inspector card. */
	private function render_oauth_flow_inspector_card(): void {
		$settings = WCAS_Utils::get_settings();
		$diagnostic = $this->get_oauth_endpoint_diagnostics( $settings );
		$debug = WCAS_Utils::get_oauth_debug();
		$last_callback = (string) ( $debug['last_callback_at'] ?? '' );
		$items = array(
			__( 'Callback efetivamente utilizado pelo OAuth', 'woocommerce-conta-azul-sync' ) => $diagnostic['effective_redirect_uri'],
			__( 'Função PHP responsável pelo callback', 'woocommerce-conta-azul-sync' ) => $diagnostic['callback_handler'],
			__( 'Onde o parâmetro code é capturado', 'woocommerce-conta-azul-sync' ) => 'WCAS_Auth::handle_admin_post_callback() → process_oauth_callback() → $_GET[code]',
			__( 'Onde o parâmetro state é validado', 'woocommerce-conta-azul-sync' ) => 'WCAS_Auth::handle_admin_post_callback() → process_oauth_callback() → get_transient(wcas_oauth_state_{state})',
			__( 'Onde ocorre token_exchange', 'woocommerce-conta-azul-sync' ) => 'WCAS_Auth::exchange_code() → WCAS_Auth::token_request()',
			__( 'Endpoint de token utilizado', 'woocommerce-conta-azul-sync' ) => (string) ( $settings['token_url'] ?? WCAS_Utils::TOKEN_URL ),
			__( 'Callback morto/não utilizado', 'woocommerce-conta-azul-sync' ) => $diagnostic['dead_callback'],
			__( 'Status de recebimento', 'woocommerce-conta-azul-sync' ) => '' === $last_callback ? __( 'Nenhum callback OAuth foi recebido pela aplicação', 'woocommerce-conta-azul-sync' ) : sprintf( __( 'Último callback recebido em %s', 'woocommerce-conta-azul-sync' ), $last_callback ),
		);
		?>
		<div class="wcas-oauth-debug wcas-oauth-flow-inspector" aria-label="<?php esc_attr_e( 'OAuth Flow Inspector', 'woocommerce-conta-azul-sync' ); ?>">
			<div class="wcas-oauth-debug__header">
				<div>
					<span class="wcas-eyebrow"><?php esc_html_e( 'OAuth Flow Inspector', 'woocommerce-conta-azul-sync' ); ?></span>
					<strong><?php esc_html_e( 'Auditoria do callback que processa code, state e token exchange', 'woocommerce-conta-azul-sync' ); ?></strong>
				</div>
			</div>
			<?php if ( '' === $last_callback ) : ?>
				<div class="wcas-empty-state wcas-empty-state--compact"><strong><?php esc_html_e( 'Nenhum callback OAuth foi recebido pela aplicação', 'woocommerce-conta-azul-sync' ); ?></strong><p><?php esc_html_e( 'Se a Conta Azul redirecionou para outra URL, copie a URL efetiva abaixo e cadastre-a no Portal do Desenvolvedor.', 'woocommerce-conta-azul-sync' ); ?></p></div>
			<?php endif; ?>
			<div class="wcas-oauth-debug__grid">
				<?php foreach ( $items as $label => $value ) : ?>
					<div class="wcas-oauth-debug__item">
						<span><?php echo esc_html( (string) $label ); ?></span>
						<code><?php echo esc_html( (string) $value ); ?></code>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/** Render OAuth debug summary card. */
	private function render_oauth_debug_card(): void {
		$debug = WCAS_Utils::get_oauth_debug();
		$items = array(
			__( 'Último callback recebido', 'woocommerce-conta-azul-sync' ) => $debug['last_callback_at'] ?? __( 'Nenhum callback recebido', 'woocommerce-conta-azul-sync' ),
			__( 'Último state recebido', 'woocommerce-conta-azul-sync' ) => $debug['last_state_received'] ?? '-',
			__( 'Último code recebido', 'woocommerce-conta-azul-sync' ) => $debug['last_code_received'] ?? '-',
			__( 'Último erro recebido', 'woocommerce-conta-azul-sync' ) => $debug['last_error_received'] ?? '-',
			__( 'Último status HTTP do token endpoint', 'woocommerce-conta-azul-sync' ) => $debug['last_token_http_status'] ?? '-',
			__( 'Última validação do state', 'woocommerce-conta-azul-sync' ) => $debug['last_state_validation'] ?? '-',
			__( 'Token endpoint', 'woocommerce-conta-azul-sync' ) => $debug['last_token_endpoint'] ?? '-',
			__( 'Resumo da última resposta', 'woocommerce-conta-azul-sync' ) => $debug['last_token_response'] ?? '-',
		);
		?>
		<div class="wcas-oauth-debug" aria-label="<?php esc_attr_e( 'OAuth Debug', 'woocommerce-conta-azul-sync' ); ?>">
			<div class="wcas-oauth-debug__header">
				<span class="wcas-eyebrow"><?php esc_html_e( 'OAuth Debug', 'woocommerce-conta-azul-sync' ); ?></span>
				<strong><?php esc_html_e( 'Resumo do último callback e token exchange', 'woocommerce-conta-azul-sync' ); ?></strong>
			</div>
			<div class="wcas-oauth-debug__grid">
				<?php foreach ( $items as $label => $value ) : ?>
					<div class="wcas-oauth-debug__item">
						<span><?php echo esc_html( (string) $label ); ?></span>
						<code><?php echo esc_html( is_scalar( $value ) ? (string) $value : WCAS_Utils::summarize( $value, 160 ) ); ?></code>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}


	/** Render OAuth endpoints diagnostic card. */
	private function render_oauth_endpoints_card(): void {
		$settings = WCAS_Utils::get_settings();
		$diagnostic = $this->get_oauth_endpoint_diagnostics( $settings );
		$items = array(
			__( 'URL para cadastrar na Conta Azul', 'woocommerce-conta-azul-sync' ) => $diagnostic['expected_return_url'],
			__( 'Redirect URI configurado no plugin', 'woocommerce-conta-azul-sync' ) => $diagnostic['configured_redirect_uri'],
			__( 'Redirect URI enviado para a Conta Azul', 'woocommerce-conta-azul-sync' ) => $diagnostic['effective_redirect_uri'],
			__( 'Callback handler registrado', 'woocommerce-conta-azul-sync' ) => $diagnostic['callback_handler'],
			__( 'Admin-post actions registradas', 'woocommerce-conta-azul-sync' ) => implode( ', ', $diagnostic['admin_post_actions'] ),
			__( 'REST routes registradas', 'woocommerce-conta-azul-sync' ) => implode( ', ', $diagnostic['rest_routes'] ),
			__( 'URL final esperada para retorno OAuth', 'woocommerce-conta-azul-sync' ) => $diagnostic['expected_return_url'],
			__( 'Callback existe?', 'woocommerce-conta-azul-sync' ) => $diagnostic['callback_exists'] ? __( 'SIM', 'woocommerce-conta-azul-sync' ) : __( 'NÃO', 'woocommerce-conta-azul-sync' ),
			__( 'Hook registrado?', 'woocommerce-conta-azul-sync' ) => $diagnostic['hook_registered'] ? __( 'SIM', 'woocommerce-conta-azul-sync' ) : __( 'NÃO', 'woocommerce-conta-azul-sync' ),
			__( 'REST route registrada?', 'woocommerce-conta-azul-sync' ) => $diagnostic['rest_route_registered'] ? __( 'SIM', 'woocommerce-conta-azul-sync' ) : __( 'NÃO', 'woocommerce-conta-azul-sync' ),
			__( 'URL acessível?', 'woocommerce-conta-azul-sync' ) => $diagnostic['url_accessible'] ? __( 'SIM', 'woocommerce-conta-azul-sync' ) : __( 'NÃO', 'woocommerce-conta-azul-sync' ),
		);
		?>
		<div class="wcas-oauth-debug wcas-oauth-endpoints" aria-label="<?php esc_attr_e( 'OAuth Endpoints', 'woocommerce-conta-azul-sync' ); ?>">
			<div class="wcas-oauth-debug__header">
				<div>
					<span class="wcas-eyebrow"><?php esc_html_e( 'OAuth Endpoints', 'woocommerce-conta-azul-sync' ); ?></span>
					<strong><?php esc_html_e( 'URL que deve ser cadastrada no Portal do Desenvolvedor', 'woocommerce-conta-azul-sync' ); ?></strong>
				</div>
				<button type="submit" form="wcas-validate-callback-form" class="button wcas-button wcas-button--primary"><?php esc_html_e( 'Validar Callback', 'woocommerce-conta-azul-sync' ); ?></button>
			</div>
			<div class="wcas-oauth-debug__grid">
				<?php foreach ( $items as $label => $value ) : ?>
					<div class="wcas-oauth-debug__item">
						<span><?php echo esc_html( (string) $label ); ?></span>
						<code><?php echo esc_html( is_scalar( $value ) ? (string) $value : WCAS_Utils::summarize( $value, 180 ) ); ?></code>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/** Render tests panel. */
	private function render_tests_panel( array $settings, array $metrics ): void {
		?>
		<section class="wcas-panel wcas-card" id="wcas-panel-tests" data-wcas-panel="tests" aria-labelledby="wcas-tests-title">
			<div class="wcas-card__header">
				<div>
					<span class="wcas-eyebrow"><?php esc_html_e( 'Diagnóstico local', 'woocommerce-conta-azul-sync' ); ?></span>
					<h2 id="wcas-tests-title"><?php esc_html_e( 'Teste de conexão', 'woocommerce-conta-azul-sync' ); ?></h2>
					<p><?php esc_html_e( 'Validação visual local: confere credenciais, URLs, status OAuth e token sem chamar a API.', 'woocommerce-conta-azul-sync' ); ?></p>
				</div>
			</div>
			<div class="wcas-test-box" data-wcas-test-box data-connected="<?php echo esc_attr( $metrics['connected'] ? 'yes' : 'no' ); ?>" data-token-expired="<?php echo esc_attr( $metrics['token_expired'] ? 'yes' : 'no' ); ?>" data-client-id="<?php echo esc_attr( '' !== (string) ( $settings['client_id'] ?? '' ) ? 'yes' : 'no' ); ?>" data-client-secret="<?php echo esc_attr( '' !== (string) ( $settings['client_secret'] ?? '' ) ? 'yes' : 'no' ); ?>" data-redirect-uri="<?php echo esc_attr( '' !== (string) ( $settings['redirect_uri'] ?? '' ) ? 'yes' : 'no' ); ?>">
				<button type="button" class="button wcas-button wcas-button--primary" data-wcas-run-test><?php esc_html_e( 'Executar teste local', 'woocommerce-conta-azul-sync' ); ?></button>
				<button type="submit" form="wcas-test-credentials-form" class="button wcas-button wcas-button--ghost"><?php esc_html_e( 'Testar credenciais', 'woocommerce-conta-azul-sync' ); ?></button>
				<button type="submit" form="wcas-test-oauth-url-form" class="button wcas-button wcas-button--ghost"><?php esc_html_e( 'Testar geração da URL OAuth', 'woocommerce-conta-azul-sync' ); ?></button>
				<button type="submit" form="wcas-test-redirect-uri-form" class="button wcas-button wcas-button--ghost"><?php esc_html_e( 'Testar Redirect URI', 'woocommerce-conta-azul-sync' ); ?></button>
				<button type="submit" form="wcas-test-permissions-form" class="button wcas-button wcas-button--ghost"><?php esc_html_e( 'Testar permissões WordPress', 'woocommerce-conta-azul-sync' ); ?></button>
				<div class="wcas-test-result" data-wcas-test-result aria-live="polite">
					<span class="wcas-status-dot wcas-is-muted"></span>
					<span><?php esc_html_e( 'Clique para validar a configuração local do painel ou use os testes com log persistente.', 'woocommerce-conta-azul-sync' ); ?></span>
				</div>
			</div>
		</section>
		<?php
	}

	/** Render help panel. */
	private function render_help_panel(): void {
		?>
		<section class="wcas-panel wcas-card" id="wcas-panel-help" data-wcas-panel="help" aria-labelledby="wcas-help-title">
			<div class="wcas-card__header">
				<div>
					<span class="wcas-eyebrow"><?php esc_html_e( 'Ajuda', 'woocommerce-conta-azul-sync' ); ?></span>
					<h2 id="wcas-help-title"><?php esc_html_e( 'Próximos passos para produção', 'woocommerce-conta-azul-sync' ); ?></h2>
				</div>
			</div>
			<div class="wcas-help-grid">
				<div><strong><?php esc_html_e( '1. Portal do Desenvolvedor', 'woocommerce-conta-azul-sync' ); ?></strong><p><?php esc_html_e( 'Crie a aplicação, cadastre a Redirect URI e copie Client ID/Secret.', 'woocommerce-conta-azul-sync' ); ?></p></div>
				<div><strong><?php esc_html_e( '2. Endpoints reais', 'woocommerce-conta-azul-sync' ); ?></strong><p><?php esc_html_e( 'Valide paths e payloads de clientes, produtos, vendas e financeiro antes de produção.', 'woocommerce-conta-azul-sync' ); ?></p></div>
				<div><strong><?php esc_html_e( '3. Teste manual', 'woocommerce-conta-azul-sync' ); ?></strong><p><?php esc_html_e( 'Use a rotina do README para instalação, OAuth, sync, cancelamento, reembolso e segurança.', 'woocommerce-conta-azul-sync' ); ?></p></div>
			</div>
		</section>
		<?php
	}

	/** Render hidden OAuth action forms referenced by premium action buttons. */
	private function render_oauth_action_forms(): void {
		?>
		<form id="wcas-connect-form" class="wcas-hidden-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wcas_connect_conta_azul" />
			<?php wp_nonce_field( WCAS_Utils::NONCE_ACTION ); ?>
		</form>
		<form id="wcas-disconnect-form" class="wcas-hidden-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wcas_disconnect" />
			<?php wp_nonce_field( WCAS_Utils::NONCE_ACTION ); ?>
		</form>
		<?php foreach ( array( 'wcas-test-credentials-form' => 'wcas_test_credentials', 'wcas-test-oauth-url-form' => 'wcas_test_oauth_url', 'wcas-test-redirect-uri-form' => 'wcas_test_redirect_uri', 'wcas-test-permissions-form' => 'wcas_test_permissions', 'wcas-validate-callback-form' => 'wcas_validate_callback' ) as $form_id => $action ) : ?>
			<form id="<?php echo esc_attr( $form_id ); ?>" class="wcas-hidden-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
				<?php wp_nonce_field( WCAS_Utils::NONCE_ACTION ); ?>
			</form>
		<?php endforeach; ?>
		<?php
	}



	/** Build OAuth endpoint diagnostics. */
	private function get_oauth_endpoint_diagnostics( array $settings ): array {
		$configured_redirect = WCAS_Utils::normalize_oauth_redirect_uri( (string) ( $settings['redirect_uri'] ?? '' ) );
		$dedicated_callback  = WCAS_Utils::get_oauth_callback_url();
		$legacy_admin_page   = admin_url( 'admin.php?page=wcas-conta-azul' );
		$rest_route          = rest_url( 'wcas/v1/oauth/callback' );
		$admin_actions       = array( 'admin_post_wcas_oauth_callback', 'admin_post_wcas_connect', 'admin_post_wcas_connect_conta_azul', 'admin_post_wcas_validate_callback' );
		$registered_admin_actions = array();
		foreach ( $admin_actions as $action ) {
			if ( has_action( $action ) ) {
				$registered_admin_actions[] = $action;
			}
		}

		$callback_exists       = '' !== $dedicated_callback && (bool) wp_http_validate_url( $dedicated_callback );
		$hook_registered       = has_action( 'admin_post_wcas_oauth_callback' ) && method_exists( $this->auth, 'handle_admin_post_callback' );
		$rest_route_registered = false;
		$callback_handler      = $hook_registered ? 'WCAS_Auth::handle_admin_post_callback → WCAS_Auth::process_oauth_callback' : 'Nenhum handler admin_post_wcas_oauth_callback ativo para token exchange';
		$dead_callback         = 'wp-admin/admin.php?page=wcas-conta-azul é apenas compatibilidade legada e não deve ser cadastrado na Conta Azul. REST API /wp-json/wcas/v1/oauth/callback não registra token exchange.';

		return array(
			'configured_redirect_uri' => $configured_redirect,
			'effective_redirect_uri'  => $dedicated_callback,
			'default_admin_callback'  => $legacy_admin_page,
			'callback_handler'        => $callback_handler,
			'callback_type'           => 'admin_post',
			'admin_post_actions'      => $registered_admin_actions,
			'rest_routes'             => array(),
			'rest_namespace'          => 'nenhum REST namespace ativo para OAuth',
			'rest_route_url'          => $rest_route,
			'expected_return_url'     => $dedicated_callback,
			'callback_exists'         => $callback_exists,
			'hook_registered'         => $hook_registered,
			'rest_route_registered'   => $rest_route_registered,
			'url_accessible'          => $callback_exists,
			'dead_callback'           => $dead_callback,
		);
	}

	/** Build safe OAuth diagnostic context. */
	private function build_oauth_diagnostic_context( array $settings ): array {
		return array(
			'client_id_exists'     => '' !== (string) ( $settings['client_id'] ?? '' ),
			'client_id'            => WCAS_Utils::mask_identifier( (string) ( $settings['client_id'] ?? '' ) ),
			'client_secret_exists' => '' !== (string) ( $settings['client_secret'] ?? '' ),
			'redirect_uri_exists'  => '' !== (string) ( $settings['redirect_uri'] ?? '' ),
			'redirect_uri'         => (string) ( $settings['redirect_uri'] ?? '' ),
			'oauth_callback_url'   => WCAS_Utils::get_oauth_callback_url(),
			'auth_url'             => (string) ( $settings['auth_url'] ?? '' ),
			'nonce'                => 'passed',
			'capability'           => 'manage_woocommerce passed',
			'admin_post_action'    => 'wcas_connect_conta_azul',
			'oauth_callback_action' => 'wcas_oauth_callback',
		);
	}

	/** Decode log context safely. */
	private function decode_log_context( string $context ): array {
		$decoded = json_decode( $context, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/** Get friendly log type label. */
	private function get_log_type_label( string $type ): string {
		if ( str_contains( $type, 'error' ) ) {
			return __( 'Erro', 'woocommerce-conta-azul-sync' );
		}
		if ( in_array( $type, array( 'oauth', 'auth', 'auth_error' ), true ) ) {
			return __( 'OAuth', 'woocommerce-conta-azul-sync' );
		}
		if ( in_array( $type, array( 'api', 'request', 'response', 'http_error' ), true ) ) {
			return __( 'API', 'woocommerce-conta-azul-sync' );
		}
		if ( in_array( $type, array( 'config', 'settings' ), true ) ) {
			return __( 'Configuração', 'woocommerce-conta-azul-sync' );
		}
		return __( 'Sistema', 'woocommerce-conta-azul-sync' );
	}

	/** Get CSS class for result chips. */
	private function get_result_class( string $result ): string {
		if ( in_array( $result, array( 'success', 'passed' ), true ) ) {
			return 'wcas-is-success';
		}
		if ( in_array( $result, array( 'warning', 'started' ), true ) ) {
			return 'wcas-is-warning';
		}
		if ( in_array( $result, array( 'error', 'failed' ), true ) ) {
			return 'wcas-is-danger';
		}
		return 'wcas-is-muted';
	}

	/** Format safe log context for display. */
	private function format_log_context( string $context ): string {
		if ( '' === $context || 'null' === $context || '[]' === $context ) {
			return '';
		}
		$context = wp_strip_all_tags( $context );
		return wp_html_excerpt( $context, 300, '…' );
	}

	/** Render checkbox field. */
	private function checkbox_field( string $key, string $label, array $settings, string $description = '' ): void {
		?>
		<label class="wcas-field wcas-toggle" for="wcas_<?php echo esc_attr( $key ); ?>">
			<input id="wcas_<?php echo esc_attr( $key ); ?>" type="checkbox" name="wcas[<?php echo esc_attr( $key ); ?>]" value="yes" <?php checked( $settings[ $key ], 'yes' ); ?> />
			<span class="wcas-toggle__switch" aria-hidden="true"></span>
			<span class="wcas-toggle__content"><strong><?php echo esc_html( $label ); ?></strong><?php if ( '' !== $description ) : ?><small><?php echo esc_html( $description ); ?></small><?php endif; ?></span>
		</label>
		<?php
	}

	/** Render text field. */
	private function text_field( string $key, string $label, array $settings, string $description = '' ): void {
		?>
		<div class="wcas-field">
			<label for="wcas_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input id="wcas_<?php echo esc_attr( $key ); ?>" type="text" name="wcas[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ?? '' ); ?>" />
			<?php if ( '' !== $description ) : ?><small><?php echo esc_html( $description ); ?></small><?php endif; ?>
		</div>
		<?php
	}

	/** Render password field. */
	private function password_field( string $key, string $label, array $settings ): void {
		$placeholder = '' !== (string) ( $settings[ $key ] ?? '' ) ? __( '•••••••• salvo; deixe em branco para manter', 'woocommerce-conta-azul-sync' ) : '';
		?>
		<div class="wcas-field">
			<label for="wcas_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input id="wcas_<?php echo esc_attr( $key ); ?>" type="password" autocomplete="new-password" name="wcas[<?php echo esc_attr( $key ); ?>]" value="" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
			<small><?php esc_html_e( 'Nunca exibido em texto puro no painel.', 'woocommerce-conta-azul-sync' ); ?></small>
		</div>
		<?php
	}

	/** Render URL field. */
	private function url_field( string $key, string $label, array $settings, string $description = '' ): void {
		?>
		<div class="wcas-field">
			<label for="wcas_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input id="wcas_<?php echo esc_attr( $key ); ?>" type="url" name="wcas[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ?? '' ); ?>" />
			<?php if ( '' !== $description ) : ?><small><?php echo esc_html( $description ); ?></small><?php endif; ?>
		</div>
		<?php
	}

	/** Render select field. */
	private function select_field( string $key, string $label, array $settings, array $options, string $description = '' ): void {
		?>
		<div class="wcas-field">
			<label for="wcas_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="wcas_<?php echo esc_attr( $key ); ?>" name="wcas[<?php echo esc_attr( $key ); ?>]">
				<?php foreach ( $options as $value => $text ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings[ $key ], $value ); ?>><?php echo esc_html( $text ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( '' !== $description ) : ?><small><?php echo esc_html( $description ); ?></small><?php endif; ?>
		</div>
		<?php
	}

	/** Verify nonce and capability. */
	private function verify_action(): void {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'unknown';
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			WCAS_Logger::diagnostic( 'system', $action, 'error', 'Capability manage_woocommerce recusada antes de executar ação administrativa.' );
			wp_die( esc_html__( 'Permissão insuficiente.', 'woocommerce-conta-azul-sync' ) );
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, WCAS_Utils::NONCE_ACTION ) ) {
			WCAS_Logger::diagnostic( 'system', $action, 'error', 'Nonce inválido ou ausente antes de executar ação administrativa.', array( 'capability' => 'manage_woocommerce passed', 'nonce' => 'failed' ) );
			wp_die( esc_html__( 'Falha na verificação de segurança. Recarregue a página e tente novamente.', 'woocommerce-conta-azul-sync' ) );
		}
	}

	/** Redirect with message. */
	private function redirect( string $message, string $tab = 'settings' ): void {
		$args = array( 'page' => 'wcas-conta-azul', 'wcas_message' => $message );
		if ( 'settings' !== $tab ) {
			$args['tab'] = $tab;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Render notices. */
	private function render_notice(): void {
		if ( empty( $_GET['wcas_message'] ) ) {
			return;
		}
		$messages = array(
			'settings_saved'      => __( 'Configurações salvas.', 'woocommerce-conta-azul-sync' ),
			'auth_success'        => __( 'Conta Azul conectada com sucesso.', 'woocommerce-conta-azul-sync' ),
			'auth_failed'         => __( 'Falha ao conectar com a Conta Azul. Verifique os logs.', 'woocommerce-conta-azul-sync' ),
			'invalid_state'       => __( 'Callback OAuth recusado por state inválido.', 'woocommerce-conta-azul-sync' ),
			'disconnected'        => __( 'Conta Azul desconectada.', 'woocommerce-conta-azul-sync' ),
			'logs_cleared'        => __( 'Logs limpos.', 'woocommerce-conta-azul-sync' ),
			'missing_credentials'  => __( 'Informe Client ID e Client Secret antes de conectar.', 'woocommerce-conta-azul-sync' ),
			'diagnostic_completed' => __( 'Diagnóstico executado. Veja o resultado nos logs.', 'woocommerce-conta-azul-sync' ),
		);
		$key = sanitize_key( wp_unslash( $_GET['wcas_message'] ) );
		if ( isset( $messages[ $key ] ) ) {
			echo '<div class="notice notice-info is-dismissible wcas-notice"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}
}
