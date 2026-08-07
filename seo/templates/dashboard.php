<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap ses-admin"><h1>SEO Enrichment Studio</h1><div class="ses-card"><h2>Resumo</h2><p>Use o scanner para identificar URLs elegíveis e o enriquecimento para processar páginas do sitemap que não estejam protegidas.</p><?php if (!empty($stats)) : ?><pre><?php echo esc_html(print_r($stats, true)); ?></pre><?php endif; ?></div></div>
