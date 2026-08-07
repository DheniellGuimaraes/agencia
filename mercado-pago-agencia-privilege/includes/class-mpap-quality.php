<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MPAP_Quality {
    const META_HASH = '_mpap_ml_normalized_title_hash';
    const META_CANONICAL = '_mpap_ml_canonical_product_id';
    const META_DUP_GROUP = '_mpap_ml_duplicate_group_id';
    const META_STATUS = '_mpap_ml_quality_status';
    const META_SCORE = '_mpap_ml_quality_score_local';
    const META_DIAG = '_mpap_ml_last_diagnostics';
    const META_RECS = '_mpap_ml_last_recommendations';
    const META_ERROR = '_mpap_ml_last_sync_error';
    const META_ANATEL = '_ml_anatel_homologation_number';
    const META_PHOTO_COUNT = '_mpap_ml_photo_count';
    const META_BLOCKED = '_mpap_ml_blocked_reason';

    private $api;
    private $category_cache = array();

    public function __construct( MPAP_API $api = null ) { $this->api = $api; }

    public function hooks() {
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_fields' ) );
        add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_fields' ) );
    }

    public static function normalize_title( $title ) {
        $title = wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) );
        $title = remove_accents( $title );
        $title = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
        $title = preg_replace( '/\s+/u', ' ', $title );
        return trim( $title );
    }

    public static function title_hash( $title ) { return hash( 'sha256', self::normalize_title( $title ) ); }

    public function product_fields() {
        woocommerce_wp_text_input( array(
            'id' => self::META_ANATEL,
            'label' => 'Número de Homologação Anatel',
            'desc_tip' => true,
            'description' => 'Preencha quando a categoria Mercado Livre exigir ou recomendar certificação/homologação Anatel.',
        ) );
    }

    public function save_product_fields( $product ) {
        if ( isset( $_POST[ self::META_ANATEL ] ) ) {
            $product->update_meta_data( self::META_ANATEL, sanitize_text_field( wp_unslash( $_POST[ self::META_ANATEL ] ) ) );
        }
    }

    public function image_sources( WC_Product $product ) {
        $ids = array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) );
        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_children() as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( $variation && $variation->get_image_id() ) { $ids[] = $variation->get_image_id(); }
            }
        }
        $sources = array();
        foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
            $url = wp_get_attachment_url( $id );
            if ( $url ) {
                $clean_url = esc_url_raw( $url );
                $sources[ 'url:' . strtolower( $clean_url ) ] = array( 'id' => $id, 'url' => $clean_url );
            }
        }
        return array_values( $sources );
    }

    public function pictures( WC_Product $product, $limit = 10 ) {
        $pictures = array();
        foreach ( array_slice( $this->image_sources( $product ), 0, $limit ) as $source ) { $pictures[] = array( 'source' => $source['url'] ); }
        return $pictures;
    }

    public function photo_count( WC_Product $product ) { return count( $this->image_sources( $product ) ); }

    public function canonical_for_product( WC_Product $product ) {
        $hash = self::title_hash( $product->get_name() );
        update_post_meta( $product->get_id(), self::META_HASH, $hash );
        $ids = get_posts( array( 'post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>100,'meta_key'=>self::META_HASH,'meta_value'=>$hash,'orderby'=>'ID','order'=>'ASC','no_found_rows'=>true ) );
        if ( ! in_array( $product->get_id(), array_map( 'absint', $ids ), true ) ) { $ids[] = $product->get_id(); sort( $ids ); }
        $canonical = 0; $canonical_item = '';
        foreach ( $ids as $id ) { $item = get_post_meta( $id, '_mpap_ml_item_id', true ); if ( $item ) { $canonical=(int)$id; $canonical_item=$item; break; } }
        if ( ! $canonical ) { $canonical = (int) min( array_map( 'absint', $ids ) ); $canonical_item = get_post_meta( $canonical, '_mpap_ml_item_id', true ); }
        foreach ( $ids as $id ) {
            update_post_meta( $id, self::META_HASH, $hash );
            update_post_meta( $id, self::META_CANONICAL, $canonical );
            update_post_meta( $id, self::META_DUP_GROUP, $hash );
            if ( (int) $id !== $canonical ) {
                update_post_meta( $id, self::META_STATUS, 'blocked' );
                update_post_meta( $id, self::META_BLOCKED, 'duplicado_por_nome' );
                if ( $canonical_item ) { update_post_meta( $id, '_mpap_ml_canonical_item_id', sanitize_text_field( $canonical_item ) ); }
            }
        }
        return array( 'hash'=>$hash, 'ids'=>array_map('absint',$ids), 'canonical_product_id'=>$canonical, 'canonical_item_id'=>$canonical_item, 'is_duplicate'=>(int)$product->get_id() !== $canonical );
    }

    public function category_attributes( $category_id ) {
        $category_id = sanitize_text_field( $category_id );
        if ( ! $category_id || ! $this->api ) { return array(); }
        if ( isset( $this->category_cache[ $category_id ] ) ) { return $this->category_cache[ $category_id ]; }
        $attrs = $this->api->get_category_attributes( $category_id );
        $this->category_cache[ $category_id ] = is_wp_error( $attrs ) ? array() : (array) $attrs;
        return $this->category_cache[ $category_id ];
    }

    public function find_regulatory_attributes( $category_id ) {
        $found = array();
        foreach ( $this->category_attributes( $category_id ) as $attr ) {
            $text = remove_accents( strtoupper( ( $attr['id'] ?? '' ) . ' ' . ( $attr['name'] ?? '' ) ) );
            if ( preg_match( '/ANATEL|HOMOLOG|CERTIFIC|REGISTRO|REGULATOR/', $text ) ) {
                $tags = isset( $attr['tags'] ) && is_array( $attr['tags'] ) ? $attr['tags'] : array();
                $attr['mpap_required'] = ! empty( $tags['required'] ) || ! empty( $tags['catalog_required'] );
                $attr['mpap_recommended'] = ! $attr['mpap_required'];
                $found[] = $attr;
            }
        }
        return $found;
    }

    public function append_anatel_attribute( array $attrs, WC_Product $product, $category_id ) {
        $value = trim( (string) get_post_meta( $product->get_id(), self::META_ANATEL, true ) );
        if ( '' === $value ) { return $attrs; }
        foreach ( $this->find_regulatory_attributes( $category_id ) as $attr ) {
            if ( ! empty( $attr['id'] ) ) { $attrs[] = array( 'id' => sanitize_text_field( $attr['id'] ), 'value_name' => mpap_plain_text( $value, 255 ) ); break; }
        }
        return $attrs;
    }

    public function has_immediate_stock( WC_Product $product ) { return $product->is_in_stock() && ( ! $product->managing_stock() || (int) $product->get_stock_quantity() > 0 ); }
    public function is_made_to_order( WC_Product $product ) { return (bool) get_post_meta( $product->get_id(), '_mpap_made_to_order', true ); }
    public function remove_manufacturing_time( array $payload, WC_Product $product ) {
        if ( empty( $payload['sale_terms'] ) || ! is_array( $payload['sale_terms'] ) || ! mpap_get_settings( 'auto_remove_manufacturing_time', 1 ) || ! $this->has_immediate_stock( $product ) || $this->is_made_to_order( $product ) ) { return $payload; }
        $payload['sale_terms'] = array_values( array_filter( $payload['sale_terms'], static function( $term ) { return strtoupper( $term['id'] ?? '' ) !== 'MANUFACTURING_TIME'; } ) );
        if ( empty( $payload['sale_terms'] ) ) { unset( $payload['sale_terms'] ); }
        return $payload;
    }

    public function validate( WC_Product $product, array $payload = array(), $mode = 'sync' ) {
        $issues = array(); $recommendations = array(); $blocked = false;
        $dup = $this->canonical_for_product( $product );
        if ( $dup['is_duplicate'] && empty( $payload['_mpap_allow_duplicate_update'] ) ) { $blocked = true; $issues[] = 'Produto duplicado por nome. Canônico: #' . $dup['canonical_product_id']; }
        $photos = $this->photo_count( $product ); update_post_meta( $product->get_id(), self::META_PHOTO_COUNT, $photos );
        if ( $photos < 3 ) { $blocked = true; $issues[] = 'Adicione pelo menos 3 fotos únicas antes de publicar/reativar.'; }
        if ( ! $product->get_name() || strlen( self::normalize_title( $product->get_name() ) ) < 5 ) { $blocked = true; $issues[] = 'Título ausente ou curto demais.'; }
        $category_id = $payload['category_id'] ?? get_post_meta( $product->get_id(), '_mpap_ml_category_id', true ) ?: mpap_get_settings( 'default_category_id', '' );
        if ( ! $category_id ) { $blocked = true; $issues[] = 'Categoria Mercado Livre não definida.'; }
        foreach ( $this->find_regulatory_attributes( $category_id ) as $attr ) {
            $has = trim( (string) get_post_meta( $product->get_id(), self::META_ANATEL, true ) ) !== '';
            if ( ! $has && ! empty( $attr['mpap_required'] ) ) { $blocked = true; $issues[] = 'Homologação/Certificação Anatel obrigatória ausente.'; }
            elseif ( ! $has ) { $recommendations[] = 'Preencher Homologação/Certificação Anatel recomendada para a categoria.'; }
        }
        if ( $this->has_immediate_stock( $product ) && ! $this->is_made_to_order( $product ) ) { $recommendations[] = 'Prazo de disponibilidade MANUFACTURING_TIME será omitido quando presente.'; }
        $recommendations[] = 'Avaliar ativação de parcelas sem juros na conta Mercado Livre/Mercado Pago; o plugin não envia campos falsos no item.';
        $score = max( 0, 100 - ( count( $issues ) * 25 ) - ( count( $recommendations ) * 5 ) );
        $status = $blocked ? 'blocked' : ( $recommendations ? 'approved_with_recommendations' : 'approved' );
        update_post_meta( $product->get_id(), self::META_STATUS, $status ); update_post_meta( $product->get_id(), self::META_SCORE, $score ); update_post_meta( $product->get_id(), self::META_DIAG, $issues ); update_post_meta( $product->get_id(), self::META_RECS, $recommendations ); update_post_meta( $product->get_id(), self::META_BLOCKED, $blocked ? implode( '; ', $issues ) : '' );
        return array( 'status'=>$status, 'score'=>$score, 'blocked'=>$blocked, 'issues'=>$issues, 'recommendations'=>$recommendations, 'duplicate'=>$dup, 'photo_count'=>$photos );
    }

    public function diagnose_item( $product_id ) {
        $product = wc_get_product( $product_id ); if ( ! $product ) { return array(); }
        $item_id = get_post_meta( $product_id, '_mpap_ml_item_id', true ); $item = $item_id && $this->api ? $this->api->get_item( $item_id ) : array();
        $local = $this->validate( $product, array(), 'diagnostic' ); $actions = $local['issues'];
        if ( is_wp_error( $item ) ) { $actions[] = 'Erro ao consultar item: ' . $item->get_error_message(); $item = array(); }
        return array( 'product_id'=>$product_id, 'item_id'=>$item_id, 'ml_status'=>$item['status'] ?? '', 'sub_status'=>$item['sub_status'] ?? array(), 'health'=>$item['health'] ?? '', 'tags'=>$item['tags'] ?? array(), 'warnings'=>$item['warnings'] ?? array(), 'quality'=>$local, 'actions'=>$actions );
    }
}
