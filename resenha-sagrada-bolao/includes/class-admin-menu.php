<?php
if (!defined('ABSPATH')) { exit; }

class RSB_Admin_Menu {
    public static function register(): void {
        add_menu_page('Resenha Sagrada', 'Resenha Sagrada', rsb_admin_cap(), 'rsb-dashboard', [__CLASS__, 'page_dashboard'], 'dashicons-awards', 26);

        $pages = [
            'configuracoes' => 'Configuracoes',
            'participantes' => 'Participantes',
            'pagamentos' => 'Pagamentos',
            'jogos' => 'Jogos Oficiais',
            'importar-copa' => 'Importar Copa 2026',
            'importar-paginas' => 'Importar Paginas',
            'atualizar-confrontos' => 'Atualizar confrontos',
            'palpites' => 'Palpites',
            'resultados' => 'Resultados',
            'ranking' => 'Ranking',
            'premiacao' => 'Premiacao',
            'relatorios' => 'Relatorios',
        ];

        foreach ($pages as $slug => $title) {
            add_submenu_page('rsb-dashboard', $title, $title, rsb_admin_cap(), 'rsb-' . $slug, function() use ($slug) {
                self::render($slug);
            });
        }
    }

    public static function page_dashboard(): void {
        self::render('dashboard');
    }

    public static function render(string $view): void {
        if (!current_user_can(rsb_admin_cap())) {
            wp_die(esc_html__('Acesso negado.', 'resenha-sagrada-bolao'));
        }

        $bolao = RSB_Bolao::current();
        include RSB_PATH . 'admin/views/' . $view . '.php';
    }



    public static function update_bracket_matches(): void {
        if (!current_user_can(rsb_admin_cap())) {
            wp_die(esc_html__('Acesso negado.', 'resenha-sagrada-bolao'));
        }
        check_admin_referer('rsb_update_bracket_matches');
        $bolao = RSB_Bolao::current();
        $warnings = [];
        $api_summary = null;
        if ($bolao && class_exists('RSB_WorldCup_2026_API')) {
            $api_summary = RSB_WorldCup_2026_API::sync_results((int) $bolao->id);
        }
        if ($bolao && class_exists('RSB_Copa2026_Bracket')) {
            $warnings = RSB_Copa2026_Bracket::recalculate_bracket((int) $bolao->id);
            set_transient('rsb_update_bracket_notice_' . get_current_user_id(), ['api'=>$api_summary, 'warnings'=>$warnings], 60);
        }
        wp_safe_redirect(admin_url('admin.php?page=rsb-atualizar-confrontos&rsb_updated=1'));
        exit;
    }

    public static function recalculate_bracket(): void {
        if (!current_user_can(rsb_admin_cap())) {
            wp_die(esc_html__('Acesso negado.', 'resenha-sagrada-bolao'));
        }
        check_admin_referer('rsb_recalculate_bracket');
        $bolao = RSB_Bolao::current();
        if ($bolao && class_exists('RSB_Copa2026_Bracket')) {
            $warnings = RSB_Copa2026_Bracket::recalculate_bracket((int) $bolao->id);
            set_transient('rsb_bracket_notice_' . get_current_user_id(), $warnings, 60);
        }
        wp_safe_redirect(admin_url('admin.php?page=rsb-resultados&rsb_bracket=1'));
        exit;
    }

