<?php
if (!defined('ABSPATH')) { exit; }
class RSB_Activator {
    public static function activate(): void {
        RSB_Database::create_tables();
        RSB_Database::seed_defaults();
        update_option('rsb_db_version', RSB_VERSION);
        flush_rewrite_rules();
    }
}
