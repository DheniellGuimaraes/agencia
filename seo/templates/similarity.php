<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap ses-admin"><h1>Similaridade</h1><div class="ses-card"><p>Limite atual: <?php echo esc_html(absint($settings['similarity_limit'] ?? 75)); ?>%</p><p>As páginas enriquecidas com similaridade acima do limite ficam marcadas com alerta.</p></div></div>
