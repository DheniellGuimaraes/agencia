<?php
/**
 * Plugin Name: Resenha Sagrada — Bolão da Copa 26
 * Description: Gestão de bolão esportivo com participantes, jogos, palpites, pagamentos, ranking e premiação.
 * Version: 2026.11
 * Author: Resenha Sagrada
 * Text Domain: resenha-sagrada-bolao
 */

if (!defined('ABSPATH')) { exit; }

define('RSB_VERSION', '2026.11');
define('RSB_FILE', __FILE__);
define('RSB_PATH', plugin_dir_path(__FILE__));
define('RSB_URL', plugin_dir_url(__FILE__));

require_once RSB_PATH . 'includes/helpers.php';
require_once RSB_PATH . 'includes/class-teams.php';
require_once RSB_PATH . 'includes/class-database.php';
require_once RSB_PATH . 'includes/class-worldcup-2026-seeder.php';
require_once RSB_PATH . 'includes/class-worldcup-format.php';
require_once RSB_PATH . 'includes/class-copa2026-bracket.php';
require_once RSB_PATH . 'includes/class-activator.php';
require_once RSB_PATH . 'includes/class-ranking.php';
require_once RSB_PATH . 'includes/class-premiacao.php';
require_once RSB_PATH . 'includes/class-bolao.php';
require_once RSB_PATH . 'includes/class-participantes.php';
require_once RSB_PATH . 'includes/class-jogos.php';
require_once RSB_PATH . 'includes/class-palpites.php';
require_once RSB_PATH . 'includes/class-pagamentos.php';
require_once RSB_PATH . 'includes/class-admin-menu.php';
require_once RSB_PATH . 'includes/class-page-importer.php';
require_once RSB_PATH . 'includes/class-shortcodes.php';
require_once RSB_PATH . 'includes/class-ajax.php';

register_activation_hook(__FILE__, ['RSB_Activator', 'activate']);

final class Resenha_Sagrada_Bolao {
    public static function init(): void {
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'public_assets']);
        add_action('init', ['RSB_Shortcodes', 'register']);
        add_action('admin_menu', ['RSB_Admin_Menu', 'register']);
        add_action('admin_post_rsb_export_report', ['RSB_Admin_Menu', 'export_report']);
        add_action('admin_post_rsb_recalculate_bracket', ['RSB_Admin_Menu', 'recalculate_bracket']);
        add_action('rest_api_init', ['RSB_Ajax', 'register_rest_routes']);
        RSB_Ajax::register_ajax();
        if (get_option('rsb_db_version') !== RSB_VERSION) {
            RSB_Database::create_tables();
            if (method_exists('RSB_Database', 'upgrade_2026_1')) { RSB_Database::upgrade_2026_1(); }
            if (method_exists('RSB_Database', 'upgrade_2026_10_unlimited_participants')) { RSB_Database::upgrade_2026_10_unlimited_participants(); }
            update_option('rsb_db_version', RSB_VERSION);
        }
    }

    public static function admin_assets($hook): void {
        if (strpos((string)$hook, 'rsb') === false && strpos((string)$hook, 'resenha-sagrada') === false) { return; }
        wp_enqueue_style('rsb-admin', RSB_URL . 'assets/css/admin.css', [], RSB_VERSION);
        wp_enqueue_script('rsb-admin', RSB_URL . 'assets/js/admin.js', ['jquery'], RSB_VERSION, true);
        wp_localize_script('rsb-admin', 'RSB', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rsb_nonce'),
        ]);
    }

    public static function public_assets(): void {
        wp_enqueue_style('rsb-public', RSB_URL . 'assets/css/public.css', [], RSB_VERSION);
        wp_enqueue_script('rsb-public', RSB_URL . 'assets/js/public.js', ['jquery'], RSB_VERSION, true);
        wp_localize_script('rsb-public', 'RSB_PUBLIC', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rsb_public_nonce'),
            'currentUrl' => esc_url_raw(remove_query_arg(['rsb_auth'], home_url(add_query_arg([], $GLOBALS['wp']->request ?? '')))),
        ]);
    }
}

Resenha_Sagrada_Bolao::init();
