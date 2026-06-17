<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Page_Scanner {
    private $settings;
    private $logs;
    private $parser;

    public function __construct($settings, $logs) {
        $this->settings = $settings;
        $this->logs = $logs;
        $this->parser = new SES_Slug_Parser();
    }

    public function scan_batch($limit = 25, $offset = 0) {
        $limit = max(1, min(5000, absint($limit)));
        $offset = absint($offset);
        return $this->scan_batch_after_id($limit, 0, $offset);
    }

    public function scan_batch_after_id($limit = 500, $after_id = 0, $legacy_offset = 0) {
        global $wpdb;
        $limit = max(1, min(2000, absint($limit)));
        $after_id = absint($after_id);
        $legacy_offset = absint($legacy_offset);
        $post_types = array_values(array_filter(array_map('sanitize_key', (array) $this->settings->get('post_types', array('page')))));
        if (!$post_types) {
            $post_types = array('page');
        }

        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $sql = "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d";
        $params = array_merge($post_types, array($after_id, $limit));
        $posts = $wpdb->get_results($wpdb->prepare($sql, $params));

        if (!$posts && $legacy_offset > 0 && 0 === $after_id) {
            $legacy_ids = get_posts(array(
                'post_type' => $post_types,
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => $limit,
                'offset' => $legacy_offset,
                'orderby' => 'ID',
                'order' => 'ASC',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ));
            $posts = array();
            foreach ($legacy_ids as $legacy_id) {
                $legacy_post = get_post($legacy_id);
                if ($legacy_post) {
                    $posts[] = (object) array('ID' => $legacy_post->ID, 'post_name' => $legacy_post->post_name);
                }
            }
        }

        $results = array(
            'scanned' => 0,
            'eligible' => 0,
            'protected' => 0,
            'offset' => $legacy_offset,
            'next_offset' => $legacy_offset,
            'last_id' => $after_id,
            'next_last_id' => $after_id,
            'limit' => $limit,
            'has_more' => false,
        );

        foreach ($posts as $post_row) {
            $scan = $this->scan_post_row($post_row, false);
            $results['scanned']++;
            $results['next_last_id'] = max($results['next_last_id'], absint($post_row->ID));
            if (!empty($scan['protected'])) {
                $results['protected']++;
            }
            if (!empty($scan['eligible'])) {
                $results['eligible']++;
            }
        }

        $results['next_offset'] = $legacy_offset + $results['scanned'];
        $results['has_more'] = $results['scanned'] === $limit;
        if ($results['scanned'] > 0) {
            $this->logs->add(0, 'scan_batch', 'success', 'Lote escaneado. Paginas: ' . $results['scanned'] . '. Elegiveis: ' . $results['eligible'] . '. Protegidas: ' . $results['protected'] . '. Ultimo ID: ' . $results['next_last_id'] . '.');
        }
        return $results;
    }

    public function scan_turbo($limit = 500, $after_id = 0, $max_seconds = 8) {
        $started = microtime(true);
        $limit = max(50, min(2000, absint($limit)));
        $max_seconds = max(2, min(12, absint($max_seconds)));
        $current_id = absint($after_id);
        $aggregate = array(
            'scanned' => 0,
            'eligible' => 0,
            'protected' => 0,
            'offset' => 0,
            'next_offset' => 0,
            'last_id' => $current_id,
            'next_last_id' => $current_id,
            'limit' => $limit,
            'has_more' => true,
            'turbo_rounds' => 0,
            'elapsed' => 0,
        );

        do {
            $batch = $this->scan_batch_after_id($limit, $current_id, $aggregate['next_offset']);
            $aggregate['turbo_rounds']++;
            $aggregate['scanned'] += absint($batch['scanned']);
            $aggregate['eligible'] += absint($batch['eligible']);
            $aggregate['protected'] += absint($batch['protected']);
            $aggregate['next_offset'] += absint($batch['scanned']);
            $aggregate['next_last_id'] = absint($batch['next_last_id']);
            $aggregate['has_more'] = !empty($batch['has_more']);
            $current_id = $aggregate['next_last_id'];
            $aggregate['elapsed'] = round(microtime(true) - $started, 2);

            if (0 === absint($batch['scanned'])) {
                $aggregate['has_more'] = false;
                break;
            }
        } while ($aggregate['has_more'] && (microtime(true) - $started) < $max_seconds);

        return $aggregate;
    }

    public function scan_batch_offset($limit = 25, $offset = 0) {
        $limit = max(1, min(5000, absint($limit)));
        $offset = absint($offset);
        $ids = get_posts(array(
            'post_type' => $this->settings->get('post_types', array('page')),
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));
        $results = array(
            'scanned' => 0,
            'eligible' => 0,
            'protected' => 0,
            'offset' => $offset,
            'next_offset' => $offset,
            'limit' => $limit,
            'has_more' => false,
        );
        foreach ($ids as $id) {
            $scan = $this->scan_post($id);
            $results['scanned']++;
            if (!empty($scan['protected'])) {
                $results['protected']++;
            }
            if (!empty($scan['eligible'])) {
                $results['eligible']++;
            }
        }
        $results['next_offset'] = $offset + $results['scanned'];
        $results['has_more'] = $results['scanned'] === $limit;
        return $results;
    }

    public function total_scannable_pages() {
        $total = 0;
        foreach ((array) $this->settings->get('post_types', array('page')) as $post_type) {
            $counts = wp_count_posts($post_type);
            $total += isset($counts->publish) ? (int) $counts->publish : 0;
        }
        return $total;
    }

    public function sync_configured_protections() {
        global $wpdb;
        $post_types = array_values(array_filter(array_map('sanitize_key', (array) $this->settings->get('post_types', array('page')))));
        if (!$post_types) {
            $post_types = array('page');
        }

        $protected_ids = array();
        $paths = array_merge(
            (array) $this->settings->get('protected_paths', array()),
            (array) $this->settings->get('protected_contains', array())
        );

        foreach ($paths as $path) {
            $path = $this->normalize_path($path);
            if (!$path) {
                continue;
            }
            $page = get_page_by_path($path, OBJECT, $post_types);
            if ($page) {
                $protected_ids[] = absint($page->ID);
                $protected_ids = array_merge($protected_ids, $this->descendant_ids(array(absint($page->ID)), $post_types));
            }
        }

        $slugs = (array) $this->settings->get('protected_slugs', array());
        foreach ((array) $this->settings->get('protected_paths', array()) as $path) {
            $path = $this->normalize_path($path);
            if ($path) {
                $parts = explode('/', $path);
                $slugs[] = end($parts);
            }
        }
        foreach ((array) $this->settings->get('protected_contains', array()) as $needle) {
            $slugs[] = $needle;
        }
        $slugs = array_values(array_unique(array_filter(array_map('sanitize_title', $slugs))));

        if ($slugs) {
            $type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
            $slug_placeholders = implode(',', array_fill(0, count($slugs), '%s'));
            $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$type_placeholders}) AND post_status <> 'trash' AND post_name IN ({$slug_placeholders})";
            $ids = $wpdb->get_col($wpdb->prepare($sql, array_merge($post_types, $slugs)));
            foreach ($ids as $id) {
                $protected_ids[] = absint($id);
                $protected_ids = array_merge($protected_ids, $this->descendant_ids(array(absint($id)), $post_types));
            }
        }

        foreach ((array) $this->settings->get('protected_contains', array()) as $needle) {
            $needle = sanitize_title($needle);
            if (!$needle) {
                continue;
            }
            $type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
            $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$type_placeholders}) AND post_status <> 'trash' AND post_name LIKE %s";
            $ids = $wpdb->get_col($wpdb->prepare($sql, array_merge($post_types, array('%' . $wpdb->esc_like($needle) . '%'))));
            foreach ($ids as $id) {
                $protected_ids[] = absint($id);
                $protected_ids = array_merge($protected_ids, $this->descendant_ids(array(absint($id)), $post_types));
            }
        }

        $protected_ids = array_values(array_unique(array_filter(array_map('absint', $protected_ids))));
        $updated = 0;
        foreach ($protected_ids as $post_id) {
            $this->update_meta_if_changed($post_id, '_ses_protected', 1);
            $this->update_meta_if_changed($post_id, '_ses_enrichment_status', 'protegida');
            $updated++;
        }

        $this->logs->add(0, 'protection_sync', 'success', 'Protecoes configuradas sincronizadas. Paginas protegidas: ' . $updated . '.');
        return array('protected' => $updated, 'ids' => $protected_ids);
    }


    public function sync_sitemap_pages($max_urls = 0) {
        $sources = array_values(array_filter((array) $this->settings->get('sitemap_sources', array())));
        $urls = $this->collect_sitemap_urls($sources, absint($max_urls));
        $out = array('urls' => count($urls), 'matched' => 0, 'scanned' => 0, 'eligible' => 0, 'protected' => 0, 'missing' => 0);
        $post_types = $this->settings->get('post_types', array('page'));

        foreach ($urls as $url) {
            $path = $this->path_from_url($url);
            if (!$path) {
                continue;
            }
            $post = $this->find_post_for_sitemap_path($path, $post_types);
            if (!$post) {
                $out['missing']++;
                continue;
            }
            $out['matched']++;
            update_post_meta($post->ID, '_ses_in_sitemap', 1);
            update_post_meta($post->ID, '_ses_sitemap_url', esc_url_raw($url));
            $scan = $this->scan_post($post->ID);
            $out['scanned']++;
            if (!empty($scan['protected'])) {
                $out['protected']++;
            }
            if (!empty($scan['eligible'])) {
                $out['eligible']++;
            }
        }

        update_option('ses_sitemap_last_sync', array_merge($out, array('synced_at' => current_time('mysql'))), false);
        $this->logs->add(0, 'sitemap_sync', 'success', 'Sitemaps sincronizados. URLs: ' . $out['urls'] . '. Encontradas: ' . $out['matched'] . '. Elegiveis: ' . $out['eligible'] . '. Protegidas: ' . $out['protected'] . '.');
        return $out;
    }


    private function find_post_for_sitemap_path($path, $post_types) {
        global $wpdb;
        $path = trim((string) $path, '/');
        $post = get_page_by_path($path, OBJECT, $post_types);
        if ($post) {
            return $post;
        }
        $parts = array_filter(explode('/', $path));
        $slug = sanitize_title(remove_accents(end($parts) ?: $path));
        if (!$slug) {
            return null;
        }
        $post_types = array_values(array_filter(array_map('sanitize_key', (array) $post_types)));
        if (!$post_types) {
            $post_types = array('page');
        }
        $type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_status <> 'trash' AND post_type IN ({$type_placeholders}) AND post_name = %s ORDER BY post_status = 'publish' DESC, ID ASC LIMIT 1";
        $id = $wpdb->get_var($wpdb->prepare($sql, array_merge($post_types, array($slug))));
        return $id ? get_post(absint($id)) : null;
    }

    private function collect_sitemap_urls($sources, $max_urls = 0) {
        $pending = array_values(array_filter(array_map('esc_url_raw', (array) $sources)));
        $seen_sitemaps = array();
        $urls = array();
        while ($pending) {
            $source = array_shift($pending);
            if (!$source || isset($seen_sitemaps[$source])) {
                continue;
            }
            $seen_sitemaps[$source] = true;
            $xml = $this->fetch_xml($source);
            if (!$xml) {
                continue;
            }
            foreach ($this->extract_locs($xml) as $loc) {
                if (preg_match('/\.xml(\.gz)?$/i', parse_url($loc, PHP_URL_PATH) ?: '')) {
                    $pending[] = $loc;
                    continue;
                }
                $urls[$loc] = true;
                if ($max_urls && count($urls) >= $max_urls) {
                    return array_keys($urls);
                }
            }
        }
        return array_keys($urls);
    }

    private function fetch_xml($url) {
        $response = wp_remote_get($url, array('timeout' => 20, 'redirection' => 3, 'user-agent' => 'SEO Enrichment Studio/' . SES_VERSION));
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            $this->logs->add(0, 'sitemap_fetch', 'error', 'Falha ao carregar sitemap: ' . esc_url_raw($url));
            return '';
        }
        return wp_remote_retrieve_body($response);
    }

    private function extract_locs($xml) {
        $locs = array();
        if (preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', (string) $xml, $matches)) {
            foreach ($matches[1] as $loc) {
                $loc = esc_url_raw(html_entity_decode(trim($loc), ENT_QUOTES));
                if ($loc) {
                    $locs[] = $loc;
                }
            }
        }
        return $locs;
    }

    private function path_from_url($url) {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        return $path ? rawurldecode($path) : '';
    }

    private function descendant_ids($parent_ids, $post_types) {
        global $wpdb;
        $all = array();
        $frontier = array_values(array_unique(array_filter(array_map('absint', (array) $parent_ids))));
        $post_types = array_values(array_filter(array_map('sanitize_key', (array) $post_types)));

        while ($frontier) {
            $parent_placeholders = implode(',', array_fill(0, count($frontier), '%d'));
            $type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
            $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_status <> 'trash' AND post_type IN ({$type_placeholders}) AND post_parent IN ({$parent_placeholders})";
            $children = $wpdb->get_col($wpdb->prepare($sql, array_merge($post_types, $frontier)));
            $children = array_values(array_diff(array_map('absint', $children), $all));
            if (!$children) {
                break;
            }
            $all = array_merge($all, $children);
            $frontier = $children;
        }

        return $all;
    }

    public function scan_post($post_id, $write_log = true) {
        $post = get_post($post_id);
        if (!$post || 'publish' !== $post->post_status) {
            return array('eligible' => 0);
        }
        return $this->scan_post_row((object) array('ID' => $post->ID, 'post_name' => $post->post_name), $write_log);
    }

    private function scan_post_row($post, $write_log = true) {
        $post_id = absint($post->ID);
        $slug = (string) $post->post_name;
        if ($this->auto_protects($slug, $post_id)) {
            $this->update_meta_if_changed($post_id, '_ses_protected', 1);
            $this->update_meta_if_changed($post_id, '_ses_enrichment_status', 'protegida');
            if ($write_log) {
                $this->logs->add($post_id, 'protection', 'success', 'Pagina protegida automaticamente pelo slug.');
            }
            return array('eligible' => 0, 'protected' => 1);
        }
        if (get_post_meta($post_id, '_ses_protected', true)) {
            $this->update_meta_if_changed($post_id, '_ses_enrichment_status', 'protegida');
            return array('eligible' => 0, 'protected' => 1);
        }
        $parsed = $this->parser->parse($slug, $this->settings->get('slug_patterns', array()));
        foreach ($parsed as $key => $value) {
            $this->update_meta_if_changed($post_id, '_ses_' . $key, is_array($value) ? wp_json_encode($value) : $value);
        }
        update_post_meta($post_id, '_ses_last_scan_at', current_time('mysql'));
        $status = !empty($parsed['is_programmatic']) ? 'elegivel' : 'ignorada';
        $this->update_meta_if_changed($post_id, '_ses_enrichment_status', $status);
        if ($write_log) {
            $this->logs->add($post_id, 'scan', 'success', 'Scanner executado. Status: ' . $status);
        }
        return array('eligible' => !empty($parsed['is_programmatic']), 'protected' => 0, 'context' => $parsed);
    }

    private function update_meta_if_changed($post_id, $key, $value) {
        if ((string) get_post_meta($post_id, $key, true) === (string) $value) {
            return false;
        }
        update_post_meta($post_id, $key, $value);
        return true;
    }

    private function auto_protects($slug, $post_id = 0) {
        $protected = array_map('sanitize_title', $this->settings->get('protected_slugs', array()));
        if (in_array(sanitize_title($slug), $protected, true)) {
            return true;
        }

        $path = $this->normalized_post_path($post_id, $slug);
        if (!$path) {
            return false;
        }

        foreach ((array) $this->settings->get('protected_paths', array()) as $protected_path) {
            $protected_path = $this->normalize_path($protected_path);
            if (!$protected_path) {
                continue;
            }
            if ($path === $protected_path || 0 === strpos($path, $protected_path . '/')) {
                return true;
            }
        }

        foreach ((array) $this->settings->get('protected_contains', array()) as $needle) {
            $needle = $this->normalize_path($needle);
            if ($needle && false !== strpos('/' . $path . '/', '/' . $needle . '/')) {
                return true;
            }
        }

        return false;
    }

    private function normalized_post_path($post_id, $fallback_slug = '') {
        $path = '';
        if ($post_id && function_exists('get_page_uri')) {
            $path = get_page_uri($post_id);
        }
        if (!$path) {
            $path = $fallback_slug;
        }
        return $this->normalize_path($path);
    }

    private function normalize_path($path) {
        $parsed = wp_parse_url((string) $path, PHP_URL_PATH);
        $path = $parsed ? $parsed : (string) $path;
        $path = remove_accents(strtolower(trim($path, " \t\n\r\0\x0B/")));
        $path = preg_replace('#/+#', '/', $path);
        return sanitize_text_field($path);
    }

    public function stats() {
        global $wpdb;
        $meta = $wpdb->postmeta;
        return array(
            'scanned' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$meta} WHERE meta_key = '_ses_last_scan_at'"),
            'protected' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$meta} WHERE meta_key = '_ses_protected' AND meta_value = '1'"),
            'eligible' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$meta} WHERE meta_key = '_ses_enrichment_status' AND meta_value = 'elegivel'"),
            'enriched' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$meta} WHERE meta_key = '_ses_enrichment_status' AND meta_value IN ('enriquecida','enriquecida_com_alerta')"),
            'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$meta} WHERE meta_key = '_ses_enrichment_status' AND meta_value = 'pendente'"),
            'rejected' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$meta} WHERE meta_key = '_ses_enrichment_status' AND meta_value = 'rejeitada_por_similaridade'"),
            'avg_score' => (int) $wpdb->get_var("SELECT AVG(meta_value+0) FROM {$meta} WHERE meta_key = '_ses_quality_score'"),
        );
    }
}
