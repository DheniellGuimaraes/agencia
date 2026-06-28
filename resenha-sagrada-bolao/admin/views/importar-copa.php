<?php if (!defined('ABSPATH')) exit; $bid=(int)$bolao->id; $result=null; if(isset($_POST['rsb_import_wc2026']) && check_admin_referer('rsb_import_wc2026')){ $overwrite = !empty($_POST['overwrite']); $result = RSB_WorldCup_2026_Seeder::import($bid, $overwrite); } $preview = RSB_WorldCup_2026_Seeder::schedule(); ?>
<div class="wrap rsb-wrap">
    <h1>Importar Copa do Mundo 2026</h1>
    <div class="rsb-panel">
        <h2>Carga oficial inicial</h2>
        <p>Esta rotina importa os 104 jogos da Copa 2026 para o bolão atual. Os 72 jogos da fase de grupos ficam abertos para palpites. Os jogos de mata-mata ficam como <strong>aguardando classificados</strong> até o administrador trocar os placeholders pelos times classificados.</p>
        <p>Os horários foram convertidos para o padrão de Brasília/São Paulo.</p>
        <?php if($result): ?><div class="notice notice-success"><p>Importação concluída: <?php echo esc_html($result['inserted']); ?> inseridos, <?php echo esc_html($result['updated']); ?> atualizados, <?php echo esc_html($result['skipped']); ?> ignorados, total de <?php echo esc_html($result['total']); ?> jogos.</p></div><?php endif; ?>
        <form method="post">
            <?php wp_nonce_field('rsb_import_wc2026'); ?>
            <label><input type="checkbox" name="overwrite" value="1"> Atualizar jogos já existentes com os dados da tabela oficial</label>
            <p><button class="button button-primary" name="rsb_import_wc2026" value="1">Importar/Atualizar jogos da Copa 2026</button></p>
        </form>
    </div>
    <div class="rsb-panel">
        <h2>Prévia da carga</h2>
        <table class="widefat striped rsb-table"><thead><tr><th>Cód.</th><th>Data BR</th><th>Fase</th><th>Grupo</th><th>Jogo</th><th>Local</th><th>Status inicial</th></tr></thead><tbody>
        <?php foreach($preview as $j): ?><tr><td><?php echo esc_html($j['codigo_jogo']); ?></td><td><?php echo esc_html($j['data_jogo'].' '.$j['hora_jogo']); ?></td><td><?php echo esc_html($j['fase']); ?></td><td><?php echo esc_html($j['grupo']); ?></td><td><?php echo esc_html($j['time_mandante'].' x '.$j['time_visitante']); ?></td><td><?php echo esc_html($j['local_jogo']); ?></td><td><?php echo esc_html($j['status']); ?></td></tr><?php endforeach; ?>
        </tbody></table>
    </div>
</div>
