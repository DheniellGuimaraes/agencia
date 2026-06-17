<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Import_Export {
    private $settings;
    private $logs;
    private $backup;

    public function __construct($settings, $logs, $backup) {
        $this->settings = $settings;
        $this->logs = $logs;
        $this->backup = $backup;
    }

    public function export_enriched() {
        $ids = get_posts(array(
            'post_type' => $this->settings->get('post_types', array('page')),
            'post_status' => array('publish', 'draft', 'private'),
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_query' => array(array('key' => '_ses_enriched_html', 'compare' => 'EXISTS')),
        ));

        $items = array();
        foreach ($ids as $id) {
            $html = get_post_meta($id, '_ses_enriched_html', true);
            if ('' === trim((string) $html)) {
                continue;
            }
            $items[] = $this->payload_for_post(absint($id), $html);
        }

        return array(
            'format' => 'ses-enrichment-portable',
            'version' => SES_VERSION,
            'site_url' => home_url('/'),
            'generated_at' => current_time('mysql'),
            'count' => count($items),
            'items' => $items,
        );
    }

    public function export_generated($limit = 100) {
        $engine = new SES_Enrichment_Engine($this->settings, $this->logs, $this->backup);
        $result = $engine->enrich_batch(max(1, min(500, absint($limit))));
        $payload = $this->export_enriched();
        $payload['generation_result'] = $result;
        return $payload;
    }

    public function import_payload($payload, $mode = 'meta') {
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (!is_array($payload) || empty($payload['items']) || !is_array($payload['items'])) {
            return new WP_Error('ses_invalid_import', 'Arquivo de importacao invalido.');
        }

        $mode = in_array($mode, array('meta', 'shortcode', 'written'), true) ? $mode : 'meta';
        $out = array('processed' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0);
        foreach ($payload['items'] as $item) {
            $out['processed']++;
            $post = $this->find_post($item);
            if (!$post || get_post_meta($post->ID, '_ses_protected', true)) {
                $out['skipped']++;
                continue;
            }
            $html = wp_kses_post($item['enriched_html'] ?? '');
            if ('' === trim(wp_strip_all_tags($html))) {
                $out['errors']++;
                continue;
            }

            $this->backup->create($post->ID);
            update_post_meta($post->ID, '_ses_enriched_html', $html);
            update_post_meta($post->ID, '_ses_enrichment_status', sanitize_key($item['status'] ?? 'enriquecida'));
            update_post_meta($post->ID, '_ses_quality_score', absint($item['quality_score'] ?? 0));
            update_post_meta($post->ID, '_ses_similarity_score', absint($item['similarity_score'] ?? 0));
            update_post_meta($post->ID, '_ses_content_hash', sanitize_text_field($item['content_hash'] ?? ''));
            update_post_meta($post->ID, '_ses_imported_at', current_time('mysql'));

            if (!empty($item['yoast_title'])) {
                update_post_meta($post->ID, '_wpseo_title', sanitize_text_field($item['yoast_title']));
            }
            if (!empty($item['yoast_description'])) {
                update_post_meta($post->ID, '_wpseo_metadesc', sanitize_text_field($item['yoast_description']));
            }
            update_post_meta($post->ID, '_wpseo_canonical', get_permalink($post->ID));

            if ('meta' !== $mode) {
                $content = SES_Bold_Builder_Adapter::append_to_content($post->post_content, $html, 'shortcode' === $mode ? 'shortcode' : 'written');
                wp_update_post(array('ID' => $post->ID, 'post_content' => $content));
            }

            $this->logs->add($post->ID, 'import', 'success', 'Enriquecimento importado por pacote JSON.');
            $out['imported']++;
        }
        return $out;
    }

    private function payload_for_post($id, $html) {
        $post = get_post($id);
        return array(
            'source_id' => $id,
            'post_type' => $post->post_type,
            'slug' => $post->post_name,
            'path' => trim(parse_url(get_permalink($id), PHP_URL_PATH), '/'),
            'title' => get_the_title($id),
            'enriched_html' => $html,
            'status' => get_post_meta($id, '_ses_enrichment_status', true),
            'quality_score' => absint(get_post_meta($id, '_ses_quality_score', true)),
            'similarity_score' => absint(get_post_meta($id, '_ses_similarity_score', true)),
            'content_hash' => get_post_meta($id, '_ses_content_hash', true),
            'yoast_title' => get_post_meta($id, '_wpseo_title', true),
            'yoast_description' => get_post_meta($id, '_wpseo_metadesc', true),
        );
    }

    private function find_post($item) {
        $post_types = $this->settings->get('post_types', array('page'));
        $path = trim((string) ($item['path'] ?? ''), '/');
        if ($path) {
            $post = get_page_by_path($path, OBJECT, $post_types);
            if ($post) {
                return $post;
            }
        }
        $slug = sanitize_title($item['slug'] ?? '');
        return $slug ? get_page_by_path($slug, OBJECT, $post_types) : null;
    }
}