    public static function export_report(): void {
        if (!current_user_can(rsb_admin_cap())) {
            wp_die(esc_html__('Acesso negado.', 'resenha-sagrada-bolao'));
        }

        check_admin_referer('rsb_export_report');

        $bolao = RSB_Bolao::current();
        if (!$bolao) {
            wp_die(esc_html__('Bolao nao encontrado.', 'resenha-sagrada-bolao'));
        }

        $types = [
            'completo' => 'Relatorio completo',
            'dados' => 'Dados e pagamentos',
            'jogos' => 'Jogos oficiais',
            'palpites' => 'Palpites cadastrados',
            'vencedores' => 'Vencedores e premiacao',
        ];
        $tipo = isset($_GET['tipo']) ? sanitize_key(wp_unslash($_GET['tipo'])) : 'completo';
        if (!isset($types[$tipo])) {
            $tipo = 'completo';
        }

        $bolao_id = (int) $bolao->id;
        RSB_Ranking::recalculate_all_scores($bolao_id);
        $participantes = RSB_Participantes::all($bolao_id);
        $jogos = RSB_Jogos::all($bolao_id);
        $selected_jogo_id = isset($_GET['jogo_id']) ? absint(wp_unslash($_GET['jogo_id'])) : 0;
        $valid_jogo_ids = array_map(static function($j) { return (int) $j->id; }, $jogos);
        if ($selected_jogo_id > 0 && !in_array($selected_jogo_id, $valid_jogo_ids, true)) {
            $selected_jogo_id = 0;
        }
        $palpites = $selected_jogo_id > 0 ? RSB_Palpites::filtered($bolao_id, ['jogo_id' => $selected_jogo_id]) : RSB_Palpites::all($bolao_id);
        $selected_jogo = null;
        foreach ($jogos as $jogo_option) { if ((int) $jogo_option->id === $selected_jogo_id) { $selected_jogo = $jogo_option; break; } }
        $participant_stats = RSB_Palpites::stats_by_participant($bolao_id);
        $game_stats = RSB_Palpites::stats_by_game($bolao_id);
        $stats_by_participant = [];
        foreach ($participant_stats as $stat) {
            $stats_by_participant[(int) $stat->participante_id] = $stat;
        }
        $ranking = RSB_Ranking::get($bolao_id);
        $premiacao = RSB_Premiacao::summary($bolao_id);
        $pagos = array_filter($participantes, static function($p) {
            return ($p->status_pagamento ?? '') === 'pago';
        });
        $ativos = array_filter($participantes, static function($p) {
            return (int) ($p->ativo ?? 0) === 1;
        });
        $active_count = count($ativos);
        $finalizados = array_filter($jogos, static function($j) {
            return ($j->status ?? '') === 'finalizado';
        });

        $format_phase = static function($fase): string {
            $fase = (string) $fase;
            return class_exists('RSB_WorldCup_Format') ? RSB_WorldCup_Format::normalize_phase($fase) : $fase;
        };
        $game_label = static function($jogo): string {
            return trim(($jogo->codigo_jogo ? $jogo->codigo_jogo . ' - ' : '') . $jogo->time_mandante . ' x ' . $jogo->time_visitante);
        };
        $game_result = static function($jogo): string {
            if ($jogo->gols_mandante === null || $jogo->gols_visitante === null || $jogo->gols_mandante === '' || $jogo->gols_visitante === '') {
                return '-';
            }
            return (int) $jogo->gols_mandante . ' x ' . (int) $jogo->gols_visitante;
        };
        $game_winner = static function($jogo): string {
            if (($jogo->status ?? '') !== 'finalizado' || $jogo->gols_mandante === null || $jogo->gols_visitante === null) {
                return '-';
            }
            $gm = (int) $jogo->gols_mandante;
            $gv = (int) $jogo->gols_visitante;
            if ($gm === $gv) {
                return 'Empate';
            }
            return $gm > $gv ? (string) $jogo->time_mandante : (string) $jogo->time_visitante;
        };

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));

        $logo = 'https://www.resenhasagrada.com.br/wp-content/uploads/2026/06/resenha.png';
        $printed_at = rsb_brasilia_datetime(time());
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($types[$tipo]); ?> - Resenha Sagrada</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Maven+Pro:wght@400;500;600;700;800;900&display=swap');
        :root{--green:#004b24;--green2:#002f1a;--yellow:#ffd500;--blue:#0057b8;--ink:#07140d;--muted:#5d6b62}
        *{box-sizing:border-box}body{margin:0;background:linear-gradient(135deg,#002514,#006633 52%,#0047a0);font-family:'Maven Pro',Arial,sans-serif;color:var(--ink)}
        .shell{width:min(1180px,calc(100% - 32px));margin:28px auto;background:#f8fbf8;border-radius:28px;overflow:hidden;box-shadow:0 28px 90px rgba(0,0,0,.35)}
        .hero{position:relative;padding:34px 38px;color:#fff;background:radial-gradient(circle at 86% 18%,rgba(255,213,0,.34),transparent 28%),linear-gradient(135deg,#002b17,#005c2d 58%,#004fb0);overflow:hidden}
        .hero:before{content:"";position:absolute;inset:-40px;background:linear-gradient(110deg,transparent 12%,rgba(255,213,0,.18) 12% 18%,transparent 18% 35%,rgba(0,87,184,.26) 35% 44%,transparent 44%);opacity:.75}
        .brand,.actions{position:relative;z-index:1}.brand{display:flex;align-items:center;justify-content:space-between;gap:22px}.brand img{width:min(280px,58vw);height:auto;filter:drop-shadow(0 18px 28px rgba(0,0,0,.35))}
        .badge{display:inline-flex;border:1px solid rgba(255,213,0,.55);border-radius:999px;padding:9px 14px;color:var(--yellow);font-weight:900;text-transform:uppercase;letter-spacing:.05em;background:rgba(0,0,0,.22)}
        h1{position:relative;z-index:1;margin:26px 0 8px;font-size:clamp(32px,5vw,62px);line-height:.95;text-transform:uppercase;color:#fff}.hero p{position:relative;z-index:1;margin:0;color:#e9fff0;font-weight:700}
        .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.button{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:14px;padding:12px 16px;background:var(--yellow);color:#042514;font-weight:900;text-decoration:none;cursor:pointer}.button.secondary{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.28)}
        .content{padding:26px 30px 34px}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:22px}.stat{border-radius:18px;padding:18px;background:linear-gradient(145deg,#fff,rgba(255,255,255,.72));border:1px solid rgba(0,75,36,.12);box-shadow:0 12px 32px rgba(0,0,0,.08)}.stat span{display:block;color:var(--muted);font-size:12px;font-weight:900;text-transform:uppercase}.stat strong{display:block;margin-top:6px;color:var(--green);font-size:28px}
        section{break-inside:avoid;margin-top:26px}.section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:10px;border-bottom:2px solid rgba(0,75,36,.14);padding-bottom:8px}h2{margin:0;color:var(--green);font-size:23px}.meta{color:var(--muted);font-weight:800;font-size:12px;text-transform:uppercase}
        table{width:100%;border-collapse:collapse;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 26px rgba(0,0,0,.06)}th,td{padding:10px 11px;border-bottom:1px solid #e6eee8;text-align:left;font-size:13px;vertical-align:top}th{background:#002f1a;color:#fff;text-transform:uppercase;font-size:11px;letter-spacing:.04em}tr:nth-child(even) td{background:#f7faf7}.pill{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;background:#ecfdf3;color:#006633;font-weight:900}.money{color:#0057b8;font-weight:900}.empty{padding:18px;border-radius:16px;background:#fff7cc;color:#5b4700;font-weight:800}
        @media(max-width:760px){.shell{width:100%;margin:0;border-radius:0}.brand{align-items:flex-start;flex-direction:column}.content{padding:18px}.stats{grid-template-columns:1fr 1fr}th,td{font-size:12px}.table-scroll{overflow-x:auto}table{min-width:760px}}
        @media print{body{background:#fff}.shell{width:100%;margin:0;border-radius:0;box-shadow:none}.actions{display:none}.hero{padding:24px 28px}.content{padding:18px 22px}section{page-break-inside:avoid}.stats{grid-template-columns:repeat(4,1fr)}}
    </style>
</head>
<body>
<main class="shell">
    <header class="hero">
        <div class="brand">
            <img src="<?php echo esc_url($logo); ?>" alt="Resenha Sagrada">
            <span class="badge">Bolao da Copa 26</span>
        </div>
        <h1><?php echo esc_html($types[$tipo]); ?></h1>
        <p><?php echo esc_html($bolao->nome); ?> - Gerado em <?php echo esc_html($printed_at); ?>.</p>
        <div class="actions">
            <button class="button" type="button" onclick="window.print()">Salvar PDF / imprimir</button>
            <a class="button secondary" href="<?php echo esc_url(admin_url('admin.php?page=rsb-relatorios')); ?>">Voltar aos relatorios</a>
        </div>
    </header>
    <div class="content">
        <div class="stats">
            <div class="stat"><span>Participantes</span><strong><?php echo esc_html(count($participantes)); ?></strong></div>
            <div class="stat"><span>Pagos</span><strong><?php echo esc_html(count($pagos)); ?></strong></div>
            <div class="stat"><span>Jogos</span><strong><?php echo esc_html(count($jogos)); ?></strong></div>
            <div class="stat"><span>Palpites</span><strong><?php echo esc_html(count($palpites)); ?></strong></div>
        </div>

        <?php if ($tipo === 'completo' || $tipo === 'dados') : ?>
        <section>
            <div class="section-head"><h2>Dados gerais</h2><span class="meta">configuracao e caixa</span></div>
            <div class="table-scroll"><table>
                <tbody>
                    <tr><th>Bolao</th><td><?php echo esc_html($bolao->nome); ?></td><th>Status</th><td><?php echo esc_html($bolao->status); ?></td></tr>
                    <tr><th>Valor inscricao</th><td><?php echo esc_html(rsb_money($bolao->valor_inscricao)); ?></td><th>Total arrecadado</th><td class="money"><?php echo esc_html(rsb_money(RSB_Pagamentos::total($bolao_id))); ?></td></tr>
                    <tr><th>1o lugar</th><td><?php echo esc_html(rsb_money($premiacao['primeiro'] ?? 0)); ?></td><th>2o / 3o lugar</th><td><?php echo esc_html(rsb_money($premiacao['segundo'] ?? 0)); ?> / <?php echo esc_html(rsb_money($premiacao['terceiro'] ?? 0)); ?></td></tr>
                    <tr><th>Pontuacao</th><td colspan="3">Placar exato: <?php echo esc_html($bolao->pontos_placar_exato); ?> | Resultado correto: <?php echo esc_html($bolao->pontos_resultado_correto); ?> | Erro: <?php echo esc_html($bolao->pontos_erro); ?></td></tr>
                </tbody>
            </table></div>
        </section>
        <section>
            <div class="section-head"><h2>Participantes</h2><span class="meta"><?php echo esc_html(count($participantes)); ?> registros</span></div>
            <div class="table-scroll"><table>
                <thead><tr><th>Nome</th><th>Email</th><th>Telefone</th><th>Pagamento</th><th>Valor</th><th>Data</th><th>Palpites reais</th><th>Ultimo palpite</th></tr></thead>
                <tbody><?php foreach ($participantes as $p) : ?><tr>
                    <td><?php echo esc_html($p->nome); ?></td>
                    <td><?php echo esc_html($p->email); ?></td>
                    <td><?php echo esc_html($p->telefone); ?></td>
                    <td><span class="pill"><?php echo esc_html($p->status_pagamento); ?></span></td>
                    <td><?php echo esc_html(rsb_money($p->valor_pago)); ?></td>
                    <td><?php echo esc_html($p->data_pagamento ?: '-'); ?></td>
                    <td><?php echo esc_html((int) ($stats_by_participant[(int) $p->id]->total_palpites ?? 0)); ?></td>
                    <td><?php echo esc_html($stats_by_participant[(int) $p->id]->ultimo_palpite ?? '-'); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        </section>
        <?php endif; ?>

        <?php if (in_array($tipo, ['completo', 'dados', 'palpites', 'vencedores'], true)) : ?>
        <section>
            <div class="section-head"><h2>Conferencia de palpites por participante</h2><span class="meta">contagem direta da tabela de palpites</span></div>
            <div class="table-scroll"><table>
                <thead><tr><th>Participante</th><th>Email</th><th>Status</th><th>Palpites reais</th><th>Jogos cadastrados</th><th>Faltantes</th><th>Pontos atuais</th><th>Ultimo palpite</th></tr></thead>
                <tbody><?php foreach ($participant_stats as $stat) :
                    $faltantes = max(0, count($jogos) - (int) $stat->total_palpites);
                    ?>
                    <tr>
                        <td><?php echo esc_html($stat->participante); ?></td>
                        <td><?php echo esc_html($stat->participante_email ?: '-'); ?></td>
                        <td><span class="pill"><?php echo (int) $stat->ativo === 1 ? 'Ativo' : 'Inativo'; ?></span></td>
                        <td><strong><?php echo esc_html((int) $stat->total_palpites); ?></strong></td>
                        <td><?php echo esc_html(count($jogos)); ?></td>
                        <td><?php echo esc_html($faltantes); ?></td>
                        <td><?php echo esc_html((int) $stat->total_pontos); ?></td>
                        <td><?php echo esc_html($stat->ultimo_palpite ?: '-'); ?></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        </section>
        <?php endif; ?>

        <?php if ($tipo === 'completo' || $tipo === 'jogos') : ?>
        <section>
            <div class="section-head"><h2>Jogos oficiais</h2><span class="meta"><?php echo esc_html(count($jogos)); ?> jogos</span></div>
            <div class="table-scroll"><table>
                <thead><tr><th>Codigo</th><th>Fase</th><th>Grupo</th><th>Inicio BRT</th><th>Fecha palpites BRT</th><th>Jogo</th><th>Resultado</th><th>Status jogo</th><th>Status palpite</th></tr></thead>
                <tbody><?php foreach ($jogos as $j) : $audit = RSB_Jogos::lock_audit($j); ?><tr>
                    <td><?php echo esc_html($j->codigo_jogo); ?></td>
                    <td><?php echo esc_html($format_phase($j->fase)); ?></td>
                    <td><?php echo esc_html($j->grupo ?: '-'); ?></td>
                    <td><?php echo esc_html($audit['game_label']); ?></td>
                    <td><?php echo esc_html($audit['lock_label']); ?></td>
                    <td><?php echo esc_html($game_label($j)); ?></td>
                    <td><?php echo esc_html($game_result($j)); ?></td>
                    <td><?php echo esc_html($j->status); ?></td>
                    <td><?php echo esc_html($audit['status']); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        </section>
        <?php endif; ?>

        <?php if (in_array($tipo, ['completo', 'jogos', 'palpites'], true)) : ?>
        <section>
            <div class="section-head"><h2>Conferencia de palpites por jogo</h2><span class="meta">ativos: <?php echo esc_html($active_count); ?></span></div>
            <div class="table-scroll"><table>
                <thead><tr><th>Codigo</th><th>Jogo</th><th>Inicio BRT</th><th>Fecha BRT</th><th>Palpites</th><th>Faltantes ativos</th><th>Status palpite</th><th>Ultimo palpite</th></tr></thead>
                <tbody><?php foreach ($game_stats as $stat) :
                    $audit = RSB_Jogos::lock_audit($stat);
                    $faltantes = max(0, $active_count - (int) $stat->participantes_com_palpite);
                    ?>
                    <tr>
                        <td><?php echo esc_html($stat->codigo_jogo); ?></td>
                        <td><?php echo esc_html(trim($stat->time_mandante . ' x ' . $stat->time_visitante)); ?></td>
                        <td><?php echo esc_html($audit['game_label']); ?></td>
                        <td><?php echo esc_html($audit['lock_label']); ?></td>
                        <td><strong><?php echo esc_html((int) $stat->total_palpites); ?></strong></td>
                        <td><?php echo esc_html($faltantes); ?></td>
                        <td><?php echo esc_html($audit['status']); ?></td>
                        <td><?php echo esc_html($stat->ultimo_palpite ?: '-'); ?></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        </section>
        <?php endif; ?>

        <?php if ($tipo === 'completo' || $tipo === 'palpites') : ?>
        <section>
            <div class="section-head"><h2>Palpites cadastrados</h2><span class="meta"><?php echo esc_html(count($palpites)); ?> palpites<?php echo $selected_jogo ? esc_html(' - '.$game_label($selected_jogo)) : ""; ?></span></div>
            <?php if (!$palpites) : ?><div class="empty">Ainda nao ha palpites cadastrados.</div><?php else : ?>
            <div class="table-scroll"><table>
                <thead><tr><th>Participante</th><th>Jogo</th><th>Palpite</th><th>Resultado</th><th>Pontos</th><th>Tipo</th><th>Atualizado</th></tr></thead>
                <tbody><?php foreach ($palpites as $p) : ?><tr>
                    <td><?php echo esc_html($p->participante); ?></td>
                    <td><?php echo esc_html($game_label($p)); ?></td>
                    <td><?php echo esc_html((int) $p->palpite_gols_mandante . ' x ' . (int) $p->palpite_gols_visitante); ?></td>
                    <td><?php echo esc_html($game_result($p)); ?></td>
                    <td><?php echo esc_html($p->pontos); ?></td>
                    <td><?php echo esc_html($p->tipo_acerto); ?></td>
                    <td><?php echo esc_html($p->updated_at ?: $p->created_at); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($tipo === 'completo' || $tipo === 'vencedores') : ?>
        <section>
            <div class="section-head"><h2>Vencedores e premiacao</h2><span class="meta">ranking atual</span></div>
            <?php if (!$ranking) : ?><div class="empty">Ranking ainda nao calculado.</div><?php else : ?>
            <div class="table-scroll"><table>
                <thead><tr><th>Posicao</th><th>Participante</th><th>Pontos</th><th>Exatos</th><th>Corretos</th><th>Palpites</th><th>Aproveitamento</th><th>Premio estimado</th></tr></thead>
                <tbody><?php foreach ($ranking as $r) : $live_total = (int) ($stats_by_participant[(int) $r->participante_id]->total_palpites ?? $r->total_palpites); ?><tr>
                    <td><?php echo esc_html($r->posicao); ?></td>
                    <td><?php echo esc_html($r->nome); ?></td>
                    <td><?php echo esc_html($r->total_pontos); ?></td>
                    <td><?php echo esc_html($r->placares_exatos); ?></td>
                    <td><?php echo esc_html($r->resultados_corretos); ?></td>
                    <td><?php echo esc_html($live_total); ?></td>
                    <td><?php echo esc_html(number_format((float) $r->aproveitamento, 2, ',', '.')); ?>%</td>
                    <td class="money"><?php echo esc_html(rsb_money($r->premiacao_estimada)); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
        </section>
        <section>
            <div class="section-head"><h2>Vencedores por jogo finalizado</h2><span class="meta"><?php echo esc_html(count($finalizados)); ?> finalizados</span></div>
            <?php if (!$finalizados) : ?><div class="empty">Ainda nao ha jogos finalizados.</div><?php else : ?>
            <div class="table-scroll"><table>
                <thead><tr><th>Codigo</th><th>Jogo</th><th>Resultado</th><th>Vencedor</th></tr></thead>
                <tbody><?php foreach ($finalizados as $j) : ?><tr>
                    <td><?php echo esc_html($j->codigo_jogo); ?></td>
                    <td><?php echo esc_html($game_label($j)); ?></td>
                    <td><?php echo esc_html($game_result($j)); ?></td>
                    <td><?php echo esc_html($game_winner($j)); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</main>
<?php if (isset($_GET['print']) && (int) $_GET['print'] === 1) : ?>
<script>setTimeout(function(){ window.print(); }, 450);</script>
<?php endif; ?>
</body>
</html>
        <?php
        exit;
    }
}
