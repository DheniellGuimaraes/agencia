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
            $result = $this->import_item($item, $mode);
            $out['processed']++;
            $out[$result]++;
        }
        return $out;
    }

    public function create_import_job($file, $mode = 'meta') {
        if (empty($file['tmp_name'])) {
            return new WP_Error('ses_no_file', 'Arquivo de importacao ausente.');
        }
        $mode = in_array($mode, array('meta', 'shortcode', 'written'), true) ? $mode : 'meta';
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir'])) {
            return new WP_Error('ses_upload_dir', 'Diretorio de uploads indisponivel.');
        }
        $dir = trailingslashit($uploads['basedir']) . 'ses-imports';
        wp_mkdir_p($dir);
        $job_id = wp_generate_uuid4();
        $path = trailingslashit($dir) . $job_id . '.json';
        if (!@move_uploaded_file($file['tmp_name'], $path) && !@copy($file['tmp_name'], $path)) {
            return new WP_Error('ses_import_copy', 'Nao foi possivel salvar o arquivo de importacao.');
        }
        $job = array(
            'id' => $job_id,
            'path' => $path,
            'mode' => $mode,
            'offset' => 0,
            'processed' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'done' => 0,
            'created_at' => current_time('mysql'),
        );
        update_option('ses_import_job_' . $job_id, $job, false);
        return $job;
    }

    public function process_import_job($job_id, $limit = 20, $max_seconds = 8) {
        $job_id = sanitize_key($job_id);
        $job = get_option('ses_import_job_' . $job_id);
        if (!is_array($job) || empty($job['path']) || !file_exists($job['path'])) {
            return new WP_Error('ses_job_missing', 'Job de importacao nao encontrado.');
        }
        if (!empty($job['done'])) {
            return $job;
        }
        $started = microtime(true);
        $batch = $this->read_items_from_json($job['path'], absint($job['offset']), max(1, absint($limit)), max(2, absint($max_seconds)), $started);
        foreach ($batch['items'] as $item) {
            $result = $this->import_item($item, $job['mode']);
            $job['processed']++;
            $job[$result]++;
        }
        $job['offset'] = absint($batch['offset']);
        $job['done'] = !empty($batch['done']) ? 1 : 0;
        $job['updated_at'] = current_time('mysql');
        update_option('ses_import_job_' . $job_id, $job, false);
        return $job;
    }

    private function read_items_from_json($path, $offset, $limit, $max_seconds, $started) {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return array('items' => array(), 'offset' => $offset, 'done' => 1);
        }
        if (0 === $offset) {
            $offset = $this->seek_items_array($handle);
        }
        fseek($handle, $offset);
        $items = array();
        $done = 0;
        while (!feof($handle) && count($items) < $limit && (microtime(true) - $started) < $max_seconds) {
            $char = fgetc($handle);
            if (false === $char) {
                $done = 1;
                break;
            }
            if (']' === $char) {
                $done = 1;
                break;
            }
            if ('{' !== $char) {
                continue;
            }
            $json = '{';
            $depth = 1;
            $in_string = false;
            $escape = false;
            while (!feof($handle) && $depth > 0) {
                $c = fgetc($handle);
                if (false === $c) {
                    break;
                }
                $json .= $c;
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ('\\' === $c) {
                    $escape = true;
                    continue;
                }
                if ('"' === $c) {
                    $in_string = !$in_string;
                    continue;
                }
                if ($in_string) {
                    continue;
                }
                if ('{' === $c) {
                    $depth++;
                } elseif ('}' === $c) {
                    $depth--;
                }
            }
            $item = json_decode($json, true);
            if (is_array($item)) {
                $items[] = $item;
            }
        }
        $offset = ftell($handle);
        fclose($handle);
        return array('items' => $items, 'offset' => $offset, 'done' => $done);
    }

    private function seek_items_array($handle) {
        $buffer = '';
        while (!feof($handle)) {
            $char = fgetc($handle);
            if (false === $char) {
                return ftell($handle);
            }
            $buffer .= $char;
            if (false !== strpos($buffer, '"items"')) {
                while (!feof($handle)) {
                    $c = fgetc($handle);
                    if ('[' === $c) {
                        return ftell($handle);
                    }
                }
            }
            if (strlen($buffer) > 32) {
                $buffer = substr($buffer, -32);
            }
        }
        return ftell($handle);
    }

    private function import_item($item, $mode) {
        $post = $this->find_post($item);
        if (!$post || get_post_meta($post->ID, '_ses_protected', true)) {
            return 'skipped';
        }
        $html = wp_kses_post($item['enriched_html'] ?? '');
        if ('' === trim(wp_strip_all_tags($html))) {
            return 'errors';
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
        $this->logs->add($post->ID, 'import', 'success', 'Enriquecimento importado por pacote JSON em lote AJAX.');
        return 'imported';
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
