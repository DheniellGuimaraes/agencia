<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Schema_Manager {
    private $settings;

    public function __construct($settings) {
        $this->settings = $settings;
    }

    public function print_schema() {
        if (is_admin() || !is_singular() || !$this->settings->get('schema_faq_enabled', 1)) {
            return;
        }
        $post_id = get_the_ID();
        if (get_post_meta($post_id, '_ses_protected', true)) {
            return;
        }
        $html = get_post_meta($post_id, '_ses_enriched_html', true);
        if (!$html || false === strpos($html, '<details')) {
            return;
        }
        preg_match_all('/<summary>(.*?)<\/summary>\s*<p>(.*?)<\/p>/is', $html, $matches, PREG_SET_ORDER);
        if (!$matches) {
            return;
        }
        $entities = array();
        foreach ($matches as $m) {
            $entities[] = array(
                '@type' => 'Question',
                'name' => wp_strip_all_tags($m[1]),
                'acceptedAnswer' => array('@type' => 'Answer', 'text' => wp_strip_all_tags($m[2])),
            );
        }
        $schema = array('@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities);
        echo "\n<script type=\"application/ld+json\" class=\"ses-schema\">" . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }
}
