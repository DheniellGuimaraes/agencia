<?php
if (!defined('ABSPATH')) { exit; }

final class PSI_AI_Plugin {
    private static $instance;
    public $logger;
    public $protection;
    public $sitemap_reader;
    public $detector;
    public $cleaner;
    public $generator;
    public $entity_generator;
    public $local_context;
    public $media_builder;
    public $category_intelligence;
    public $similarity_guard;
    public $visual_builder;
    public $scorer;
    public $schema;
    public $backup;
    public $batch;
    public $boldbuilder;
    public $admin;

    public static function instance() {
        if (!self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    public function init() {
        $this->load_dependencies();
        $this->logger = new PSI_AI_Logger();
        $this->protection = new PSI_AI_Protection_Rules();
        $this->sitemap_reader = new PSI_AI_Sitemap_Reader($this->logger);
        $this->detector = new PSI_AI_Page_Detector($this->protection);
        $this->cleaner = new PSI_AI_Content_Cleaner();
        $this->category_intelligence = new PSI_AI_Category_Intelligence();
        $this->entity_generator = new PSI_AI_Semantic_Entity_Generator($this->category_intelligence);
        $this->local_context = new PSI_AI_Local_Context_Generator();
        $this->media_builder = new PSI_AI_Unique_Media_Builder();
        $this->similarity_guard = new PSI_AI_Similarity_Guard();
        $this->visual_builder = new PSI_AI_Privilege_Visual_Builder();
        $this->generator = new PSI_AI_Semantic_Generator($this->entity_generator, $this->local_context, $this->media_builder, $this->visual_builder);
        $this->scorer = new PSI_AI_Quality_Score();
        $this->schema = new PSI_AI_Schema_Generator();
        $this->backup = new PSI_AI_Backup_Manager();
        $this->boldbuilder = new PSI_AI_BoldBuilder_Adapter();
        $this->batch = new PSI_AI_Batch_Runner($this);
        if (is_admin()) { $this->admin = new PSI_AI_Admin($this); $this->admin->init(); }
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend'));
        add_action('wp_head', array($this->schema, 'print_schema'), 20);
        add_action('psi_ai_process_batch', array($this->batch, 'process_scheduled_batch'));
    }

    private function load_dependencies() {
        foreach (array('logger','protection-rules','sitemap-reader','page-detector','boldbuilder-adapter','content-cleaner','category-intelligence','semantic-entity-generator','local-context-generator','unique-media-builder','similarity-guard','privilege-visual-builder','semantic-generator','quality-score','schema-generator','backup-manager','batch-runner','admin') as $file) {
            require_once PSI_AI_DIR . 'includes/class-' . $file . '.php';
        }
    }

    public function enqueue_frontend() {
        wp_enqueue_style('psi-ai-frontend', PSI_AI_URL . 'assets/css/frontend.css', array(), PSI_AI_VERSION);
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$wpdb->prefix}psi_ai_logs (id bigint unsigned NOT NULL AUTO_INCREMENT, level varchar(20) NOT NULL, message text NOT NULL, context longtext NULL, created_at datetime NOT NULL, PRIMARY KEY (id), KEY level (level)) $charset;");
        dbDelta("CREATE TABLE {$wpdb->prefix}psi_ai_backups (id bigint unsigned NOT NULL AUTO_INCREMENT, post_id bigint unsigned NOT NULL, post_content longtext NOT NULL, seo_meta longtext NULL, content_hash varchar(64) NOT NULL, plugin_version varchar(20) NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), KEY post_id (post_id)) $charset;");
        dbDelta("CREATE TABLE {$wpdb->prefix}psi_ai_rollouts (id bigint unsigned NOT NULL AUTO_INCREMENT, mode varchar(40) NOT NULL, started_at datetime NULL, finished_at datetime NULL, target_count int unsigned NOT NULL DEFAULT 0, processed_count int unsigned NOT NULL DEFAULT 0, published_count int unsigned NOT NULL DEFAULT 0, blocked_count int unsigned NOT NULL DEFAULT 0, review_count int unsigned NOT NULL DEFAULT 0, reverted_count int unsigned NOT NULL DEFAULT 0, average_quality decimal(5,2) NULL, status varchar(30) NOT NULL DEFAULT 'planned', PRIMARY KEY (id), KEY status (status)) $charset;");
        dbDelta("CREATE TABLE {$wpdb->prefix}psi_ai_validation_plan (id bigint unsigned NOT NULL AUTO_INCREMENT, post_id bigint unsigned NULL, url text NOT NULL, changed_at datetime NULL, status_before varchar(80) NULL, status_after varchar(80) NULL, inspect_at date NULL, request_indexing_at date NULL, review_at date NULL, result_7d varchar(80) NULL, result_15d varchar(80) NULL, result_30d varchar(80) NULL, PRIMARY KEY (id), KEY post_id (post_id)) $charset;");
        dbDelta("CREATE TABLE {$wpdb->prefix}psi_ai_pages (id bigint unsigned NOT NULL AUTO_INCREMENT, post_id bigint unsigned NULL, url text NOT NULL, status varchar(30) NOT NULL DEFAULT 'pending', quality_score decimal(5,2) NULL, flags text NULL, data longtext NULL, updated_at datetime NULL, PRIMARY KEY (id), KEY post_id (post_id), KEY status (status)) $charset;");
        add_option('psi_ai_settings', PSI_AI_Plugin::default_settings());
    }

    public static function deactivate() { wp_clear_scheduled_hook('psi_ai_process_batch'); }

    public static function default_settings() {
        return array(
            'sitemaps' => array_map(function($i){ return 'https://www.studioprivilege.com.br/sitemap-' . $i . '.xml'; }, range(1,6)),
            'batch_size' => 10,
            'dry_run' => 1,
            'quality_threshold' => 72,
            'similarity_threshold' => 86,
            'whatsapp' => '5532988167666',
            'visual_premium' => 1,
            'visual_style' => 'auto',
            'visual_svg' => 1,
            'visual_robot' => 1,
            'visual_internal_cards' => 1,
            'visual_premium_faq' => 1,
        );
    }
}
