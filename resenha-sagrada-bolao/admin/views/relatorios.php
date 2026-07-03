<?php
if (!defined('ABSPATH')) { exit; }

$bolao_id = $bolao ? (int) $bolao->id : 0;
$participantes = $bolao_id ? RSB_Participantes::all($bolao_id) : [];
$jogos = $bolao_id ? RSB_Jogos::all($bolao_id) : [];
$selected_jogo_id = isset($_GET['jogo_id']) ? absint(wp_unslash($_GET['jogo_id'])) : 0;
$valid_jogo_ids = array_map(static function($j) { return (int) $j->id; }, $jogos);
if ($selected_jogo_id > 0 && !in_array($selected_jogo_id, $valid_jogo_ids, true)) { $selected_jogo_id = 0; }
$palpites = $bolao_id ? ($selected_jogo_id > 0 ? RSB_Palpites::filtered($bolao_id, ['jogo_id' => $selected_jogo_id]) : RSB_Palpites::all($bolao_id)) : [];
$ranking = $bolao_id ? RSB_Ranking::get($bolao_id) : [];
$paid = array_filter($participantes, static function($p) { return ($p->status_pagamento ?? '') === 'pago'; });

$reports = [
    [
        'tipo' => 'completo',
        'title' => 'Relatorio completo',
        'text' => 'Dados gerais, participantes, pagamentos, jogos, palpites, ranking e vencedores no mesmo PDF.',
        'metric' => count($participantes) . ' participantes',
    ],
    [
        'tipo' => 'dados',
        'title' => 'Dados e pagamentos',
        'text' => 'Resumo do bolao, caixa, premiacao estimada e tabela de participantes.',
        'metric' => count($paid) . ' pagos',
    ],
    [
        'tipo' => 'jogos',
        'title' => 'Jogos oficiais',
        'text' => 'Calendario, fase, grupo, status e resultados cadastrados ate agora.',
        'metric' => count($jogos) . ' jogos',
    ],
    [
        'tipo' => 'palpites',
        'title' => 'Palpites cadastrados',
        'text' => 'Todos os placares enviados, pontos, tipo de acerto e data de atualizacao.',
        'metric' => count($palpites) . ' palpites',
        'filter_game' => true,
    ],
    [
        'tipo' => 'vencedores',
        'title' => 'Vencedores e premiacao',
        'text' => 'Ranking atual, premios estimados e vencedores dos jogos ja finalizados.',
        'metric' => count($ranking) . ' no ranking',
    ],
];
?>
<div class="wrap rsb-wrap">
    <div class="rsb-admin-hero">
        <div>
            <span class="rsb-admin-kicker">PDF premium</span>
            <h1>Relatorios</h1>
            <p>Exporte o bolao com identidade da Resenha Sagrada: logo, cores da Copa/Brasil, tabelas responsivas e layout pronto para salvar como PDF.</p>
        </div>
        <img src="https://www.resenhasagrada.com.br/wp-content/uploads/2026/06/resenha.png" alt="Resenha Sagrada">
    </div>

    <?php if (!$bolao) : ?>
        <div class="rsb-empty-state">Configure um bolao antes de gerar relatorios.</div>
    <?php else : ?>
        <div class="rsb-report-grid">
            <?php foreach ($reports as $report) :
                $args = add_query_arg([
                    'action' => 'rsb_export_report',
                    'tipo' => $report['tipo'],
                    'print' => 1,
                ], admin_url('admin-post.php'));
                if (!empty($report['filter_game']) && $selected_jogo_id > 0) {
                    $args['jogo_id'] = $selected_jogo_id;
                }
                $url = wp_nonce_url($args, 'rsb_export_report');
                ?>
                <article class="rsb-report-card">
                    <span><?php echo esc_html($report['metric']); ?></span>
                    <h2><?php echo esc_html($report['title']); ?></h2>
                    <p><?php echo esc_html($report['text']); ?></p>
                    <?php if (!empty($report['filter_game'])) : ?>
                        <form method="get" style="margin: 10px 0 0;">
                            <input type="hidden" name="page" value="rsb-relatorios">
                            <label for="rsb-relatorio-jogo"><strong>Filtrar por jogo</strong></label>
                            <select id="rsb-relatorio-jogo" name="jogo_id">
                                <option value="0">Todos os jogos</option>
                                <?php foreach ($jogos as $jogo_option) : ?>
                                    <option value="<?php echo esc_attr((int) $jogo_option->id); ?>" <?php selected($selected_jogo_id, (int) $jogo_option->id); ?>><?php echo esc_html(trim(($jogo_option->data_jogo ?: '-') . ' ' . ($jogo_option->hora_jogo ?: '') . ' - ' . $jogo_option->fase . ' - ' . $jogo_option->time_mandante . ' x ' . $jogo_option->time_visitante . ' - ' . $jogo_option->status)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="button" type="submit">Aplicar</button>
                        </form>
                    <?php endif; ?>
                    <a class="button button-primary" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Exportar PDF</a>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="rsb-panel rsb-admin-note">
            <strong>Como funciona:</strong>
            <p>O botao abre uma versao de impressao com a identidade visual do plugin e aciona a janela de impressao. Escolha "Salvar como PDF" no navegador para baixar o arquivo.</p>
        </div>
    <?php endif; ?>
</div>
