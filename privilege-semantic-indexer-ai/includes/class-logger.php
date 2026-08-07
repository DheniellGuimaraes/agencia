<?php
if (!defined('ABSPATH')) { exit; }
class PSI_AI_Logger {
    public function log($level, $message, $context = array()) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'psi_ai_logs', array('level'=>sanitize_key($level),'message'=>sanitize_textarea_field($message),'context'=>wp_json_encode($context),'created_at'=>current_time('mysql')), array('%s','%s','%s','%s'));
    }
    public function recent($limit = 100) { global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}psi_ai_logs ORDER BY id DESC LIMIT %d", absint($limit))); }
}
