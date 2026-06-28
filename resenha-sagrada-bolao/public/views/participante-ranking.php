<?php if (!defined('ABSPATH')) exit; ?>
<div class="rsb-public rsb-section">
    <h3>Ranking Geral</h3>
    <div class="rsb-table-wrap">
        <table>
            <thead><tr><th>Posicao</th><th>Mau elemento</th><th>Pontos</th><th>Premiacao</th></tr></thead>
            <tbody><?php foreach($ranking as $r): ?><tr><td><?php echo esc_html($r->posicao); ?></td><td><?php echo esc_html($r->nome); ?></td><td><?php echo esc_html($r->total_pontos); ?></td><td><?php echo esc_html(rsb_money($r->premiacao_estimada)); ?></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
</div>
