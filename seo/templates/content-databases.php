<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap ses-admin"><h1>Bases de Conteúdo</h1><div class="ses-card"><p>Categorias usadas para adaptar contexto profissional.</p><pre><?php echo esc_html(print_r($categories ?? array(), true)); ?></pre></div></div>
