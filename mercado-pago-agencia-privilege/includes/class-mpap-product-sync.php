<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MPAP_Product_Sync {
    private $api;
    private $quality;

    public function __construct( MPAP_API $api, MPAP_Quality $quality = null ) {
        $this->api = $api;
        $this->quality = $quality ?: new MPAP_Quality( $api );
    }

    public function hooks() {
        add_action( 'add_meta_boxes', array( $this, 'metabox' ) );
        add_action( 'save_post_product', array( $this, 'save_meta' ), 10, 2 );
        add_action( 'save_post_product', array( $this, 'maybe_sync_on_save' ), 30, 2 );
        add_action( 'wp_ajax_mpap_sync_product', array( $this, 'ajax_sync_product' ) );
        add_action( 'wp_ajax_mpap_sync_all', array( $this, 'ajax_sync_all' ) );
        add_action( 'wp_ajax_mpap_update_stock_all', array( $this, 'ajax_update_stock_all' ) );
        add_action( 'wp_ajax_mpap_predict_category', array( $this, 'ajax_predict_category' ) );
        add_action( 'wp_ajax_mpap_set_category', array( $this, 'ajax_set_category' ) );
        add_action( 'wp_ajax_mpap_dry_run_sync_all', array( $this, 'ajax_dry_run_sync_all' ) );
        add_action( 'wp_ajax_mpap_close_all_ml_items', array( $this, 'ajax_close_all_ml_items' ) );
    }

    public function metabox() {
        if ( ! mpap_has_wc() ) {
            return;
        }
        add_meta_box( 'mpap_product', 'Mercado Pago Agência Privilege', array( $this, 'metabox_html' ), 'product', 'side', 'high' );
    }

    public function metabox_html( $post ) {
        $settings = mpap_get_settings();
        $enabled  = get_post_meta( $post->ID, '_mpap_sync_enabled', true );
        $category = get_post_meta( $post->ID, '_mpap_ml_category_id', true );
        $listing  = get_post_meta( $post->ID, '_mpap_ml_listing_type_id', true );
        $condition = get_post_meta( $post->ID, '_mpap_ml_condition', true );
        $item_id  = get_post_meta( $post->ID, '_mpap_ml_item_id', true );
        wp_nonce_field( 'mpap_product_meta', 'mpap_product_meta_nonce' );
        echo '<p><label><input type="checkbox" name="_mpap_sync_enabled" value="1" ' . checked( 1, (int) $enabled, false ) . '> Sincronizar com Mercado Livre</label></p>';
        echo '<p><label><strong>Categoria ML</strong><input class="widefat" name="_mpap_ml_category_id" value="' . esc_attr( $category ) . '" placeholder="' . esc_attr( $settings['default_category_id'] ?: 'MLB...' ) . '"></label></p>';
        echo '<p><label><strong>Tipo de anúncio</strong><input class="widefat" name="_mpap_ml_listing_type_id" value="' . esc_attr( $listing ) . '" placeholder="' . esc_attr( $settings['listing_type_id'] ) . '"></label></p>';
        echo '<p><label><strong>Condição</strong><select class="widefat" name="_mpap_ml_condition"><option value="">Padrão</option><option value="new" ' . selected( $condition, 'new', false ) . '>Novo</option><option value="used" ' . selected( $condition, 'used', false ) . '>Usado</option></select></label></p>';
        echo '<p><label><strong>Nº Homologação Anatel</strong><input class="widefat" name="_ml_anatel_homologation_number" value="' . esc_attr( get_post_meta( $post->ID, '_ml_anatel_homologation_number', true ) ) . '"></label></p>';
        echo '<p><small>Qualidade ML: ' . esc_html( get_post_meta( $post->ID, '_mpap_ml_quality_status', true ) ?: 'não avaliada' ) . '</small></p>';
        echo '<p><strong>ID Mercado Livre</strong><input class="widefat" readonly value="' . esc_attr( $item_id ) . '"></p>';
        echo '<p><button type="button" class="button button-primary mpap-sync-product" data-product-id="' . esc_attr( $post->ID ) . '">Sincronizar agora</button></p><p class="mpap-product-result"></p>';
    }

    public function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST['mpap_product_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mpap_product_meta_nonce'] ) ), 'mpap_product_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        update_post_meta( $post_id, '_mpap_sync_enabled', isset( $_POST['_mpap_sync_enabled'] ) ? 1 : 0 );
        foreach ( array( '_mpap_ml_category_id', '_mpap_ml_listing_type_id', '_mpap_ml_condition', '_ml_anatel_homologation_number' ) as $field ) {
            update_post_meta( $post_id, $field, isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '' );
        }
    }

    public function maybe_sync_on_save( $post_id, $post ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! mpap_get_settings( 'auto_sync_on_save', 0 ) || ! get_post_meta( $post_id, '_mpap_sync_enabled', true ) ) {
            return;
        }
        $this->sync_product( $post_id );
    }

    private function product_price( WC_Product $product ) {
        $price = $product->get_price();
        if ( '' === $price || null === $price ) {
            $price = $product->get_regular_price();
        }
        return mpap_price_with_adjustment( $price );
    }

    private function product_stock( WC_Product $product ) {
        if ( $product->is_type( 'variable' ) ) {
            $qty = 0;
            foreach ( $product->get_children() as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( $variation ) {
                    $qty += $this->product_stock( $variation );
                }
            }
            return max( 0, $qty );
        }
        if ( $product->managing_stock() ) {
            return max( 0, (int) $product->get_stock_quantity() );
        }
        return $product->is_in_stock() ? 1 : 0;
    }

    private function pictures( WC_Product $product ) {
        return $this->quality->pictures( $product, 10 );
    }

    private function add_attribute_unique( array &$attrs, $id, $value ) {
        $id = strtoupper( trim( (string) $id ) );
        $value = mpap_plain_text( $value, 255 );
        if ( '' === $id || '' === $value ) {
            return;
        }
        foreach ( $attrs as $attr ) {
            if ( isset( $attr['id'] ) && strtoupper( (string) $attr['id'] ) === $id ) {
                return;
            }
        }
        $attrs[] = array( 'id' => $id, 'value_name' => $value );
    }

    private function product_attribute_value( WC_Product $product, array $names ) {
        foreach ( $names as $name ) {
            $value = $product->get_attribute( $name );
            if ( '' !== trim( (string) $value ) ) {
                return mpap_plain_text( $value, 255 );
            }
        }

        $meta_keys = array();
        foreach ( $names as $name ) {
            $clean = sanitize_key( $name );
            $meta_keys[] = $clean;
            $meta_keys[] = '_' . $clean;
            $meta_keys[] = 'mpap_' . $clean;
            $meta_keys[] = '_mpap_' . $clean;
        }
        foreach ( array_unique( $meta_keys ) as $meta_key ) {
            $value = get_post_meta( $product->get_id(), $meta_key, true );
            if ( '' !== trim( (string) $value ) ) {
                return mpap_plain_text( $value, 255 );
            }
        }

        return '';
    }

    private function product_version_value( WC_Product $product ) {
        $version = $this->product_attribute_value(
            $product,
            array( 'pa_versao', 'pa_version', 'versao', 'version', 'software_version', 'pa_software_version' )
        );
        if ( $version ) {
            return $version;
        }

        $text = $product->get_name() . ' ' . $product->get_short_description();
        if ( preg_match( '/(?:^|[\s\-])v(?:ersion)?\s*([0-9]+(?:\.[0-9]+){0,4}(?:[\-\._]?[a-z0-9]+)?)/i', $text, $matches ) ) {
            return mpap_plain_text( $matches[1], 255 );
        }
        if ( preg_match( '/\bvers(?:a|ã)o\s*([0-9]+(?:\.[0-9]+){0,4}(?:[\-\._]?[a-z0-9]+)?)/iu', $text, $matches ) ) {
            return mpap_plain_text( $matches[1], 255 );
        }

        return '1.0';
    }

    private function product_software_name_value( WC_Product $product ) {
        $software_name = $this->product_attribute_value(
            $product,
            array( 'pa_software_name', 'software_name', 'nome_do_software', 'pa_nome_do_software', 'software', 'pa_software' )
        );
        if ( $software_name ) {
            return $software_name;
        }

        $name = preg_replace( '/\bv(?:ersion)?\s*[0-9]+(?:\.[0-9]+){0,4}(?:[\-\._]?[a-z0-9]+)?\b/i', '', $product->get_name() );
        $name = preg_replace( '/\s+/', ' ', trim( (string) $name ) );
        return $name ? mpap_trim_title( $name, 255 ) : mpap_trim_title( $product->get_name(), 255 );
    }

    private function product_developer_value( WC_Product $product ) {
        $developer = $this->product_attribute_value(
            $product,
            array( 'pa_developer', 'developer', 'pa_desenvolvedor', 'desenvolvedor', 'fabricante', 'pa_fabricante', 'marca', 'pa_marca', 'brand' )
        );
        if ( $developer ) {
            return $developer;
        }

        $site_name = get_bloginfo( 'name' );
        return $site_name ? mpap_plain_text( $site_name, 255 ) : 'Premium Templates';
    }

    private function attributes( WC_Product $product ) {
        $attrs = array();
        if ( $product->get_sku() ) {
            $this->add_attribute_unique( $attrs, 'SELLER_SKU', $product->get_sku() );
        }
        $brand = $this->product_attribute_value( $product, array( 'pa_marca', 'brand', 'marca' ) );
        if ( $brand ) {
            $this->add_attribute_unique( $attrs, 'BRAND', $brand );
        }

        /*
         * Categorias de software do Mercado Livre, como MLB1731, podem exigir os atributos
         * DEVELOPER, VERSION e SOFTWARE_NAME. Sem eles, a API retorna erro 400:
         * item.attributes.missing_required. Para evitar bloqueio no primeiro envio, o plugin
         * preenche esses campos automaticamente a partir de atributos do WooCommerce, metadados
         * ou valores seguros derivados do título/site.
         */
        $this->add_attribute_unique( $attrs, 'DEVELOPER', $this->product_developer_value( $product ) );
        $this->add_attribute_unique( $attrs, 'VERSION', $this->product_version_value( $product ) );
        $this->add_attribute_unique( $attrs, 'SOFTWARE_NAME', $this->product_software_name_value( $product ) );

        return $attrs;
    }

    private function variations( WC_Product $product ) {
        if ( ! $product->is_type( 'variable' ) ) {
            return array();
        }
        $variations = array();
        foreach ( $product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation ) {
                continue;
            }
            $combos = array();
            foreach ( $variation->get_attributes() as $name => $value ) {
                $taxonomy = str_replace( 'attribute_', '', $name );
                $combos[] = array(
                    'name'       => mpap_plain_text( wc_attribute_label( $taxonomy ), 255 ),
                    'value_name' => mpap_plain_text( $value, 255 ),
                );
            }
            $row = array(
                'price'                  => $this->product_price( $variation ),
                'available_quantity'     => $this->product_stock( $variation ),
                'attribute_combinations' => $combos,
            );
            if ( $variation->get_sku() ) {
                $row['seller_custom_field'] = mpap_plain_text( $variation->get_sku(), 64 );
            }
            $variations[] = $row;
        }
        return $variations;
    }

    public function payload( WC_Product $product ) {
        $settings = mpap_get_settings();
        $category = get_post_meta( $product->get_id(), '_mpap_ml_category_id', true );
        if ( ! $category ) {
            $category = $settings['default_category_id'];
        }
        if ( ! $category ) {
            return new WP_Error( 'mpap_missing_category', 'Defina uma categoria Mercado Livre no produto ou nas configurações.' );
        }

        $listing = get_post_meta( $product->get_id(), '_mpap_ml_listing_type_id', true );
        if ( ! $listing ) {
            $listing = $settings['listing_type_id'];
        }
        $condition = get_post_meta( $product->get_id(), '_mpap_ml_condition', true );
        if ( ! $condition ) {
            $condition = $settings['condition'];
        }

        $payload = array(
            'title'              => mpap_trim_title( $product->get_name(), 60 ),
            'category_id'        => sanitize_text_field( $category ),
            'price'              => $this->product_price( $product ),
            'currency_id'        => sanitize_text_field( $settings['currency_id'] ),
            'available_quantity' => $this->product_stock( $product ),
            'buying_mode'        => 'buy_it_now',
            'listing_type_id'    => sanitize_text_field( $listing ),
            'condition'          => sanitize_text_field( $condition ),
            'pictures'           => $this->pictures( $product ),
            'attributes'         => $this->attributes( $product ),
        );
        if ( ! empty( $payload['category_id'] ) ) {
            $payload['attributes'] = $this->quality->append_anatel_attribute( $payload['attributes'], $product, $payload['category_id'] );
        }
        $payload = $this->quality->remove_manufacturing_time( $payload, $product );
        if ( $product->get_sku() ) {
            $payload['seller_custom_field'] = mpap_plain_text( $product->get_sku(), 64 );
        }
        if ( mpap_get_settings( 'include_description', 1 ) ) {
            $payload['description'] = array( 'plain_text' => mpap_plain_text( $product->get_description() ?: $product->get_short_description() ) );
        }
        $variations = $this->variations( $product );
        if ( $variations ) {
            $payload['variations'] = $variations;
        }
        return $payload;
    }

    public function sync_product( $product_id, $force_create = false ) {
        if ( ! mpap_has_wc() ) {
            return new WP_Error( 'mpap_woocommerce_missing', 'WooCommerce não está ativo.' );
        }
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'mpap_product_not_found', 'Produto WooCommerce não encontrado.' );
        }

        MPAP_Logger::log(
            'info',
            'products',
            'Sincronização de produto iniciada.',
            array(
                'product_id' => $product_id,
                'name'       => $product->get_name(),
                'sku'        => $product->get_sku(),
                'has_ml_item'=> (bool) get_post_meta( $product_id, '_mpap_ml_item_id', true ),
            ),
            array( 'event' => 'product_sync_start' )
        );

        $payload = $this->payload( $product );
        if ( is_wp_error( $payload ) ) {
            update_post_meta( $product_id, '_mpap_ml_status', 'error' );
            MPAP_Logger::log( 'error', 'products', 'Payload inválido.', array( 'product_id' => $product_id, 'error' => $payload->get_error_message() ) );
            return $payload;
        }

        $quality = $this->quality->validate( $product, is_wp_error( $payload ) ? array() : $payload );
        if ( ! is_wp_error( $payload ) && ! empty( $quality['blocked'] ) ) {
            $error = new WP_Error( 'mpap_quality_blocked', 'Sincronização bloqueada pelo checklist de qualidade Mercado Livre: ' . implode( '; ', $quality['issues'] ), $quality );
            update_post_meta( $product_id, '_mpap_ml_status', 'blocked' );
            update_post_meta( $product_id, '_mpap_ml_last_sync_error', $error->get_error_message() );
            MPAP_Logger::warning( 'quality', 'Sincronização bloqueada por qualidade.', array( 'product_id' => $product_id, 'quality' => $quality ), array( 'event' => 'quality_blocked' ) );
            return $error;
        }

        $attribute_ids = array();
        if ( ! empty( $payload['attributes'] ) && is_array( $payload['attributes'] ) ) {
            foreach ( $payload['attributes'] as $attr ) {
                if ( isset( $attr['id'] ) ) {
                    $attribute_ids[] = (string) $attr['id'];
                }
            }
        }
        MPAP_Logger::log(
            'debug',
            'products',
            'Payload Mercado Livre preparado antes do envio.',
            array(
                'product_id'           => $product_id,
                'plugin_version'       => defined( 'MPAP_VERSION' ) ? MPAP_VERSION : '',
                'category_id'          => isset( $payload['category_id'] ) ? $payload['category_id'] : '',
                'listing_type_id'      => isset( $payload['listing_type_id'] ) ? $payload['listing_type_id'] : '',
                'condition'            => isset( $payload['condition'] ) ? $payload['condition'] : '',
                'price'                => isset( $payload['price'] ) ? $payload['price'] : '',
                'available_quantity'   => isset( $payload['available_quantity'] ) ? $payload['available_quantity'] : '',
                'pictures_count'       => isset( $payload['pictures'] ) && is_array( $payload['pictures'] ) ? count( $payload['pictures'] ) : 0,
                'attribute_ids'        => $attribute_ids,
                'required_software_ok' => in_array( 'DEVELOPER', $attribute_ids, true ) && in_array( 'VERSION', $attribute_ids, true ) && in_array( 'SOFTWARE_NAME', $attribute_ids, true ),
                'attributes_preview'   => isset( $payload['attributes'] ) ? $payload['attributes'] : array(),
            ),
            array( 'event' => 'product_payload_ready' )
        );

        $item_id = get_post_meta( $product_id, '_mpap_ml_item_id', true );
        if ( $item_id && ! $force_create ) {
            $update = array_intersect_key( $payload, array_flip( array( 'title', 'price', 'available_quantity', 'pictures', 'attributes', 'seller_custom_field' ) ) );
            $result = $this->api->update_item( $item_id, $update );
            if ( ! is_wp_error( $result ) && ! empty( $payload['description']['plain_text'] ) ) {
                $this->api->update_description( $item_id, $payload['description']['plain_text'] );
            }
        } else {
            $result = $this->api->create_item( $payload );
            if ( ! is_wp_error( $result ) && ! empty( $result['id'] ) ) {
                $item_id = sanitize_text_field( $result['id'] );
                update_post_meta( $product_id, '_mpap_ml_item_id', $item_id );
            }
        }

        if ( is_wp_error( $result ) ) {
            update_post_meta( $product_id, '_mpap_ml_status', 'error' );
            MPAP_Logger::log( 'error', 'products', 'Erro ao sincronizar produto.', array( 'product_id' => $product_id, 'ml_item_id' => $item_id, 'error' => $result->get_error_message(), 'data' => $result->get_error_data() ) );
            return $result;
        }

        update_post_meta( $product_id, '_mpap_ml_status', 'synced' );
        update_post_meta( $product_id, '_mpap_last_sync', current_time( 'mysql' ) );
        MPAP_Logger::log( 'info', 'products', 'Produto sincronizado.', array( 'product_id' => $product_id, 'ml_item_id' => $item_id ) );
        return array( 'product_id' => $product_id, 'ml_item_id' => $item_id, 'response' => $result );
    }

    public function dry_run_sync_all( $limit = 20 ) {
        $limit = max( 1, min( 100, absint( $limit ) ) );
        $ids = get_posts( array( 'post_type'=>'product', 'post_status'=>'publish', 'fields'=>'ids', 'posts_per_page'=>$limit, 'orderby'=>'modified', 'order'=>'DESC', 'no_found_rows'=>true ) );
        $details = array();
        foreach ( $ids as $product_id ) {
            $product = wc_get_product( $product_id ); if ( ! $product ) { continue; }
            $payload = $this->payload( $product );
            $quality = $this->quality->validate( $product, is_wp_error( $payload ) ? array() : $payload, 'dry-run' );
            $details[] = array( 'product_id'=>absint($product_id), 'name'=>$product->get_name(), 'sku'=>$product->get_sku(), 'action'=> empty( $quality['blocked'] ) ? ( get_post_meta($product_id, '_mpap_ml_item_id', true ) ? 'update' : 'create' ) : 'ignore', 'quality'=>$quality );
        }
        return array( 'dry_run'=>true, 'found'=>count($ids), 'details'=>$details );
    }

    public function sync_all( $limit = 20 ) {
        $limit = max( 1, min( 100, absint( $limit ) ) );

        MPAP_Logger::log(
            'info',
            'products',
            'Sincronização em lote iniciada.',
            array(
                'limit'          => $limit,
                'connected_hint' => get_option( 'mpap_manual_access_token', '' ) ? true : null,
                'category'       => mpap_get_settings( 'default_category_id', '' ),
            ),
            array( 'event' => 'batch_sync_start' )
        );

        if ( ! mpap_has_wc() ) {
            MPAP_Logger::log(
                'error',
                'products',
                'Sincronização em lote bloqueada: WooCommerce não está ativo.',
                array( 'limit' => $limit ),
                array( 'event' => 'batch_sync_blocked' )
            );
            return array(
                'ok'      => 0,
                'errors'  => 1,
                'found'   => 0,
                'skipped' => 0,
                'message' => 'WooCommerce não está ativo.',
            );
        }

        /*
         * Até a versão 3.4.0 o lote buscava apenas produtos com _mpap_sync_enabled = 1.
         * Na prática, a tela de produtos mostrava itens pendentes, mas o lote não achava nada
         * quando o usuário ainda não tinha marcado cada produto no metabox individual.
         * A partir da 3.5.0 o botão sincroniza os produtos publicados mais recentes exibidos
         * no painel. Produtos já vinculados são atualizados; produtos pendentes são criados.
         */
        $product_ids = get_posts(
            array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'posts_per_page' => $limit,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            )
        );

        if ( empty( $product_ids ) ) {
            MPAP_Logger::log(
                'warning',
                'products',
                'Sincronização em lote não encontrou produtos publicados.',
                array( 'limit' => $limit ),
                array( 'event' => 'batch_sync_empty' )
            );
            update_option( 'mpap_last_cron_sync', current_time( 'mysql' ), false );
            return array(
                'ok'       => 0,
                'errors'   => 0,
                'found'    => 0,
                'skipped'  => 0,
                'warning'  => true,
                'message'  => 'Nenhum produto publicado encontrado para sincronizar.',
            );
        }

        $ok = 0;
        $errors = 0;
        $details = array();

        foreach ( $product_ids as $product_id ) {
            $product_id = absint( $product_id );
            if ( ! $product_id ) {
                continue;
            }

            $result = $this->sync_product( $product_id );
            if ( is_wp_error( $result ) ) {
                $errors++;
                $details[] = array(
                    'product_id' => $product_id,
                    'status'     => 'error',
                    'message'    => $result->get_error_message(),
                    'data'       => $result->get_error_data(),
                );
            } else {
                $ok++;
                $details[] = array(
                    'product_id' => $product_id,
                    'status'     => 'ok',
                    'ml_item_id' => isset( $result['ml_item_id'] ) ? $result['ml_item_id'] : '',
                );
            }
        }

        $summary = array(
            'ok'          => $ok,
            'errors'      => $errors,
            'found'       => count( $product_ids ),
            'skipped'     => 0,
            'limit'       => $limit,
            'product_ids' => array_map( 'absint', $product_ids ),
            'details'     => $details,
        );

        update_option( 'mpap_last_cron_sync', current_time( 'mysql' ), false );
        MPAP_Logger::log(
            $errors ? 'warning' : 'info',
            'products',
            'Sincronização em lote concluída.',
            $summary,
            array( 'event' => 'batch_sync_done' )
        );

        return $summary;
    }

    public function update_stock_all( $limit = 50 ) {
        $limit = max( 1, min( 100, absint( $limit ) ) );
        MPAP_Logger::log(
            'info',
            'products',
            'Atualização de estoque em lote iniciada.',
            array( 'limit' => $limit ),
            array( 'event' => 'stock_batch_start' )
        );

        if ( ! mpap_has_wc() ) {
            MPAP_Logger::log(
                'error',
                'products',
                'Atualização de estoque bloqueada: WooCommerce não está ativo.',
                array( 'limit' => $limit ),
                array( 'event' => 'stock_batch_blocked' )
            );
            return array( 'ok' => 0, 'errors' => 1, 'found' => 0, 'message' => 'WooCommerce não está ativo.' );
        }

        $product_ids = get_posts(
            array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'posts_per_page' => $limit,
                'meta_query'     => array(
                    array(
                        'key'     => '_mpap_ml_item_id',
                        'compare' => 'EXISTS',
                    ),
                ),
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            )
        );

        $ok = 0;
        $errors = 0;
        $details = array();

        foreach ( $product_ids as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }
            $item_id = get_post_meta( $product_id, '_mpap_ml_item_id', true );
            if ( ! $item_id ) {
                continue;
            }
            $result = $this->api->update_item( $item_id, array( 'available_quantity' => $this->product_stock( $product ) ) );
            if ( is_wp_error( $result ) ) {
                $errors++;
                $details[] = array( 'product_id' => $product_id, 'ml_item_id' => $item_id, 'status' => 'error', 'message' => $result->get_error_message(), 'data' => $result->get_error_data() );
            } else {
                $ok++;
                $details[] = array( 'product_id' => $product_id, 'ml_item_id' => $item_id, 'status' => 'ok' );
            }
        }

        $summary = array(
            'ok'      => $ok,
            'errors'  => $errors,
            'found'   => count( $product_ids ),
            'limit'   => $limit,
            'details' => $details,
        );

        MPAP_Logger::log(
            $errors ? 'warning' : 'info',
            'products',
            'Atualização de estoque em lote concluída.',
            $summary,
            array( 'event' => 'stock_batch_done' )
        );

        return $summary;
    }

    public function process_item_notification( $resource ) {
        $item_id = '';
        if ( preg_match( '~items/([^/?]+)~', (string) $resource, $matches ) ) {
            $item_id = sanitize_text_field( $matches[1] );
        } else {
            $item_id = sanitize_text_field( basename( (string) $resource ) );
        }
        if ( ! $item_id ) {
            return new WP_Error( 'mpap_invalid_item_resource', 'Recurso de item inválido.' );
        }
        $product_id = $this->find_product_by_item( $item_id );
        if ( ! $product_id ) {
            MPAP_Logger::log( 'warning', 'webhooks', 'Item recebido sem produto local vinculado.', array( 'ml_item_id' => $item_id ) );
            return false;
        }
        if ( ! in_array( mpap_get_settings( 'stock_strategy', 'wc_to_ml' ), array( 'ml_to_wc', 'two_way' ), true ) ) {
            return true;
        }
        $item = $this->api->get_item( $item_id );
        if ( is_wp_error( $item ) || ! mpap_has_wc() ) {
            return $item;
        }
        $product = wc_get_product( $product_id );
        if ( $product && isset( $item['available_quantity'] ) ) {
            $product->set_manage_stock( true );
            $product->set_stock_quantity( max( 0, (int) $item['available_quantity'] ) );
            if ( isset( $item['price'] ) ) {
                $product->set_regular_price( (string) round( (float) $item['price'], 2 ) );
            }
            $product->save();
        }
        return true;
    }

    public function find_product_by_item( $item_id ) {
        $query = new WP_Query(
            array(
                'post_type'      => 'product',
                'post_status'    => 'any',
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'meta_key'       => '_mpap_ml_item_id',
                'meta_value'     => sanitize_text_field( $item_id ),
            )
        );
        return $query->posts ? (int) $query->posts[0] : 0;
    }

    public function rows( $limit = 20 ) {
        if ( ! mpap_has_wc() ) {
            return array();
        }
        $ids = wc_get_products( array( 'limit' => absint( $limit ), 'status' => array( 'publish', 'draft', 'private' ), 'return' => 'ids', 'orderby' => 'modified', 'order' => 'DESC' ) );
        $rows = array();
        foreach ( $ids as $id ) {
            $product = wc_get_product( $id );
            if ( ! $product ) {
                continue;
            }
            $rows[] = array(
                'id'       => $id,
                'name'     => $product->get_name(),
                'sku'      => $product->get_sku(),
                'stock'    => $product->managing_stock() ? $product->get_stock_quantity() : ( $product->is_in_stock() ? 'em estoque' : 'sem estoque' ),
                'wc_status'=> get_post_status( $id ),
                'ml_item'  => get_post_meta( $id, '_mpap_ml_item_id', true ),
                'status'   => get_post_meta( $id, '_mpap_ml_status', true ),
                'edit_url' => get_edit_post_link( $id ),
            );
        }
        return $rows;
    }

    public function stats() {
        $synced = new WP_Query( array( 'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => '_mpap_ml_item_id', 'meta_compare' => 'EXISTS' ) );
        $enabled = new WP_Query( array( 'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => '_mpap_sync_enabled', 'meta_value' => 1 ) );
        return array(
            'synced'  => (int) $synced->found_posts,
            'enabled' => (int) $enabled->found_posts,
            'stock'   => 0,
        );
    }

    public function ajax_sync_product() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }
        $product_id = absint( $_POST['product_id'] ?? 0 );
        $result = $product_id ? $this->sync_product( $product_id ) : new WP_Error( 'mpap_missing_product_id', 'Produto inválido.' );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        }
        wp_send_json_success( array( 'message' => 'Produto sincronizado com sucesso.', 'result' => $result ) );
    }

    public function ajax_sync_all() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }
        $result = $this->sync_all( mpap_get_settings( 'sync_batch_size', 20 ) );
        $message = isset( $result['message'] ) ? $result['message'] : sprintf(
            'Lote processado: %d produto(s) encontrado(s), %d sincronizado(s), %d erro(s).',
            isset( $result['found'] ) ? (int) $result['found'] : 0,
            isset( $result['ok'] ) ? (int) $result['ok'] : 0,
            isset( $result['errors'] ) ? (int) $result['errors'] : 0
        );
        wp_send_json_success( array( 'message' => $message, 'result' => $result ) );
    }

    public function ajax_update_stock_all() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }
        $result = $this->update_stock_all( mpap_get_settings( 'sync_batch_size', 20 ) );
        $message = sprintf(
            'Estoque processado: %d produto(s) encontrado(s), %d atualizado(s), %d erro(s).',
            isset( $result['found'] ) ? (int) $result['found'] : 0,
            isset( $result['ok'] ) ? (int) $result['ok'] : 0,
            isset( $result['errors'] ) ? (int) $result['errors'] : 0
        );
        wp_send_json_success( array( 'message' => $message, 'result' => $result ) );
    }

    private function normalize_category_suggestion( array $row, $include_details = true ) {
        $category_id = sanitize_text_field( $row['category_id'] ?? ( $row['id'] ?? '' ) );
        if ( '' === $category_id ) {
            return null;
        }

        $suggestion = array(
            'category_id'       => $category_id,
            'category_name'     => sanitize_text_field( $row['category_name'] ?? ( $row['name'] ?? '' ) ),
            'domain_id'         => sanitize_text_field( $row['domain_id'] ?? '' ),
            'domain_name'       => sanitize_text_field( $row['domain_name'] ?? '' ),
            'probability'       => isset( $row['probability'] ) ? (float) $row['probability'] : null,
            'path'              => '',
            'required'          => array(),
            'listing_types'     => array(),
            'raw'               => mpap_sanitize_log_context( $row ),
        );

        if ( $include_details ) {
            $details = $this->api->get_category( $category_id );
            if ( ! is_wp_error( $details ) && is_array( $details ) ) {
                $path = array();
                if ( ! empty( $details['path_from_root'] ) && is_array( $details['path_from_root'] ) ) {
                    foreach ( $details['path_from_root'] as $node ) {
                        if ( ! empty( $node['name'] ) ) {
                            $path[] = $node['name'];
                        }
                    }
                }
                $suggestion['category_name'] = $suggestion['category_name'] ?: sanitize_text_field( $details['name'] ?? '' );
                $suggestion['path'] = $path ? implode( ' > ', array_map( 'sanitize_text_field', $path ) ) : $suggestion['category_name'];
                $suggestion['details'] = mpap_sanitize_log_context( array_intersect_key( $details, array_flip( array( 'id', 'name', 'picture', 'permalink', 'total_items_in_this_category' ) ) ) );
            }

            $attrs = $this->api->get_category_attributes( $category_id );
            if ( ! is_wp_error( $attrs ) && is_array( $attrs ) ) {
                foreach ( $attrs as $attr ) {
                    $tags = isset( $attr['tags'] ) && is_array( $attr['tags'] ) ? $attr['tags'] : array();
                    $is_required = ! empty( $tags['required'] ) || ! empty( $tags['catalog_required'] ) || ! empty( $tags['required_for_catalog_listing'] );
                    if ( $is_required ) {
                        $suggestion['required'][] = array(
                            'id'         => sanitize_text_field( $attr['id'] ?? '' ),
                            'name'       => sanitize_text_field( $attr['name'] ?? '' ),
                            'value_type' => sanitize_text_field( $attr['value_type'] ?? '' ),
                        );
                    }
                }
            }

            $listing_types = $this->api->get_category_listing_types( $category_id );
            if ( ! is_wp_error( $listing_types ) && is_array( $listing_types ) ) {
                foreach ( $listing_types as $listing_type ) {
                    if ( ! empty( $listing_type['id'] ) ) {
                        $suggestion['listing_types'][] = array(
                            'id'   => sanitize_text_field( $listing_type['id'] ),
                            'name' => sanitize_text_field( $listing_type['name'] ?? $listing_type['id'] ),
                        );
                    }
                }
            }
        }

        return $suggestion;
    }

    private function category_query_from_product( $product_id ) {
        if ( ! $product_id || ! mpap_has_wc() ) {
            return '';
        }
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return '';
        }
        $parts = array( $product->get_name() );
        $terms = get_the_terms( $product_id, 'product_cat' );
        if ( is_array( $terms ) ) {
            foreach ( array_slice( $terms, 0, 3 ) as $term ) {
                $parts[] = $term->name;
            }
        }
        return trim( implode( ' ', array_filter( array_map( 'mpap_plain_text', $parts ) ) ) );
    }

    public function ajax_predict_category() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }

        $product_id = absint( $_POST['product_id'] ?? 0 );
        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        if ( '' === $title && $product_id ) {
            $title = $this->category_query_from_product( $product_id );
        }
        if ( '' === trim( $title ) ) {
            wp_send_json_error( array( 'message' => 'Informe um termo de busca ou selecione um produto.' ), 400 );
        }

        $limit = max( 1, min( 10, absint( $_POST['limit'] ?? 8 ) ) );
        $result = $this->api->predict_category( $title, $limit );
        if ( is_wp_error( $result ) ) {
            MPAP_Logger::error( 'categories', 'Falha ao consultar preditor de categoria.', array( 'query' => $title, 'error' => $result->get_error_message(), 'data' => $result->get_error_data() ), array( 'event' => 'category_predict_failed' ) );
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'data' => $result->get_error_data() ), 400 );
        }

        $suggestions = array();
        foreach ( array_slice( (array) $result, 0, $limit ) as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $suggestion = $this->normalize_category_suggestion( $row, true );
            if ( $suggestion ) {
                $suggestions[] = $suggestion;
            }
        }

        MPAP_Logger::info( 'categories', 'Categorias sugeridas pelo Mercado Livre.', array( 'query' => $title, 'count' => count( $suggestions ), 'suggestions' => $suggestions ), array( 'event' => 'category_predict_ok' ) );
        wp_send_json_success( array( 'message' => 'Categorias consultadas.', 'query' => $title, 'suggestions' => $suggestions, 'raw' => mpap_sanitize_log_context( $result ) ) );
    }

    public function ajax_set_category() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }

        $category_id = strtoupper( preg_replace( '/[^A-Z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_POST['category_id'] ?? '' ) ) ) );
        if ( ! preg_match( '/^ML[A-Z0-9]+$/', $category_id ) ) {
            wp_send_json_error( array( 'message' => 'ID de categoria inválido. Use um ID como MLB12345.' ), 400 );
        }

        $category_name = sanitize_text_field( wp_unslash( $_POST['category_name'] ?? '' ) );
        $category_path = sanitize_text_field( wp_unslash( $_POST['category_path'] ?? '' ) );
        $domain_id     = sanitize_text_field( wp_unslash( $_POST['domain_id'] ?? '' ) );
        $query         = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
        $product_id    = absint( $_POST['product_id'] ?? 0 );

        if ( $product_id ) {
            update_post_meta( $product_id, '_mpap_ml_category_id', $category_id );
            MPAP_Logger::info( 'categories', 'Categoria aplicada ao produto WooCommerce.', array( 'product_id' => $product_id, 'category_id' => $category_id, 'category_name' => $category_name, 'path' => $category_path ), array( 'event' => 'category_set_product' ) );
            wp_send_json_success( array( 'message' => 'Categoria aplicada ao produto.', 'category_id' => $category_id ) );
        }

        $settings = mpap_get_settings();
        $settings['default_category_id']     = $category_id;
        $settings['default_category_name']   = $category_name;
        $settings['default_category_path']   = $category_path;
        $settings['default_category_domain'] = $domain_id;
        $settings['default_category_query']  = $query;
        mpap_update_settings( $settings );

        MPAP_Logger::info( 'categories', 'Categoria padrão Mercado Livre atualizada.', array( 'category_id' => $category_id, 'category_name' => $category_name, 'path' => $category_path, 'domain_id' => $domain_id, 'query' => $query ), array( 'event' => 'category_set_default' ) );
        wp_send_json_success( array( 'message' => 'Categoria padrão atualizada.', 'category_id' => $category_id, 'category_name' => $category_name, 'path' => $category_path ) );
    }


    public function ajax_dry_run_sync_all() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 );
        }
        $limit = max( 1, min( 100, absint( $_POST['limit'] ?? mpap_get_settings( 'sync_batch_size', 20 ) ) ) );
        wp_send_json_success( array( 'message' => 'Dry-run concluído. Nenhuma chamada de criação/atualização foi enviada ao Mercado Livre.', 'result' => $this->dry_run_sync_all( $limit ) ) );
    }

    public function close_all_ml_items( $dry_run = true, $limit = 100 ) {
        $limit = max( 1, min( 500, absint( $limit ) ) );
        $ids = get_posts( array( 'post_type'=>'product', 'post_status'=>'any', 'fields'=>'ids', 'posts_per_page'=>$limit, 'meta_key'=>'_mpap_ml_item_id', 'meta_compare'=>'EXISTS', 'orderby'=>'ID', 'order'=>'ASC', 'no_found_rows'=>true ) );
        $details = array(); $ok = 0; $errors = 0; $skipped = 0;
        foreach ( $ids as $product_id ) {
            $item_id = sanitize_text_field( get_post_meta( $product_id, '_mpap_ml_item_id', true ) );
            if ( '' === $item_id ) {
                $skipped++;
                $details[] = array( 'product_id'=>(int)$product_id, 'ml_item_id'=>'', 'status'=>'skipped', 'message'=>'Item_id vazio; anúncio não será encerrado.' );
                continue;
            }
            if ( $dry_run ) {
                $details[] = array( 'product_id'=>(int)$product_id, 'ml_item_id'=>$item_id, 'action'=>'would_close', 'warning'=>'Anúncio encerrado no Mercado Livre pode não ser restaurável.' );
                continue;
            }

            $previous = $this->api->get_item( $item_id );
            if ( is_wp_error( $previous ) ) {
                $errors++;
                update_post_meta( $product_id, '_mpap_ml_last_sync_error', $previous->get_error_message() );
                $details[] = array( 'product_id'=>(int)$product_id, 'ml_item_id'=>$item_id, 'status'=>'error', 'message'=>'Não foi possível consultar status anterior antes do encerramento: ' . $previous->get_error_message(), 'data'=>$previous->get_error_data() );
                continue;
            }
            $backup = array(
                'item_id'      => $item_id,
                'status'       => isset( $previous['status'] ) ? sanitize_text_field( $previous['status'] ) : '',
                'sub_status'   => isset( $previous['sub_status'] ) ? mpap_sanitize_log_context( $previous['sub_status'] ) : array(),
                'permalink'    => isset( $previous['permalink'] ) ? esc_url_raw( $previous['permalink'] ) : '',
                'backed_up_at' => current_time( 'mysql' ),
            );
            update_post_meta( $product_id, '_mpap_ml_status_before_close', $backup );

            $result = $this->api->update_item( $item_id, array( 'status' => 'closed' ) );
            if ( is_wp_error( $result ) ) {
                $errors++; update_post_meta( $product_id, '_mpap_ml_last_sync_error', $result->get_error_message() );
                $details[] = array( 'product_id'=>(int)$product_id, 'ml_item_id'=>$item_id, 'previous_status'=>$backup['status'], 'status'=>'error', 'message'=>$result->get_error_message(), 'data'=>$result->get_error_data() );
            } else {
                $ok++; update_post_meta( $product_id, '_mpap_ml_status', 'closed' );
                $details[] = array( 'product_id'=>(int)$product_id, 'ml_item_id'=>$item_id, 'previous_status'=>$backup['status'], 'status'=>'closed' );
            }
        }
        MPAP_Logger::warning( 'products', $dry_run ? 'Dry-run de encerramento de anúncios ML executado.' : 'Encerramento em massa de anúncios ML executado.', array( 'dry_run'=>$dry_run, 'found'=>count($ids), 'ok'=>$ok, 'errors'=>$errors, 'skipped'=>$skipped, 'affected_item_ids'=>wp_list_pluck( $details, 'ml_item_id' ), 'details'=>$details ), array( 'event'=>'ml_close_all_items' ) );
        return array( 'dry_run'=>(bool)$dry_run, 'found'=>count($ids), 'ok'=>$ok, 'errors'=>$errors, 'skipped'=>$skipped, 'affected_item_ids'=>array_values( array_filter( wp_list_pluck( $details, 'ml_item_id' ) ) ), 'details'=>$details, 'warning'=>'Anúncio encerrado no Mercado Livre pode não ser restaurável.', 'note'=>'A API oficial usa atualização de status do item para encerrar anúncio; o plugin não remove produtos WooCommerce nem apaga dados locais.' );
    }

    public function ajax_close_all_ml_items() {
        check_ajax_referer( 'mpap_admin_nonce', 'nonce' );
        if ( ! current_user_can( mpap_capability() ) ) { wp_send_json_error( array( 'message'=>'Permissão negada.' ), 403 ); }
        $confirm = sanitize_text_field( wp_unslash( $_POST['confirm'] ?? '' ) );
        $dry_run = empty( $_POST['execute'] ) || 'ENCERAR' !== $confirm;
        $limit = max( 1, min( 500, absint( $_POST['limit'] ?? 100 ) ) );
        $result = $this->close_all_ml_items( $dry_run, $limit );
        wp_send_json_success( array( 'message' => $dry_run ? 'Prévia gerada. Para executar, digite exatamente ENCERAR e confirme.' : 'Solicitação de encerramento enviada aos anúncios vinculados. Anúncio encerrado no Mercado Livre pode não ser restaurável.', 'result'=>$result ) );
    }

}
