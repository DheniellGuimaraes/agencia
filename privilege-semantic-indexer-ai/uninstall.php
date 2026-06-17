<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
delete_option('psi_ai_settings');
// Backups and logs are intentionally preserved for audit and safe restoration.
