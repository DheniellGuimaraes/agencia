<?php if (!defined('ABSPATH')) exit; if(!isset($ranking)){ $ranking=RSB_Ranking::get((int)$bolao->id); } ?>
<table class="widefat striped rsb-table"><thead><tr><th>Posição</th><th>Participante</th><th>Pontos</th><th>Exatos</th><th>Resultado</th><th>Aproveitamento</th><th>Premiação</th></tr></thead><tbody>
<?php if(!$ranking): ?><tr><td colspan="7">Sem ranking calculado.</td></tr><?php endif; ?>
<?php foreach($ranking as $r): ?><tr><td><?php echo esc_html($r->posicao); ?></td><td><?php echo esc_html($r->nome); ?></td><td><?php echo esc_html($r->total_pontos); ?></td><td><?php echo esc_html($r->placares_exatos); ?></td><td><?php echo esc_html($r->resultados_corretos); ?></td><td><?php echo esc_html(number_format((float)$r->aproveitamento,2,',','.')); ?>%</td><td><?php echo esc_html(rsb_money($r->premiacao_estimada)); ?></td></tr><?php endforeach; ?>
</tbody></table>
