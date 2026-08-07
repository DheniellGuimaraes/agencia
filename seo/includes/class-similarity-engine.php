<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Similarity_Engine {
    public function evaluate($post_id, $html) {
        global $wpdb;
        $plain = $this->plain($html);
        $hash = md5($plain);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id <> %d LIMIT 60",
            '_ses_enriched_html',
            $post_id
        ));
        $max = 0;
        foreach ($rows as $row) {
            similar_text($plain, $this->plain($row->meta_value), $percent);
            $max = max($max, (int) round($percent));
        }
        return array(
            'hash' => $hash,
            'intro_hash' => md5(substr($plain, 0, 500)),
            'faq_hash' => md5($this->extract_faq($html)),
            'cta_hash' => md5($this->extract_cta($html)),
            'block_hashes' => array_map('md5', preg_split('/<h2/i', $html)),
            'score' => $max,
        );
    }

    private function plain($html) {
        return preg_replace('/\s+/', ' ', strtolower(wp_strip_all_tags((string) $html)));
    }

    private function extract_faq($html) {
        preg_match_all('/<details.*?<\/details>/is', $html, $m);
        return implode(' ', $m[0] ?? array());
    }

    private function extract_cta($html) {
        preg_match('/class="ses-cta"[^>]*>(.*?)<\/p>/is', $html, $m);
        return wp_strip_all_tags($m[1] ?? '');
    }
}
