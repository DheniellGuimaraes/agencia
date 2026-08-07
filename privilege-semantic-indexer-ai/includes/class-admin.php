<?php
if (!defined('ABSPATH')) { exit; }
class PSI_AI_Admin {
    private $plugin;
    private $tabs = array('overview'=>'Visão Geral','sitemaps'=>'Sitemaps','pages'=>'Páginas','protection'=>'Regras de Proteção','templates'=>'Templates Semânticos','logs'=>'Logs','backups'=>'Backups','seo'=>'Configurações SEO','appearance'=>'Aparência','batch'=>'Execução em Lote','rollout'=>'Execução Gradual','validation'=>'Validação Search Console','design'=>'Design do Enriquecimento');
    public function __construct($plugin) { $this->plugin=$plugin; }
    public function init() { add_action('admin_menu', array($this,'menu')); add_action('admin_enqueue_scripts', array($this,'assets')); add_action('admin_post_psi_ai_action', array($this,'handle')); }
    public function menu() { add_menu_page('Privilege Semantic Indexer','Privilege Semantic Indexer','manage_options','privilege-semantic-indexer',array($this,'render'),'dashicons-chart-area',58); }
    public function assets($hook) { if (strpos($hook,'privilege-semantic-indexer')!==false) wp_enqueue_style('psi-ai-admin', PSI_AI_URL.'assets/css/admin.css', array(), PSI_AI_VERSION); }
    public function handle() {
        if (!current_user_can('manage_options') || !check_admin_referer('psi_ai_action')) wp_die(esc_html__('Acesso negado.', 'privilege-semantic-indexer-ai'));
        $do=sanitize_key($_POST['do'] ?? '');
        if ($do==='import') $this->plugin->resilient_runner->enqueue_sitemap_import();
        if ($do==='simulate') $this->plugin->batch->enqueue_batch(true);
        if ($do==='process') $this->plugin->batch->enqueue_batch(false);
        if ($do==='save_design') $this->save_design_settings();
        if ($do==='rollout') { $mode=sanitize_key($_POST['rollout_mode'] ?? 'test'); if ($mode==='total' && empty($_POST['double_confirm'])) { wp_safe_redirect(admin_url('admin.php?page=privilege-semantic-indexer&tab=rollout&confirm_required=1')); exit; } $limit = $mode === 'test' ? 500 : ($mode === 'small' ? 2000 : ($mode === 'medium' ? 10000 : ($mode === 'total' ? 117948 : absint($_POST['custom_limit'] ?? 10)))); $this->plugin->batch->enqueue_batch(!empty($_POST['dry_run']), $limit, $mode); }
        wp_safe_redirect(admin_url('admin.php?page=privilege-semantic-indexer&tab=' . sanitize_key($_POST['return_tab'] ?? 'overview'))); exit;
    }
    public function render() {
        global $wpdb;
        $tab=sanitize_key($_GET['tab'] ?? 'overview');
        $counts=$wpdb->get_results("SELECT status, COUNT(*) total FROM {$wpdb->prefix}psi_ai_pages GROUP BY status", OBJECT_K);
        $rollouts=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}psi_ai_rollouts ORDER BY id DESC LIMIT 20");
        $plans=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}psi_ai_validation_plan ORDER BY id DESC LIMIT 50");
        $settings=get_option('psi_ai_settings', PSI_AI_Plugin::default_settings());
        $preview=$this->preview_sample();
        include PSI_AI_DIR.'templates/admin-dashboard.php';
    }
    private function save_design_settings() {
        $settings=get_option('psi_ai_settings', PSI_AI_Plugin::default_settings());
        $settings['visual_premium']=!empty($_POST['visual_premium']) ? 1 : 0;
        $settings['visual_style']=sanitize_key($_POST['visual_style'] ?? 'auto');
        $settings['visual_svg']=!empty($_POST['visual_svg']) ? 1 : 0;
        $settings['visual_robot']=!empty($_POST['visual_robot']) ? 1 : 0;
        $settings['visual_internal_cards']=!empty($_POST['visual_internal_cards']) ? 1 : 0;
        $settings['visual_premium_faq']=!empty($_POST['visual_premium_faq']) ? 1 : 0;
        update_option('psi_ai_settings', $settings);
    }
    private function preview_sample() {
        $detected=array('post_id'=>999,'profession'=>'Costureiro de roupa de couro e pele','city'=>'Dourados','uf'=>'MS','macro_category'=>'moda','uses_boldbuilder'=>true);
        $generated=$this->plugin->generator->generate($detected);
        return $this->plugin->visual_builder->preview(array('post_id'=>999,'entities'=>$generated['entities'],'local_context'=>$generated['local_context'],'media_svg'=>$this->plugin->media_builder->build(999,$generated['entities']),'faq'=>$generated['faq']));
    }
}
