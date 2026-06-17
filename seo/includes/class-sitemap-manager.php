<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Sitemap_Manager {
    private $settings;
    private $logs;

    public function __construct($settings, $logs) {
        $this->settings = $settings;
        $this->logs = $logs;
    }

    public function register_rewrite() {
        add_rewrite_rule('^ses-sitemap\.xml$', 'index.php?ses_sitemap=1', 'top');
        add_rewrite_tag('%ses_sitemap%', '1');
    }

    public function maybe_render_sitemap() {
        if (!get_query_var('ses_sitemap')) {
            return;
        }
        if (!$this->settings->get('sitemap_enabled', 0)) {
            status_header(404);
            exit;
        }
        header('Content-Type: application/xml; charset=utf-8');
        $ids = get_posts(array(
            'post_type' => $this->settings->get('post_types', array('page')),
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => 1000,
            'meta_query' => array(
                array('key' => '_ses_enrichment_status', 'value' => 'enriquecida'),
                array('key' => '_ses_quality_score', 'value' => absint($this->settings->get('minimum_score', 75)), 'compare' => '>=', 'type' => 'NUMERIC'),
            ),
        ));
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($ids as $id) {
            if (get_post_meta($id, '_ses_protected', true)) {
                continue;
            }
            echo '<url><loc>' . esc_url(get_permalink($id)) . '</loc><lastmod>' . esc_html(get_the_modified_date('c', $id)) . "</lastmod></url>\n";
        }
        echo "</urlset>";
        exit;
    }
}
