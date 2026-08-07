<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Redirect_Manager {
    private $settings;
    private $logs;

    public function __construct($settings, $logs) {
        $this->settings = $settings;
        $this->logs = $logs;
    }

    public function maybe_redirect_accented_url() {
        if (!$this->settings->get('redirects_enabled', 0) || is_admin()) {
            return;
        }
        $request = $_SERVER['REQUEST_URI'] ?? '';
        if ($request === remove_accents($request)) {
            return;
        }
        $clean_path = remove_accents($request);
        $clean_url = home_url($clean_path);
        $post_id = url_to_postid($clean_url);
        if (!$post_id || get_post_meta($post_id, '_ses_protected', true)) {
            return;
        }
        $this->logs->add($post_id, 'redirect', 'success', 'Redirect 301 de URL com acento para versao limpa.');
        wp_safe_redirect($clean_url, 301);
        exit;
    }
}
