<?php
/**
 * Plugin Name: RMA Woo Sync
 * Description: Sincroniza status financeiro da entidade com pedidos WooCommerce (anuidade via PIX).
 * Version: 0.7.0
 * Author: RMA
 */

if (! defined('ABSPATH')) {
    exit;
}


function rma_sync_debug_enabled(): bool {
    if (defined('RMA_SYNC_DEBUG') && RMA_SYNC_DEBUG) {
        return true;
    }

    if (isset($_GET['rma_debug_flow']) && sanitize_text_field((string) $_GET['rma_debug_flow']) === '1') {
        return true;
    }

    return (bool) get_option('rma_sync_debug_enabled', false);
}

function rma_sync_debug_log(string $message, array $context = []): void {
    if (! rma_sync_debug_enabled()) {
        return;
    }

    if (! empty($context)) {
        $message .= ' | ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
    }

    error_log('[RMA_SYNC] ' . $message);
}

function rma_get_order_locked_amount(WC_Order $order): float {
    $locked = (float) $order->get_meta('rma_due_amount_locked');
    if ($locked > 0) {
        return $locked;
    }

    return (float) $order->get_total();
}

final class RMA_Woo_Sync {
    private const CPT = 'rma_entidade';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);

        add_action('woocommerce_order_status_completed', [$this, 'mark_adimplente']);
        add_action('woocommerce_order_status_processing', [$this, 'mark_adimplente']);
        add_action('woocommerce_order_status_failed', [$this, 'mark_inadimplente']);
        add_action('woocommerce_order_status_cancelled', [$this, 'mark_inadimplente']);
        add_action('woocommerce_order_status_refunded', [$this, 'mark_inadimplente']);

        add_action('init', [$this, 'schedule_annual_dues_cron']);
        add_action('rma_generate_annual_dues', [$this, 'generate_annual_dues']);
    }

    public function register_routes(): void {
        register_rest_route('rma/v1', '/entities/(?P<id>\d+)/finance/payment-status', [
            'methods' => 'GET',
            'callback' => [$this, 'payment_status'],
            'permission_callback' => [$this, 'can_view_entity_finance'],
        ]);

        register_rest_route('rma/v1', '/orders/(?P<id>\d+)/pix-status', [
            'methods' => 'GET',
            'callback' => [$this, 'order_pix_status'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function can_view_entity_finance(WP_REST_Request $request): bool {
        if (! is_user_logged_in()) {
            return false;
        }

        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return false;
        }

        if (current_user_can('edit_others_posts')) {
            return true;
        }

        return (int) get_post_field('post_author', $entity_id) === get_current_user_id();
    }

    public function payment_status(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $finance_status = (string) get_post_meta($entity_id, 'finance_status', true);
        $is_paid = $finance_status === 'adimplente';
        $latest_order = $this->get_latest_entity_order($entity_id);

        $payload = [
            'entity_id' => $entity_id,
            'finance_status' => $finance_status,
            'is_paid' => $is_paid,
            'should_redirect' => $is_paid,
            'redirect_url' => home_url('/dashboard/'),
            'latest_order' => $latest_order,
        ];

        return new WP_REST_Response($payload);
    }

    public function order_pix_status(WP_REST_Request $request): WP_REST_Response {
        if (! function_exists('wc_get_order')) {
            return new WP_REST_Response(['message' => 'WooCommerce indisponível.'], 500);
        }

        $order_id = (int) $request->get_param('id');
        $order = wc_get_order($order_id);
        if (! $order instanceof WC_Order) {
            return new WP_REST_Response(['message' => 'Pedido não encontrado.'], 404);
        }

        $is_authorized = false;
        $order_key = trim((string) $request->get_param('key'));
        if ($order_key !== '' && hash_equals((string) $order->get_order_key(), $order_key)) {
            $is_authorized = true;
        }

        $current_user_id = get_current_user_id();
        if (! $is_authorized && $current_user_id > 0) {
            if ((int) $order->get_user_id() === $current_user_id || current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders')) {
                $is_authorized = true;
            }
        }

        if (! $is_authorized) {
            return new WP_REST_Response(['message' => 'Sem permissão para consultar este pedido.'], 403);
        }

        $status = (string) $order->get_status();
        $is_paid = in_array($status, ['processing', 'completed'], true);

        return new WP_REST_Response([
            'order_id' => $order_id,
            'status' => $status,
            'is_paid' => $is_paid,
            'received_url' => $order->get_checkout_order_received_url(),
            'updated_at' => current_time('mysql', true),
        ]);
    }

    public function mark_adimplente(int $order_id): void {
        $this->sync_financial_status($order_id, 'adimplente');
    }

    public function mark_inadimplente(int $order_id): void {
        $this->sync_financial_status($order_id, 'inadimplente');
    }

    private function sync_financial_status(int $order_id, string $target_status): void {
        if (! function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $entity_id = (int) $order->get_meta('rma_entity_id');
        if ($entity_id <= 0 || get_post_type($entity_id) !== self::CPT) {
            return;
        }

        update_post_meta($entity_id, 'finance_status', $target_status);

        if ($target_status === 'adimplente') {
            $paid_ts = time();
            $due_ts = $paid_ts + (365 * DAY_IN_SECONDS);
            update_post_meta($entity_id, 'finance_paid_at', gmdate('Y-m-d H:i:s', $paid_ts));
            update_post_meta($entity_id, 'finance_due_at', gmdate('Y-m-d H:i:s', $due_ts));
            update_post_meta($entity_id, 'anuidade_vencimento', gmdate('Y-m-d', $due_ts));
            update_post_meta($entity_id, 'finance_access_status', 'active');
            update_post_meta($entity_id, 'finance_last_paid_amount', (string) rma_get_order_locked_amount($order));
            update_post_meta($entity_id, 'finance_last_paid_order_id', (int) $order_id);

        }

        $history = get_post_meta($entity_id, 'finance_history', true);
        $history = is_array($history) ? $history : [];

        $event_key = $order_id . '|' . $order->get_status() . '|' . $target_status;
        $is_duplicate = false;
        foreach ($history as $event) {
            $existing_key = (int) ($event['order_id'] ?? 0) . '|' . (string) ($event['status'] ?? '') . '|' . (string) ($event['finance_status'] ?? '');
            if ($existing_key === $event_key) {
                $is_duplicate = true;
                break;
            }
        }

        if (! $is_duplicate) {
            $paid_date = $order->get_date_paid();
            $year = $paid_date ? $paid_date->date('Y') : gmdate('Y');

            $history[] = [
                'order_id' => $order_id,
                'year' => $year,
                'status' => $order->get_status(),
                'finance_status' => $target_status,
                'total' => rma_get_order_locked_amount($order),
                'valor' => (string) rma_get_order_locked_amount($order),
                'paid_at' => current_time('mysql', true),
            ];

            $max_history = 500;
            if (count($history) > $max_history) {
                $history = array_slice($history, -1 * $max_history);
            }

            update_post_meta($entity_id, 'finance_history', $history);
        }

        do_action('rma/entity_finance_updated', $entity_id, $order_id, $history);
    }

    public function schedule_annual_dues_cron(): void {
        if (! wp_next_scheduled('rma_generate_annual_dues')) {
            wp_schedule_event(time() + 10 * MINUTE_IN_SECONDS, 'daily', 'rma_generate_annual_dues');
        }
    }

    public function generate_annual_dues(): void {
        if (! function_exists('wc_create_order')) {
            return;
        }

        $year = gmdate('Y');
        if (! $this->is_due_cycle_open()) {
            return;
        }

        $product_id = absint((int) get_option('rma_annual_dues_product_id', 0));
        $annual_value = (float) get_option('rma_annual_due_value', '0');

        $query = new WP_Query([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => 500,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'governance_status',
                    'value' => 'aprovado',
                ],
            ],
        ]);

        $created = 0;
        foreach ($query->posts as $entity_id) {
            $entity_id = (int) $entity_id;
            if ($this->has_due_order_for_year($entity_id, $year)) {
                continue;
            }

            $author_id = (int) get_post_field('post_author', $entity_id);
            $customer_id = $author_id > 0 ? $author_id : 0;

            $order = wc_create_order(['customer_id' => $customer_id]);
            if (is_wp_error($order) || ! $order) {
                continue;
            }

            $has_item = false;
            if ($product_id > 0) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $item_id = $order->add_product($product, 1);
                    if ($item_id && $annual_value > 0) {
                        $item = $order->get_item($item_id);
                        if ($item instanceof WC_Order_Item_Product) {
                            $item->set_subtotal($annual_value);
                            $item->set_total($annual_value);
                            $item->save();
                        }
                    }
                    $has_item = true;
                }
            }

            if (! $has_item && $annual_value > 0) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('Anuidade RMA ' . $year);
                $fee->set_amount($annual_value);
                $fee->set_total($annual_value);
                $order->add_item($fee);
            }

            $order->update_meta_data('rma_entity_id', $entity_id);
            $order->update_meta_data('rma_due_year', $year);
            $order->update_meta_data('rma_is_annual_due', '1');
            $order->calculate_totals();
            $order->update_meta_data('rma_due_amount_locked', (string) $order->get_total());
            $order->save();

            update_post_meta($entity_id, 'finance_status', 'inadimplente');
            $this->send_anexo2_event_email($entity_id, 'confirmacao', [
                'vencimento' => $year . '-12-31',
                'status' => 'inadimplente',
                'link_pagamento' => $order->get_checkout_payment_url(),
                'valor' => (string) rma_get_order_locked_amount($order),
            ]);
            $created++;
        }

        wp_reset_postdata();
        update_option('rma_annual_dues_last_run', [
            'year' => $year,
            'created_orders' => $created,
            'ran_at' => current_time('mysql', true),
        ], false);
    }

    private function is_due_cycle_open(): bool {
        $day_month = (string) get_option('rma_due_day_month', '01-01');
        if (! preg_match('/^(\d{2})-(\d{2})$/', $day_month, $matches)) {
            $day_month = '01-01';
            $matches = ['01-01', '01', '01'];
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];
        if (! checkdate($month, $day, 2024)) {
            $day_month = '01-01';
        }

        $current_md = gmdate('m-d');
        $target_md = substr($day_month, 3, 2) . '-' . substr($day_month, 0, 2);

        return $current_md >= $target_md;
    }

    private function has_due_order_for_year(int $entity_id, string $year): bool {
        if (! function_exists('wc_get_orders')) {
            return false;
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'return' => 'ids',
            'meta_query' => [
                [
                    'key' => 'rma_entity_id',
                    'value' => $entity_id,
                ],
                [
                    'key' => 'rma_due_year',
                    'value' => $year,
                ],
            ],
            'status' => ['pending', 'on-hold', 'processing', 'completed', 'cancelled', 'failed', 'refunded'],
        ]);

        return ! empty($orders);
    }

    private function get_latest_entity_order(int $entity_id): array {
        if (! function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => 'rma_entity_id',
            'meta_value' => $entity_id,
            'status' => ['pending', 'on-hold', 'processing', 'completed', 'cancelled', 'failed', 'refunded'],
        ]);

        if (empty($orders) || ! is_array($orders)) {
            return [];
        }

        $order = $orders[0];
        if (! $order instanceof WC_Order) {
            return [];
        }

        return [
            'order_id' => (int) $order->get_id(),
            'status' => (string) $order->get_status(),
            'total' => (float) $order->get_total(),
            'pay_url' => $order->get_checkout_payment_url(),
            'due_year' => (string) $order->get_meta('rma_due_year'),
        ];
    }

    private function send_anexo2_event_email(int $entity_id, string $event_key, array $context = []): void {
        $author_id = (int) get_post_field('post_author', $entity_id);
        if ($author_id <= 0) {
            return;
        }

        $email = (string) get_the_author_meta('user_email', $author_id);
        if (! is_email($email)) {
            return;
        }

        $context = wp_parse_args($context, [
            'nome' => (string) get_the_author_meta('display_name', $author_id),
            'entidade' => (string) get_the_title($entity_id),
            'valor' => (string) get_option('rma_annual_due_value', ''),
        ]);

        if (function_exists('rma_send_anexo2_email')) {
            rma_send_anexo2_email($event_key, $email, $context);
        }
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('rma_generate_annual_dues');
    }
}

