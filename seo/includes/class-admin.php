<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Admin {
    private $settings;
    private $logs;
    private $scanner;
    private $enrichment;
    private $backup;
    private $import_export;

    public function __construct($settings, $logs, $scanner, $enrichment, $backup) {
        $this->settings = $settings;
        $this->logs = $logs;
        $this->scanner = $scanner;
        $this->enrichment = $enrichment;
        $this->backup = $backup;
        $this->import_export = new SES_Import_Export($settings, $logs, $backup);
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
        add_action('admin_post_ses_save_settings', array($this, 'save_settings'));
        add_action('admin_post_ses_toggle_protection', array($this, 'toggle_protection'));
        add_action('admin_post_ses_bulk_protection', array($this, 'bulk_protection'));
        add_action('admin_post_ses_sync_protections', array($this, 'sync_protections'));
        add_action('admin_post_ses_scan', array($this, 'scan'));
        add_action('admin_post_ses_enrich', array($this, 'enrich'));
        add_action('admin_post_ses_restore', array($this, 'restore'));
        add_action('admin_post_ses_export_enriched', array($this, 'export_enriched'));
        add_action('admin_post_ses_export_generated', array($this, 'export_generated'));
        add_action('admin_post_ses_import_enriched', array($this, 'import_enriched'));
        add_action('wp_ajax_ses_scan_batch', array($this, 'ajax_scan_batch'));
        add_action('wp_ajax_ses_enrich_batch', array($this, 'ajax_enrich_batch'));
        add_action('wp_ajax_ses_import_batch', array($this, 'ajax_import_batch'));
    }

    public function menu() {
        add_menu_page('SEO Enrichment Studio', 'SEO Enrichment Studio', 'manage_options', 'ses-dashboard', array($this, 'dashboard'), 'dashicons-chart-area', 58);
        add_submenu_page('ses-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'ses-dashboard', array($this, 'dashboard'));
        add_submenu_page('ses-dashboard', 'Scanner de Páginas', 'Scanner de Páginas', 'manage_options', 'ses-scanner', array($this, 'scanner_page'));
        add_submenu_page('ses-dashboard', 'Páginas Protegidas', 'Páginas Protegidas', 'manage_options', 'ses-protected-pages', array($this, 'protected_pages'));
        add_submenu_page('ses-dashboard', 'Enriquecimento em Lote', 'Enriquecimento em Lote', 'manage_options', 'ses-enrichment', array($this, 'enrichment_page'));
        add_submenu_page('ses-dashboard', 'Bases de Conteúdo', 'Bases de Conteúdo', 'manage_options', 'ses-content-databases', array($this, 'content_databases'));
        add_submenu_page('ses-dashboard', 'Similaridade', 'Similaridade', 'manage_options', 'ses-similarity', array($this, 'similarity_page'));
        add_submenu_page('ses-dashboard', 'Logs', 'Logs', 'manage_options', 'ses-logs', array($this, 'logs_page'));
        add_submenu_page('ses-dashboard', 'Importar/Exportar', 'Importar/Exportar', 'manage_options', 'ses-import-export', array($this, 'import_export_page'));
        add_submenu_page('ses-dashboard', 'Configurações', 'Configurações', 'manage_options', 'ses-settings', array($this, 'settings_page'));
    }

    public function assets($hook) {
        if (false === strpos($hook, 'ses-')) {
            return;
        }
        wp_enqueue_style('ses-glass-ui', SES_URL . 'assets/glass-ui.css', array(), SES_VERSION);
        wp_enqueue_style('ses-admin', SES_URL . 'assets/admin.css', array('ses-glass-ui'), SES_VERSION);
        wp_enqueue_script('ses-admin', SES_URL . 'assets/admin.js', array('jquery'), SES_VERSION, true);
        wp_localize_script('ses-admin', 'SESAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ses_ajax'),
            'maxScanBatch' => 2000,
            'maxEnrichBatch' => 50,
        ));
    }

    private function render($template, $vars = array()) {
        if (!SES_Security::can_manage()) {
            wp_die(esc_html__('Permissao negada.', 'seo-enrichment-studio'));
        }
        extract($vars, EXTR_SKIP);
        $template_path = SES_PATH . 'templates/' . $template . '.php';
        if (!file_exists($template_path)) {
            echo '<div class="wrap ses-admin"><h1>' . esc_html__('SEO Enrichment Studio', 'seo-enrichment-studio') . '</h1><div class="notice notice-error"><p>' . esc_html(sprintf(__('Template administrativo nao encontrado: %s', 'seo-enrichment-studio'), $template)) . '</p></div></div>';
            return;
        }
        include $template_path;
    }

    public function dashboard() {
        $this->render('dashboard', array('stats' => $this->scanner->stats(), 'settings' => $this->settings->all()));
    }

    public function scanner_page() {
        $this->render('scanner', array('settings' => $this->settings->all(), 'total_pages' => $this->scanner->total_scannable_pages()));
    }

    public function protected_pages() {
        $query = sanitize_text_field($_GET['s'] ?? '');
        $status = sanitize_key($_GET['status'] ?? 'any');
        $post_type = sanitize_key($_GET['post_type'] ?? 'any');
        $mode = sanitize_key($_GET['mode'] ?? 'protected');
        $allowed_types = $this->settings->get('post_types', array('page'));
        $args = array(
            'post_type' => 'any' === $post_type ? $allowed_types : $post_type,
            'post_status' => 'any' === $status ? array('publish', 'draft', 'private') : $status,
            'posts_per_page' => 50,
            's' => $query,
        );
        if ('all' !== $mode) {
            $args['meta_query'] = array(array('key' => '_ses_protected', 'value' => '1'));
        }
        $pages = get_posts($args);
        $this->render('protected-pages', array('pages' => $pages, 'query' => $query, 'status' => $status, 'post_type' => $post_type, 'mode' => $mode, 'allowed_types' => $allowed_types));
    }

    public function enrichment_page() {
        $this->render('enrichment', array('settings' => $this->settings->all(), 'eligible_total' => $this->enrichment->eligible_total()));
    }

    public function content_databases() {
        $this->render('content-databases', array('categories' => (new SES_Profession_Intelligence())->categories()));
    }

    public function similarity_page() {
        $this->render('similarity', array('settings' => $this->settings->all()));
    }

    public function logs_page() {
        $this->render('logs', array('logs' => $this->logs->recent(100)));
    }

    public function settings_page() {
        $this->render('settings', array('settings' => $this->settings->all()));
    }

    public function import_export_page() {
        $this->render('import-export', array(
            'settings' => $this->settings->all(),
            'eligible_total' => $this->enrichment->eligible_total(),
            'sitemap_sync' => get_option('ses_sitemap_last_sync', array()),
        ));
    }

    public function save_settings() {
        SES_Security::verify('ses_save_settings');
        $this->settings->update($_POST['ses'] ?? array());
        wp_safe_redirect(admin_url('admin.php?page=ses-settings&updated=1'));
        exit;
    }

    public function toggle_protection() {
        SES_Security::verify('ses_toggle_protection');
        $post_id = absint($_GET['post_id'] ?? 0);
        $protect = absint($_GET['protect'] ?? 0);
        if ($post_id) {
            $protect ? update_post_meta($post_id, '_ses_protected', 1) : delete_post_meta($post_id, '_ses_protected');
            update_post_meta($post_id, '_ses_enrichment_status', $protect ? 'protegida' : 'pendente');
            $this->logs->add($post_id, $protect ? 'protection' : 'unprotection', 'success', $protect ? 'Protecao manual ativada.' : 'Protecao manual removida.');
        }
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=ses-protected-pages'));
        exit;
    }

    public function bulk_protection() {
        SES_Security::verify('ses_bulk_protection');
        $ids = array_map('absint', (array) ($_POST['post_ids'] ?? array()));
        $mode = sanitize_key($_POST['bulk_mode'] ?? 'protect');
        foreach ($ids as $post_id) {
            if (!$post_id) {
                continue;
            }
            if ('unprotect' === $mode) {
                delete_post_meta($post_id, '_ses_protected');
                update_post_meta($post_id, '_ses_enrichment_status', 'pendente');
                $this->logs->add($post_id, 'unprotection', 'success', 'Protecao removida em massa.');
            } else {
                update_post_meta($post_id, '_ses_protected', 1);
                update_post_meta($post_id, '_ses_enrichment_status', 'protegida');
                $this->logs->add($post_id, 'protection', 'success', 'Protecao aplicada em massa.');
            }
        }
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=ses-protected-pages'));
        exit;
    }

    public function sync_protections() {
        SES_Security::verify('ses_sync_protections');
        $result = $this->scanner->sync_configured_protections();
        wp_safe_redirect(admin_url('admin.php?page=ses-protected-pages&synced=' . absint($result['protected'])));
        exit;
    }

    public function scan() {
        SES_Security::verify('ses_scan');
        $result = $this->scanner->scan_batch_offset(min(2000, absint($_POST['limit'] ?? 25)), absint($_POST['offset'] ?? 0));
        wp_safe_redirect(admin_url('admin.php?page=ses-scanner&scanned=' . absint($result['scanned']) . '&eligible=' . absint($result['eligible'])));
        exit;
    }

    public function enrich() {
        SES_Security::verify('ses_enrich');
        if ($this->settings->get('sync_sitemaps_before_enrich', 1)) {
            $this->scanner->sync_sitemap_pages();
        }
        $result = $this->enrichment->enrich_batch(absint($_POST['limit'] ?? 5));
        wp_safe_redirect(admin_url('admin.php?page=ses-enrichment&processed=' . absint($result['processed']) . '&enriched=' . absint($result['enriched']) . '&errors=' . absint($result['errors'])));
        exit;
    }

    public function restore() {
        SES_Security::verify('ses_restore');
        $this->backup->restore(absint($_GET['post_id'] ?? 0));
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=ses-logs'));
        exit;
    }


    public function export_enriched() {
        SES_Security::verify('ses_export_enriched');
        $payload = $this->import_export->export_enriched();
        $this->download_json($payload, 'ses-enriched-' . gmdate('Ymd-His') . '.json');
    }

    public function export_generated() {
        SES_Security::verify('ses_export_generated');
        $payload = $this->import_export->export_generated(absint($_POST['limit'] ?? 100));
        $this->download_json($payload, 'ses-generated-' . gmdate('Ymd-His') . '.json');
    }

    public function import_enriched() {
        SES_Security::verify('ses_import_enriched');
        if (empty($_FILES['ses_import_file']['tmp_name'])) {
            wp_safe_redirect(admin_url('admin.php?page=ses-import-export&import_error=1'));
            exit;
        }
        $job = $this->import_export->create_import_job($_FILES['ses_import_file'], sanitize_key($_POST['import_mode'] ?? 'meta'));
        if (is_wp_error($job)) {
            wp_safe_redirect(admin_url('admin.php?page=ses-import-export&import_error=1'));
            exit;
        }
        wp_safe_redirect(admin_url('admin.php?page=ses-import-export&import_job=' . rawurlencode($job['id'])));
        exit;
    }

    private function download_json($payload, $filename) {
        if (!SES_Security::can_manage()) {
            wp_die(esc_html__('Permissao negada.', 'seo-enrichment-studio'));
        }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }


    public function ajax_import_batch() {
        SES_Security::verify_ajax('ses_ajax');
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(25);
        }
        $result = $this->import_export->process_import_job(sanitize_key($_POST['job_id'] ?? ''), min(50, max(1, absint($_POST['limit'] ?? 20))), absint($_POST['seconds'] ?? 8));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }
        wp_send_json_success($result);
    }

    public function ajax_scan_batch() {
        SES_Security::verify_ajax('ses_ajax');
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(25);
        }
        $result = $this->scanner->scan_turbo(min(2000, absint($_POST['limit'] ?? 250)), absint($_POST['last_id'] ?? 0), absint($_POST['seconds'] ?? 8));
        $result['total'] = $this->scanner->total_scannable_pages();
        wp_send_json_success($result);
    }

    public function ajax_enrich_batch() {
        SES_Security::verify_ajax('ses_ajax');
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(25);
        }
        $last_id = absint($_POST['last_id'] ?? 0);
        $sitemap_sync = null;
        if (0 === $last_id && $this->settings->get('sync_sitemaps_before_enrich', 1)) {
            $sitemap_sync = $this->scanner->sync_sitemap_pages();
        }
        $result = $this->enrichment->enrich_turbo(min(50, absint($_POST['limit'] ?? 5)), $last_id, absint($_POST['seconds'] ?? 8));
        if (null !== $sitemap_sync) {
            $result['sitemap_sync'] = $sitemap_sync;
        }
        $result['total'] = $this->enrichment->eligible_total();
        wp_send_json_success($result);
    }
}

