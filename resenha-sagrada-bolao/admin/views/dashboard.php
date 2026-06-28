<?php if (!defined('ABSPATH')) exit; $bid=(int)$bolao->id; $participantes=RSB_Participantes::all($bid); $jogos=RSB_Jogos::all($bid); $palpites=RSB_Palpites::all($bid); $ranking=RSB_Ranking::get($bid); $total=RSB_Pagamentos::total($bid); ?>
<div class="wrap rsb-wrap"><h1>Resenha Sagrada — Dashboard</h1><div class="rsb-grid">
<div class="rsb-card"><span>Total arrecadado</span><strong><?php echo esc_html(rsb_money($total)); ?></strong></div>
<div class="rsb-card"><span>Participantes</span><strong><?php echo esc_html(count($participantes)); ?></strong></div>
<div class="rsb-card"><span>Pagos</span><strong><?php echo esc_html(RSB_Participantes::count_paid($bid)); ?></strong></div>
<div class="rsb-card"><span>Jogos</span><strong><?php echo esc_html(count($jogos)); ?></strong></div>
<div class="rsb-card"><span>Palpites</span><strong><?php echo esc_html(count($palpites)); ?></strong></div>
<div class="rsb-card"><span>Líder atual</span><strong><?php echo esc_html($ranking[0]->nome ?? 'Aguardando'); ?></strong></div>
</div><p><button class="button button-primary" id="rsb-recalculate">Recalcular Pontuação e Ranking</button></p>
<div class="rsb-panel"><h2>Ranking resumido</h2><?php include RSB_PATH.'admin/views/ranking-table.php'; ?></div></div>
