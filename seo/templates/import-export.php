<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap ses-admin">
    <h1>Importar/Exportar enriquecimentos</h1>
    <p>Use esta tela para gerar o enriquecimento em um ambiente mais rápido, baixar um JSON portátil e importar o resultado no site de produção sem processar tudo lá.</p>

    <?php if (isset($_GET['imported'])) : ?>
        <div class="notice notice-success"><p>
            Importação concluída. Processados: <?php echo esc_html(absint($_GET['processed'] ?? 0)); ?>,
            importados: <?php echo esc_html(absint($_GET['imported'] ?? 0)); ?>,
            ignorados: <?php echo esc_html(absint($_GET['skipped'] ?? 0)); ?>,
            erros: <?php echo esc_html(absint($_GET['errors'] ?? 0)); ?>.
        </p></div>
    <?php elseif (isset($_GET['import_error'])) : ?>
        <div class="notice notice-error"><p>Não foi possível importar o arquivo informado.</p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['import_job'])) : ?>
        <div class="ses-card" id="ses-import-job" data-job-id="<?php echo esc_attr(sanitize_key($_GET['import_job'])); ?>">
            <h2>Importação premium em lotes</h2>
            <p>O arquivo foi recebido. A importação será processada em pequenos lotes via AJAX para evitar timeout 524.</p>
            <p><button id="ses-import-start" type="button" class="button button-primary">Continuar importação</button> <button id="ses-import-stop" type="button" class="button" disabled>Pausar</button></p>
            <div class="ses-progress ses-import-progress"><span></span></div>
            <p id="ses-import-status">Aguardando início.</p>
        </div>
    <?php endif; ?>

    <div class="ses-card">
        <h2>1. Exportar conteúdo já enriquecido</h2>
        <p>Baixa um JSON com todas as páginas que já possuem <code>_ses_enriched_html</code>.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ses_export_enriched'); ?>
            <input type="hidden" name="action" value="ses_export_enriched">
            <?php submit_button('Baixar JSON enriquecido', 'primary', 'submit', false); ?>
        </form>
    </div>

    <div class="ses-card">
        <h2>2. Gerar lote aqui e exportar</h2>
        <p>Processa páginas elegíveis neste ambiente e já baixa o pacote JSON para importar no outro site.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ses_export_generated'); ?>
            <input type="hidden" name="action" value="ses_export_generated">
            <label for="ses-export-limit">Limite do lote</label>
            <input id="ses-export-limit" type="number" name="limit" value="100" min="1" max="500">
            <?php submit_button('Gerar e baixar JSON', 'secondary', 'submit', false); ?>
        </form>
    </div>

    <div class="ses-card">
        <h2>3. Importar no site de destino</h2>
        <p>O importador localiza as páginas por caminho/slug, respeita páginas protegidas e grava metadados, Yoast e conteúdo enriquecido.</p>
        <p><strong>Modo anti-timeout 524:</strong> após enviar o arquivo, o processamento continua em lotes pequenos via AJAX. Não processe um JSON gigante em uma única requisição.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('ses_import_enriched'); ?>
            <input type="hidden" name="action" value="ses_import_enriched">
            <p><input type="file" name="ses_import_file" accept="application/json,.json" required></p>
            <p>
                <label><input type="radio" name="import_mode" value="meta" checked> Apenas metadado seguro</label><br>
                <label><input type="radio" name="import_mode" value="shortcode"> Inserir shortcode no conteúdo</label><br>
                <label><input type="radio" name="import_mode" value="written"> Escrever HTML no conteúdo</label>
            </p>
            <?php submit_button('Importar JSON', 'primary', 'submit', false); ?>
        </form>
    </div>
</div>
