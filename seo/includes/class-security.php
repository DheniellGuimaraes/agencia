<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Security {
    public static function can_manage() {
        return current_user_can('manage_options');
    }

    public static function verify($action = 'ses_admin') {
        if (!self::can_manage()) {
            wp_die(esc_html__('Voce nao tem permissao para acessar esta area.', 'seo-enrichment-studio'));
        }
        check_admin_referer($action);
    }

    public static function verify_ajax($action = 'ses_ajax') {
        if (!self::can_manage()) {
            wp_send_json_error(array('message' => 'Permissao negada.'), 403);
        }
        check_ajax_referer($action, 'nonce');
    }

    public static function clean_lines($value) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        return array_values(array_filter(array_map('sanitize_text_field', $lines)));
    }
}