function rma_get_annual_dues_product_ids(): array {
    $ids = [];

    $configured_id = (int) get_option('rma_annual_dues_product_id', 0);
    if ($configured_id > 0) {
        $ids[] = $configured_id;
    }

    $legacy_id = (int) get_option('rma_woo_product_id', 0);
    if ($legacy_id > 0) {
        $ids[] = $legacy_id;
    }

    $ids[] = 3407;

    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    return $ids;
}

function rma_contains_annual_dues_product(): bool {
    $dues_product_ids = rma_get_annual_dues_product_ids();
    if (empty($dues_product_ids)) {
        return false;
    }

    $requested_product_id = absint((int) ($_GET['add-to-cart'] ?? 0)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ($requested_product_id > 0 && in_array($requested_product_id, $dues_product_ids, true)) {
        return true;
    }

    if (! function_exists('WC') || ! WC()->cart) {
        return false;
    }

    foreach (WC()->cart->get_cart() as $item) {
        $product_id = absint((int) ($item['product_id'] ?? 0));
        $variation_id = absint((int) ($item['variation_id'] ?? 0));
        $is_due_row = (string) ($item['rma_is_annual_due'] ?? '') === '1';

        if ($is_due_row || in_array($product_id, $dues_product_ids, true) || in_array($variation_id, $dues_product_ids, true)) {
            return true;
        }
    }

    return false;
}

function rma_is_checkout_mode(): bool {
    if (! function_exists('is_checkout') || ! is_checkout()) {
        return false;
    }

    return rma_contains_annual_dues_product();
}

function rma_get_annual_due_amount(): float {
    return max(0, (float) get_option('rma_annual_due_value', '0'));
}

function rma_is_dues_product_id(int $product_id): bool {
    if ($product_id <= 0) {
        return false;
    }

    return in_array($product_id, rma_get_annual_dues_product_ids(), true);
}

add_filter('woocommerce_add_cart_item_data', function (array $cart_item_data, int $product_id, int $variation_id): array {
    $is_due_product = rma_is_dues_product_id($product_id) || rma_is_dues_product_id($variation_id);
    if (! $is_due_product) {
        return $cart_item_data;
    }

    $amount = rma_get_annual_due_amount();
    if ($amount <= 0) {
        return $cart_item_data;
    }

    $cart_item_data['rma_is_annual_due'] = '1';
    $cart_item_data['rma_annual_due_unit_price'] = $amount;

    return $cart_item_data;
}, 20, 3);

add_action('woocommerce_before_calculate_totals', function ($cart): void {
    if (! is_a($cart, 'WC_Cart')) {
        return;
    }

    if (is_admin() && ! defined('DOING_AJAX')) {
        return;
    }

    $amount = rma_get_annual_due_amount();
    if ($amount <= 0) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = (int) ($cart_item['product_id'] ?? 0);
        $variation_id = (int) ($cart_item['variation_id'] ?? 0);
        $is_due_item = (string) ($cart_item['rma_is_annual_due'] ?? '') === '1';
        $is_due_product = rma_is_dues_product_id($product_id) || rma_is_dues_product_id($variation_id);

        if (! $is_due_item && ! $is_due_product) {
            continue;
        }

        if (! isset($cart_item['data']) || ! is_object($cart_item['data']) || ! method_exists($cart_item['data'], 'set_price')) {
            continue;
        }

        $cart_item['data']->set_price($amount);
        $cart->cart_contents[$cart_item_key]['rma_is_annual_due'] = '1';
        $cart->cart_contents[$cart_item_key]['rma_annual_due_unit_price'] = $amount;
    }
}, 20);

add_filter('body_class', function (array $classes): array {
    if (rma_is_checkout_mode()) {
        $classes[] = 'rma-checkout-mode';
    }

    return $classes;
});

add_filter('woocommerce_available_payment_gateways', function (array $gateways): array {
    if (! rma_is_checkout_mode()) {
        return $gateways;
    }

    if (isset($gateways['rma_pix'])) {
        return ['rma_pix' => $gateways['rma_pix']];
    }

    return $gateways;
});

add_filter('wc_add_to_cart_message_html', function (string $message): string {
    if (rma_is_checkout_mode()) {
        return '';
    }

    return $message;
}, 10, 1);

add_action('woocommerce_before_checkout_form', function (): void {
    if (! rma_is_checkout_mode() || ! function_exists('wc_get_notices')) {
        return;
    }

    $success = wc_get_notices('success');
    if (! empty($success)) {
        wc_clear_notices();
    }
}, 1);

function rma_pix_build_payload(string $pix_key, string $amount, string $txid): string {
    $pix_key = preg_replace('/\s+/', '', sanitize_text_field($pix_key));
    $amount = number_format(max(0, (float) $amount), 2, '.', '');
    $txid = strtoupper(preg_replace('/[^A-Z0-9]/', '', sanitize_text_field($txid)));
    if ($txid === '') {
        $txid = 'RMA';
    }
    $txid = substr($txid, 0, 25);

    $merchant = 'RMA';
    $city = 'SAO PAULO';

    $gui = '0014BR.GOV.BCB.PIX';
    $keyField = sprintf('%02d%s', strlen($pix_key), $pix_key);
    $merchantAccount = '26' . sprintf('%02d%s', strlen($gui . '01' . $keyField), $gui . '01' . $keyField);

    $payload = '000201'; // payload format indicator
    $payload .= $merchantAccount;
    $payload .= '52040000';
    $payload .= '5303986'; // BRL
    $payload .= '54' . sprintf('%02d%s', strlen($amount), $amount);
    $payload .= '5802BR';
    $payload .= '59' . sprintf('%02d%s', strlen($merchant), $merchant);
    $payload .= '60' . sprintf('%02d%s', strlen($city), $city);
    $tx = '05' . sprintf('%02d%s', strlen($txid), $txid);
    $payload .= '62' . sprintf('%02d%s', strlen($tx), $tx);
    $payload .= '6304';

    $crc = strtoupper(dechex(rma_pix_crc16($payload)));
    $crc = str_pad($crc, 4, '0', STR_PAD_LEFT);

    return $payload . $crc;
}

function rma_pix_crc16(string $payload): int {
    $polynomial = 0x1021;
    $result = 0xFFFF;
    $len = strlen($payload);

    for ($i = 0; $i < $len; $i++) {
        $result ^= (ord($payload[$i]) << 8);
        for ($bit = 0; $bit < 8; $bit++) {
            $result = ($result & 0x8000) ? (($result << 1) ^ $polynomial) : ($result << 1);
            $result &= 0xFFFF;
        }
    }

    return $result;
}

