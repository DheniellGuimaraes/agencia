<?php if (!defined('ABSPATH')) exit; ?>
<div class="rsb-public rsb-section">
    <h3>Calendario de jogos</h3>
    <div class="rsb-table-wrap">
        <table>
            <thead><tr><th>Data BR</th><th>Fase</th><th>Grupo</th><th>Jogo</th><th>Local</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($jogos as $j): ?>
                <tr>
                    <td><?php echo esc_html(trim(($j->data_jogo ?: '').' '.($j->hora_jogo ?: ''))); ?></td>
                    <td><?php echo esc_html(class_exists('RSB_WorldCup_Format') ? RSB_WorldCup_Format::normalize_phase((string)$j->fase) : $j->fase); ?></td>
                    <td><?php echo esc_html($j->grupo ?: '-'); ?></td>
                    <td>
                        <span class="rsb-table-match">
                            <span class="rsb-team-pill"><?php echo rsb_flag_html($j->time_mandante); ?><span><?php echo esc_html($j->time_mandante); ?></span></span>
                            <strong>x</strong>
                            <span class="rsb-team-pill"><?php echo rsb_flag_html($j->time_visitante); ?><span><?php echo esc_html($j->time_visitante); ?></span></span>
                        </span>
                    </td>
                    <td><?php echo esc_html($j->local_jogo ?? ''); ?></td>
                    <td><?php echo esc_html($j->status); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
