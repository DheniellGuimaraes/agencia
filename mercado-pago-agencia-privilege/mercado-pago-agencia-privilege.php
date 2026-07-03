<?php
/**
 * Plugin Name: Mercado Pago Agência Privilege
 * Plugin URI: https://agenciaprivilege.local/mercado-pago-agencia-privilege
 * Description: Integra WooCommerce ao Mercado Livre para sincronização de produtos, estoque, pedidos e webhooks, com painel glassmorphism, diagnóstico OAuth avançado, logs profissionais com redução de falsos erros PolicyAgent, modo Connect sem digitar credenciais via broker seguro e ferramentas de validação para o erro de aplicativo não pronto e callback OAuth limpo via REST API e buscador inteligente de categorias Mercado Livre por IA/API e correção profissional da sincronização em lote para produtos publicados ainda não habilitados individualmente e preenchimento automático de atributos obrigatórios de software exigidos pelo Mercado Livre.
 * Version: 3.6.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Agência Privilege
 * Text Domain: mercado-pago-agencia-privilege
 * Domain Path: /languages
 *
 * Este plugin não é afiliado, patrocinado ou endossado por Mercado Pago, Mercado Livre ou WooCommerce.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MPAP_VERSION', '3.7.0' );
define( 'MPAP_DB_VERSION', '3.7.0' );
define( 'MPAP_FILE', __FILE__ );
define( 'MPAP_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPAP_URL', plugin_dir_url( __FILE__ ) );
define( 'MPAP_BASENAME', plugin_basename( __FILE__ ) );
define( 'MPAP_TEXT_DOMAIN', 'mercado-pago-agencia-privilege' );

add_action(
    'before_woocommerce_init',
    static function () {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);

require_once MPAP_DIR . 'includes/functions.php';
require_once MPAP_DIR . 'includes/class-mpap-crypto.php';
require_once MPAP_DIR . 'includes/class-mpap-logger.php';
require_once MPAP_DIR . 'includes/class-mpap-auth.php';
require_once MPAP_DIR . 'includes/class-mpap-api.php';
require_once MPAP_DIR . 'includes/class-mpap-quality.php';
require_once MPAP_DIR . 'includes/class-mpap-product-sync.php';
require_once MPAP_DIR . 'includes/class-mpap-order-importer.php';
require_once MPAP_DIR . 'includes/class-mpap-webhooks.php';
require_once MPAP_DIR . 'includes/class-mpap-diagnostics.php';
require_once MPAP_DIR . 'includes/class-mpap-admin.php';
require_once MPAP_DIR . 'includes/class-mpap-plugin.php';

register_activation_hook( __FILE__, array( 'MPAP_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MPAP_Plugin', 'deactivate' ) );

add_action(
    'plugins_loaded',
    static function () {
        MPAP_Plugin::instance()->hooks();
    },
    20
);

function mpap_plugin() {
    return MPAP_Plugin::instance();
}