function rma_get_pix_key_value(): string {
    $legacy = trim((string) get_option('rma_pix_key', ''));
    if ($legacy !== '') {
        return $legacy;
    }

    $gateway_settings = get_option('woocommerce_rma_pix_settings', []);
    if (is_array($gateway_settings)) {
        $from_gateway = trim((string) ($gateway_settings['pix_key'] ?? ''));
        if ($from_gateway !== '') {
            return $from_gateway;
        }
    }

    return '';
}

add_filter('woocommerce_checkout_fields', function (array $fields): array {
    if (! rma_contains_annual_dues_product()) {
        return $fields;
    }

    $fields['billing'] = [];
    $fields['shipping'] = [];

    if (isset($fields['order']['order_comments'])) {
        unset($fields['order']['order_comments']);
    }

    return $fields;
}, 999);

add_filter('woocommerce_enable_order_notes_field', function ($enabled) {
    if (rma_contains_annual_dues_product()) {
        return false;
    }
    return $enabled;
});

add_filter('woocommerce_cart_needs_shipping', function ($needs_shipping) {
    if (rma_contains_annual_dues_product()) {
        return false;
    }
    return $needs_shipping;
});

function rma_get_current_user_entity_id(): int {
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return 0;
    }

    if (function_exists('rma_get_entity_id_by_author')) {
        return (int) rma_get_entity_id_by_author($user_id);
    }

    $entity_id = (int) get_posts([
        'post_type' => 'rma_entidade',
        'post_status' => ['publish', 'draft'],
        'author' => $user_id,
        'fields' => 'ids',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
    ])[0] ?? 0;

    return max(0, $entity_id);
}

add_action('woocommerce_checkout_process', function (): void {
    if (! rma_contains_annual_dues_product()) {
        return;
    }

    if (rma_get_current_user_entity_id() <= 0) {
        wc_add_notice('Não foi possível vincular o pagamento a uma entidade RMA. Faça login com a conta da entidade e tente novamente.', 'error');
    }
});

add_action('woocommerce_checkout_create_order', function (WC_Order $order): void {
    if (! rma_contains_annual_dues_product()) {
        return;
    }

    $entity_id = rma_get_current_user_entity_id();
    if ($entity_id > 0) {
        $order->update_meta_data('rma_entity_id', $entity_id);
    }

    $order->update_meta_data('rma_is_annual_due', '1');
    $order->update_meta_data('rma_due_year', gmdate('Y'));
    $order->update_meta_data('rma_due_amount_locked', (string) $order->get_total());
}, 20);


add_action('woocommerce_checkout_order_processed', function (int $order_id): void {
    if (! function_exists('wc_get_order')) {
        return;
    }

    $order = wc_get_order($order_id);
    if (! $order instanceof WC_Order) {
        return;
    }

    if ((string) $order->get_meta('rma_is_annual_due') !== '1') {
        return;
    }

    $locked = (float) $order->get_meta('rma_due_amount_locked');
    if ($locked <= 0) {
        $order->update_meta_data('rma_due_amount_locked', (string) $order->get_total());
        $order->save();
    }
}, 20);

