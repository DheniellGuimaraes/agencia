<?php
if (!defined('ABSPATH')) { exit; }

if (isset($_POST['rsb_save_config']) && check_admin_referer('rsb_config')) {
    $_POST['limite_participantes'] = 0;
    RSB_Bolao::update_current($_POST);
    echo '<div class="notice notice-success"><p>Configuracoes salvas.</p></div>';
    $bolao = RSB_Bolao::current();
}
?>
<div class="wrap rsb-wrap">
    <h1>Configuracoes do Bolao</h1>
    <form method="post" class="rsb-form">
        <?php wp_nonce_field('rsb_config'); ?>
        <input type="hidden" name="limite_participantes" value="0">

        <label>Nome <input name="nome" value="<?php echo esc_attr($bolao->nome); ?>"></label>
        <label>Descricao <textarea name="descricao"><?php echo esc_textarea($bolao->descricao); ?></textarea></label>
        <label>Valor inscricao <input type="number" step="0.01" name="valor_inscricao" value="<?php echo esc_attr($bolao->valor_inscricao); ?>"></label>

        <div class="rsb-unlimited-note">
            <strong>Participantes sem limite</strong>
            <span>O cadastro publico nao bloqueia mais em 30 maus elementos. Todos os novos usuarios continuam vinculados ao bolao, com pagamentos, palpites, ranking e relatorios funcionando normalmente.</span>
        </div>

        <label>Status <select name="status"><option value="ativo" <?php selected($bolao->status,'ativo'); ?>>Ativo</option><option value="encerrado" <?php selected($bolao->status,'encerrado'); ?>>Encerrado</option></select></label>
        <label>Pontos placar exato <input type="number" name="pontos_placar_exato" value="<?php echo esc_attr($bolao->pontos_placar_exato); ?>"></label>
        <label>Pontos resultado correto <input type="number" name="pontos_resultado_correto" value="<?php echo esc_attr($bolao->pontos_resultado_correto); ?>"></label>
        <label>% 1o lugar <input type="number" step="0.01" name="percentual_primeiro" value="<?php echo esc_attr($bolao->percentual_primeiro); ?>"></label>
        <label>% 2o lugar <input type="number" step="0.01" name="percentual_segundo" value="<?php echo esc_attr($bolao->percentual_segundo); ?>"></label>
        <label>% 3o lugar <input type="number" step="0.01" name="percentual_terceiro" value="<?php echo esc_attr($bolao->percentual_terceiro); ?>"></label>
        <p><button class="button button-primary" name="rsb_save_config" value="1">Salvar</button></p>
    </form>
</div>
