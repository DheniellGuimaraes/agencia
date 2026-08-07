<?php
/**
 * Plugin Name: Privilege Semantic Indexer AI
 * Description: Enriquecimento semântico seguro para páginas programáticas do Studio Privilege, compatível com AIKO, Bold Builder, Yoast SEO e Contact Form 7.
 * Version: 1.0.0
 * Author: Studio Privilege
 * Text Domain: privilege-semantic-indexer-ai
 */

if (!defined('ABSPATH')) { exit; }

define('PSI_AI_VERSION', '1.0.0');
define('PSI_AI_FILE', __FILE__);
define('PSI_AI_DIR', plugin_dir_path(__FILE__));
define('PSI_AI_URL', plugin_dir_url(__FILE__));

require_once PSI_AI_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, array('PSI_AI_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('PSI_AI_Plugin', 'deactivate'));

add_action('plugins_loaded', function () {
    PSI_AI_Plugin::instance()->init();
});
