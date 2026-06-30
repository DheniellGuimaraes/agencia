<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SAP_Admin {
	private static $hook = '';
	public static function init() { add_action( 'admin_menu', array( __CLASS__, 'menu' ) ); add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) ); }
	public static function menu() { self::$hook = add_menu_page( 'Sitemap Agencia Privilége', 'Sitemap Agencia Privilége', 'manage_options', 'sitemap-agencia-privilege', array( __CLASS__, 'render' ), 'dashicons-networking', 58 ); }
	public static function assets( $hook ) {
		if ( self::$hook !== $hook ) { return; }
		wp_enqueue_style( 'sap-maven-pro', 'https://fonts.googleapis.com/css2?family=Maven+Pro:wght@800&display=swap', array(), SAP_VERSION );
		wp_enqueue_style( 'sap-admin', SAP_PLUGIN_URL . 'assets/css/admin.css', array( 'dashicons', 'sap-maven-pro' ), SAP_VERSION );
		wp_enqueue_script( 'sap-admin', SAP_PLUGIN_URL . 'assets/js/admin.js', array(), SAP_VERSION, true );
		wp_localize_script( 'sap-admin', 'sapSitemap', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( SAP_NONCE_ACTION ), 'mainUrl' => SAP_Files::main_url() ) );
	}
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$settings = SAP_Settings::get(); $types = SAP_Settings::public_post_types(); $taxes = SAP_Settings::public_taxonomies(); $diag = SAP_Files::diagnostics(); $files = SAP_Files::list_files(); $last = get_option( SAP_OPTION_LAST_RUN, '—' ); $estimated = SAP_Generator::estimate_total( $settings );
		?>
		<div class="sap-wrap">
			<section class="sap-hero"><div><span class="sap-kicker">XML físico • alta escala • premium</span><h1>Sitemap Agencia Privilége</h1><p>Gerador premium de sitemaps XML físicos para sites WordPress com alto volume de páginas.</p></div><div class="sap-orb"></div></section>
			<div class="sap-grid">
				<section class="sap-card sap-span-2"><h2><span class="dashicons dashicons-shield"></span> Diagnóstico</h2><div class="sap-stats">
					<?php self::stat( 'Pasta', $diag['exists'] ? 'Criada' : 'Ausente', $diag['exists'] ); self::stat( 'Escrita', $diag['writable'] ? 'Permitida' : 'Bloqueada', $diag['writable'] ); self::stat( 'URLs estimadas', number_format_i18n( $estimated ), true ); self::stat( 'Última geração', $last ? $last : '—', true ); self::stat( 'Arquivos físicos', count( $files ), true ); ?>
				</div><label class="sap-url-label">URL principal</label><div class="sap-url-row"><input id="sap-main-url" readonly value="<?php echo esc_url( SAP_Files::main_url() ); ?>"><button class="sap-btn sap-copy" data-copy="#sap-main-url">Copiar</button><a class="sap-btn sap-ghost" href="<?php echo esc_url( SAP_Files::main_url() ); ?>" target="_blank" rel="noopener">Abrir</a><a class="sap-btn sap-ghost" href="<?php echo esc_url( SAP_Files::main_url() ); ?>" download>Baixar</a></div><p class="sap-path">Pasta: <?php echo esc_html( $diag['directory'] ); ?></p></section>
				<section class="sap-card"><h2><span class="dashicons dashicons-admin-generic"></span> Configuração</h2><form id="sap-form">
					<div class="sap-fields"><label>URLs por sitemap<input type="number" name="urls_per_file" min="100" max="50000" value="<?php echo esc_attr( $settings['urls_per_file'] ); ?>"></label><label>Lote AJAX<input type="number" name="batch_size" min="100" max="1000" value="<?php echo esc_attr( $settings['batch_size'] ); ?>"></label><label>Pausa entre lotes (ms)<input type="number" name="batch_pause" min="0" max="5000" value="<?php echo esc_attr( $settings['batch_pause'] ); ?>"></label><label>Excluir IDs<textarea name="exclude_ids" placeholder="12, 45, 98"><?php echo esc_textarea( $settings['exclude_ids'] ); ?></textarea></label></div>
					<h3>Tipos de conteúdo</h3><div class="sap-checks"><?php foreach ( $types as $key => $obj ) : ?><label><input type="checkbox" name="include_types[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $settings['include_types'], true ) ); ?>><?php echo esc_html( $obj->labels->name ); ?></label><?php endforeach; ?></div>
					<h3>Taxonomias públicas</h3><div class="sap-checks"><?php foreach ( $taxes as $key => $obj ) : ?><label><input type="checkbox" name="include_taxonomies[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $settings['include_taxonomies'], true ) ); ?>><?php echo esc_html( $obj->labels->name ); ?></label><?php endforeach; ?></div>
					<div class="sap-toggles"><?php self::toggle( 'clean_before', 'Limpar arquivos antes de gerar', $settings ); self::toggle( 'exclude_noindex', 'Excluir noindex detectável', $settings ); self::toggle( 'include_lastmod', 'Incluir lastmod', $settings ); self::toggle( 'include_changefreq', 'Incluir changefreq', $settings ); self::toggle( 'include_priority', 'Incluir priority', $settings ); ?></div>
				</form></section>
				<section class="sap-card sap-action"><h2>Centro de controle</h2><button id="sap-generate" class="sap-btn sap-primary"><span class="dashicons dashicons-update"></span> Gerar Sitemaps Físicos</button><div class="sap-actions"><button id="sap-permissions" class="sap-btn sap-ghost">Verificar Permissões</button><button id="sap-clean" class="sap-btn sap-ghost">Limpar Arquivos</button><button class="sap-btn sap-ghost sap-copy" data-copy="#sap-main-url">Copiar URL Principal</button><a class="sap-btn sap-ghost" href="<?php echo esc_url( SAP_Files::main_url() ); ?>" target="_blank" rel="noopener">Abrir Sitemap</a></div></section>
				<section class="sap-card sap-span-2"><h2>Progresso em tempo real</h2><div class="sap-progress"><span id="sap-progress-bar"></span></div><div class="sap-progress-meta"><strong id="sap-percent">0%</strong><span id="sap-processed">0 processados</span><span id="sap-current">Aguardando</span><span id="sap-status" class="sap-badge">Pronto</span></div></section>
				<section class="sap-card sap-span-2"><h2>Console premium</h2><div id="sap-log" class="sap-log"><p>[sistema] Pronto para gerar sitemaps físicos.</p></div></section>
				<section class="sap-card sap-span-2"><h2>Arquivos gerados</h2><div id="sap-files"><?php self::files_table( $files ); ?></div></section>
			</div>
		</div><?php
	}
	private static function stat( $label, $value, $ok ) { echo '<div class="sap-stat"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong><em class="' . ( $ok ? 'ok' : 'bad' ) . '"></em></div>'; }
	private static function toggle( $name, $label, $settings ) { echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $settings[ $name ] ), true, false ) . '>' . esc_html( $label ) . '</label>'; }
	private static function files_table( $files ) { if ( empty( $files ) ) { echo '<p class="sap-empty">Nenhum arquivo físico gerado ainda.</p>'; return; } echo '<div class="sap-file-list">'; foreach ( $files as $file ) { echo '<div class="sap-file"><strong>' . esc_html( $file['name'] ) . '</strong><span>' . esc_html( $file['urls'] ) . ' URLs</span><span>' . esc_html( $file['size'] ) . '</span><span>' . esc_html( $file['created'] ) . '</span><a class="sap-btn sap-mini" target="_blank" rel="noopener" href="' . esc_url( $file['url'] ) . '">Abrir</a><button class="sap-btn sap-mini sap-copy-url" data-url="' . esc_url( $file['url'] ) . '">Copiar</button></div>'; } echo '</div>'; }
}
