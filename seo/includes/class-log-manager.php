<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Log_Manager {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ses_logs';
    }

    public static function create_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table = self::table();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned DEFAULT 0,
            action varchar(80) NOT NULL,
            status varchar(40) NOT NULL,
            message text NULL,
            created_at datetime NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY action_status (action, status),
            KEY created_at (created_at)
        ) {$charset};";
        dbDelta($sql);
    }

    public function add($post_id, $action, $status, $message = '') {
        global $wpdb;
        $wpdb->insert(self::table(), array(
            'post_id' => absint($post_id),
            'action' => sanitize_key($action),
            'status' => sanitize_key($status),
            'message' => wp_kses_post($message),
            'created_at' => current_time('mysql'),
            'user_id' => get_current_user_id(),
        ), array('%d', '%s', '%s', '%s', '%s', '%d'));
    }

    public function recent($limit = 50) {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", absint($limit)));
    }
}
