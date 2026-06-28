<?php if (!defined('ABSPATH')) exit; ?>
<?php
$premios_visiveis = [
    1 => (float)($premiacao['primeiro'] ?? 0),
    2 => (float)($premiacao['segundo'] ?? 0),
    3 => (float)($premiacao['terceiro'] ?? 0),
];
?>
<div class="rsb-public rsb-section"><h3>Premiação atual</h3><div class="rsb-grid rsb-grid-3"><?php foreach($premios_visiveis as $pos=>$valor): ?><div class="rsb-card"><span><?php echo esc_html($pos); ?>º lugar</span><strong><?php echo esc_html(rsb_money($valor)); ?></strong></div><?php endforeach; ?></div><p class="rsb-muted">Em caso de empate, o sistema soma as posições ocupadas e divide igualmente entre os empatados.</p></div>
