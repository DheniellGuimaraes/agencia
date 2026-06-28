<?php if (!defined('ABSPATH')) exit; $map = RSB_Palpites::indexed_by_jogo($meus_palpites ?? []); ?>
<div class="rsb-public rsb-section"><h3>Jogos disponiveis para o mau elemento palpitar</h3>
<p class="rsb-muted">Voce pode editar ate 1 hora antes do jogo, no horario de Brasilia. Se o mau elemento nao palpitar antes do prazo, perdeu o direito daquele jogo.</p>
<?php if(!is_user_logged_in()): ?><p>Faca login para o mau elemento palpitar.</p><?php elseif(!$participant): ?><p>Mau elemento nao vinculado ao bolao.</p><?php else: ?>
<div class="rsb-bet-tools">
    <div>
        <strong>Palpites em andamento</strong>
        <span class="rsb-save-all-status" aria-live="polite">Autosave ativo a cada 10s.</span>
    </div>
    <button class="button rsb-save-all-bets" type="button">Salvar todos</button>
</div>
<div class="rsb-bet-list">
<?php foreach($jogos as $j): $bettable = RSB_Jogos::is_bettable($j); $status = RSB_Jogos::betting_status($j); $bet = $map[(int)$j->id] ?? null; ?>
    <form class="rsb-card rsb-bet-form" data-rsb-bet-form="1">
        <input type="hidden" name="jogo_id" value="<?php echo esc_attr($j->id); ?>">
        <div class="rsb-match-head"><span><?php echo esc_html(trim(((class_exists('RSB_WorldCup_Format') ? RSB_WorldCup_Format::normalize_phase((string)$j->fase) : $j->fase) ?: 'Jogo').' '.($j->grupo ? '- Grupo '.$j->grupo : '').' '.($j->rodada ? '- '.$j->rodada : ''))); ?></span><span><?php echo esc_html(trim(($j->data_jogo ?: '').' '.($j->hora_jogo ?: ''))); ?></span></div>
        <div class="rsb-match-head"><small><?php echo esc_html($j->local_jogo ?? ''); ?></small><small>Fecha: <?php echo esc_html(rsb_match_lock_label($j)); ?></small></div>
        <div class="rsb-match-row rsb-match-row-flags">
            <strong class="rsb-team-side"><?php echo rsb_flag_html($j->time_mandante); ?><span><?php echo esc_html($j->time_mandante); ?></span></strong>
            <input type="number" min="0" step="1" name="gols_mandante" value="<?php echo esc_attr($bet->palpite_gols_mandante ?? ''); ?>" <?php disabled(!$bettable); ?> required>
            <span>x</span>
            <input type="number" min="0" step="1" name="gols_visitante" value="<?php echo esc_attr($bet->palpite_gols_visitante ?? ''); ?>" <?php disabled(!$bettable); ?> required>
            <strong class="rsb-team-side"><?php echo rsb_flag_html($j->time_visitante); ?><span><?php echo esc_html($j->time_visitante); ?></span></strong>
        </div>
        <div class="rsb-match-foot">
            <?php if($status === 'aguardando_classificados'): ?><span class="rsb-badge">Aguardando classificados</span><?php elseif($status === 'encerrado_1h'): ?><span class="rsb-badge rsb-danger">Encerrado 1h antes</span><?php elseif($status === 'jogo_iniciado'): ?><span class="rsb-badge rsb-danger">Jogo iniciado</span><?php elseif($status === 'finalizado'): ?><span class="rsb-badge rsb-danger">Finalizado</span><?php elseif($bet): ?><span class="rsb-badge">Editavel ate 1h antes</span><?php elseif($bettable): ?><span class="rsb-badge rsb-open">Aberto</span><?php else: ?><span class="rsb-badge rsb-danger">Indisponivel</span><?php endif; ?>
            <span class="rsb-save-status" aria-live="polite"><?php echo $bet ? 'Salvo' : ''; ?></span>
            <button class="button" type="submit" <?php disabled(!$bettable); ?>><?php echo $bet ? 'Atualizar palpite' : 'Salvar palpite'; ?></button>
        </div>
    </form>
<?php endforeach; ?>
</div>
<?php endif; ?></div>
