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
    $notice = get_transient('rsb_update_bracket_notice_' . get_current_user_id());
    delete_transient('rsb_update_bracket_notice_' . get_current_user_id());
    $api = is_array($notice) && isset($notice['api']) ? (array) $notice['api'] : null;
    $warnings = is_array($notice) && isset($notice['warnings']) ? (array) $notice['warnings'] : (array) $notice;
    $api_message = $api ? ' API: ' . (int) ($api['updated'] ?? 0) . ' placares importados, ' . (int) ($api['skipped'] ?? 0) . ' ignorados.' : '';
    echo '<div class="notice notice-success"><p>Confrontos atualizados.' . esc_html($api_message) . ' Jogos futuros com selecoes definidas ficam liberados para palpites ate 1 hora antes da partida.</p></div>';
    if ($api && !empty($api['errors'])) {
        echo '<div class="notice notice-warning"><p>' . esc_html(implode(' ', (array) $api['errors'])) . '</p></div>';
    }
    if ($warnings) {
        echo '<div class="notice notice-warning"><p>' . esc_html(implode(' ', (array) $warnings)) . '</p></div>';
    }
}
?>
<div class="wrap rsb-wrap">
    <h1>Atualizar confrontos</h1>
    <?php if (!$bolao) : ?>
        <div class="rsb-empty-state">Configure um bolao antes de atualizar confrontos.</div>
    <?php else : ?>
        <div class="rsb-grid">
            <div class="rsb-card"><span>Jogos cadastrados</span><strong><?php echo esc_html(count($jogos)); ?></strong></div>
            <div class="rsb-card"><span>Com selecoes definidas</span><strong><?php echo esc_html($definidos); ?></strong></div>
            <div class="rsb-card"><span>Aguardando classificados</span><strong><?php echo esc_html($aguardando); ?></strong></div>
        </div>
        <div class="rsb-panel">
            <h2>Atualizar mata-mata automaticamente</h2>
            <p>Clique para buscar placares na API gratuita configurada e recalcular os classificados. A rotina preserva placares ja preenchidos, palpites e selecoes reais preenchidas manualmente.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('rsb_update_bracket_matches'); ?>
                <input type="hidden" name="action" value="rsb_update_bracket_matches">
                <button class="button button-primary" type="submit">Buscar API e atualizar confrontos</button>
            </form>
        </div>
    <?php endif; ?>
</div>
