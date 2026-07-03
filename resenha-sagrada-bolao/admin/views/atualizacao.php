<?php
if (!defined('ABSPATH')) { exit; }
$bid = $bolao ? (int) $bolao->id : 0;
$jogos = $bid ? RSB_Jogos::all($bid) : [];
$definidos = 0;
$aguardando = 0;
foreach ($jogos as $jogo) {
    if (($jogo->status ?? '') === 'aguardando_classificados') { $aguardando++; }
    if (RSB_Jogos::has_defined_teams($jogo)) { $definidos++; }
}
if (isset($_GET['rsb_updated'])) {
    $warnings = get_transient('rsb_update_future_notice_' . get_current_user_id());
    delete_transient('rsb_update_future_notice_' . get_current_user_id());
    echo '<div class="notice notice-success"><p>Atualizacao executada. Os jogos futuros com classificados definidos agora ficam disponiveis para palpites ate 1 hora antes da partida.</p></div>';
    if ($warnings) {
        echo '<div class="notice notice-warning"><p>' . esc_html(implode(' ', (array) $warnings)) . '</p></div>';
    }
}
?>
<div class="wrap rsb-wrap">
    <h1>Atualizacao de confrontos e palpites futuros</h1>
    <?php if (!$bolao) : ?>
        <div class="rsb-empty-state">Configure um bolao antes de atualizar confrontos.</div>
    <?php else : ?>
        <div class="rsb-grid">
            <div class="rsb-card"><span>Jogos cadastrados</span><strong><?php echo esc_html(count($jogos)); ?></strong></div>
            <div class="rsb-card"><span>Com selecoes definidas</span><strong><?php echo esc_html($definidos); ?></strong></div>
            <div class="rsb-card"><span>Aguardando classificados</span><strong><?php echo esc_html($aguardando); ?></strong></div>
        </div>
        <div class="rsb-panel">
            <h2>Atualizar proximas fases</h2>
            <p>Use este botao depois de cadastrar resultados oficiais. O sistema autopreenche os classificados futuros com nome e bandeira quando o confronto ja puder ser definido, sem apagar placares ou palpites.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('rsb_update_future_matches'); ?>
                <input type="hidden" name="action" value="rsb_update_future_matches">
                <button class="button button-primary" type="submit">Atualizar confrontos futuros</button>
            </form>
        </div>
    <?php endif; ?>
</div>
