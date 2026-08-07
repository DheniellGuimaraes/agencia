<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Backup_Manager {
    private $logs;

    public function __construct($logs) {
        $this->logs = $logs;
    }

    public function create($post_id) {
        $post = get_post($post_id);
        if (!$post || get_post_meta($post_id, '_ses_backup_created_at', true)) {
            return;
        }
        update_post_meta($post_id, '_ses_original_post_content', $post->post_content);
        update_post_meta($post_id, '_ses_original_title', $post->post_title);
        update_post_meta($post_id, '_ses_original_excerpt', $post->post_excerpt);
        update_post_meta($post_id, '_ses_original_yoast_title', get_post_meta($post_id, '_wpseo_title', true));
        update_post_meta($post_id, '_ses_original_yoast_description', get_post_meta($post_id, '_wpseo_metadesc', true));
        update_post_meta($post_id, '_ses_original_yoast_canonical', get_post_meta($post_id, '_wpseo_canonical', true));
        update_post_meta($post_id, '_ses_original_robots', get_post_meta($post_id, '_wpseo_meta-robots-noindex', true));
        update_post_meta($post_id, '_ses_backup_created_at', current_time('mysql'));
        $this->logs->add($post_id, 'backup', 'success', 'Backup completo criado antes do enriquecimento.');
    }

    public function restore($post_id) {
        if (get_post_meta($post_id, '_ses_protected', true)) {
            return new WP_Error('ses_protected', 'Pagina protegida nao pode ser restaurada pelo fluxo automatico.');
        }
        $original = get_post_meta($post_id, '_ses_original_post_content', true);
        if ('' === $original) {
            return new WP_Error('ses_no_backup', 'Nao existe backup para esta pagina.');
        }
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $original,
            'post_title' => get_post_meta($post_id, '_ses_original_title', true),
            'post_excerpt' => get_post_meta($post_id, '_ses_original_excerpt', true),
        ));
        update_post_meta($post_id, '_wpseo_title', get_post_meta($post_id, '_ses_original_yoast_title', true));
        update_post_meta($post_id, '_wpseo_metadesc', get_post_meta($post_id, '_ses_original_yoast_description', true));
        update_post_meta($post_id, '_wpseo_canonical', get_post_meta($post_id, '_ses_original_yoast_canonical', true));
        update_post_meta($post_id, '_wpseo_meta-robots-noindex', get_post_meta($post_id, '_ses_original_robots', true));
        delete_post_meta($post_id, '_ses_enriched_html');
        update_post_meta($post_id, '_ses_enrichment_status', 'restaurada');
        $this->logs->add($post_id, 'restore', 'success', 'Pagina restaurada a partir do backup.');
        return true;
    }
}
