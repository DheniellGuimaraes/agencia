<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Enrichment_Engine {
    private $settings;
    private $logs;
    private $backup;

    public function __construct($settings, $logs, $backup) {
        $this->settings = $settings;
        $this->logs = $logs;
        $this->backup = $backup;
    }

    public function enrich($post_id) {
        if (get_post_meta($post_id, '_ses_protected', true)) {
            $this->logs->add($post_id, 'enrichment', 'ignored', 'Pagina protegida. Nenhuma alteracao realizada.');
            return new WP_Error('ses_protected', 'Pagina protegida.');
        }
        $post = get_post($post_id);
        if (!$post || 'publish' !== $post->post_status) {
            return new WP_Error('ses_invalid_post', 'Pagina invalida.');
        }
        $scanner = new SES_Page_Scanner($this->settings, $this->logs);
        $scan = $scanner->scan_post($post_id);
        if (empty($scan['eligible'])) {
            update_post_meta($post_id, '_ses_enrichment_status', 'ignorada');
            return new WP_Error('ses_not_eligible', 'Pagina nao elegivel.');
        }
        $context = $scan['context'];
        $settings = $this->settings->all();
        $generated = (new SES_Semantic_Engine())->build($context, $settings);
        $content_length = strlen(wp_strip_all_tags($generated['html']));
        $minimum_length = max(3000, absint($settings['minimum_content_length'] ?? 3000));
        update_post_meta($post_id, '_ses_enriched_content_length', $content_length);
        if ($content_length < $minimum_length) {
            update_post_meta($post_id, '_ses_enrichment_status', 'erro');
            $this->logs->add($post_id, 'enrichment', 'error', 'Conteudo gerado abaixo do minimo. Caracteres: ' . $content_length . '. Minimo: ' . $minimum_length . '.');
            return new WP_Error('ses_short_content', 'Conteudo gerado abaixo do minimo.');
        }

        $similarity = (new SES_Similarity_Engine())->evaluate($post_id, $generated['html']);
        update_post_meta($post_id, '_ses_similarity_score', $similarity['score']);
        update_post_meta($post_id, '_ses_content_hash', $similarity['hash']);
        update_post_meta($post_id, '_ses_intro_hash', $similarity['intro_hash']);
        update_post_meta($post_id, '_ses_faq_hash', $similarity['faq_hash']);
        update_post_meta($post_id, '_ses_cta_hash', $similarity['cta_hash']);
        update_post_meta($post_id, '_ses_block_hashes', wp_json_encode($similarity['block_hashes']));

        $similarity_warning = $similarity['score'] >= absint($settings['similarity_limit']);
        update_post_meta($post_id, '_ses_similarity_warning', $similarity_warning ? 1 : 0);
        if ($similarity_warning) {
            $this->logs->add($post_id, 'similarity', 'warning', 'Similaridade acima do limite: ' . $similarity['score'] . '%. Conteudo salvo com alerta para revisao.');
        }

        $score = (new SES_Quality_Score())->calculate($context, $generated, $similarity['score']);
        update_post_meta($post_id, '_ses_quality_score', $score);
        if ($score < absint($settings['minimum_score'])) {
            update_post_meta($post_id, '_ses_enrichment_status', 'pendente');
        }

        $this->backup->create($post_id);
        update_post_meta($post_id, '_ses_enriched_html', $generated['html']);

        $mode = $settings['render_mode'];
        if ('shortcode' === $mode || 'written' === $mode || !empty($settings['write_to_post_content'])) {
            $new_content = SES_Bold_Builder_Adapter::append_to_content($post->post_content, $generated['html'], $mode);
            wp_update_post(array('ID' => $post_id, 'post_content' => $new_content));
        }
        if (!empty($settings['yoast_enabled'])) {
            (new SES_Yoast_Integration())->update_meta($post_id, $context);
        }
        update_post_meta($post_id, '_ses_enrichment_status', ($score >= absint($settings['minimum_score']) && !$similarity_warning) ? 'enriquecida' : 'enriquecida_com_alerta');
        update_post_meta($post_id, '_ses_enriched_at', current_time('mysql'));
        $this->logs->add($post_id, 'enrichment', 'success', 'Pagina enriquecida. Score: ' . $score . '. Similaridade: ' . $similarity['score'] . '%. Caracteres: ' . $content_length . '.');
        return array('post_id' => $post_id, 'score' => $score, 'similarity' => $similarity['score']);
    }

    public function enrich_batch($limit = 5) {
        $meta_query = array(array('key' => '_ses_enrichment_status', 'value' => array('elegivel', 'pendente', 'erro', 'rejeitada_por_similaridade'), 'compare' => 'IN'));
        if ($this->settings->get('enrich_from_sitemaps', 1)) {
            $meta_query[] = array('key' => '_ses_in_sitemap', 'value' => '1');
        }
        $ids = get_posts(array(
            'post_type' => $this->settings->get('post_types', array('page')),
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => max(1, min(100, absint($limit))),
            'meta_query' => $meta_query,
        ));
        $out = array('processed' => 0, 'enriched' => 0, 'errors' => 0);
        foreach ($ids as $id) {
            $out['processed']++;
            $result = $this->enrich($id);
            is_wp_error($result) ? $out['errors']++ : $out['enriched']++;
        }
        return $out;
    }

    public function eligible_total() {
        global $wpdb;
        $statuses = array('elegivel', 'pendente', 'erro', 'rejeitada_por_similaridade');
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $post_types = array_values(array_filter(array_map('sanitize_key', (array) $this->settings->get('post_types', array('page')))));
        if (!$post_types) {
            $post_types = array('page');
        }
        $type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $sitemap_join = $this->settings->get('enrich_from_sitemaps', 1) ? "INNER JOIN {$wpdb->postmeta} sitemap_meta ON sitemap_meta.post_id = p.ID AND sitemap_meta.meta_key = '_ses_in_sitemap' AND sitemap_meta.meta_value = '1'" : '';
        $sql = "SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} status_meta ON status_meta.post_id = p.ID AND status_meta.meta_key = '_ses_enrichment_status'
            {$sitemap_join}
            LEFT JOIN {$wpdb->postmeta} protected_meta ON protected_meta.post_id = p.ID AND protected_meta.meta_key = '_ses_protected'
            WHERE p.post_status = 'publish'
            AND p.post_type IN ({$type_placeholders})
            AND status_meta.meta_value IN ({$status_placeholders})
            AND (protected_meta.meta_value IS NULL OR protected_meta.meta_value <> '1')";
        return (int) $wpdb->get_var($wpdb->prepare($sql, array_merge($post_types, $statuses)));
    }

    public function enrich_batch_after_id($limit = 5, $after_id = 0) {
        global $wpdb;
        $limit = max(1, min(50, absint($limit)));
        $after_id = absint($after_id);
        $statuses = array('elegivel', 'pendente', 'erro', 'rejeitada_por_similaridade');
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $post_types = array_values(array_filter(array_map('sanitize_key', (array) $this->settings->get('post_types', array('page')))));
        if (!$post_types) {
            $post_types = array('page');
        }
        $type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $sitemap_join = $this->settings->get('enrich_from_sitemaps', 1) ? "INNER JOIN {$wpdb->postmeta} sitemap_meta ON sitemap_meta.post_id = p.ID AND sitemap_meta.meta_key = '_ses_in_sitemap' AND sitemap_meta.meta_value = '1'" : '';
        $sql = "SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} status_meta ON status_meta.post_id = p.ID AND status_meta.meta_key = '_ses_enrichment_status'
            {$sitemap_join}
            LEFT JOIN {$wpdb->postmeta} protected_meta ON protected_meta.post_id = p.ID AND protected_meta.meta_key = '_ses_protected'
            WHERE p.post_status = 'publish'
            AND p.post_type IN ({$type_placeholders})
            AND p.ID > %d
            AND status_meta.meta_value IN ({$status_placeholders})
            AND (protected_meta.meta_value IS NULL OR protected_meta.meta_value <> '1')
            ORDER BY p.ID ASC
            LIMIT %d";
        $ids = $wpdb->get_col($wpdb->prepare($sql, array_merge($post_types, array($after_id), $statuses, array($limit))));

        $out = array(
            'processed' => 0,
            'enriched' => 0,
            'errors' => 0,
            'last_id' => $after_id,
            'next_last_id' => $after_id,
            'limit' => $limit,
            'has_more' => false,
        );

        foreach ($ids as $id) {
            $id = absint($id);
            $out['processed']++;
            $out['next_last_id'] = max($out['next_last_id'], $id);
            $result = $this->enrich($id);
            is_wp_error($result) ? $out['errors']++ : $out['enriched']++;
        }

        $out['has_more'] = $out['processed'] === $limit;
        return $out;
    }

    public function enrich_turbo($limit = 5, $after_id = 0, $max_seconds = 8) {
        $started = microtime(true);
        $limit = max(1, min(50, absint($limit)));
        $max_seconds = max(2, min(12, absint($max_seconds)));
        $current_id = absint($after_id);
        $aggregate = array(
            'processed' => 0,
            'enriched' => 0,
            'errors' => 0,
            'last_id' => $current_id,
            'next_last_id' => $current_id,
            'limit' => $limit,
            'has_more' => true,
            'turbo_rounds' => 0,
            'elapsed' => 0,
        );

        do {
            $batch = $this->enrich_batch_after_id($limit, $current_id);
            $aggregate['turbo_rounds']++;
            $aggregate['processed'] += absint($batch['processed']);
            $aggregate['enriched'] += absint($batch['enriched']);
            $aggregate['errors'] += absint($batch['errors']);
            $aggregate['next_last_id'] = absint($batch['next_last_id']);
            $aggregate['has_more'] = !empty($batch['has_more']);
            $current_id = $aggregate['next_last_id'];
            $aggregate['elapsed'] = round(microtime(true) - $started, 2);

            if (0 === absint($batch['processed'])) {
                $aggregate['has_more'] = false;
                break;
            }
        } while ($aggregate['has_more'] && (microtime(true) - $started) < $max_seconds);

        return $aggregate;
    }
}
