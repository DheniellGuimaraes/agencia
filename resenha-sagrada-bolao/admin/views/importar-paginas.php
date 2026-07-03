<?php
if (!defined('ABSPATH')) exit;
$result = null;
if (isset($_POST['rsb_import_pages']) && check_admin_referer('rsb_import_pages')) {
    $overwrite = !empty($_POST['overwrite']);
    $result = RSB_Page_Importer::import($overwrite);
}
$pages = RSB_Page_Importer::pages();
$shortcodes = RSB_Page_Importer::all_shortcode_reference();
?>
<div class="wrap rsb-wrap">
    <h1>Importar Páginas do Bolão</h1>

    <div class="rsb-panel">
        <h2>Gerador automático de páginas públicas</h2>
        <p>Esta rotina cria páginas publicadas no WordPress com todos os shortcodes do plugin. Use para montar rapidamente a experiência do apostador: área principal, palpites, ranking, jogos, premiação, pagamento e regras.</p>
        <?php if($result): ?>
            <div class="notice notice-success"><p>Páginas processadas: <?php echo esc_html($result['created']); ?> criadas, <?php echo esc_html($result['updated']); ?> atualizadas, <?php echo esc_html($result['skipped']); ?> ignoradas.</p></div>
            <table class="widefat striped rsb-table">
                <thead><tr><th>Página</th><th>Status</th><th>Link</th></tr></thead>
                <tbody>
                <?php foreach($result['items'] as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item['title']); ?></td>
                        <td><?php echo esc_html($item['status']); ?></td>
                        <td><?php if(!empty($item['url'])): ?><a href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener">Abrir página</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <form method="post">
            <?php wp_nonce_field('rsb_import_pages'); ?>
            <label><input type="checkbox" name="overwrite" value="1"> Atualizar conteúdo das páginas se elas já existirem</label>
            <p><button class="button button-primary" name="rsb_import_pages" value="1">Criar/Atualizar páginas do bolão</button></p>
        </form>
    </div>

    <div class="rsb-panel">
        <h2>Páginas que serão criadas</h2>
        <table class="widefat striped rsb-table">
            <thead><tr><th>Página</th><th>URL sugerida</th><th>Shortcode</th><th>Objetivo</th></tr></thead>
            <tbody>
            <?php foreach($pages as $p): ?>
                <tr>
                    <td><?php echo esc_html($p['title']); ?></td>
                    <td><code><?php echo esc_html('/'.$p['slug'].'/'); ?></code></td>
                    <td><code><?php echo esc_html($p['shortcode']); ?></code></td>
                    <td><?php echo esc_html($p['description']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="rsb-panel">
        <h2>Todos os shortcodes disponíveis</h2>
        <table class="widefat striped rsb-table">
            <thead><tr><th>Shortcode</th><th>Uso</th></tr></thead>
            <tbody>
            <?php foreach($shortcodes as $code=>$desc): ?>
                <tr><td><code><?php echo esc_html($code); ?></code></td><td><?php echo esc_html($desc); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
