<?php if (!defined('ABSPATH')) exit; ?>
<div class="rsb-public rsb-section"><h3>Palpites do mau elemento</h3>
<?php if(!$participant): ?><p>Faca login com um usuario vinculado ao bolao.</p><?php elseif(empty($meus_palpites)): ?><p>O mau elemento ainda nao registrou palpites.</p><?php else: ?>
<div class="rsb-table-wrap"><table><thead><tr><th>Jogo</th><th>Palpite do mau elemento</th><th>Resultado</th><th>Pontos</th><th>Status</th></tr></thead><tbody>
<?php foreach($meus_palpites as $p): ?>
<tr>
    <td><span class="rsb-table-match"><span class="rsb-team-pill"><?php echo rsb_flag_html($p->time_mandante); ?><span><?php echo esc_html($p->time_mandante); ?></span></span><strong>x</strong><span class="rsb-team-pill"><?php echo rsb_flag_html($p->time_visitante); ?><span><?php echo esc_html($p->time_visitante); ?></span></span></span></td>
    <td><?php echo esc_html($p->palpite_gols_mandante.' x '.$p->palpite_gols_visitante); ?></td>
    <td><?php echo is_null($p->gols_mandante) ? '-' : esc_html($p->gols_mandante.' x '.$p->gols_visitante); ?></td>
    <td><?php echo esc_html((int)$p->pontos); ?></td>
    <td><?php echo esc_html($p->tipo_acerto ?: $p->status); ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div><?php endif; ?></div>
