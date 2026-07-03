<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MPAP_Order_Importer {
    private $api;
    private $products;

    public function __construct( MPAP_API $api, MPAP_Product_Sync $products ) {
        $this->api      = $api;
        $this->products = $products;
    }

    public function hooks() {
        add_action( 'wp_ajax_mpap_import_order', array( $this, 'ajax_import_order' ) );
    }

    public function stats() {
        if ( ! mpap_has_wc() ) {
            return array( 'imported' => 0 );
        }
        $orders = wc_get_orders( array( 'limit' => 1, 'paginate' => true, 'return' => 'ids', 'meta_key' => '_mpap_source', 'meta_value' => 'mercado_livre' ) );
        return array( 'imported' => is_object( $orders ) && isset( $orders->total ) ? (int) $orders->total : 0 );
    }

    public function existing_order( $ml_order_id ) {
        if ( ! mpap_has_wc() ) {
            return 0;
        }
        $orders = wc_get_orders( array( 'limit' => 1, 'return' => 'ids', 'meta_key' => '_mpap_ml_order_id', 'meta_value' => (string) $ml_order_id ) );
        return $orders ? (int) $orders[0] : 0;
    }

    public function import_from_resource( $resource ) {
        $order_id = '';
        if ( preg_match( '~orders/(\d+)~', (string) $resource, $matches ) ) {
            $order_id = $matches[1];
        } elseif ( preg_match( '~^(\d+)$~', (string) $resource, $matches ) ) {
            $order_id = $matches[1];
        }
        if ( ! $order_id ) {
            return new WP_Error( 'mpap_invalid_order_resource', 'Recurso de pedido inválido.' );
        }
        return $this->import_order( $order_id );
    }

    public function import_order( $ml_order_id ) {
        if ( ! mpap_has_wc() ) {
            return new WP_Error( 'mpap_woocommerce_missing', 'WooCommerce não está ativo.' );
        }
        if ( ! mpap_get_settings( 'enable_order_import', 1 ) ) {
            return new WP_Error( 'mpap_order_import_disabled', 'Importação de pedidos desativada nas configurações.' );
        }
        $existing = $this->existing_order( $ml_order_id );
        if ( $existing ) {
            return $existing;
        }

        $data = $this->api->get_order( $ml_order_id );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $order = wc_create_order( array( 'created_via' => 'mpap' ) );
        if ( is_wp_error( $order ) ) {
            return $order;
        }

        foreach ( (array) ( $data['order_items'] ?? array() ) as $order_item ) {
            $quantity = max( 1, (int) ( $order_item['quantity'] ?? 1 ) );
            $unit     = (float) ( $order_item['unit_price'] ?? 0 );
            $ml_item  = is_array( $order_item['item'] ?? null ) ? $order_item['item'] : array();
            $ml_item_id = (string) ( $ml_item['id'] ?? '' );
            $title    = (string) ( $ml_item['title'] ?? 'Produto Mercado Livre' );
            $product_id = $ml_item_id ? $this->products->find_product_by_item( $ml_item_id ) : 0;
            $product = $product_id ? wc_get_product( $product_id ) : false;
            if ( $product ) {
                $order->add_product( $product, $quantity, array( 'subtotal' => $unit * $quantity, 'total' => $unit * $quantity ) );
            } else {
                $line = new WC_Order_Item_Product();
                $line->set_name( mpap_plain_text( $title, 255 ) );
                $line->set_quantity( $quantity );
                $line->set_subtotal( $unit * $quantity );
                $line->set_total( $unit * $quantity );
                $line->add_meta_data( '_mpap_ml_item_id', $ml_item_id, true );
                $order->add_item( $line );
            }
        }

        $buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
        $buyer_id = (string) ( $buyer['id'] ?? '' );
        $order->set_billing_first_name( mpap_plain_text( $buyer['first_name'] ?? 'Cliente', 120 ) );
        $order->set_billing_last_name( mpap_plain_text( $buyer['last_name'] ?? 'Mercado Livre', 120 ) );
        $email = isset( $buyer['email'] ) && is_email( $buyer['email'] ) ? $buyer['email'] : ( $buyer_id ? 'mercadolivre-' . sanitize_key( $buyer_id ) . '@noemail.local' : 'mercadolivre@noemail.local' );
        $order->set_billing_email( $email );
        $order->set_payment_method( 'mercado_livre' );
        $order->set_payment_method_title( 'Mercado Livre' );
        $order->update_meta_data( '_mpap_source', 'mercado_livre' );
        $order->update_meta_data( '_mpap_ml_order_id', (string) $ml_order_id );
        $order->update_meta_data( '_mpap_ml_payload', wp_json_encode( $data ) );
        $order->calculate_totals();
        $order->update_status( 'processing', 'Pedido importado do Mercado Livre.' );
        $order->save();

        MPAP_Logger::log( 'info', 'orders', 'Pedido importado.', array( 'ml_order_id' => $ml_order_id, 'wc_order_id' => $order->get_id() ) );
        return $order->get_id();
    }

    public function ajax_import_order() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }
        $order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ?? '' ) );
        $result = $this->import_order( $order_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        }
        wp_send_json_success( array( 'message' => 'Pedido importado com sucesso.', 'order_id' => $result ) );
    }
}
