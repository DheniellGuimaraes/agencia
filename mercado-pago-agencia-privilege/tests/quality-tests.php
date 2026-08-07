<?php
/**
 * Static safety tests for the Mercado Livre quality module.
 * Run from plugin root: php tests/quality-tests.php
 */
$root = dirname( __DIR__ );
$repo = dirname( $root );
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) {
        $failures[] = $message;
        fwrite( STDERR, "FAIL: {$message}\n" );
    } else {
        fwrite( STDOUT, "PASS: {$message}\n" );
    }
};
$read = static function ( $relative ) use ( $root ) {
    $path = $root . '/' . $relative;
    return is_file( $path ) ? file_get_contents( $path ) : '';
};

$bootstrap = $read( 'mercado-pago-agencia-privilege.php' );
$sync      = $read( 'includes/class-mpap-product-sync.php' );
$quality   = $read( 'includes/class-mpap-quality.php' );
$admin     = $read( 'includes/class-mpap-admin.php' );
$api       = $read( 'includes/class-mpap-api.php' );
$js        = $read( 'assets/js/admin.js' );

$assert( false !== strpos( $bootstrap, "includes/class-mpap-quality.php" ), 'bootstrap requires MPAP_Quality before product sync' );
$assert( preg_match( '/new\s+MPAP_Quality\s*\(/', $read( 'includes/class-mpap-plugin.php' ) ), 'plugin container instantiates MPAP_Quality' );
$assert( false !== strpos( $sync, "wp_ajax_mpap_close_all_ml_items" ), 'close-all AJAX hook is registered' );
$assert( false !== strpos( $sync, "check_ajax_referer( 'mpap_admin_nonce'" ) && false !== strpos( $sync, "current_user_can( mpap_capability() )" ), 'sensitive AJAX actions have nonce and capability checks' );
$assert( false !== strpos( $sync, "'ENCERAR' !== \$confirm" ) && false !== strpos( $js, "confirmWord !== 'ENCERAR'" ), 'mass close requires exact ENCERAR confirmation in PHP and JS' );
$assert( false !== strpos( $sync, "'affected_item_ids'" ) && false !== strpos( $sync, "'action'=>'would_close'" ), 'mass close dry-run exposes affected item_id preview' );
$assert( false !== strpos( $sync, "'_mpap_ml_status_before_close'" ) && false !== strpos( $sync, "get_item( \$item_id )" ), 'mass close backs up previous ML status before PUT status=closed' );
$assert( false !== strpos( $sync, "if ( '' === \$item_id )" ), 'mass close skips empty item_id' );
$assert( false !== strpos( $sync, 'public function dry_run_sync_all' ) && false === strpos( substr( $sync, strpos( $sync, 'public function dry_run_sync_all' ), 900 ), 'create_item(' ) && false === strpos( substr( $sync, strpos( $sync, 'public function dry_run_sync_all' ), 900 ), 'update_item(' ), 'dry-run does not call create_item/update_item in its execution block' );
$assert( false !== strpos( $quality, "strtolower( \$clean_url )" ), 'photo uniqueness is keyed by normalized URL, not only attachment ID' );
$assert( false !== strpos( $quality, "\$photos < 3" ), 'quality checklist blocks products with fewer than 3 unique photos' );
$assert( false !== strpos( $quality, "! empty( \$attr['mpap_required'] )" ), 'Anatel blocks only when dynamic category attribute is marked required' );
$assert( false !== strpos( $quality, 'has_immediate_stock' ) && false !== strpos( $quality, "'_mpap_made_to_order'" ), 'MANUFACTURING_TIME removal checks immediate stock and made-to-order flag' );
$assert( false !== strpos( $api, '429 === $status' ) && false !== strpos( $api, '403 === $status' ), 'API wrapper handles 429 backoff and 403 without infinite retry' );
$assert( false !== strpos( $admin, 'Qualidade dos Anúncios' ) && false !== strpos( $admin, 'anúncio encerrado no Mercado Livre pode não ser restaurável' ), 'admin UI exposes quality page and irreversible-close warning' );

$root_zip = $repo . '/mercado-pago-agencia-privilege.zip';
$preview_png = $root . '/assets/img/admin-preview.png';
$assert( ! is_file( $root_zip ), 'root plugin ZIP binary is not committed' );
$assert( ! is_file( $preview_png ), 'admin preview PNG binary is not committed' );
$assert( is_file( $repo . '/scripts/build-plugin-zip.sh' ), 'text build script exists for generating install ZIP locally' );

if ( $failures ) {
    fwrite( STDERR, count( $failures ) . " static test(s) failed.\n" );
    exit( 1 );
}

fwrite( STDOUT, "All static quality tests passed.\n" );