add_action('wp_enqueue_scripts', function (): void {
    if (! function_exists('is_checkout') || ! is_checkout()) {
        return;
    }
    if (! rma_contains_annual_dues_product()) {
        return;
    }

    wp_register_style('rma-woo-checkout-premium', false, [], '1.1.0');
    wp_enqueue_style('rma-woo-checkout-premium');
    wp_add_inline_style('rma-woo-checkout-premium', '
        .woocommerce-checkout{font-family:"Maven Pro",system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#fff!important;padding:0;border-radius:0}.rma-checkout-mode,.rma-checkout-mode .site,.rma-checkout-mode .site-main,.rma-checkout-mode .content-area{background:#fff!important}.rma-checkout-mode .woocommerce-notices-wrapper{display:none!important}.rma-checkout-mode .woocommerce-message .button,.rma-checkout-mode a.wc-forward{display:none!important}.rma-checkout-mode .woocommerce form.checkout{max-width:460px!important;margin:0 auto!important;display:block!important}.rma-checkout-mode .woocommerce form.checkout>.row{display:block!important;margin-left:0!important;margin-right:0!important}.rma-checkout-mode .woocommerce form.checkout>.row>[class*="col-xl-8"],.rma-checkout-mode .woocommerce form.checkout>.row>[class*="col-lg-8"]{display:none!important}.rma-checkout-mode .woocommerce form.checkout>.row>[class*="col-xl-4"],.rma-checkout-mode .woocommerce form.checkout>.row>[class*="col-lg-4"]{flex:0 0 100%!important;max-width:100%!important;width:100%!important;padding-left:0!important;padding-right:0!important}.rma-checkout-mode .woocommerce-checkout .col2-set,.rma-checkout-mode .woocommerce-checkout #customer_details{display:none!important;height:0!important;margin:0!important;padding:0!important;overflow:hidden!important}.rma-checkout-mode .woocommerce-checkout #order_review_heading{display:none!important}.rma-checkout-mode .woocommerce-checkout #order_review,.rma-checkout-mode .woocommerce-checkout .woocommerce-checkout-review-order,.rma-checkout-mode .woocommerce-checkout .woocommerce-checkout-review-order-table{float:none!important;width:100%!important;max-width:460px!important;margin:0 auto!important;display:block!important}
        .woocommerce-checkout .col2-set,.woocommerce-checkout #customer_details{display:none!important}
        .woocommerce-checkout #order_review_heading{display:none!important}.woocommerce-checkout #order_review{float:none!important;width:100%!important;max-width:100%!important;margin:0 auto!important}
        .woocommerce-checkout #order_review{display:grid;gap:8px}
        .woocommerce-checkout #payment{background:#ffffff;border:1px solid #e5e7eb;border-radius:20px;padding:18px;box-shadow:0 16px 40px rgba(15,23,42,.09);width:100%!important;max-width:100%!important;margin:0 auto!important}.woocommerce-checkout #order_review{background:#fff;border:1px solid #edf1f4;border-radius:18px;padding:14px;box-shadow:0 12px 28px rgba(0,0,0,.05)}
        .woocommerce-checkout h3,.woocommerce-checkout h2{color:#1f2937;letter-spacing:-.02em}
        .woocommerce-checkout-review-order-table{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 10px 26px rgba(15,23,42,.05)}
        .woocommerce-checkout-payment ul.payment_methods{padding:0!important;border:none!important;background:transparent!important}
        .woocommerce-checkout-payment .payment_method_rma_pix>label{font-weight:800;color:#1f2937;font-size:1.05rem}
        /* WooCommerce Blocks checkout minimization (logged-in entity dues flow) */
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-checkout__contact-fields,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-checkout__billing-fields,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-checkout__shipping-fields,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-checkout__shipping-fields-block,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-components-address-form,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-checkout__create-account,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-checkout__add-note,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-components-express-payment,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-components-checkout-return-to-cart-button {display:none!important}
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-components-sidebar,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-checkout__totals,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-checkout__payment-method,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-components-totals-wrapper {max-width:460px!important;margin:0 auto!important}
        .rma-pix-card{background:#fff;border:1px solid #d9e3dc;border-radius:20px;padding:14px;box-shadow:0 14px 34px rgba(15,23,42,.08);margin:0 auto;max-width:520px}
        .rma-pix-hero{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}
        .rma-pix-title{font-size:1.7rem;line-height:1.2;margin:0;color:#1f2937}
        .rma-pix-subtitle{margin:0;color:#4b5563}
        .rma-pix-badge{background:rgba(93,218,187,.16);color:#0f766e;border:1px solid rgba(15,118,110,.18);border-radius:999px;padding:6px 12px;font-size:.78rem;font-weight:700;white-space:nowrap}
        .rma-pix-grid{display:grid;grid-template-columns:1fr;gap:12px;margin-top:12px;align-items:start}
        .rma-pix-panel{background:#f9fbfd;border:1px solid #e5e7eb;border-radius:16px;padding:16px}
        .rma-pix-panel h4{margin:0 0 8px;color:#1f2937;font-size:1.02rem}
        .rma-pix-qr-wrap{text-align:center}
        .rma-pix-qr{max-width:170px;width:100%;background:#fff;border:10px solid #fff;box-shadow:0 14px 30px rgba(15,23,42,.16);border-radius:14px}
        .rma-pix-copy{width:100%;padding:9px;border:1px solid #cbd5e1;border-radius:10px;color:#111827;background:#fff;font-size:.92rem;margin:8px 0 10px;min-height:92px;resize:vertical;line-height:1.35}
        .rma-pix-copy-btn{background:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;border:none;border-radius:12px;padding:9px 12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
        .rma-pix-copy-btn:hover{filter:brightness(1.03);transform:translateY(-1px)}
        .rma-pix-copy-status{font-size:.9rem;color:#047857;margin-top:8px;display:none}
        .rma-pix-copy-status.is-visible{display:block}
        .rma-pix-steps{margin:12px 0 0;padding-left:18px;color:#4b5563}
        .rma-pix-steps li{margin:6px 0}
        .rma-pix-warning{font-size:.9rem;color:#6b7280;margin-top:10px}
        .rma-pix-qr-fallback{display:none;color:#4b5563;font-size:.9rem;margin-top:8px}
        .rma-pix-qr-fallback.is-visible{display:block}
        /* Glass white futuristic (iOS-like) skin */
        .rma-checkout-mode .woocommerce,
        .rma-checkout-mode .woocommerce-checkout,
        .rma-checkout-mode .wp-block-woocommerce-checkout{position:relative}
        .rma-checkout-mode .woocommerce::before,
        .rma-checkout-mode .wp-block-woocommerce-checkout::before{content:"";position:fixed;inset:0;pointer-events:none;background:
            radial-gradient(1200px 540px at 10% -10%, rgba(255,255,255,.88), rgba(255,255,255,.2) 48%, rgba(255,255,255,0) 70%),
            radial-gradient(900px 420px at 95% 0%, rgba(183,213,255,.26), rgba(183,213,255,0) 60%),
            radial-gradient(700px 360px at 50% 100%, rgba(196,255,242,.22), rgba(196,255,242,0) 65%);
            z-index:0}
        .rma-checkout-mode .woocommerce form.checkout,
        .rma-checkout-mode .woocommerce-checkout #order_review,
        .rma-checkout-mode .woocommerce-checkout #payment,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-components-sidebar,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-checkout__totals,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-checkout__payment-method,
        .rma-checkout-mode .rma-pix-card{
            position:relative;z-index:1;
            max-width:520px!important;
            background:linear-gradient(145deg, rgba(255,255,255,.82), rgba(255,255,255,.58))!important;
            border:1px solid rgba(255,255,255,.72)!important;
            box-shadow:0 30px 70px rgba(15,23,42,.12), inset 0 1px 0 rgba(255,255,255,.8)!important;
            backdrop-filter: blur(16px) saturate(150%);
            -webkit-backdrop-filter: blur(16px) saturate(150%);
            border-radius:24px!important;
        }
        .rma-checkout-mode .woocommerce-checkout-review-order-table,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-components-order-summary,
        .rma-checkout-mode .wp-block-woocommerce-checkout .wc-block-components-totals-wrapper{
            background:rgba(255,255,255,.62)!important;
            border:1px solid rgba(255,255,255,.7)!important;
            border-radius:18px!important;
            backdrop-filter: blur(14px) saturate(145%);
            -webkit-backdrop-filter: blur(14px) saturate(145%);
        }
        .rma-checkout-mode .woocommerce-checkout #payment button,
        .rma-checkout-mode .woocommerce-checkout .button,
        .rma-checkout-mode .wp-block-woocommerce-checkout button,
        .rma-checkout-mode .rma-pix-copy-btn{
            background:linear-gradient(135deg, #ffffff 0%, #f4f8ff 55%, #e9f8ff 100%)!important;
            color:#0f172a!important;
            border:1px solid rgba(148,163,184,.35)!important;
            box-shadow:0 10px 22px rgba(15,23,42,.12), inset 0 1px 0 rgba(255,255,255,.85)!important;
            border-radius:14px!important;
            font-weight:700!important;
        }
        .rma-checkout-mode .rma-pix-badge,
        .rma-checkout-mode .wc-block-components-notice-banner,
        .rma-checkout-mode .woocommerce-info{background:rgba(255,255,255,.62)!important;border-color:rgba(148,163,184,.25)!important;color:#0f172a!important}
        .rma-checkout-mode .rma-pix-title,
        .rma-checkout-mode .woocommerce-checkout h2,
        .rma-checkout-mode .woocommerce-checkout h3{letter-spacing:-.02em}
        @media (max-width:900px){.rma-pix-grid{grid-template-columns:1fr}.rma-pix-hero{flex-direction:column;align-items:flex-start}.rma-pix-title{font-size:1.2rem}.rma-pix-card{padding:12px;max-width:96%!important}.woocommerce-checkout{padding:0}}
        .rma-checkout-mode .rma-pix-card--neo{overflow:hidden;isolation:isolate}
        .rma-checkout-mode .rma-pix-orb{position:absolute;border-radius:999px;filter:blur(2px);pointer-events:none;z-index:0;opacity:.7}
        .rma-checkout-mode .rma-pix-orb--a{width:180px;height:180px;right:-50px;top:-40px;background:radial-gradient(circle at 30% 30%, rgba(255,255,255,.95), rgba(219,234,254,.6) 45%, rgba(255,255,255,0) 72%)}
        .rma-checkout-mode .rma-pix-orb--b{width:150px;height:150px;left:-45px;bottom:-35px;background:radial-gradient(circle at 70% 40%, rgba(255,255,255,.95), rgba(209,250,229,.55) 50%, rgba(255,255,255,0) 72%)}
        .rma-checkout-mode .rma-pix-hero,.rma-checkout-mode .rma-pix-grid,.rma-checkout-mode .rma-pix-meta,.rma-checkout-mode .rma-pix-success,.rma-checkout-mode .rma-pix-progress{position:relative;z-index:1}
        .rma-checkout-mode .rma-pix-meta{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 8px}
        .rma-checkout-mode .rma-pix-chip{padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.72);border:1px solid rgba(203,213,225,.65);font-size:.72rem;color:#475569;font-weight:700}
        .rma-checkout-mode .rma-pix-success{margin-top:10px;padding:9px 11px;border-radius:12px;background:rgba(236,253,245,.85);color:#166534;font-weight:700;border:1px solid rgba(134,239,172,.5)}
        .rma-checkout-mode .rma-pix-progress{height:6px;margin:10px 0 2px;border-radius:999px;background:rgba(255,255,255,.7);border:1px solid rgba(203,213,225,.45);overflow:hidden}
        .rma-checkout-mode .rma-pix-progress>span{display:block;height:100%;width:55%;background:linear-gradient(90deg, rgba(186,230,253,.95), rgba(220,252,231,.95), rgba(191,219,254,.95));animation:rma-pix-flow 2.6s linear infinite}
        @keyframes rma-pix-flow{0%{transform:translateX(-70%)}100%{transform:translateX(190%)}}
        .rma-checkout-mode .rma-pix-panel{background:rgba(255,255,255,.66)!important;border:1px solid rgba(226,232,240,.85)!important;border-radius:14px!important}
        .rma-checkout-mode .rma-pix-copy{background:rgba(255,255,255,.9)!important}
        .rma-checkout-mode .rma-pix-warning{color:#64748b!important}
        .rma-checkout-mode .rma-pix-copy-btn:active{transform:translateY(0)!important}
    ');

    wp_add_inline_style('rma-woo-checkout-premium', '
        /* Layout refinement: wider desktop canvas + compact content */
        .rma-checkout-mode .woocommerce,
        .rma-checkout-mode .woocommerce-checkout,
        .rma-checkout-mode .wp-block-woocommerce-checkout{max-width:1400px!important;margin:0 auto!important;padding-inline:18px!important}
        .rma-checkout-mode .woocommerce form.checkout{max-width:1400px!important}
        .rma-checkout-mode .woocommerce-checkout #order_review{max-width:1400px!important;display:grid!important;grid-template-columns:minmax(360px,520px) minmax(400px,560px)!important;gap:28px!important;justify-content:center!important;align-items:start!important;position:relative}
        .rma-checkout-mode .woocommerce-checkout #order_review::before{content:"";position:absolute;left:50%;top:12px;bottom:12px;width:1px;background:linear-gradient(180deg, rgba(203,213,225,.15), rgba(148,163,184,.35), rgba(203,213,225,.15));transform:translateX(-14px)}
        .rma-checkout-mode .woocommerce-checkout .woocommerce-checkout-review-order-table{margin:0!important;height:auto!important;max-width:520px!important;justify-self:end!important}
        .rma-checkout-mode .woocommerce-checkout #payment{margin:0!important;padding:12px!important;max-width:560px!important;justify-self:start!important}
        .rma-checkout-mode .rma-pix-card{max-width:100%!important;padding:10px!important;border-radius:16px!important}
        .rma-checkout-mode .rma-pix-title{font-size:1.15rem!important}
        .rma-checkout-mode .rma-pix-subtitle,
        .rma-checkout-mode .rma-pix-steps li,
        .rma-checkout-mode .rma-pix-warning,
        .rma-checkout-mode .woocommerce-checkout,
        .rma-checkout-mode .woocommerce-checkout table,
        .rma-checkout-mode .woocommerce-checkout p,
        .rma-checkout-mode .woocommerce-checkout label{font-size:.88rem!important;line-height:1.4!important}
        .rma-checkout-mode .rma-pix-qr{max-width:220px!important;padding:6px!important}
        .rma-checkout-mode .rma-pix-copy{min-height:72px!important;font-size:.82rem!important;padding:8px!important}
        .rma-checkout-mode .rma-pix-copy-btn,
        .rma-checkout-mode .woocommerce-checkout #payment button,
        .rma-checkout-mode .woocommerce-checkout .button{padding:10px 14px!important;font-size:.9rem!important}
        .rma-checkout-mode .woocommerce-checkout #payment ul.payment_methods{margin:0 0 8px!important;padding:0!important;border:0!important;background:transparent!important}
        .rma-checkout-mode .woocommerce-checkout #payment ul.payment_methods>li{list-style:none!important;margin:0!important;padding:0!important;border:0!important;background:transparent!important}
        .rma-checkout-mode .woocommerce-checkout #payment .payment_method_rma_pix>input{display:none!important}
        .rma-checkout-mode .woocommerce-checkout #payment .payment_method_rma_pix>label{display:none!important}
        .rma-checkout-mode .woocommerce-checkout #payment .payment_method_rma_pix .payment_box{display:block!important}
        .rma-checkout-mode .woocommerce-checkout .place-order .woocommerce-privacy-policy-text,
        .rma-checkout-mode .woocommerce-checkout .place-order .form-row.terms,
        .rma-checkout-mode .woocommerce-checkout .woocommerce-terms-and-conditions-wrapper{display:none!important}
        .rma-checkout-mode .woocommerce-checkout #place_order{width:100%!important;border-radius:12px!important;font-size:1rem!important;padding:12px 14px!important;background:#22c55e!important;border-color:#22c55e!important;color:#fff!important;box-shadow:none!important}
        .rma-checkout-mode .woocommerce-checkout #payment .place-order{padding-top:10px!important}
        .rma-checkout-mode .page-breadcrumb,.rma-checkout-mode .breadcrumb-area,.rma-checkout-mode .page-title,.rma-checkout-mode .page-header,.rma-checkout-mode .woocommerce-breadcrumb,.rma-checkout-mode .entry-title{display:none!important}
        .rma-checkout-mode .rma-pix-card--clean{background:transparent!important;border:none!important;box-shadow:none!important;padding:0!important}
        .rma-checkout-mode .rma-pix-surface{background:linear-gradient(145deg, rgba(255,255,255,.95), rgba(248,250,252,.92));border:1px solid rgba(226,232,240,.95);border-radius:16px;padding:14px}
        .rma-checkout-mode .rma-pix-top-alert{display:flex;align-items:flex-start;gap:10px;background:rgba(236,253,245,.9);border:1px solid rgba(167,243,208,.8);color:#14532d;border-radius:14px;padding:12px;margin-bottom:12px;font-size:.94rem}
        .rma-checkout-mode .rma-pix-top-alert svg{flex:0 0 auto;margin-top:1px}
        .rma-checkout-mode .rma-pix-surface h4{margin:0 0 10px;font-size:1.9rem;font-weight:700;letter-spacing:-.01em}
        .rma-checkout-mode .rma-pix-copy-row{display:flex;gap:8px;align-items:flex-end}
        .rma-checkout-mode .rma-pix-copy-row .rma-pix-copy{flex:1;min-height:66px!important}
        .rma-checkout-mode .rma-pix-qr{max-width:300px!important;width:100%!important}
        .rma-checkout-mode .rma-summary-card{background:linear-gradient(165deg, rgba(255,255,255,.96), rgba(247,250,252,.92));border:1px solid rgba(226,232,240,.95);border-radius:18px;padding:18px;box-shadow:0 18px 42px rgba(15,23,42,.08)}
        .rma-checkout-mode .rma-summary-title{font-size:1.2rem;line-height:1.2;letter-spacing:-.02em;margin:0 0 12px;color:#0f172a}
        .rma-checkout-mode .rma-summary-card .shop_table{margin:0!important;border-collapse:separate;border-spacing:0}
        .rma-checkout-mode .rma-summary-card .shop_table th,.rma-checkout-mode .rma-summary-card .shop_table td{padding:12px 10px!important;font-size:1.02rem!important;vertical-align:middle;border-color:rgba(148,163,184,.22)!important}
        .rma-checkout-mode .rma-summary-card .shop_table tr:last-child th,.rma-checkout-mode .rma-summary-card .shop_table tr:last-child td{border-bottom:0!important}
        .rma-checkout-mode .rma-summary-card .shop_table th:last-child,.rma-checkout-mode .rma-summary-card .shop_table td:last-child{text-align:right!important;font-variant-numeric:tabular-nums;font-weight:700;color:#111827}
        .rma-checkout-mode .rma-summary-safe{display:flex;gap:10px;align-items:flex-start;background:rgba(236,253,245,.9);border:1px solid rgba(167,243,208,.85);border-radius:14px;padding:12px;margin-top:14px;color:#14532d}
        .rma-checkout-mode .rma-summary-total{margin-top:12px;background:rgba(255,255,255,.9);border:1px solid rgba(226,232,240,.95);border-radius:14px;padding:12px}
        .rma-checkout-mode .rma-summary-total strong{font-size:2rem;color:#111827;font-variant-numeric:tabular-nums}
        .rma-checkout-mode .rma-summary-meta{display:flex;gap:14px;align-items:center;margin-top:10px;color:#64748b;font-size:.95rem}
        .rma-checkout-mode .rma-summary-gif{display:block;margin-top:14px;border-radius:12px;width:100%;max-width:340px;opacity:.95}

        /* Replace default Woo payment box color with iOS-like white glass */
        #add_payment_method #payment div.payment_box,
        .woocommerce-cart #payment div.payment_box,
        .woocommerce-checkout #payment div.payment_box,
        .rma-checkout-mode .woocommerce-checkout #payment div.payment_box{
            position:relative;
            box-sizing:border-box;
            width:100%;
            padding:.85em .95em;
            margin:.75em 0 0;
            font-size:.88em;
            line-height:1.45;
            border-radius:14px;
            border:1px solid rgba(226,232,240,.95)!important;
            color:#334155!important;
            background:linear-gradient(145deg, rgba(255,255,255,.94), rgba(248,250,252,.92))!important;
            backdrop-filter:blur(10px) saturate(130%);
            -webkit-backdrop-filter:blur(10px) saturate(130%);
            box-shadow:0 8px 22px rgba(15,23,42,.07), inset 0 1px 0 rgba(255,255,255,.75);
        }

        @media (max-width:1200px){
            .rma-checkout-mode .woocommerce,
            .rma-checkout-mode .woocommerce-checkout,
            .rma-checkout-mode .wp-block-woocommerce-checkout{max-width:100%!important;padding-inline:12px!important}
            .rma-checkout-mode .woocommerce form.checkout,
            .rma-checkout-mode .woocommerce-checkout #order_review{max-width:100%!important;grid-template-columns:minmax(0,1fr)!important;gap:14px!important}
            .rma-checkout-mode .woocommerce-checkout #order_review::before{display:none!important}
        }
    ');
}, 30);

add_action('wp_footer', function (): void {
    if (! function_exists('is_checkout') || ! is_checkout()) {
        return;
    }

    if (! rma_contains_annual_dues_product()) {
        return;
    }

    $theme_gif_url = esc_url(trailingslashit(get_template_directory_uri()) . 'images/pagamentos.gif');
    $theme_gif_fallback_url = esc_url(trailingslashit(get_template_directory_uri()) . 'images/pagamento.gif');

    $script = <<<'JS'
<script>
(function(){
  function forcePix(){
    var method=document.querySelector('input[name="payment_method"][value="rma_pix"]');
    if(method && !method.checked){
      method.checked=true;
      method.dispatchEvent(new Event('change',{bubbles:true}));
      if(window.jQuery){ window.jQuery(document.body).trigger('update_checkout'); }
    }
    var box=document.querySelector('#payment .payment_method_rma_pix .payment_box');
    if(box){ box.style.display='block'; box.style.visibility='visible'; box.style.opacity='1'; }
  }

  function enhanceSummary(){
    var review=document.querySelector('.rma-checkout-mode .woocommerce-checkout-review-order-table');
    if(!review || review.closest('.rma-summary-card')){ return; }

    var card=document.createElement('div');
    card.className='rma-summary-card';
    card.innerHTML=''
      +'<h3 class="rma-summary-title">Pagamento da Anuidade RMA</h3>';

    review.parentNode.insertBefore(card, review);
    card.appendChild(review);

    var totalEl=review.querySelector('.order-total .amount, tfoot tr:last-child .amount');
    var totalText=totalEl ? totalEl.textContent.trim() : '';

    var safe=document.createElement('div');
    safe.className='rma-summary-safe';
    safe.innerHTML='<span>✅</span><div><strong>Pagamento seguro via PIX</strong></div>';
    card.appendChild(safe);

    var total=document.createElement('div');
    total.className='rma-summary-total';
    total.innerHTML='<div>Total</div><strong>'+totalText+'</strong><div class="rma-summary-meta"><span>⏱️ Expira em: 15:00</span><span>🔒 Pagamento protegido • Criptografia SSL</span></div>';
    card.appendChild(total);

    var gif=document.createElement('img');
    gif.className='rma-summary-gif';
    gif.alt='Pagamento via PIX';
    gif.loading='lazy';

    var gifSources=[
      '__RMA_THEME_GIF__',
      '__RMA_THEME_GIF_FALLBACK__'
    ];
    var gifIdx=0;
    var tryNextGif=function(){
      if(gifIdx>=gifSources.length){ gif.style.display='none'; return; }
      gif.src=gifSources[gifIdx++];
    };
    gif.onerror=tryNextGif;
    tryNextGif();
    card.appendChild(gif);
  }

  function run(){ forcePix(); enhanceSummary(); }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded',run); } else { run(); }
  setTimeout(run,500); setTimeout(run,1200); setTimeout(run,2200);
})();
</script>
JS;
    echo str_replace(
        ['__RMA_THEME_GIF__', '__RMA_THEME_GIF_FALLBACK__'],
        [esc_js($theme_gif_url), esc_js($theme_gif_fallback_url)],
        $script
    );
}, 99);

add_filter('render_block', function (string $block_content, array $block): string {
    if (is_admin() || ! function_exists('is_checkout') || ! is_checkout()) {
        return $block_content;
    }

    $block_name = (string) ($block['blockName'] ?? '');
    if ($block_name !== 'woocommerce/checkout') {
        return $block_content;
    }

    if (! rma_contains_annual_dues_product()) {
        return $block_content;
    }

    if (! function_exists('do_shortcode')) {
        return $block_content;
    }

    return do_shortcode('[woocommerce_checkout]');
}, 20, 2);

add_filter('woocommerce_payment_gateways', function (array $gateways): array {
    if (class_exists('WC_Payment_Gateway')) {
        $gateways[] = 'RMA_WC_Gateway_PIX';
    }

    return $gateways;
});

add_action('plugins_loaded', function (): void {
    if (! class_exists('WC_Payment_Gateway')) {
        return;
    }

    class RMA_WC_Gateway_PIX extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'rma_pix';
            $this->method_title = 'PIX RMA';
            $this->method_description = 'Pagamento institucional via PIX para Anuidade RMA.';
            $this->has_fields = true;
            $this->supports = ['products'];

            $this->init_form_fields();
            $this->init_settings();

            $this->enabled = (string) $this->get_option('enabled', 'yes');
            $this->title = (string) $this->get_option('title', 'PIX institucional RMA');
            $this->description = (string) $this->get_option('description', 'Finalize sua filiação com segurança via PIX.');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        }

        public function init_form_fields(): void {
            $this->form_fields = [
                'enabled' => [
                    'title' => 'Ativar/Desativar',
                    'type' => 'checkbox',
                    'label' => 'Ativar PIX RMA',
                    'default' => 'yes',
                ],
                'title' => [
                    'title' => 'Título',
                    'type' => 'text',
                    'default' => 'PIX institucional RMA',
                ],
                'description' => [
                    'title' => 'Descrição',
                    'type' => 'textarea',
                    'default' => 'Finalize sua filiação com segurança via PIX.',
                ],
                'pix_key' => [
                    'title' => 'Chave PIX',
                    'type' => 'text',
                    'description' => 'Chave PIX usada para gerar QR Code e código Copia e Cola.',
                    'default' => '',
                ],
            ];
        }

        public function process_admin_options() {
            parent::process_admin_options();

            $pix_key = trim((string) $this->get_option('pix_key', ''));
            if ($pix_key !== '') {
                update_option('rma_pix_key', $pix_key);
            }
        }

        public function is_available() {
            if (! parent::is_available()) {
                return false;
            }

            if (! rma_contains_annual_dues_product()) {
                return false;
            }

            $pix_key = rma_get_pix_key_value();
            return $pix_key !== '';
        }

        public function payment_fields() {
            $pix_key = rma_get_pix_key_value();
            if ($pix_key === '') {
                echo '<p><strong>Pagamento PIX indisponível no momento.</strong> Entre em contato com a equipe RMA.</p>';
                return;
            }

            $cart_total = function_exists('WC') && WC()->cart ? (float) WC()->cart->total : 0;
            $payload = rma_pix_build_payload($pix_key, (string) $cart_total, 'RMA-CHECKOUT');
            $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=420x420&data=' . rawurlencode($payload);

            echo '<div class="rma-pix-card rma-pix-card--clean">';
            echo '<div class="rma-pix-top-alert"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="10" fill="#22C55E"/><path d="M6 10.5L8.6 13L14 7.5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><div><strong>Anuidade adicionada</strong><br/>Finalize o pagamento via PIX para liberar seu acesso automaticamente.</div></div>';
            echo '<div class="rma-pix-surface">';
            echo '<h4>Escaneie para pagar</h4>';
            echo '<div class="rma-pix-qr-wrap">';
            echo '<img class="rma-pix-qr" src="' . esc_url($qr_url) . '" alt="QR Code PIX" onerror="var n=this.nextElementSibling;this.style.display=\'none\';if(n){n.classList.add(\'is-visible\');}" />';
            echo '<div class="rma-pix-qr-fallback">Não foi possível carregar o QR Code agora. Use o código copia e cola abaixo.</div>';
            echo '</div>';
            echo '<ol class="rma-pix-steps"><li>Abra o app do seu banco</li><li>Escolha PIX &gt; QR Code ou Copiar e Colar</li><li>Confirme o pagamento e finalize o pedido</li></ol>';
            echo '<div class="rma-pix-copy-row">';
            echo '<textarea readonly class="rma-pix-copy" id="rma-pix-copy-code">' . esc_textarea($payload) . '</textarea>';
            echo '<button type="button" class="rma-pix-copy-btn" id="rma-pix-copy-btn">Copiar código PIX</button>';
            echo '</div>';
            echo '<div class="rma-pix-copy-status" id="rma-pix-copy-status">Código PIX copiado com sucesso.</div>';
            echo '</div>';
            echo '</div>';
            echo '<script>(function(){var b=document.getElementById("rma-pix-copy-btn");var i=document.getElementById("rma-pix-copy-code");var s=document.getElementById("rma-pix-copy-status");if(!b||!i){return;}function ok(msg){if(!s){return;}s.textContent=msg||"Código PIX copiado com sucesso.";s.classList.add("is-visible");setTimeout(function(){s.classList.remove("is-visible");},2200);}b.addEventListener("click",function(){var v=i.value||"";if(!v){return;}if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(v).then(function(){ok();});return;}i.focus();i.select();i.setSelectionRange(0,99999);try{document.execCommand("copy");ok();}catch(e){}});i.addEventListener("focus",function(){this.select();});})();</script>';
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            if (! $order) {
                return ['result' => 'fail'];
            }

            $entity_id = (int) $order->get_meta('rma_entity_id');
            if ($entity_id <= 0) {
                $entity_id = rma_get_current_user_entity_id();
                if ($entity_id > 0) {
                    $order->update_meta_data('rma_entity_id', $entity_id);
                    $order->save();
                }
            }

            $order->update_status('on-hold', 'Aguardando pagamento PIX da Anuidade RMA.');
            $order->add_order_note('Pedido criado via PIX institucional RMA.');
            wc_reduce_stock_levels($order_id);
            if (function_exists('WC') && WC()->cart) {
                WC()->cart->empty_cart();
            }

            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }
    }
});

add_action('woocommerce_thankyou_rma_pix', function (int $order_id): void {
    $order = wc_get_order($order_id);
    if (! $order instanceof WC_Order) {
        return;
    }

    $pix_key = rma_get_pix_key_value();
    if ($pix_key === '') {
        wc_print_notice('Pagamento PIX indisponível no momento. Entre em contato com a equipe RMA.', 'notice');
        return;
    }

    $total = (string) $order->get_total();
    $payload = rma_pix_build_payload($pix_key, $total, 'RMAORDER' . $order->get_id());
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=420x420&data=' . rawurlencode($payload);
    $poll_endpoint = rest_url('rma/v1/orders/' . $order->get_id() . '/pix-status');

    echo '<section class="rma-pix-card" style="margin-top:20px">';
    echo '<h2 style="margin-top:0;color:#1f2937">Pagamento da Anuidade RMA</h2>';
    echo '<p style="color:#4b5563">Seu pedido está em <strong>aguardando pagamento</strong>. Escaneie o QR Code ou copie o código PIX abaixo.</p>';
    echo '<div id="rma-pix-order-status" style="display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:999px;background:rgba(250,204,21,.15);color:#92400e;font-weight:700;margin:6px 0 10px">Aguardando compensação PIX • atualização automática a cada 3s</div>';
    echo '<img class="rma-pix-qr" src="' . esc_url($qr_url) . '" alt="QR Code PIX do pedido" onerror="this.style.display=\'none\'" />';
    echo '<textarea readonly class="rma-pix-copy" id="rma-pix-order-copy-code">' . esc_textarea($payload) . '</textarea>';
    echo '<button type="button" class="rma-pix-copy-btn" id="rma-pix-order-copy-btn">Copiar código PIX</button>';
    echo '<div class="rma-pix-copy-status" id="rma-pix-order-copy-status">Copiado com sucesso.</div>';
    echo '<p style="color:#6b7280;font-size:.9rem">Assim que houver compensação, seu acesso RMA será liberado automaticamente.</p>';
    echo '</section>';

    echo '<script>(function(){'
        . 'var orderKey=' . wp_json_encode((string) $order->get_order_key()) . ';'
        . 'var statusUrl=' . wp_json_encode((string) $poll_endpoint) . ';'
        . 'var receivedUrl=' . wp_json_encode((string) $order->get_checkout_order_received_url()) . ';'
        . 'var copyBtn=document.getElementById("rma-pix-order-copy-btn");'
        . 'var copyInput=document.getElementById("rma-pix-order-copy-code");'
        . 'var copyStatus=document.getElementById("rma-pix-order-copy-status");'
        . 'var statusPill=document.getElementById("rma-pix-order-status");'
        . 'if(copyBtn&&copyInput){copyBtn.addEventListener("click",function(){var v=copyInput.value||"";if(!v){return;}if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(v).then(function(){copyStatus&&copyStatus.classList.add("is-visible");});return;}copyInput.select();copyInput.setSelectionRange(0,99999);document.execCommand("copy");copyStatus&&copyStatus.classList.add("is-visible");});}'
        . 'function setStatus(text,bg,color){if(!statusPill){return;}statusPill.textContent=text;statusPill.style.background=bg;statusPill.style.color=color;}'
        . 'function poll(){fetch(statusUrl+"?key="+encodeURIComponent(orderKey),{credentials:"same-origin"}).then(function(r){return r.json();}).then(function(data){if(!data||!data.status){return;}if(data.is_paid){setStatus("Pagamento confirmado • redirecionando para pedido confirmado", "rgba(34,197,94,.16)", "#166534");setTimeout(function(){window.location.assign(data.received_url||receivedUrl);},1100);return;}if(data.status==="failed"||data.status==="cancelled"||data.status==="refunded"){setStatus("Pagamento não confirmado ("+data.status+")", "rgba(239,68,68,.14)", "#991b1b");return;}setStatus("Aguardando compensação PIX • atualização automática a cada 3s", "rgba(250,204,21,.15)", "#92400e");}).catch(function(){});}'
        . 'poll();setInterval(poll,3000);'
        . '})();</script>';
});

function rma_get_entity_id_for_current_user_checkout_flow(): int {
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return 0;
    }

    if (function_exists('rma_get_entity_id_by_author')) {
        return max(0, (int) rma_get_entity_id_by_author($user_id));
    }

    $entity_id = (int) get_posts([
        'post_type' => 'rma_entidade',
        'post_status' => ['publish', 'draft'],
        'author' => $user_id,
        'fields' => 'ids',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
    ])[0] ?? 0;

    return max(0, $entity_id);
}

function rma_entity_has_started_or_completed_dues_checkout(int $entity_id): bool {
    if ($entity_id <= 0 || ! function_exists('wc_get_orders')) {
        return false;
    }

    $orders = wc_get_orders([
        'limit' => 1,
        'return' => 'ids',
        'meta_query' => [
            [
                'key' => 'rma_entity_id',
                'value' => $entity_id,
            ],
            [
                'key' => 'rma_is_annual_due',
                'value' => '1',
            ],
        ],
        'status' => ['pending', 'on-hold', 'processing', 'completed'],
    ]);

    return ! empty($orders);
}

function rma_entity_can_leave_activation_for_dashboard(int $entity_id): bool {
    if ($entity_id <= 0) {
        return false;
    }

    if (! rma_entity_has_started_or_completed_dues_checkout($entity_id)) {
        return false;
    }

    if (function_exists('rma_entity_has_completed_activation_flow')) {
        return (bool) rma_entity_has_completed_activation_flow($entity_id);
    }

    $governance = (string) get_post_meta($entity_id, 'governance_status', true);
    $finance = (string) get_post_meta($entity_id, 'finance_status', true);
    $docs_status = (string) get_post_meta($entity_id, 'documentos_status', true);

    return $governance === 'aprovado'
        && $finance === 'adimplente'
        && in_array($docs_status, ['enviado', 'aprovado', 'validado', 'aceito'], true);
}

add_action('template_redirect', function (): void {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (! is_user_logged_in()) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $request_path = wp_parse_url($request_uri, PHP_URL_PATH);
    $request_path = is_string($request_path) ? untrailingslashit($request_path) : '';
    if ($request_path === '') {
        return;
    }

    $activation_path = function_exists('rma_account_setup_path')
        ? (string) rma_account_setup_path()
        : (string) untrailingslashit((string) wp_parse_url(home_url('/conta/'), PHP_URL_PATH));

    if ($activation_path === '' || $request_path !== $activation_path) {
        return;
    }

    $entity_id = rma_get_entity_id_for_current_user_checkout_flow();
    if (! rma_entity_can_leave_activation_for_dashboard($entity_id)) {
        rma_sync_debug_log('activation_page_allow', [
            'request_path' => $request_path,
            'entity_id' => $entity_id,
            'reason' => 'activation_not_completed',
        ]);
        return;
    }

    rma_sync_debug_log('activation_page_redirect_dashboard', [
        'request_path' => $request_path,
        'entity_id' => $entity_id,
    ]);
    wp_safe_redirect(home_url('/dashboard/'));
    exit;
}, 30);




function rma_plugin_get_entity_id_by_author(int $user_id): int {
    if ($user_id <= 0) {
        return 0;
    }

    if (function_exists('rma_get_entity_id_by_author')) {
        return max(0, (int) rma_get_entity_id_by_author($user_id));
    }

    $ids = get_posts([
        'post_type' => 'rma_entidade',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'author' => $user_id,
        'fields' => 'ids',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    return max(0, (int) ($ids[0] ?? 0));
}

function rma_plugin_get_employer_post_id_by_author(int $user_id): int {
    if ($user_id <= 0) {
        return 0;
    }

    $ids = get_posts([
        'post_type' => 'employer',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'author' => $user_id,
        'fields' => 'ids',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    return max(0, (int) ($ids[0] ?? 0));
}

function rma_plugin_is_valid_logo_attachment(int $attachment_id): bool {
    if ($attachment_id <= 0 || ! wp_attachment_is_image($attachment_id)) {
        return false;
    }

    $url = strtolower((string) wp_get_attachment_url($attachment_id));
    if ($url === '') {
        return false;
    }

    return (strpos($url, 'emp_default') === false && strpos($url, 'default-avatar') === false);
}

function rma_plugin_entity_has_logo(int $entity_id, int $user_id): bool {
    $entity_logo = (int) get_post_meta($entity_id, '_profile_pic_attachment_id', true);
    if (rma_plugin_is_valid_logo_attachment($entity_logo)) {
        return true;
    }

    $employer_id = rma_plugin_get_employer_post_id_by_author($user_id);
    if ($employer_id > 0) {
        $employer_logo = (int) get_post_meta($employer_id, '_profile_pic_attachment_id', true);
        if (rma_plugin_is_valid_logo_attachment($employer_logo)) {
            return true;
        }
    }

    return false;
}

add_action('wp_ajax_rma_required_logo_upload', function (): void {
    if (! is_user_logged_in()) {
        wp_send_json_error(['message' => 'Sessão expirada. Faça login novamente.'], 401);
    }

    check_ajax_referer('rma_required_logo_upload', 'nonce');

    $user_id = get_current_user_id();
    $entity_id = rma_plugin_get_entity_id_by_author($user_id);
    if ($entity_id <= 0) {
        wp_send_json_error(['message' => 'Entidade não encontrada para este usuário.'], 400);
    }

    if (empty($_FILES['logo']) || ! is_array($_FILES['logo'])) {
        wp_send_json_error(['message' => 'Selecione uma imagem de logotipo para continuar.'], 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $uploaded = wp_handle_upload($_FILES['logo'], ['test_form' => false, 'mimes' => [
        'jpg|jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ]]);

    if (! is_array($uploaded) || ! empty($uploaded['error']) || empty($uploaded['file'])) {
        wp_send_json_error(['message' => (string) ($uploaded['error'] ?? 'Falha no upload do logotipo.')], 400);
    }

    $file = (string) $uploaded['file'];
    $type = wp_check_filetype($file, null);
    $attachment = [
        'post_mime_type' => (string) ($type['type'] ?? 'image/jpeg'),
        'post_title' => sanitize_file_name(pathinfo($file, PATHINFO_FILENAME)),
        'post_content' => '',
        'post_status' => 'inherit',
    ];

    $attachment_id = wp_insert_attachment($attachment, $file, $entity_id);
    if (! $attachment_id || is_wp_error($attachment_id)) {
        wp_send_json_error(['message' => 'Não foi possível registrar o anexo do logotipo.'], 500);
    }

    $meta = wp_generate_attachment_metadata($attachment_id, $file);
    wp_update_attachment_metadata($attachment_id, $meta);

    update_post_meta($entity_id, '_profile_pic_attachment_id', $attachment_id);
    $employer_id = rma_plugin_get_employer_post_id_by_author($user_id);
    if ($employer_id > 0) {
        update_post_meta($employer_id, '_profile_pic_attachment_id', $attachment_id);
    }

    wp_send_json_success([
        'message' => 'Logotipo enviado com sucesso. Atualizando dashboard...',
        'attachment_id' => (int) $attachment_id,
    ]);
});

add_action('wp_footer', function (): void {
    if (! is_user_logged_in()) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    $dashboard_path = untrailingslashit((string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH));
    if ($dashboard_path === '' || $request_path === '' || strpos(untrailingslashit($request_path), $dashboard_path) !== 0) {
        return;
    }

    $user_id = get_current_user_id();
    $entity_id = rma_plugin_get_entity_id_by_author($user_id);
    if ($entity_id <= 0) {
        return;
    }

    if (rma_plugin_entity_has_logo($entity_id, $user_id)) {
        return;
    }

    $entity_name = (string) get_the_title($entity_id);
    if ($entity_name === '') {
        $entity_name = 'sua entidade';
    }

    $nonce = wp_create_nonce('rma_required_logo_upload');
    ?>
    <script>
    (function(){
        function mountLogoRequirement(){
            var legacyAlert = document.getElementById('rma-logo-required-alert');
            if (legacyAlert && legacyAlert.parentNode) {
                legacyAlert.parentNode.removeChild(legacyAlert);
            }

            if (document.getElementById('rma-logo-required-uploader')) return true;

            var targets = Array.prototype.slice.call(document.querySelectorAll('h5, .text-info, .exertio-alert, .alert'));
            var anchor = targets.find(function(node){
                var t = (node.textContent || '').replace(/\s+/g, ' ').trim();
                return /Seu endereço de e-mail não foi verificado|Your email address is not verified/i.test(t);
            });
            if (!anchor) return false;

            var host = anchor.closest('.exertio-alert') || anchor.parentElement || anchor;
            var box = document.createElement('div');
            box.id = 'rma-logo-required-uploader';
            box.className = 'exertio-alert alert alert-danger alert-dismissible fade show';
            box.setAttribute('role', 'alert');
            box.style.marginTop = '12px';
            box.innerHTML = '<div class="exertio-alart-box">' +
                '<span class="icon-info">' +
                '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" width="1em" height="1em" viewBox="0 0 24 24"><path d="M14.8 4.613l6.701 11.161c.963 1.603.49 3.712-1.057 4.71a3.213 3.213 0 0 1-1.743.516H5.298C3.477 21 2 19.47 2 17.581c0-.639.173-1.264.498-1.807L9.2 4.613c.962-1.603 2.996-2.094 4.543-1.096c.428.276.79.651 1.057 1.096zm-2.22.839a1.077 1.077 0 0 0-1.514.365L4.365 16.98a1.17 1.17 0 0 0-.166.602c0 .63.492 1.14 1.1 1.14H18.7c.206 0 .407-.06.581-.172a1.164 1.164 0 0 0 .353-1.57L12.933 5.817a1.12 1.12 0 0 0-.352-.365zM12 17a1 1 0 1 1 0-2a1 1 0 0 1 0 2zm0-9a1 1 0 0 1 1 1v4a1 1 0 0 1-2 0V9a1 1 0 0 1 1-1z" fill="#dc2626"/></svg>' +
                '</span>' +
                '<div class="text-info">' +
                '<h5>Ação obrigatória: envie o logotipo oficial de <?php echo esc_js($entity_name); ?>.</h5>' +
                '<p>Esse campo é obrigatório para concluir a ativação da entidade.</p>' +
                '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px">' +
                '<input type="file" id="rma-required-logo-file" accept="image/*" required style="max-width:320px" />' +
                '<button type="button" id="rma-required-logo-btn" style="background:#22c55e;color:#fff;border:none;border-radius:8px;padding:9px 14px;font-weight:700;cursor:pointer;">Enviar logotipo</button>' +
                '</div>' +
                '<div id="rma-required-logo-feedback" style="margin-top:8px;font-size:.92rem;color:#475569"></div>' +
                '</div>' +
                '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
                '</div>';

            host.insertAdjacentElement('afterend', box);

            var input = document.getElementById('rma-required-logo-file');
            var btn = document.getElementById('rma-required-logo-btn');
            var feedback = document.getElementById('rma-required-logo-feedback');
            if (!input || !btn || !feedback) return true;

            btn.addEventListener('click', function(){
                if (!input.files || !input.files[0]) {
                    feedback.textContent = 'Selecione uma imagem antes de enviar.';
                    feedback.style.color = '#991b1b';
                    return;
                }

                var fd = new FormData();
                fd.append('action', 'rma_required_logo_upload');
                fd.append('nonce', <?php echo wp_json_encode($nonce); ?>);
                fd.append('logo', input.files[0]);

                btn.disabled = true;
                btn.textContent = 'Enviando...';
                feedback.textContent = 'Enviando logotipo...';
                feedback.style.color = '#334155';

                fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                })
                .then(function(r){ return r.json().then(function(j){ return {ok:r.ok, j:j}; }); })
                .then(function(res){
                    if (!res.ok || !res.j || !res.j.success) {
                        feedback.textContent = (res.j && res.j.data && res.j.data.message) ? res.j.data.message : 'Falha ao enviar logotipo.';
                        feedback.style.color = '#991b1b';
                        btn.disabled = false;
                        btn.textContent = 'Enviar logotipo';
                        return;
                    }
                    feedback.textContent = (res.j.data && res.j.data.message) ? res.j.data.message : 'Logotipo enviado com sucesso.';
                    feedback.style.color = '#166534';
                    btn.textContent = 'Concluído';
                    setTimeout(function(){ window.location.reload(); }, 900);
                })
                .catch(function(){
                    feedback.textContent = 'Erro de conexão ao enviar logotipo.';
                    feedback.style.color = '#991b1b';
                    btn.disabled = false;
                    btn.textContent = 'Enviar logotipo';
                });
            });

            return true;
        }

        if (!mountLogoRequirement()) {
            var tries = 0;
            var t = setInterval(function(){
                tries += 1;
                if (mountLogoRequirement() || tries >= 12) clearInterval(t);
            }, 500);
        }
    })();
    </script>
    <?php
}, 100);


add_action('wp_footer', function (): void {
    if (is_admin() || ! is_user_logged_in()) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    $dashboard_path = untrailingslashit((string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH));
    if ($dashboard_path === '' || $request_path === '' || strpos(untrailingslashit($request_path), $dashboard_path) !== 0) {
        return;
    }

    $product_id = (int) get_option('rma_annual_dues_product_id', 0);
    if ($product_id <= 0) {
        $product_id = (int) get_option('rma_woo_product_id', 0);
    }
    if ($product_id <= 0) {
        return;
    }

    $annual_value = max(0, (float) get_option('rma_annual_due_value', '0'));
    $checkout_url = function_exists('wc_get_checkout_url')
        ? wc_get_checkout_url()
        : home_url('/checkout/');
    $debug_enabled = rma_sync_debug_enabled();

    ?>
    <script>
    (function(){
        var config = {
            productId: <?php echo (int) $product_id; ?>,
            checkoutUrl: <?php echo wp_json_encode($checkout_url); ?>,
            annualValue: <?php echo wp_json_encode($annual_value); ?>,
            debugEnabled: <?php echo $debug_enabled ? 'true' : 'false'; ?>,
            ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>
        };

        function postDebug(payload){
            if (!config.debugEnabled) return;
            var fd = new FormData();
            fd.append('action', 'rma_sync_debug_log');
            fd.append('payload', JSON.stringify(payload || {}));
            fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd }).catch(function(){});
        }

        function normalize(text){
            return (text || '').replace(/\s+/g, ' ').trim().toLowerCase();
        }

        function getQty(el){
            if (!el) return 0;

            var dataQty = parseInt(el.getAttribute('data-rma-dues-qty') || '', 10);
            if (dataQty >= 1 && dataQty <= 3) return dataQty;

            var text = normalize(el.textContent || '');
            var byText = text.match(/\bgerar\s+([123])\s+anuidade(?:s)?\b/);
            if (byText) return parseInt(byText[1], 10);

            var href = (el.getAttribute('href') || '').toLowerCase();
            var byHref = href.match(/gerar[-_\s]?([123])[-_\s]?anuidade/);
            if (byHref) return parseInt(byHref[1], 10);

            return 0;
        }

        function buildCheckoutUrl(qty){
            var url = new URL(config.checkoutUrl, window.location.origin);
            url.searchParams.set('add-to-cart', String(config.productId));
            url.searchParams.set('quantity', String(qty));
            return url.toString();
        }

        function priceLabel(qty){
            var total = Number(config.annualValue || 0) * qty;
            try {
                return total.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
            } catch (e) {
                return String(total);
            }
        }

        function enhanceElement(el, qty){
            if (!el || !qty) return;

            var checkoutTarget = buildCheckoutUrl(qty);
            el.setAttribute('data-rma-dues-qty', String(qty));
            el.setAttribute('data-rma-dues-target', checkoutTarget);
            el.setAttribute('title', 'Total previsto: ' + priceLabel(qty));

            if (el.tagName && el.tagName.toLowerCase() === 'a') {
                el.setAttribute('href', checkoutTarget);
            }

            if (el.getAttribute('data-rma-dues-bound') === '1') return;
            el.setAttribute('data-rma-dues-bound', '1');

            el.addEventListener('click', function(ev){
                ev.preventDefault();
                if (ev.stopPropagation) ev.stopPropagation();
                if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();

                postDebug({
                    event: 'generate_dues_click',
                    qty: qty,
                    target: checkoutTarget,
                    tag: (el.tagName || '').toLowerCase(),
                    text: normalize(el.textContent || ''),
                    href_before: el.getAttribute('href') || ''
                });

                window.location.href = checkoutTarget;
            }, true);
        }

        function collectCandidates(){
            var selector = [
                'a',
                'button',
                '[role="button"]',
                '[onclick]'
            ].join(',');

            return Array.prototype.slice.call(document.querySelectorAll(selector)).filter(function(el){
                var qty = getQty(el);
                if (!qty) return false;
                el.setAttribute('data-rma-dues-qty', String(qty));
                return true;
            });
        }

        function runEnhancer(){
            var candidates = collectCandidates();
            candidates.forEach(function(el){
                enhanceElement(el, getQty(el));
            });

            postDebug({
                event: 'generate_dues_enhancer_run',
                found: candidates.length,
                checkout_url: config.checkoutUrl,
                product_id: config.productId
            });
        }

        runEnhancer();

        var observer = new MutationObserver(function(){
            runEnhancer();
        });

        observer.observe(document.documentElement || document.body, {
            childList: true,
            subtree: true
        });

        document.addEventListener('click', function(ev){
            var el = ev.target && ev.target.closest ? ev.target.closest('a,button,[role="button"],[onclick]') : null;
            var qty = getQty(el);
            if (!el || !qty) return;

            var checkoutTarget = buildCheckoutUrl(qty);
            ev.preventDefault();
            if (ev.stopPropagation) ev.stopPropagation();
            if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();

            postDebug({
                event: 'generate_dues_delegated_click',
                qty: qty,
                target: checkoutTarget,
                tag: (el.tagName || '').toLowerCase(),
                text: normalize(el.textContent || '')
            });

            window.location.href = checkoutTarget;
        }, true);
    })();
    </script>
    <?php
}, 120);


add_action('wp_ajax_rma_sync_debug_log', function (): void {
    if (! is_user_logged_in() || ! rma_sync_debug_enabled()) {
        wp_die('0');
    }

    $payload_raw = isset($_POST['payload']) ? wp_unslash((string) $_POST['payload']) : '';
    $payload = json_decode($payload_raw, true);
    if (! is_array($payload)) {
        $payload = ['raw' => $payload_raw];
    }

    $payload['request_uri'] = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $payload['user_id'] = get_current_user_id();
    rma_sync_debug_log('client_event', $payload);
    wp_die('1');
});

register_deactivation_hook(__FILE__, ['RMA_Woo_Sync', 'deactivate']);
new RMA_Woo_Sync();
