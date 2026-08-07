<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SES_Plugin {
    private static $instance = null;
    public $settings;
    public $logs;
    public $backup;
    public $scanner;
    public $enrichment;
    public $schema;
    public $sitemap;
    public $redirects;
    public $admin;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        self::load_files();
        SES_Log_Manager::create_table();
        SES_Settings::install_defaults();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    private function __construct() {
        self::load_files();
        $this->settings = new SES_Settings();
        $this->logs = new SES_Log_Manager();
        $this->backup = new SES_Backup_Manager($this->logs);
        $this->scanner = new SES_Page_Scanner($this->settings, $this->logs);
        $this->enrichment = new SES_Enrichment_Engine($this->settings, $this->logs, $this->backup);
        $this->schema = new SES_Schema_Manager($this->settings);
        $this->sitemap = new SES_Sitemap_Manager($this->settings, $this->logs);
        $this->redirects = new SES_Redirect_Manager($this->settings, $this->logs);

        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
        add_filter('the_content', array($this, 'render_enriched_content'), 99);
        add_action('wp_head', array($this->schema, 'print_schema'), 30);
        add_action('init', array($this->sitemap, 'register_rewrite'));
        add_action('template_redirect', array($this->sitemap, 'maybe_render_sitemap'), 0);
        add_action('template_redirect', array($this->redirects, 'maybe_redirect_accented_url'), 1);

        if (is_admin()) {
            $this->admin = new SES_Admin($this->settings, $this->logs, $this->scanner, $this->enrichment, $this->backup);
        }
    }

    public static function load_files() {
        $files = array(
            'class-security.php',
            'class-settings.php',
            'class-log-manager.php',
            'class-backup-manager.php',
            'class-slug-parser.php',
            'class-bold-builder-adapter.php',
            'class-local-intelligence.php',
            'class-profession-intelligence.php',
            'class-content-matrix.php',
            'class-semantic-engine.php',
            'class-similarity-engine.php',
            'class-quality-score.php',
            'class-internal-links.php',
            'class-schema-manager.php',
            'class-yoast-integration.php',
            'class-sitemap-manager.php',
            'class-redirect-manager.php',
            'class-page-scanner.php',
            'class-enrichment-engine.php',
            'class-import-export.php',
            'class-batch-processor.php',
            'class-dashboard.php',
            'class-admin.php',
        );

        foreach ($files as $file) {
            $path = SES_PATH . 'includes/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    public function register_shortcodes() {
        add_shortcode('ses_enriched_content', array($this, 'shortcode_enriched_content'));
    }

    public function frontend_assets() {
        if (is_singular()) {
            wp_enqueue_style('ses-frontend', SES_URL . 'assets/admin.css', array(), SES_VERSION);
        }
    }

    public function shortcode_enriched_content($atts = array()) {
        if (!is_singular()) {
            return '';
        }
        return SES_Bold_Builder_Adapter::wrap(get_post_meta(get_the_ID(), '_ses_enriched_html', true));
    }

    public function render_enriched_content($content) {
        if (is_admin() || !is_singular()) {
            return $content;
        }
        $post_id = get_the_ID();
        if (get_post_meta($post_id, '_ses_protected', true)) {
            return $content;
        }
        $mode = $this->settings->get('render_mode', 'safe');
        if ('safe' !== $mode) {
            return $content;
        }
        $html = get_post_meta($post_id, '_ses_enriched_html', true);
        if (!$html) {
            return $content;
        }
        return $content . "\n" . SES_Bold_Builder_Adapter::wrap($html);
    }
}
