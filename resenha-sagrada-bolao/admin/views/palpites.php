<?php
if (!defined('ABSPATH')) { exit; }

$bolao_id = $bolao ? (int) $bolao->id : 0;
$participantes = $bolao_id ? RSB_Participantes::all($bolao_id) : [];
$jogos = $bolao_id ? RSB_Jogos::all($bolao_id) : [];

$filters = [
    'participante_id' => isset($_GET['participante_id']) ? absint($_GET['participante_id']) : 0,
    'jogo_id' => isset($_GET['jogo_id']) ? absint($_GET['jogo_id']) : 0,
    'busca' => isset($_GET['busca']) ? sanitize_text_field(wp_unslash($_GET['busca'])) : '',
];
$items = $bolao_id ? RSB_Palpites::filtered($bolao_id, $filters) : [];
?>
<div class="wrap rsb-wrap">
    <div class="rsb-admin-hero rsb-admin-hero-compact">
        <div>
            <span class="rsb-admin-kicker">Controle dos palpites</span>
            <h1>Palpites</h1>
            <p>Filtre por mau elemento, jogo ou termo livre para conferir rapidamente qualquer placar cadastrado.</p>
        </div>
        <strong><?php echo esc_html(count($items)); ?></strong>
    </div>

    <?php if (!$bolao) : ?>
        <div class="rsb-empty-state">Configure um bolao antes de listar palpites.</div>
    <?php else : ?>
        <form class="rsb-filter-panel" method="get">
            <input type="hidden" name="page" value="rsb-palpites">
            <label>
                <span>Participante</span>
                <select name="participante_id">
                    <option value="0">Todos</option>
                    <?php foreach ($participantes as $participante) : ?>
                        <option value="<?php echo esc_attr($participante->id); ?>" <?php selected($filters['participante_id'], (int) $participante->id); ?>>
                            <?php echo esc_html($participante->nome); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Jogo</span>
                <select name="jogo_id">
                    <option value="0">Todos</option>
                    <?php foreach ($jogos as $jogo) :
                        $label = trim(($jogo->codigo_jogo ? $jogo->codigo_jogo . ' - ' : '') . $jogo->time_mandante . ' x ' . $jogo->time_visitante);
                        ?>
                        <option value="<?php echo esc_attr($jogo->id); ?>" <?php selected($filters['jogo_id'], (int) $jogo->id); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Busca</span>
                <input type="search" name="busca" value="<?php echo esc_attr($filters['busca']); ?>" placeholder="Nome, email, fase ou selecao">
            </label>
            <div class="rsb-filter-actions">
                <button class="button button-primary" type="submit">Filtrar</button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rsb-palpites')); ?>">Limpar</a>
            </div>
        </form>

        <div class="rsb-table-responsive">
            <table class="widefat striped rsb-table">
                <thead>
                    <tr>
                        <th>Participante</th>
                        <th>Jogo</th>
                        <th>Data</th>
                        <th>Palpite</th>
                        <th>Resultado</th>
                        <th>Pontos</th>
                        <th>Tipo</th>
                        <th>Atualizado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$items) : ?>
                        <tr><td colspan="8">Nenhum palpite encontrado para os filtros aplicados.</td></tr>
                    <?php else : foreach ($items as $i) :
                        $game = trim(($i->codigo_jogo ? $i->codigo_jogo . ' - ' : '') . $i->time_mandante . ' x ' . $i->time_visitante);
                        $result = ($i->gols_mandante === null || $i->gols_visitante === null || $i->gols_mandante === '' || $i->gols_visitante === '') ? '-' : ((int) $i->gols_mandante . ' x ' . (int) $i->gols_visitante);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($i->participante); ?></strong>
                                <?php if (!empty($i->participante_email)) : ?><small><?php echo esc_html($i->participante_email); ?></small><?php endif; ?>
                            </td>
                            <td><?php echo esc_html($game); ?></td>
                            <td><?php echo esc_html(trim(($i->data_jogo ?: '') . ' ' . ($i->hora_jogo ?: '')) ?: '-'); ?></td>
                            <td><span class="rsb-score-pill"><?php echo esc_html((int) $i->palpite_gols_mandante . ' x ' . (int) $i->palpite_gols_visitante); ?></span></td>
                            <td><?php echo esc_html($result); ?></td>
                            <td><?php echo esc_html($i->pontos); ?></td>
                            <td><?php echo esc_html($i->tipo_acerto); ?></td>
                            <td><?php echo esc_html($i->updated_at ?: $i->created_at); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
