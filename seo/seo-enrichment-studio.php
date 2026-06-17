<?php
/**
 * Plugin Name: SEO Enrichment Studio
 * Description: Enriquecimento semantico em massa para paginas programaticas com suporte a Yoast SEO e Bold Builder.
 * Version: 1.0.8
 * Author: Studio Privilege
 * Text Domain: seo-enrichment-studio
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SES_VERSION', '1.0.8');
define('SES_FILE', __FILE__);
define('SES_PATH', plugin_dir_path(__FILE__));
define('SES_URL', plugin_dir_url(__FILE__));

require_once SES_PATH . 'includes/class-plugin.php';

register_activation_hook(__FILE__, array('SES_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('SES_Plugin', 'deactivate'));

add_action('plugins_loaded', array('SES_Plugin', 'instance'));
