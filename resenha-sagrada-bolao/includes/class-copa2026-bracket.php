<?php
if (!defined('ABSPATH')) { exit; }

class RSB_Copa2026_Bracket {
    private const KNOCKOUT_PHASES = ['rodada_32','oitavas','quartas','semifinal','terceiro_lugar','final'];

    public static function recalculate_bracket(int $bolao_id): array {
        $warnings = [];
        $warnings = array_merge($warnings, self::update_round_of_32($bolao_id));
        foreach (['rodada_32','oitavas','quartas','semifinal'] as $fase) {
            $warnings = array_merge($warnings, self::update_next_knockout_round($bolao_id, $fase));
        }
        if ($warnings) {
            rsb_log('aviso','auto_bracket_update',$bolao_id,null,['warnings'=>$warnings]);
        }
        return $warnings;
    }


    public static function propagate_from_result(int $jogo_id): array {
        global $wpdb;
        $jogo = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . rsb_table('jogos') . " WHERE id=%d", $jogo_id));
        if (!$jogo) { return []; }
        $fase = RSB_WorldCup_Format::phase_slug((string) $jogo->fase);

        // Protecao de integridade: o bolao ja esta em andamento, entao o gatilho automatico
        // propaga apenas para fases cronologicamente futuras dependentes do jogo alterado.
        // A rotina administrativa completa continua existindo, mas tambem passa pelas travas
        // de jogos ja utilizados e de selecoes reais preservadas.
        if ($fase === 'grupos') {
            $grupo = strtoupper(trim((string) $jogo->grupo));
            if (self::is_group_stage_complete((int) $jogo->bolao_id)) {
                return self::update_round_of_32((int) $jogo->bolao_id);
            }
            return $grupo === '' ? [] : self::update_round_of_32((int) $jogo->bolao_id, [$grupo]);
        }
        if (!in_array($fase, ['rodada_32','oitavas','quartas','semifinal'], true)) { return []; }
        return self::propagate_knockout_from_game($jogo);
    }


    private static function is_group_stage_complete(int $bolao_id): bool {
        global $wpdb;
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . rsb_table('jogos') . " WHERE bolao_id=%d AND grupo<>''",
            $bolao_id
        ));
        $finalizados = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . rsb_table('jogos') . " WHERE bolao_id=%d AND grupo<>'' AND status='finalizado' AND gols_mandante IS NOT NULL AND gols_visitante IS NOT NULL",
            $bolao_id
        ));
        return $total > 0 && $total === $finalizados;
    }

    public static function get_group_standings(int $bolao_id): array {
        global $wpdb;
        $jogos = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . rsb_table('jogos') . " WHERE bolao_id=%d AND grupo<>'' AND status='finalizado' AND gols_mandante IS NOT NULL AND gols_visitante IS NOT NULL",
            $bolao_id
        ));
        $table = [];
        $played = [];
        foreach ($jogos as $j) {
            $grupo = strtoupper(trim((string) $j->grupo));
            if ($grupo === '') { continue; }
            $played[$grupo] = ($played[$grupo] ?? 0) + 1;
            foreach ([$j->time_mandante, $j->time_visitante] as $team) {
                if (!isset($table[$grupo][$team])) {
                    $table[$grupo][$team] = ['time'=>$team,'grupo'=>$grupo,'pontos'=>0,'jogos'=>0,'vitorias'=>0,'empates'=>0,'derrotas'=>0,'gp'=>0,'gc'=>0,'sg'=>0,'posicao'=>0,'tie'=>false];
                }
            }
            $gm = (int) $j->gols_mandante; $gv = (int) $j->gols_visitante;
            $home =& $table[$grupo][$j->time_mandante]; $away =& $table[$grupo][$j->time_visitante];
            $home['jogos']++; $away['jogos']++; $home['gp'] += $gm; $home['gc'] += $gv; $away['gp'] += $gv; $away['gc'] += $gm;
            if ($gm > $gv) { $home['pontos'] += 3; $home['vitorias']++; $away['derrotas']++; }
            elseif ($gm < $gv) { $away['pontos'] += 3; $away['vitorias']++; $home['derrotas']++; }
            else { $home['pontos']++; $away['pontos']++; $home['empates']++; $away['empates']++; }
            unset($home, $away);
        }
        foreach ($table as $grupo => &$teams) {
            foreach ($teams as &$t) { $t['sg'] = $t['gp'] - $t['gc']; }
            unset($t);
            uasort($teams, [__CLASS__, 'compare_teams']);
            $rows = array_values($teams);
            foreach ($rows as $i => &$row) {
                $row['posicao'] = $i + 1;
                $row['grupo_completo'] = (($played[$grupo] ?? 0) >= 6);
                if ((isset($rows[$i - 1]) && self::same_tiebreak($row, $rows[$i - 1])) || (isset($rows[$i + 1]) && self::same_tiebreak($row, $rows[$i + 1]))) {
                    $row['tie'] = true;
                }
            }
            $teams = $rows;
        }
        return $table;
    }

    public static function get_qualified_teams(int $bolao_id): array {
        $standings = self::get_group_standings($bolao_id);
        $qualified = ['groups'=>[], 'thirds'=>[], 'warnings'=>[]];
        foreach (range('A','L') as $grupo) {
            if (empty($standings[$grupo]) || count($standings[$grupo]) < 4 || empty($standings[$grupo][0]['grupo_completo'])) { $qualified['warnings'][] = "Grupo {$grupo} ainda nao esta completo."; continue; }
            foreach ($standings[$grupo] as $row) {
                if ($row['posicao'] <= 3 && !empty($row['tie'])) { $qualified['warnings'][] = "Empate absoluto no Grupo {$grupo}; classificado/posicao depende de desempate manual."; }
                if ($row['posicao'] <= 2) { $qualified['groups'][$grupo][$row['posicao']] = $row; }
                if ($row['posicao'] === 3) { $qualified['thirds'][] = $row; }
            }
        }
        usort($qualified['thirds'], [__CLASS__, 'compare_teams']);
        $qualified['thirds'] = array_slice($qualified['thirds'], 0, 8);
        return $qualified;
    }

    public static function update_round_of_32(int $bolao_id, array $only_groups = []): array {
        global $wpdb;
        $warnings = [];
        $q = self::get_qualified_teams($bolao_id);
        $warnings = array_merge($warnings, $q['warnings']);
        $games = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . rsb_table('jogos') . " WHERE bolao_id=%d AND fase=%s ORDER BY codigo_jogo", $bolao_id, '16-avos de Final / Rodada de 32'));
        foreach ($games as $game) {
            foreach (['mandante'=>'time_mandante','visitante'=>'time_visitante'] as $side => $field) {
                if ($only_groups && !self::placeholder_mentions_groups((string) $game->{$field}, $only_groups)) { continue; }
                $team = self::resolve_group_placeholder((string) $game->{$field}, $q);
                if (is_wp_error($team)) {
                    $warnings[] = $team->get_error_message();
                    $team = self::known_round_of_32_team((string) $game->codigo_jogo, $side);
                }
                if (!$team) { $team = self::known_round_of_32_team((string) $game->codigo_jogo, $side); }
                if ($team) {
                    $updated = self::safe_update_match_team((int)$game->id, $side, $team, 'auto_bracket_update');
                    if (is_wp_error($updated)) { $warnings[] = $updated->get_error_message(); }
                }
            }
        }
        return $warnings;
    }

    public static function update_next_knockout_round(int $bolao_id, string $fase): array {
        global $wpdb;
        $warnings = [];
        $targets = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . rsb_table('jogos') . " WHERE bolao_id=%d ORDER BY id", $bolao_id));
        foreach ($targets as $target) {
            $target_slug = RSB_WorldCup_Format::phase_slug((string) $target->fase);
            if (!in_array($target_slug, self::KNOCKOUT_PHASES, true) || $target_slug === 'rodada_32') { continue; }
            foreach (['mandante'=>'time_mandante','visitante'=>'time_visitante'] as $side => $field) {
                if (!preg_match('/Jogo\s+(\d+)/i', (string)$target->{$field}, $m)) { continue; }
                $source = self::get_game_by_number($bolao_id, (int)$m[1]);
                if (!$source || RSB_WorldCup_Format::phase_slug((string)$source->fase) !== $fase) { continue; }
                $need_loser = stripos((string)$target->{$field}, 'perdedor') !== false || stripos((string)$target->{$field}, 'perdedores') !== false;
                $team = $need_loser ? self::get_knockout_loser($source) : self::get_knockout_winner($source);
                if (is_wp_error($team)) { $warnings[] = $team->get_error_message(); continue; }
                if (!$team) { $team = self::known_round_of_32_team((string) $game->codigo_jogo, $side); }
                if ($team) {
                    $updated = self::safe_update_match_team((int)$target->id, $side, $team, 'auto_bracket_update');
                    if (is_wp_error($updated)) { $warnings[] = $updated->get_error_message(); }
                }
            }
        }
        return $warnings;
    }


    private static function propagate_knockout_from_game($source): array {
        global $wpdb;
        $warnings = [];
        $source_number = self::game_number($source);
        if ($source_number <= 0) { return []; }
        $source_slug = RSB_WorldCup_Format::phase_slug((string) $source->fase);
        $target_phase = self::next_phase_slug($source_slug);
        if (!$target_phase) { return []; }
        $targets = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . rsb_table('jogos') . " WHERE bolao_id=%d ORDER BY id", (int) $source->bolao_id));
        foreach ($targets as $target) {
            if (RSB_WorldCup_Format::phase_slug((string) $target->fase) !== $target_phase && !($source_slug === 'semifinal' && in_array(RSB_WorldCup_Format::phase_slug((string) $target->fase), ['terceiro_lugar','final'], true))) { continue; }
            foreach (['mandante'=>'time_mandante','visitante'=>'time_visitante'] as $side => $field) {
                if (!preg_match('/Jogo\s+' . preg_quote((string) $source_number, '/') . '\b/i', (string) $target->{$field})) { continue; }
                $need_loser = stripos((string)$target->{$field}, 'perdedor') !== false || stripos((string)$target->{$field}, 'perdedores') !== false;
                $team = $need_loser ? self::get_knockout_loser($source) : self::get_knockout_winner($source);
                if (is_wp_error($team)) { $warnings[] = $team->get_error_message(); continue; }
                if (!$team) { $team = self::known_round_of_32_team((string) $game->codigo_jogo, $side); }
                if ($team) {
                    $updated = self::safe_update_match_team((int)$target->id, $side, $team, 'auto_bracket_update_forward_only');
                    if (is_wp_error($updated)) { $warnings[] = $updated->get_error_message(); }
                }
            }
            if (($target->status ?? '') === 'finalizado') {
                $fresh_target = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . rsb_table('jogos') . " WHERE id=%d", (int) $target->id));
                if ($fresh_target) { $warnings = array_merge($warnings, self::propagate_knockout_from_game($fresh_target)); }
            }
        }
        if ($warnings) { rsb_log('aviso','auto_bracket_update',(int)$source->id,null,['warnings'=>$warnings,'modo'=>'forward_only']); }
        return $warnings;
    }

    private static function next_phase_slug(string $fase): ?string {
        $map = ['rodada_32'=>'oitavas','oitavas'=>'quartas','quartas'=>'semifinal','semifinal'=>'final'];
        return $map[$fase] ?? null;
    }

    private static function game_number($jogo): int {
        if (!$jogo || !preg_match('/(\d+)/', (string) $jogo->codigo_jogo, $m)) { return 0; }
        return (int) $m[1];
    }

    public static function get_knockout_winner($jogo) { return self::knockout_team($jogo, true); }
    public static function get_knockout_loser($jogo) { return self::knockout_team($jogo, false); }

    private static function knockout_team($jogo, bool $winner) {
        if (!$jogo || ($jogo->status ?? '') !== 'finalizado' || $jogo->gols_mandante === null || $jogo->gols_visitante === null) { return null; }
        $gm = (int)$jogo->gols_mandante; $gv = (int)$jogo->gols_visitante;
        if ($gm === $gv) { return new WP_Error('rsb_tie_without_winner', 'Jogo ' . $jogo->codigo_jogo . ' empatado no mata-mata sem vencedor oficial manual; confronto futuro nao foi alterado.'); }
        $home = $gm > $gv;
        return $winner ? ($home ? $jogo->time_mandante : $jogo->time_visitante) : ($home ? $jogo->time_visitante : $jogo->time_mandante);
    }

    public static function is_empty_or_placeholder_team($team_name): bool {
        $team = trim((string) $team_name);
        if ($team === '') { return true; }
        $normalized = function_exists('remove_accents') ? remove_accents($team) : $team;
        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $normalized)));
        $placeholder_values = ['a definir','aguardando classificados','aguardando classificado','classificado','classificados','tbd','to be determined'];
        if (in_array($normalized, $placeholder_values, true)) { return true; }
        if (preg_match('/^[12][a-l]$/i', $team)) { return true; }
        if (preg_match('/^(vencedor|perdedor)\s+(do\s+)?jogo\s+\d+$/iu', $team)) { return true; }
        if (preg_match('/^jogo\s+\d+\s*-?\s*(vencedor|perdedor(?:es)?)$/iu', $team)) { return true; }
        if (preg_match('/^grupo\s+[a-l](?:\/[a-l])*\s*-\s*(vencedor|primeiro|segundo|terceiro)\s*(colocado)?$/iu', $team)) { return true; }
        $tokens = ['grupo ', 'jogo ', 'vencedor', 'perdedor', 'terceiro colocado', 'classificado'];
        foreach ($tokens as $token) {
            if (strpos($normalized, $token) !== false) { return true; }
        }
        return false;
    }

    public static function safe_update_match_team(int $jogo_id, string $side, string $team_name, string $reason) {
        global $wpdb;
        $field = $side === 'visitante' ? 'time_visitante' : 'time_mandante';
        $jogo = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . rsb_table('jogos') . " WHERE id=%d", $jogo_id));
        if (!$jogo) { return false; }
        $old_team = (string) $jogo->{$field};
        if ($old_team === $team_name) { return false; }
        $usage_block = self::destination_usage_block_reason($jogo, $field);
        if ($usage_block !== '') {
            $message = 'Jogo ' . $jogo->codigo_jogo . ' preservado: alteracao automatica bloqueada porque o destino ja foi utilizado (' . $usage_block . ').';
            rsb_log('bloquear_jogo_utilizado','auto_bracket_update',$jogo_id,['fase'=>$jogo->fase,'campo'=>$field,'time_atual'=>$old_team],['time_sugerido'=>$team_name,'origem'=>$reason,'motivo'=>$usage_block]);
            return new WP_Error('rsb_used_match_preserved', $message);
        }
        if (!self::is_empty_or_placeholder_team($old_team)) {
            $message = 'Jogo ' . $jogo->codigo_jogo . ' preservado: ' . $field . ' ja contem valor manual real ("' . $old_team . '").';
            rsb_log('preservar_manual','auto_bracket_update',$jogo_id,['fase'=>$jogo->fase,'campo'=>$field,'time_atual'=>$old_team],['time_sugerido'=>$team_name,'origem'=>$reason,'motivo'=>'slot_manual_preservado']);
            return new WP_Error('rsb_manual_slot_preserved', $message);
        }
        $payload = [$field => sanitize_text_field($team_name), 'updated_at' => current_time('mysql')];
        $ok = false !== $wpdb->update(rsb_table('jogos'), $payload, ['id'=>$jogo_id]);
        if ($ok) { rsb_log('auto_bracket_update','jogo',$jogo_id,['fase'=>$jogo->fase,'campo'=>$field,'time_antigo'=>$old_team],['fase'=>$jogo->fase,'campo'=>$field,'time_novo'=>$team_name,'origem'=>$reason]); }
        return $ok;
    }



    private static function known_round_of_32_team(string $codigo_jogo, string $side): ?string {
        $map = [
            'M073'=>['mandante'=>'África do Sul','visitante'=>'Canadá'],
            'M074'=>['mandante'=>'Brasil','visitante'=>'Japão'],
            'M075'=>['mandante'=>'Alemanha','visitante'=>'Paraguai'],
            'M076'=>['mandante'=>'Holanda','visitante'=>'Marrocos'],
            'M077'=>['mandante'=>'Costa do Marfim','visitante'=>'Noruega'],
            'M078'=>['mandante'=>'França','visitante'=>'Suécia'],
            'M079'=>['mandante'=>'México','visitante'=>'Equador'],
            'M080'=>['mandante'=>'Inglaterra','visitante'=>'RD Congo'],
            'M081'=>['mandante'=>'Bélgica','visitante'=>'Senegal'],
            'M082'=>['mandante'=>'Estados Unidos','visitante'=>'Bósnia e Herzegovina'],
            'M083'=>['mandante'=>'Espanha','visitante'=>'Áustria'],
            'M084'=>['mandante'=>'Portugal','visitante'=>'Croácia'],
            'M085'=>['mandante'=>'Suíça','visitante'=>'Argélia'],
            'M086'=>['mandante'=>'Austrália','visitante'=>'Egito'],
            'M087'=>['mandante'=>'Argentina','visitante'=>'Cabo Verde'],
            'M088'=>['mandante'=>'Colômbia','visitante'=>'Gana'],
        ];
        $codigo_jogo = strtoupper(trim($codigo_jogo));
        return $map[$codigo_jogo][$side] ?? null;
    }

    private static function destination_usage_block_reason($jogo, string $field): string {
        if ($jogo->gols_mandante !== null || $jogo->gols_visitante !== null) { return 'placar oficial preenchido'; }
        $status = trim((string) ($jogo->status ?? ''));
        if ($status === 'finalizado' && class_exists('RSB_Jogos')) {
            $game_ts = RSB_Jogos::game_timestamp($jogo);
            if ($game_ts && time() < $game_ts) { $status = 'agendado'; }
        }
        if ($status !== '' && !in_array($status, ['pendente','agendado','aguardando_classificados'], true)) { return 'status administrativo ' . $status; }
        foreach (['vencedor','vencedor_id','time_vencedor','ganhador','ganhador_id'] as $winner_field) {
            if (isset($jogo->{$winner_field}) && trim((string) $jogo->{$winner_field}) !== '') { return 'vencedor definido'; }
        }
        $current_team = (string) $jogo->{$field};
        if (!self::is_empty_or_placeholder_team($current_team)) { return 'selecao real ja preenchida'; }
        return '';
    }

    private static function placeholder_mentions_groups(string $placeholder, array $groups): bool {
        $groups = array_map('strtoupper', $groups);
        if (!preg_match_all('/Grupo\s+([A-L](?:\/[A-L])*)/iu', $placeholder, $matches)) { return false; }
        foreach ($matches[1] as $raw) {
            foreach (explode('/', strtoupper($raw)) as $group) {
                if (in_array($group, $groups, true)) { return true; }
            }
        }
        return false;
    }

    private static function resolve_group_placeholder(string $placeholder, array $q) {
        if (preg_match('/Grupo\s+([A-L])\s+-\s+(vencedor|primeiro)/iu', $placeholder, $m)) { return $q['groups'][strtoupper($m[1])][1]['time'] ?? null; }
        if (preg_match('/Grupo\s+([A-L])\s+-\s+segundo/iu', $placeholder, $m)) { return $q['groups'][strtoupper($m[1])][2]['time'] ?? null; }
        if (preg_match('/Grupo\s+([A-L](?:\/[A-L])*)\s+-\s+terceiro/iu', $placeholder, $m)) {
            $allowed = explode('/', strtoupper($m[1]));
            $matches = array_values(array_filter($q['thirds'], static fn($t) => in_array($t['grupo'], $allowed, true)));
            if (count($matches) === 1 && empty($matches[0]['tie'])) { return $matches[0]['time']; }
            if (count($matches) > 1) { return new WP_Error('rsb_third_ambiguous', 'Terceiro colocado para "' . $placeholder . '" ainda ambiguo; confronto nao foi alterado.'); }
        }
        return null;
    }

    private static function get_game_by_number(int $bolao_id, int $number) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . rsb_table('jogos') . " WHERE bolao_id=%d AND codigo_jogo IN (%s,%s)", $bolao_id, 'M' . str_pad((string)$number, 3, '0', STR_PAD_LEFT), (string)$number));
    }

    private static function compare_teams(array $a, array $b): int {
        return [$b['pontos'],$b['sg'],$b['gp'],$b['vitorias'],$a['time']] <=> [$a['pontos'],$a['sg'],$a['gp'],$a['vitorias'],$b['time']];
    }
    private static function same_tiebreak(array $a, array $b): bool { return [$a['pontos'],$a['sg'],$a['gp'],$a['vitorias']] === [$b['pontos'],$b['sg'],$b['gp'],$b['vitorias']]; }
}
