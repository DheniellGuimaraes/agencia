<?php
if (!defined('ABSPATH')) { exit; }

class PSI_AI_Similarity_Guard {
    public function evaluate($post_id, $new_html, $entities) {
        $samples = $this->collect_samples($post_id, $entities);
        $new_text = $this->normalize($new_html);
        $max = 0; $source = 'none';
        foreach ($samples as $label => $sample) {
            similar_text($new_text, $this->normalize($sample), $pct);
            if ($pct > $max) { $max = $pct; $source = $label; }
        }
        $action = 'publish';
        if ($max > 90) { $action = 'recommend_noindex'; }
        elseif ($max > 80) { $action = 'block'; }
        elseif ($max > 65) { $action = 'regenerate'; }
        return array('similarity'=>round($max, 2), 'source'=>$source, 'action'=>$action, 'samples'=>count($samples));
    }

    private function collect_samples($post_id, $entities) {
        global $wpdb;
        $samples = array();
        $post = get_post($post_id);
        if ($post) { $samples['current_page'] = $post->post_content; }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT data FROM {$wpdb->prefix}psi_ai_pages WHERE post_id <> %d AND data IS NOT NULL ORDER BY updated_at DESC LIMIT 30", absint($post_id)));
        foreach ($rows as $i => $row) { $samples['recent_' . $i] = $row->data; }
        $like_city = '%' . $wpdb->esc_like(sanitize_title($entities['city'])) . '%';
        $like_profession = '%' . $wpdb->esc_like(sanitize_title($entities['profession'])) . '%';
        $posts = $wpdb->get_results($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID <> %d AND post_status IN ('publish','draft') AND (post_name LIKE %s OR post_name LIKE %s) LIMIT 20", absint($post_id), $like_city, $like_profession));
        foreach ($posts as $i => $row) { $samples['related_' . $i] = $row->post_content; }
        return $samples;
    }

    private function normalize($text) {
        $text = strtolower(remove_accents(wp_strip_all_tags((string) $text)));
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
