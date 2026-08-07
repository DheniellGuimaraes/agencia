<?php
if (!defined('ABSPATH')) { exit; }

class PSI_AI_Resilient_Runner {
    private $plugin;
    private $default_time_budget = 20;
    private $default_memory_ratio = 0.82;
    private $lock_key = 'psi_ai_resilient_runner_lock';

    public function __construct($plugin) { $this->plugin = $plugin; }

    public function enqueue($args = array()) {
        $args = wp_parse_args($args, array('dry_run'=>true, 'limit'=>10, 'rollout_mode'=>'manual', 'cursor'=>0));
        $args['dry_run'] = (bool) $args['dry_run'];
        $args['limit'] = max(1, absint($args['limit']));
        $args['rollout_mode'] = sanitize_key($args['rollout_mode']);
        $args['cursor'] = absint($args['cursor']);
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('psi_ai_resilient_process_queue', array($args), 'privilege-semantic-indexer-ai');
        } elseif (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(time() + 5, 'psi_ai_resilient_process_queue', array($args), 'privilege-semantic-indexer-ai');
        } else {
            wp_schedule_single_event(time() + 5, 'psi_ai_resilient_process_queue', array($args));
        }
        $this->plugin->logger->log('info', 'Processamento enfileirado em background para evitar timeout HTTP/Cloudflare.', $args);
        return true;
    }

    public function enqueue_sitemap_import() {
        if (function_exists('as_enqueue_async_action')) { as_enqueue_async_action('psi_ai_resilient_import_sitemaps', array(), 'privilege-semantic-indexer-ai'); }
        else { wp_schedule_single_event(time() + 5, 'psi_ai_resilient_import_sitemaps'); }
        $this->plugin->logger->log('info', 'Importação de sitemaps enfileirada em background.');
        return true;
    }

    public function process_sitemap_import() {
        if (!$this->acquire_lock()) { $this->enqueue_sitemap_import(); return; }
        try { $count = $this->plugin->batch->import_sitemaps(); $this->plugin->logger->log('info', 'Importação de sitemaps concluída em background.', array('urls'=>$count)); }
        finally { $this->release_lock(); }
    }

    public function process_queue($args = array()) {
        $args = wp_parse_args((array) $args, array('dry_run'=>true, 'limit'=>10, 'rollout_mode'=>'manual', 'cursor'=>0));
        if (!$this->acquire_lock()) { $this->enqueue($args); return; }
        $start = microtime(true);
        $processed = 0;
        $limit = max(1, absint($args['limit']));
        try {
            while ($processed < $limit && $this->has_budget($start)) {
                $result = $this->plugin->batch->process_next((bool) $args['dry_run'], sanitize_key($args['rollout_mode']));
                if (empty($result['processed'])) { break; }
                $processed++;
            }
        } finally {
            $this->release_lock();
        }
        $remaining = $limit - $processed;
        $this->plugin->logger->log('info', 'Fatia de processamento concluída.', array('processed'=>$processed,'remaining'=>$remaining,'mode'=>$args['rollout_mode'],'dry_run'=>$args['dry_run']));
        if ($remaining > 0) {
            $args['limit'] = $remaining;
            $args['cursor'] = absint($args['cursor']) + $processed;
            $this->enqueue($args);
        }
    }

    public function has_budget($start_time) {
        if ((microtime(true) - $start_time) >= $this->default_time_budget) { return false; }
        $limit = $this->memory_limit_bytes();
        if ($limit > 0 && memory_get_usage(true) >= ($limit * $this->default_memory_ratio)) { return false; }
        return true;
    }

    private function acquire_lock() {
        if (get_transient($this->lock_key)) { return false; }
        set_transient($this->lock_key, 1, 60);
        return true;
    }

    private function release_lock() { delete_transient($this->lock_key); }

    private function memory_limit_bytes() {
        $value = ini_get('memory_limit');
        if (!$value || $value === '-1') { return 0; }
        $unit = strtolower(substr($value, -1));
        $bytes = (int) $value;
        if ($unit === 'g') $bytes *= 1024 * 1024 * 1024;
        elseif ($unit === 'm') $bytes *= 1024 * 1024;
        elseif ($unit === 'k') $bytes *= 1024;
        return $bytes;
    }
}
