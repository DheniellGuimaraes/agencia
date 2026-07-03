<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MPAP_Admin {
    private $auth;
    private $api;
    private $products;
    private $orders;
    private $diagnostics;
    private $quality;

    public function __construct( MPAP_Auth $auth, MPAP_API $api, MPAP_Product_Sync $products, MPAP_Order_Importer $orders, MPAP_Diagnostics $diagnostics, MPAP_Quality $quality = null ) {
        $this->auth        = $auth;
        $this->api         = $api;
        $this->products    = $products;
        $this->orders      = $orders;
        $this->diagnostics = $diagnostics;
        $this->quality     = $quality ?: new MPAP_Quality( $api );
    }

    public function hooks() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'admin_init', array( $this, 'handle_forms' ) );
        add_action( 'admin_notices', array( $this, 'notices' ) );
        add_action( 'admin_post_mpap_export_logs', array( $this, 'export_logs' ) );
        add_filter( 'plugin_action_links_' . MPAP_BASENAME, array( $this, 'action_links' ) );
    }

    public function menu() {
        $cap = mpap_capability();
        add_menu_page( 'Mercado Pago Agência Privilege', 'Mercado Pago Agência Privilege', $cap, 'mpap-dashboard', array( $this, 'dashboard' ), 'dashicons-store', 56 );
        add_submenu_page( 'mpap-dashboard', 'Dashboard', 'Dashboard', $cap, 'mpap-dashboard', array( $this, 'dashboard' ) );
        add_submenu_page( 'mpap-dashboard', 'Produtos', 'Produtos', $cap, 'mpap-products', array( $this, 'products' ) );
        add_submenu_page( 'mpap-dashboard', 'Qualidade dos Anúncios', 'Qualidade dos Anúncios', $cap, 'mpap-quality', array( $this, 'quality_page' ) );
        add_submenu_page( 'mpap-dashboard', 'Categorias ML', 'Categorias ML', $cap, 'mpap-categories', array( $this, 'categories' ) );
        add_submenu_page( 'mpap-dashboard', 'Pedidos', 'Pedidos', $cap, 'mpap-orders', array( $this, 'orders' ) );
        add_submenu_page( 'mpap-dashboard', 'Webhooks', 'Webhooks', $cap, 'mpap-webhooks', array( $this, 'webhooks' ) );
        add_submenu_page( 'mpap-dashboard', 'Configurações', 'Configurações', $cap, 'mpap-settings', array( $this, 'settings' ) );
        add_submenu_page( 'mpap-dashboard', 'Diagnóstico', 'Diagnóstico', $cap, 'mpap-diagnostics', array( $this, 'diagnostics' ) );
        add_submenu_page( 'mpap-dashboard', 'Logs', 'Logs', $cap, 'mpap-logs', array( $this, 'logs' ) );
        add_submenu_page( 'mpap-dashboard', 'Suporte', 'Suporte', $cap, 'mpap-support', array( $this, 'support' ) );
    }

    public function action_links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=mpap-settings' ) ) . '">Configurações</a>' );
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=mpap-logs' ) ) . '">Logs</a>' );
        return $links;
    }

    public function assets( $hook ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $is_plugin_page = $screen && false !== strpos( (string) $screen->id, 'mpap' );
        $is_product_page = $screen && 'product' === $screen->post_type;
        if ( ! $is_plugin_page && ! $is_product_page ) {
            return;
        }
        wp_enqueue_style( 'mpap-admin', MPAP_URL . 'assets/css/admin.css', array(), MPAP_VERSION );
        wp_enqueue_script( 'mpap-admin', MPAP_URL . 'assets/js/admin.js', array( 'jquery' ), MPAP_VERSION, true );
        wp_localize_script(
            'mpap-admin',
            'MPAP_Admin',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'mpap_admin_nonce' ),
                'i18n'     => array(
                    'syncing' => 'Processando...',
                    'done'    => 'Concluído.',
                    'error'   => 'Erro ao executar.',
                    'copied'  => 'Copiado.',
                ),
            )
        );
    }

    public function notices() {
        if ( ! mpap_has_wc() ) {
            echo '<div class="notice notice-warning"><p>Mercado Pago Agência Privilege requer WooCommerce ativo para sincronizar produtos e pedidos.</p></div>';
        }
        $notice = isset( $_GET['mpap_notice'] ) ? sanitize_key( wp_unslash( $_GET['mpap_notice'] ) ) : '';
        $messages = array(
            'settings_saved'       => array( 'success', 'Configurações salvas.' ),
            'connected'            => array( 'success', 'Conta Mercado Livre conectada.' ),
            'managed_connected'    => array( 'success', 'Conta Mercado Livre conectada via Privilege Connect.' ),
            'disconnected'         => array( 'success', 'Conta desconectada.' ),
            'logs_cleared'         => array( 'success', 'Logs limpos.' ),
            'missing_credentials'  => array( 'error', 'Informe Client ID e Client Secret do Mercado Livre Developers antes de conectar no modo manual.' ),
            'missing_managed_service' => array( 'error', 'Informe a URL do broker Privilege Connect antes de conectar sem credenciais.' ),
            'managed_callback_error' => array( 'error', 'Privilege Connect retornou erro no callback. Veja Logs > fonte connect.' ),
            'managed_invalid_state' => array( 'error', 'State do Privilege Connect inválido ou expirado. Tente conectar novamente.' ),
            'managed_exchange_failed' => array( 'error', 'Falha ao trocar o código do broker por token de conexão. Veja Logs > fonte connect.' ),
            'invalid_state'        => array( 'error', 'State OAuth inválido ou expirado. Tente conectar novamente.' ),
            'token_failed'         => array( 'error', 'Falha ao obter token OAuth. Veja os detalhes em Mercado Pago Agência Privilege > Logs, fonte OAuth.' ),
            'invalid_auth_host'    => array( 'error', 'Auth Host inválido. Use o host oficial do Mercado Livre.' ),
            'oauth_callback_error' => array( 'error', 'Mercado Livre retornou erro no callback OAuth. Veja Logs > OAuth.' ),
            'refresh_failed'       => array( 'error', 'Falha ao renovar token. Veja Logs > OAuth.' ),
            'token_refreshed'      => array( 'success', 'Token renovado.' ),
            'category_saved'       => array( 'success', 'Categoria padrão Mercado Livre salva.' ),
        );
        if ( isset( $messages[ $notice ] ) ) {
            echo '<div class="notice notice-' . esc_attr( $messages[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $notice ][1] ) . '</p></div>';
        }
    }

    public function handle_forms() {
        if ( ! current_user_can( mpap_capability() ) ) {
            return;
        }
        if ( isset( $_POST['mpap_save_settings'] ) ) {
            check_admin_referer( 'mpap_save_settings' );
            $keys = array(
                'connection_mode', 'managed_service_url', 'site_id', 'auth_base', 'api_base', 'currency_id', 'default_category_id', 'listing_type_id', 'condition', 'logistic_type',
                'sync_interval_minutes', 'sync_batch_size', 'stock_strategy', 'price_adjustment_type', 'price_adjustment_value',
                'webhook_secret', 'log_retention_days'
            );
            $settings = array();
            foreach ( $keys as $key ) {
                $settings[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
            }
            foreach ( array( 'auto_sync_on_save', 'include_description', 'enable_order_import', 'require_webhook_secret', 'pkce_enabled', 'debug_mode', 'log_http_bodies', 'diagnostic_rest_probe', 'remove_data_on_uninstall', 'auto_remove_manufacturing_time', 'auto_remove_manufacturing_time_dry_run', 'interest_free_installments_done' ) as $key ) {
                $settings[ $key ] = isset( $_POST[ $key ] ) ? 1 : 0;
            }
            $settings['auth_base'] = esc_url_raw( $settings['auth_base'] );
            $settings['api_base']  = esc_url_raw( $settings['api_base'] );
            $settings['managed_service_url'] = esc_url_raw( $settings['managed_service_url'] );
            mpap_update_settings( $settings );

            $client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
            $client_secret = isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '';
            if ( $client_id || $client_secret ) {
                $this->auth->save_credentials( $client_id, $client_secret );
            }
            $this->reschedule_cron();
            MPAP_Logger::info( 'settings', 'Configurações salvas pelo painel.', array( 'settings' => $settings, 'client_id' => $this->auth->mask( $client_id ) ), array( 'event' => 'settings_saved' ) );
            wp_safe_redirect( add_query_arg( 'mpap_notice', 'settings_saved', admin_url( 'admin.php?page=mpap-settings' ) ) );
            exit;
        }
        if ( isset( $_POST['mpap_clear_logs'] ) ) {
            check_admin_referer( 'mpap_clear_logs' );
            MPAP_Logger::clear();
            wp_safe_redirect( add_query_arg( 'mpap_notice', 'logs_cleared', admin_url( 'admin.php?page=mpap-logs' ) ) );
            exit;
        }
    }

    public function export_logs() {
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_die( 'Permissão negada.' );
        }
        check_admin_referer( 'mpap_export_logs' );
        $logs = MPAP_Logger::export_json();
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=mpap-logs-' . gmdate( 'Ymd-His' ) . '.json' );
        echo wp_json_encode( $logs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        exit;
    }

    private function reschedule_cron() {
        foreach ( array( 'mpap_cron_sync', 'mpap_cron_refresh_token', 'mpap_cron_prune_logs' ) as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            while ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
                $timestamp = wp_next_scheduled( $hook );
            }
        }
        MPAP_Plugin::schedule_events();
    }

    private function shell( $active ) {
        $connected = $this->auth->connected();
        echo '<div class="wrap mpap-wrap">';
        echo '<div class="mpap-hero"><div class="mpap-hero__icon"><span class="dashicons dashicons-store"></span></div><div><h1>Mercado Pago Agência Privilege</h1><p>Integração completa entre Mercado Livre e WooCommerce.</p></div><div class="mpap-system-pill ' . ( $connected ? 'is-online' : 'is-offline' ) . '"><span></span>' . ( $connected ? 'Sistema operacional' : 'Aguardando conexão' ) . '</div></div>';
        echo '<nav class="mpap-tabs">';
        $tabs = array( 'dashboard' => 'Dashboard', 'products' => 'Produtos', 'quality' => 'Qualidade dos Anúncios', 'categories' => 'Categorias ML', 'orders' => 'Pedidos', 'webhooks' => 'Webhooks', 'settings' => 'Configurações', 'diagnostics' => 'Diagnóstico', 'logs' => 'Logs', 'support' => 'Suporte' );
        foreach ( $tabs as $slug => $label ) {
            echo '<a class="' . ( $slug === $active ? 'active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=mpap-' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
    }

    private function end_shell() {
        echo '</div>';
    }

    private function stat_card( $icon, $label, $number, $sub ) {
        echo '<div class="mpap-stat-card"><div class="mpap-stat-icon"><span class="dashicons ' . esc_attr( $icon ) . '"></span></div><div><h3>' . esc_html( $label ) . '</h3><strong>' . esc_html( $number ) . '</strong><p>' . esc_html( $sub ) . '</p></div><div class="mpap-sparkline"></div></div>';
    }

    public function dashboard() {
        $this->shell( 'dashboard' );
        $ps = $this->products->stats();
        $os = $this->orders->stats();
        $ws = MPAP_Webhooks::stats();
        $counts = MPAP_Logger::counts();
        $tokens = $this->auth->tokens();
        $managed = $this->auth->managed_connection();
        $mode_label = $this->auth->using_managed() ? 'Privilege Connect' : 'Manual';
        $token_until = $this->auth->using_managed() ? mpap_datetime( $managed['expires_at'] ?? 0 ) : mpap_datetime( $tokens['expires_at'] ?? '' );
        $seller_id = $this->auth->using_managed() ? ( $managed['seller_nickname'] ?: ( $managed['seller_user_id'] ?: '—' ) ) : ( $tokens['user_id'] ?? '—' );
        echo '<section class="mpap-grid mpap-stats-grid">';
        $this->stat_card( 'dashicons-products', 'Produtos Sincronizados', number_format_i18n( $ps['synced'] ), number_format_i18n( $ps['enabled'] ) . ' habilitados' );
        $this->stat_card( 'dashicons-cart', 'Pedidos Importados', number_format_i18n( $os['imported'] ), 'WooCommerce' );
        $this->stat_card( 'dashicons-database', 'Webhooks Recebidos', number_format_i18n( $ws['received'] ), number_format_i18n( $ws['topics'] ) . ' tópicos' );
        $this->stat_card( 'dashicons-visibility', 'Logs de Erro', number_format_i18n( $counts['error'] ), 'Debug ativo' );
        echo '</section>';
        echo '<section class="mpap-grid mpap-grid-3"><div class="mpap-card"><h2><span class="dashicons dashicons-shield"></span>Conexão & API</h2><ul class="mpap-status-list"><li>Status<strong>' . ( $this->auth->connected() ? 'Conectado' : 'Desconectado' ) . '</strong></li><li>Modo<strong>' . esc_html( $mode_label ) . '</strong></li><li>Token/conexão válido até<strong>' . esc_html( $token_until ) . '</strong></li><li>Vendedor ML<strong>' . esc_html( $seller_id ) . '</strong></li></ul><a class="mpap-button" href="' . esc_url( admin_url( 'admin.php?page=mpap-settings' ) ) . '">Gerenciar conexão</a> <a class="mpap-button" href="' . esc_url( admin_url( 'admin.php?page=mpap-logs&source=' . ( $this->auth->using_managed() ? 'connect' : 'oauth' ) ) ) . '">Ver logs da conexão</a></div>';
        echo '<div class="mpap-card"><h2><span class="dashicons dashicons-update"></span>Sincronização de Produtos</h2><p>Sincronize produtos, preços e estoque entre WooCommerce e Mercado Livre.</p><button class="mpap-button mpap-button-primary mpap-sync-all">Sincronizar Agora</button><button class="mpap-button mpap-update-stock-all">Atualizar Estoque</button><div class="mpap-ajax-result"></div></div>';
        echo '<div class="mpap-card"><h2><span class="dashicons dashicons-search"></span>Diagnóstico Rápido</h2><p>Gere logs e valide configuração local antes de conectar.</p><button class="mpap-button mpap-run-diagnostics">Rodar Diagnóstico</button><button class="mpap-button mpap-test-oauth-readiness">Validar OAuth Local</button><button class="mpap-button mpap-test-public-api">Testar API (opcional)</button><div class="mpap-ajax-result"></div></div></section>';
        echo '<section class="mpap-grid mpap-grid-2"><div class="mpap-card"><h2>Produtos Sincronizados</h2>';
        $this->products_table( $this->products->rows( 8 ) );
        echo '</div><div class="mpap-card"><h2>Configurações & Sistema</h2><ul class="mpap-status-list"><li>Modo de conexão<strong>' . esc_html( $mode_label ) . '</strong></li><li>Broker Connect<strong>' . esc_html( mpap_managed_service_url() ?: '—' ) . '</strong></li><li>Redirect manual<strong class="mpap-copy-value">' . esc_html( mpap_oauth_redirect_uri() ) . '</strong></li><li>Webhook URL<strong class="mpap-copy-value">' . esc_html( mpap_webhook_url( true ) ) . '</strong></li><li>Cron<strong>A cada ' . esc_html( mpap_get_settings( 'sync_interval_minutes', 15 ) ) . ' min</strong></li></ul></div></section>';
        $this->end_shell();
    }

    public function categories() {
        $this->shell( 'categories' );
        $settings = mpap_get_settings();
        $example = $settings['default_category_query'] ?: 'template wordpress elementor';
        echo '<div class="mpap-grid mpap-grid-2"><div class="mpap-card"><h2><span class="dashicons dashicons-category"></span> Buscador de Categoria Mercado Livre</h2><p>Digite o título real de um produto. O plugin consulta o preditor do Mercado Livre com sua conexão autenticada e retorna o <strong>ID MLB correto</strong>, caminho da categoria e atributos obrigatórios.</p>';
        echo '<div class="mpap-inline-form mpap-category-search"><input type="text" class="mpap-category-query" value="' . esc_attr( $example ) . '" placeholder="Ex.: template wordpress elementor, tema wordpress para loja virtual"><input type="number" class="mpap-category-limit" value="8" min="1" max="10"><button type="button" class="mpap-button mpap-button-primary mpap-predict-category">Buscar categoria correta</button></div>';
        echo '<p class="mpap-help">Sugestões para seus produtos: <button type="button" class="mpap-link-button mpap-fill-category-query" data-query="template wordpress elementor">template wordpress elementor</button> <button type="button" class="mpap-link-button mpap-fill-category-query" data-query="tema wordpress woocommerce">tema wordpress woocommerce</button> <button type="button" class="mpap-link-button mpap-fill-category-query" data-query="template para site wordpress">template para site wordpress</button></p>';
        echo '<div class="mpap-ajax-result"></div></div>';
        echo '<div class="mpap-card"><h2>Categoria padrão atual</h2><ul class="mpap-status-list"><li>ID da categoria<strong class="mpap-default-category-id">' . esc_html( $settings['default_category_id'] ?: '—' ) . '</strong></li><li>Nome<strong class="mpap-default-category-name">' . esc_html( $settings['default_category_name'] ?: '—' ) . '</strong></li><li>Caminho<strong class="mpap-default-category-path">' . esc_html( $settings['default_category_path'] ?: '—' ) . '</strong></li><li>Domínio<strong class="mpap-default-category-domain">' . esc_html( $settings['default_category_domain'] ?: '—' ) . '</strong></li></ul><p class="mpap-help">Depois de aplicar a categoria padrão, sincronize apenas 1 produto para validar atributos e regras de publicação.</p></div></div>';
        echo '<div class="mpap-card"><h2>Resultados</h2><div class="mpap-category-results"><p>Nenhuma busca executada nesta tela ainda.</p></div></div>';
        $this->end_shell();
    }

    public function products() {
        $this->shell( 'products' );
        echo '<div class="mpap-card"><div class="mpap-card-head"><h2>Produtos Integrados</h2><div><button class="mpap-button mpap-button-primary mpap-sync-all">Sincronizar lote</button> <button class="mpap-button mpap-update-stock-all">Atualizar estoque</button></div></div><div class="mpap-ajax-result"></div>';
        $this->products_table( $this->products->rows( 50 ), true );
        echo '</div>';
        $this->end_shell();
    }

    private function products_table( array $rows, $actions = false ) {
        echo '<table class="mpap-table"><thead><tr><th>Produto</th><th>SKU</th><th>WooCommerce</th><th>Mercado Livre</th><th>Estoque</th><th>Status</th>' . ( $actions ? '<th>Ações</th>' : '' ) . '</tr></thead><tbody>';
        if ( ! $rows ) {
            echo '<tr><td colspan="' . ( $actions ? 7 : 6 ) . '">Nenhum produto encontrado.</td></tr>';
        }
        foreach ( $rows as $row ) {
            echo '<tr><td><a href="' . esc_url( $row['edit_url'] ) . '">' . esc_html( $row['name'] ) . '</a></td><td>' . esc_html( $row['sku'] ?: '—' ) . '</td><td><span class="mpap-dot green"></span>' . esc_html( $row['wc_status'] ) . '</td><td>' . ( $row['ml_item'] ? '<span class="mpap-dot green"></span>' . esc_html( $row['ml_item'] ) : '<span class="mpap-dot yellow"></span>—' ) . '</td><td>' . esc_html( (string) $row['stock'] ) . '</td><td><span class="mpap-badge ' . ( $row['ml_item'] ? 'success' : 'warning' ) . '">' . esc_html( $row['status'] ?: ( $row['ml_item'] ? 'sincronizado' : 'pendente' ) ) . '</span></td>';
            if ( $actions ) {
                echo '<td><button class="mpap-button small mpap-sync-product" data-product-id="' . esc_attr( $row['id'] ) . '">Sincronizar</button></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public function orders() {
        $this->shell( 'orders' );
        echo '<div class="mpap-card"><h2>Pedidos Mercado Livre</h2><p>Importação automática via webhook <code>orders_v2</code> e importação manual por ID.</p><div class="mpap-inline-form"><input type="text" class="mpap-order-id" placeholder="ID do pedido Mercado Livre"><button class="mpap-button mpap-button-primary mpap-import-order">Importar Pedido</button></div><div class="mpap-ajax-result"></div></div>';
        $this->end_shell();
    }

    public function webhooks() {
        $this->shell( 'webhooks' );
        echo '<div class="mpap-card"><h2>Webhooks Recebidos</h2><p>Configure esta URL na aplicação Mercado Livre:</p><div class="mpap-copy-box"><code>' . esc_html( mpap_webhook_url( true ) ) . '</code><button type="button" class="mpap-copy-button" data-copy="' . esc_attr( mpap_webhook_url( true ) ) . '">Copiar</button></div><table class="mpap-table"><thead><tr><th>Data</th><th>Tópico</th><th>Recurso</th><th>Status</th></tr></thead><tbody>';
        foreach ( MPAP_Webhooks::recent( 100 ) as $event ) {
            echo '<tr><td>' . esc_html( mpap_datetime( $event['created_at'] ) ) . '</td><td>' . esc_html( $event['topic'] ) . '</td><td>' . esc_html( $event['resource'] ) . '</td><td><span class="mpap-badge success">' . esc_html( $event['status'] ) . '</span></td></tr>';
        }
        echo '</tbody></table></div>';
        $this->end_shell();
    }

    public function settings() {
        $this->shell( 'settings' );
        $settings = mpap_get_settings();
        $credentials = $this->auth->credentials( false );
        $tokens = $this->auth->tokens();
        $managed = $this->auth->managed_connection();
        $oauth = $this->auth->oauth_status();
        $mode = $settings['connection_mode'] ?? 'manual';
        echo '<form method="post" class="mpap-settings-form">';
        wp_nonce_field( 'mpap_save_settings' );
        echo '<div class="mpap-grid mpap-grid-2"><div class="mpap-card mpap-connect-card"><h2>Conexão Rápida — Privilege Connect</h2><p class="mpap-help">Use este modo para funcionar como Tiny/Bling: o WordPress não recebe nem armazena Client ID/Client Secret. As credenciais ficam em um broker OAuth seguro da Agência Privilege e o plugin recebe apenas um token de conexão.</p>';
        $this->select( 'Modo de conexão', 'connection_mode', $mode, array( 'manual' => 'Manual: credenciais no WordPress', 'managed' => 'Privilege Connect: sem digitar credenciais' ) );
        $this->field( 'URL do broker Privilege Connect', 'managed_service_url', $settings['managed_service_url'], 'url', 'Exemplo: https://connect.seudominio.com. Esta URL precisa hospedar o serviço broker que guarda o Client Secret da aplicação Mercado Livre.' );
        echo '<div class="mpap-copy-box"><strong>Callback do broker para voltar ao WordPress</strong><code>' . esc_html( mpap_managed_oauth_callback_uri() ) . '</code><button type="button" class="mpap-copy-button" data-copy="' . esc_attr( mpap_managed_oauth_callback_uri() ) . '">Copiar</button></div>';
        echo '<div class="mpap-actions"><button class="mpap-button mpap-button-primary" name="mpap_save_settings" value="1">Salvar Configurações</button> <a class="mpap-button mpap-button-primary" href="' . esc_url( mpap_managed_oauth_start_uri() ) . '">Conectar sem credenciais</a> <a class="mpap-button danger" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mpap_managed_disconnect' ), 'mpap_managed_disconnect' ) ) . '">Desconectar Connect</a></div>';
        echo '<ul class="mpap-status-list compact"><li>Status Connect<strong>' . ( $this->auth->managed_connected() ? 'Conectado' : 'Desconectado' ) . '</strong></li><li>Connection ID<strong>' . esc_html( $managed['connection_id'] ?: '—' ) . '</strong></li><li>Vendedor ML<strong>' . esc_html( $managed['seller_nickname'] ?: ( $managed['seller_user_id'] ?: '—' ) ) . '</strong></li><li>Serviço<strong>' . esc_html( $managed['service_url'] ?: '—' ) . '</strong></li></ul></div>';

        echo '<div class="mpap-card"><h2>Conexão Manual Mercado Livre Developers</h2><p class="mpap-help">Use somente quando você quiser cadastrar manualmente a aplicação Mercado Livre deste site. Para sincronizar produtos, use credenciais do <strong>Mercado Livre Developers</strong>, não do Mercado Pago Developers.</p>';
        $this->field( 'Client ID / App ID Mercado Livre', 'client_id', $credentials['client_id'] ?? '', 'text' );
        $has_manual_secret = ! empty( $credentials['client_secret'] );
        $this->field( 'Client Secret Mercado Livre', 'client_secret', '', 'password', $has_manual_secret ? 'Secret já cadastrado e armazenado criptografado. O campo fica vazio por segurança. Preencha somente se quiser substituir.' : 'Nenhum secret salvo ainda. Preencha uma vez e clique em Salvar Configurações.' );
        echo '<p class="mpap-help"><span class="mpap-badge ' . ( $has_manual_secret ? 'success' : 'warning' ) . '">' . ( $has_manual_secret ? 'Client Secret armazenado com segurança' : 'Client Secret ainda não armazenado' ) . '</span></p>';
        echo '<div class="mpap-copy-box"><strong>Redirect URI limpa para aplicação manual</strong><code>' . esc_html( mpap_oauth_redirect_uri() ) . '</code><button type="button" class="mpap-copy-button" data-copy="' . esc_attr( mpap_oauth_redirect_uri() ) . '">Copiar</button><p class="description">Use esta URL no Mercado Livre Developers. Ela evita o callback antigo com query string fixa em admin-post.php.</p></div>';
        echo '<div class="mpap-copy-box mpap-muted"><strong>Redirect URI antiga — referência</strong><code>' . esc_html( mpap_legacy_oauth_redirect_uri() ) . '</code></div>';
        $oauth_preview = ! empty( $credentials['client_id'] ) ? $this->auth->build_authorization_url( 'DIAGNOSTICO-STATE', false, false ) : '';
        if ( $oauth_preview && ! is_wp_error( $oauth_preview ) ) {
            echo '<div class="mpap-copy-box"><strong>URL OAuth de diagnóstico sem PKCE</strong><code>' . esc_html( $oauth_preview ) . '</code><button type="button" class="mpap-copy-button" data-copy="' . esc_attr( $oauth_preview ) . '">Copiar</button></div>';
        }
        echo '<div class="mpap-actions"><button class="mpap-button mpap-button-primary" name="mpap_save_settings" value="1">Salvar Configurações</button> <a class="mpap-button" href="' . esc_url( mpap_oauth_start_uri() ) . '">Conectar com PKCE</a> <a class="mpap-button mpap-button-primary" href="' . esc_url( mpap_oauth_start_no_pkce_uri() ) . '">Conectar sem PKCE</a> <a class="mpap-button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mpap_refresh_token' ), 'mpap_refresh_token' ) ) . '">Renovar Token</a> <a class="mpap-button danger" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mpap_disconnect' ), 'mpap_disconnect' ) ) . '">Desconectar</a></div>';
        echo '<ul class="mpap-status-list compact"><li>Status manual<strong>' . ( ! empty( $tokens['access_token'] ) ? 'Conectado' : 'Desconectado' ) . '</strong></li><li>Token expira em<strong>' . esc_html( mpap_datetime( $tokens['expires_at'] ?? 0 ) ) . '</strong></li><li>Modo ativo<strong>' . ( 'managed' === $mode ? 'Privilege Connect' : 'Manual' ) . '</strong></li><li>Último OAuth<strong>' . esc_html( ( $oauth['status'] ?? 'idle' ) . ' — ' . ( $oauth['message'] ?? '' ) ) . '</strong></li><li>OAuth iniciado em<strong>' . esc_html( mpap_datetime( $oauth['started_at'] ?? 0 ) ) . '</strong></li><li>Callback recebido em<strong>' . esc_html( mpap_datetime( $oauth['callback_at'] ?? 0 ) ) . '</strong></li></ul></div>';

        echo '<div class="mpap-card"><h2>API & Padrões</h2>';
        $this->field( 'Site ID', 'site_id', $settings['site_id'] );
        $this->field( 'Auth Host', 'auth_base', $settings['auth_base'] );
        $this->field( 'API Host', 'api_base', $settings['api_base'] );
        $this->field( 'Moeda', 'currency_id', $settings['currency_id'] );
        $this->field( 'Categoria padrão ML', 'default_category_id', $settings['default_category_id'], 'text', 'Use o ID da categoria Mercado Livre. Ex.: MLB1055. Para descobrir o ID correto, use a aba Categorias ML.' );
        echo '<div class="mpap-category-mini"><div class="mpap-inline-form"><input type="text" class="mpap-category-query" value="' . esc_attr( $settings['default_category_query'] ?: 'template wordpress elementor' ) . '" placeholder="Buscar categoria por título do produto"><button type="button" class="mpap-button mpap-predict-category">Buscar categoria</button><a class="mpap-button" href="' . esc_url( admin_url( 'admin.php?page=mpap-categories' ) ) . '">Abrir buscador completo</a></div><div class="mpap-ajax-result"></div><div class="mpap-category-results mini"></div></div>';
        if ( ! empty( $settings['default_category_name'] ) || ! empty( $settings['default_category_path'] ) ) { echo '<p class="mpap-help"><strong>Categoria salva:</strong> ' . esc_html( $settings['default_category_id'] ) . ' — ' . esc_html( $settings['default_category_path'] ?: $settings['default_category_name'] ) . '</p>'; }
        $this->field( 'Tipo de anúncio padrão', 'listing_type_id', $settings['listing_type_id'] );
        $this->select( 'Condição padrão', 'condition', $settings['condition'], array( 'new' => 'Novo', 'used' => 'Usado' ) );
        echo '</div><div class="mpap-card"><h2>Sincronização</h2>';
        $this->field( 'Intervalo Cron (min)', 'sync_interval_minutes', $settings['sync_interval_minutes'], 'number' );
        $this->field( 'Tamanho do lote', 'sync_batch_size', $settings['sync_batch_size'], 'number' );
        $this->select( 'Estratégia de estoque', 'stock_strategy', $settings['stock_strategy'], array( 'wc_to_ml' => 'WooCommerce → Mercado Livre', 'ml_to_wc' => 'Mercado Livre → WooCommerce', 'two_way' => 'Duas vias' ) );
        $this->select( 'Ajuste de preço', 'price_adjustment_type', $settings['price_adjustment_type'], array( 'none' => 'Nenhum', 'percent' => 'Percentual', 'fixed' => 'Valor fixo' ) );
        $this->field( 'Valor do ajuste', 'price_adjustment_value', $settings['price_adjustment_value'], 'number' );
        $this->checkbox( 'Sincronizar ao salvar produto', 'auto_sync_on_save', $settings['auto_sync_on_save'] );
        $this->checkbox( 'Enviar descrição do produto', 'include_description', $settings['include_description'] );
        $this->checkbox( 'Remover prazo de disponibilidade automaticamente quando houver estoque', 'auto_remove_manufacturing_time', $settings['auto_remove_manufacturing_time'] );
        $this->checkbox( 'Primeira execução de remoção de prazo em dry-run', 'auto_remove_manufacturing_time_dry_run', $settings['auto_remove_manufacturing_time_dry_run'] );
        $this->checkbox( 'Parcelas sem juros ativadas na conta ML/MP (controle interno)', 'interest_free_installments_done', $settings['interest_free_installments_done'] );
        $this->checkbox( 'Importar pedidos por webhook', 'enable_order_import', $settings['enable_order_import'] );
        echo '</div><div class="mpap-card"><h2>Webhooks, Logs & Segurança</h2>';
        $this->field( 'Webhook Secret', 'webhook_secret', $settings['webhook_secret'] );
        echo '<div class="mpap-copy-box"><strong>Webhook URL</strong><code>' . esc_html( mpap_webhook_url( true ) ) . '</code><button type="button" class="mpap-copy-button" data-copy="' . esc_attr( mpap_webhook_url( true ) ) . '">Copiar</button></div>';
        $this->checkbox( 'Exigir segredo no webhook', 'require_webhook_secret', $settings['require_webhook_secret'] );
        $this->checkbox( 'Usar PKCE no OAuth manual', 'pkce_enabled', $settings['pkce_enabled'] );
        echo '<p class="mpap-help">Para diagnóstico, deixe desmarcado e use o botão <strong>Conectar sem PKCE</strong>. Se sua aplicação aceitar PKCE, você pode reativar depois.</p>';
        $this->checkbox( 'Ativar logs de debug', 'debug_mode', $settings['debug_mode'] );
        $this->checkbox( 'Registrar bodies HTTP sanitizados', 'log_http_bodies', $settings['log_http_bodies'] );
        $this->field( 'Retenção de logs (dias)', 'log_retention_days', $settings['log_retention_days'], 'number' );
        $this->checkbox( 'Remover dados ao desinstalar', 'remove_data_on_uninstall', $settings['remove_data_on_uninstall'] );
        echo '</div></div><p><button class="mpap-button mpap-button-primary" name="mpap_save_settings" value="1">Salvar Configurações</button></p></form>';
        $this->end_shell();
    }

    private function field( $label, $name, $value, $type = 'text', $help = '' ) {
        echo '<label class="mpap-field"><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"></label>';
        if ( $help ) {
            echo '<p class="mpap-help">' . wp_kses_post( $help ) . '</p>';
        }
    }

    private function select( $label, $name, $value, array $options ) {
        echo '<label class="mpap-field"><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '">';
        foreach ( $options as $key => $option_label ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $option_label ) . '</option>';
        }
        echo '</select></label>';
    }

    private function checkbox( $label, $name, $checked ) {
        echo '<label class="mpap-check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $checked, 1, false ) . '> <span>' . esc_html( $label ) . '</span></label>';
    }



    public function quality_page() {
        $this->shell( 'quality' );
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
        $tabs = array(
            'overview'=>'Visão geral','duplicates'=>'Produtos duplicados por nome','photos'=>'Produtos com poucas fotos','attributes'=>'Ficha técnica incompleta','anatel'=>'Informação regulatória/Anatel','availability'=>'Prazo de disponibilidade','inactive'=>'Anúncios inativos','logs'=>'Logs de sincronização'
        );
        echo '<div class="mpap-card"><h2>Qualidade e Recomendações Mercado Livre</h2><p class="mpap-help">Camada preventiva: bloqueia duplicidades por nome, exige 3 fotos únicas, valida Anatel quando a categoria indicar atributo regulatório e evita MANUFACTURING_TIME para estoque imediato. Ações destrutivas sempre começam por dry-run; anúncio encerrado no Mercado Livre pode não ser restaurável.</p><nav class="mpap-tabs compact">';
        foreach ( $tabs as $key => $label ) { echo '<a class="' . ( $tab === $key ? 'active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=mpap-quality&tab=' . $key ) ) . '">' . esc_html( $label ) . '</a>'; }
        echo '</nav><div class="mpap-actions"><button class="mpap-button mpap-dry-run-sync-all">Dry-run sincronização</button> <button class="mpap-button danger mpap-close-all-ml-dry-run">Prévia: deletar/encerrar todos anúncios ML</button> <button class="mpap-button danger mpap-close-all-ml-execute">Executar encerramento (digite ENCERAR)</button></div><div class="mpap-ajax-result"></div></div>';
        if ( 'logs' === $tab ) {
            echo '<div class="mpap-card"><h2>Logs de sincronização</h2><p><a class="mpap-button" href="' . esc_url( admin_url( 'admin.php?page=mpap-logs&source=products' ) ) . '">Abrir logs de produtos</a> <a class="mpap-button" href="' . esc_url( admin_url( 'admin.php?page=mpap-logs&source=quality' ) ) . '">Abrir logs de qualidade</a></p></div>';
            $this->end_shell();
            return;
        }
        $rows = $this->quality_rows( $tab );
        echo '<div class="mpap-card"><div class="mpap-card-head"><h2>' . esc_html( $tabs[ $tab ] ?? $tabs['overview'] ) . '</h2><a class="mpap-button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mpap_export_logs' ), 'mpap_export_logs' ) ) . '">Exportar logs JSON</a></div>';
        echo '<table class="mpap-table"><thead><tr><th>Produto</th><th>SKU</th><th>Fotos</th><th>Item ML</th><th>Status ML</th><th>Qualidade</th><th>Ação sugerida</th></tr></thead><tbody>';
        if ( ! $rows ) { echo '<tr><td colspan="7">Nenhum item encontrado para este filtro.</td></tr>'; }
        foreach ( $rows as $row ) {
            echo '<tr><td><a href="' . esc_url( get_edit_post_link( $row['id'] ) ) . '">' . esc_html( $row['name'] ) . '</a><br><small>#' . esc_html( $row['id'] ) . '</small></td><td>' . esc_html( $row['sku'] ) . '</td><td>' . esc_html( $row['photos'] ) . '</td><td>' . esc_html( $row['item_id'] ?: '—' ) . '</td><td>' . esc_html( $row['ml_status'] ?: '—' ) . '</td><td><span class="mpap-badge ' . esc_attr( 'blocked' === $row['quality_status'] ? 'danger' : ( 'approved' === $row['quality_status'] ? 'success' : 'warning' ) ) . '">' . esc_html( $row['quality_status'] ?: 'pendente' ) . '</span><br>' . esc_html( $row['score'] ) . '%</td><td>' . esc_html( $row['action'] ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="mpap-card"><h2>Checklist de fotos</h2><ul class="mpap-ordered"><li>Foto nítida, produto centralizado e fundo branco/neutro.</li><li>Sem marca d’água, textos promocionais, selos ML, telefone, WhatsApp, e-mail, QR Code ou link.</li><li>Mínimo 500x500 px; recomendado 1200x1200 ou superior.</li></ul><p class="mpap-help">Parcelas sem juros: sem endpoint de item já usado pelo plugin para ativar isso com segurança. Marque como concluído nas configurações após ativar na conta Mercado Livre/Mercado Pago/campanhas oficiais.</p></div>';
        $this->end_shell();
    }

    private function quality_rows( $tab ) {
        if ( ! mpap_has_wc() ) { return array(); }
        $ids = get_posts( array( 'post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>100,'orderby'=>'modified','order'=>'DESC','no_found_rows'=>true ) );
        $rows = array();
        foreach ( $ids as $id ) {
            $product = wc_get_product( $id ); if ( ! $product ) { continue; }
            $payload = $this->products->payload( $product );
            $quality = $this->quality->validate( $product, is_wp_error( $payload ) ? array() : $payload, 'admin' );
            $diag = get_post_meta( $id, MPAP_Quality::META_DIAG, true ); $diag = is_array( $diag ) ? implode( '; ', $diag ) : (string) $diag;
            $row = array( 'id'=>$id, 'name'=>$product->get_name(), 'sku'=>$product->get_sku(), 'photos'=>$quality['photo_count'], 'item_id'=>get_post_meta($id,'_mpap_ml_item_id',true), 'ml_status'=>get_post_meta($id,'_mpap_ml_status',true), 'quality_status'=>$quality['status'], 'score'=>$quality['score'], 'action'=>$quality['blocked'] ? $diag : implode( '; ', $quality['recommendations'] ) );
            if ( 'duplicates' === $tab && empty( $quality['duplicate']['is_duplicate'] ) ) { continue; }
            if ( 'photos' === $tab && $quality['photo_count'] >= 3 ) { continue; }
            if ( 'anatel' === $tab && false === stripos( $row['action'], 'Anatel' ) && false === stripos( $row['action'], 'Certifica' ) ) { continue; }
            if ( 'availability' === $tab && false === stripos( $row['action'], 'MANUFACTURING_TIME' ) && false === stripos( $row['action'], 'disponibilidade' ) ) { continue; }
            if ( 'inactive' === $tab && ! in_array( $row['ml_status'], array( 'inactive', 'paused', 'under_review', 'inactive_to_review', 'closed', 'error', 'blocked' ), true ) ) { continue; }
            $rows[] = $row;
        }
        return $rows;
    }

    public function diagnostics() {
        $this->shell( 'diagnostics' );
        $diag = $this->diagnostics->run();
        echo '<div class="mpap-grid mpap-grid-2"><div class="mpap-card"><h2>Diagnóstico de Conexão</h2><p>Use esta tela para validar o plugin antes do OAuth. Quando o Mercado Livre mostra <strong>“O aplicativo não está pronto para se conectar”</strong>, geralmente o erro acontece no painel Mercado Livre antes de voltar para o WordPress; por isso o log mais útil fica em <strong>oauth_start</strong> e no status <strong>Último OAuth</strong>. Se o status estiver <strong>idle</strong>, a conexão ainda não foi iniciada nesta instalação/log atual.</p><div class="mpap-actions"><a class="mpap-button mpap-button-primary" href="' . esc_url( mpap_oauth_start_no_pkce_uri() ) . '">Iniciar OAuth sem PKCE</a><a class="mpap-button" href="' . esc_url( mpap_oauth_start_uri() ) . '">Iniciar OAuth com PKCE</a><button class="mpap-button mpap-run-diagnostics">Rodar Diagnóstico</button><button class="mpap-button mpap-test-oauth-readiness">Validar OAuth Local</button><button class="mpap-button mpap-test-public-api">Testar API (opcional)</button><button class="mpap-button mpap-test-connection">Testar Conexão Autenticada</button><button class="mpap-button mpap-test-log">Gerar Log de Teste</button></div><div class="mpap-ajax-result"></div></div>';
        echo '<div class="mpap-card"><h2>Checklist do erro “aplicativo não está pronto”</h2><ol class="mpap-ordered"><li>Use credenciais da aplicação criada em <strong>Mercado Livre Developers</strong>.</li><li>Copie a Redirect URI exatamente como aparece no plugin.</li><li>Confirme se a aplicação está completa/aprovada e com permissões para a conta vendedora.</li><li>Não misture Client ID/Secret de Mercado Pago com Mercado Livre.</li><li>Depois de clicar em Conectar, abra <strong>Logs → fonte OAuth</strong> e confira a URL de autorização registrada.</li><li>Teste também <strong>Conectar sem PKCE</strong> se a autorização parar antes do callback.</li></ol></div></div>';
        echo '<div class="mpap-card"><h2>Resultado Local</h2>';
        $this->diagnostics_table( $diag['checks'] );
        echo '</div>';
        $this->end_shell();
    }

    private function diagnostics_table( array $checks ) {
        echo '<table class="mpap-table"><thead><tr><th>Teste</th><th>Status</th><th>Mensagem</th></tr></thead><tbody>';
        foreach ( $checks as $check ) {
            $class = 'ok' === $check['status'] ? 'success' : ( 'warning' === $check['status'] ? 'warning' : 'danger' );
            echo '<tr><td>' . esc_html( $check['label'] ) . '</td><td><span class="mpap-badge ' . esc_attr( $class ) . '">' . esc_html( $check['status'] ) . '</span></td><td><code class="mpap-code-inline">' . esc_html( $check['message'] ) . '</code></td></tr>';
        }
        echo '</tbody></table>';
    }

    public function logs() {
        $this->shell( 'logs' );
        $level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
        $source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $limit = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 200;
        $logs = MPAP_Logger::recent( $limit, array( 'level' => $level, 'source' => $source, 'search' => $search ) );
        $counts = MPAP_Logger::counts();

        echo '<div class="mpap-card"><div class="mpap-card-head"><h2>Logs Profissionais</h2><div><button class="mpap-button mpap-test-log">Gerar log de teste</button> <a class="mpap-button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mpap_export_logs' ), 'mpap_export_logs' ) ) . '">Exportar JSON</a></div></div>';
        echo '<div class="mpap-log-summary"><span>Debug: ' . esc_html( $counts['debug'] ) . '</span><span>Info: ' . esc_html( $counts['info'] ) . '</span><span>Avisos: ' . esc_html( $counts['warning'] ) . '</span><span>Erros: ' . esc_html( $counts['error'] ) . '</span></div>';
        echo '<p class="mpap-help"><strong>Leitura rápida:</strong> se não houver evento <code>oauth_start</code> na fonte <code>oauth</code>, o Mercado Livre ainda não recebeu uma tentativa real de conexão a partir do botão do plugin. O aviso <code>PolicyAgent 403</code> do teste público é apenas conectividade opcional.</p>';
        echo '<form method="get" class="mpap-log-filter"><input type="hidden" name="page" value="mpap-logs"><select name="level"><option value="">Todos níveis</option>';
        foreach ( array( 'debug', 'info', 'warning', 'error' ) as $lv ) {
            echo '<option value="' . esc_attr( $lv ) . '" ' . selected( $level, $lv, false ) . '>' . esc_html( strtoupper( $lv ) ) . '</option>';
        }
        echo '</select><select name="source"><option value="">Todas fontes</option>';
        foreach ( MPAP_Logger::sources() as $src ) {
            echo '<option value="' . esc_attr( $src ) . '" ' . selected( $source, $src, false ) . '>' . esc_html( $src ) . '</option>';
        }
        echo '</select><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="Buscar mensagem, contexto, request_id"><input type="number" name="limit" value="' . esc_attr( $limit ) . '" min="1" max="1000"><button class="mpap-button">Filtrar</button></form>';
        echo '<form method="post" onsubmit="return confirm(\'Limpar todos os logs?\');">';
        wp_nonce_field( 'mpap_clear_logs' );
        echo '<button class="mpap-button danger" name="mpap_clear_logs" value="1">Limpar logs</button></form><div class="mpap-ajax-result"></div>';
        echo '<table class="mpap-table mpap-log-table"><thead><tr><th>ID</th><th>Data</th><th>Nível</th><th>Fonte</th><th>Evento</th><th>HTTP</th><th>Duração</th><th>Mensagem</th><th>Contexto</th></tr></thead><tbody>';
        if ( ! $logs ) {
            echo '<tr><td colspan="9">Nenhum log encontrado. Clique em <strong>Gerar log de teste</strong> para validar.</td></tr>';
        }
        foreach ( $logs as $log ) {
            $class = 'error' === $log['level'] ? 'danger' : ( 'warning' === $log['level'] ? 'warning' : ( 'debug' === $log['level'] ? 'neutral' : 'success' ) );
            echo '<tr><td>' . esc_html( $log['id'] ) . '</td><td>' . esc_html( mpap_datetime( $log['created_at'] ) ) . '</td><td><span class="mpap-badge ' . esc_attr( $class ) . '">' . esc_html( $log['level'] ) . '</span></td><td>' . esc_html( $log['source'] ) . '</td><td>' . esc_html( $log['event'] ?? '' ) . '</td><td>' . esc_html( $log['http_status'] ?? '' ) . '</td><td>' . esc_html( isset( $log['duration_ms'] ) && '' !== $log['duration_ms'] ? $log['duration_ms'] . 'ms' : '—' ) . '</td><td>' . esc_html( $log['message'] ) . '<br><small>' . esc_html( $log['request_id'] ?? '' ) . '</small></td><td><details><summary>ver JSON</summary><pre>' . esc_html( $log['context'] ) . '</pre></details></td></tr>';
        }
        echo '</tbody></table></div>';
        $this->end_shell();
    }

    public function support() {
        $this->shell( 'support' );
        echo '<div class="mpap-card"><h2>Suporte & Operação</h2><p>Para investigar falhas, exporte o JSON na tela Logs. No modo manual envie também um print da aplicação no Mercado Livre Developers. No modo Privilege Connect envie a URL do broker e os logs das fontes <code>connect</code> e <code>managed_api</code>.</p><h3>Fluxo recomendado</h3><ol class="mpap-ordered"><li>Escolha o modo: Manual ou Privilege Connect.</li><li>No modo Connect, salve a URL do broker e clique em <strong>Conectar sem credenciais</strong>.</li><li>No modo manual, salve Client ID/Secret, copie a Redirect URI e clique em <strong>Conectar manualmente</strong>.</li><li>Rode o Diagnóstico.</li><li>Se der erro, abra Logs filtrando por <code>connect</code>, <code>managed_api</code> ou <code>oauth</code>.</li></ol></div>';
        $this->end_shell();
    }
}
