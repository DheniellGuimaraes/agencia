<?php
if (!defined('ABSPATH')) { exit; }

class RSB_WorldCup_Format {
    public static function phases(): array {
        return [
            'grupos' => 'Fase de Grupos',
            'rodada_32' => '16-avos de Final / Rodada de 32',
            'oitavas' => 'Oitavas de Final',
            'quartas' => 'Quartas de Final',
            'semifinal' => 'Semifinal',
            'terceiro_lugar' => 'Disputa de 3º Lugar',
            'final' => 'Final',
        ];
    }

    public static function phase_slug(string $fase): string {
        $f = mb_strtolower($fase);
        if (strpos($f, 'grupo') !== false) return 'grupos';
        if (strpos($f, '32') !== false || strpos($f, '32 avos') !== false || strpos($f, '16-avos') !== false) return 'rodada_32';
        if (strpos($f, 'oitava') !== false) return 'oitavas';
        if (strpos($f, 'quarta') !== false) return 'quartas';
        if (strpos($f, 'semi') !== false) return 'semifinal';
        if (strpos($f, '3') !== false || strpos($f, 'terceiro') !== false) return 'terceiro_lugar';
        if (strpos($f, 'final') !== false) return 'final';
        return 'grupos';
    }

    public static function normalize_phase(string $fase): string {
        $phases = self::phases();
        return $phases[self::phase_slug($fase)] ?? $fase;
    }

    public static function format_summary(): array {
        return [
            'selecoes' => 48,
            'grupos' => 12,
            'por_grupo' => 4,
            'classificados_diretos' => 24,
            'melhores_terceiros' => 8,
            'rodada_32_jogos' => 16,
            'total_jogos_campeao' => 8,
        ];
    }

    public static function group_standings(int $bolao_id): array {
        global $wpdb;
        $jogos = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".rsb_table('jogos')." WHERE bolao_id=%d AND grupo<>'' AND status='finalizado'", $bolao_id));
        $table = [];
        foreach ($jogos as $j) {
            foreach ([$j->time_mandante, $j->time_visitante] as $team) {
                if (!isset($table[$j->grupo][$team])) {
                    $table[$j->grupo][$team] = ['time'=>$team,'grupo'=>$j->grupo,'pontos'=>0,'jogos'=>0,'vitorias'=>0,'empates'=>0,'derrotas'=>0,'gp'=>0,'gc'=>0,'sg'=>0];
                }
            }
            $home =& $table[$j->grupo][$j->time_mandante];
            $away =& $table[$j->grupo][$j->time_visitante];
            $gm = (int)$j->gols_mandante; $gv = (int)$j->gols_visitante;
            $home['jogos']++; $away['jogos']++;
            $home['gp'] += $gm; $home['gc'] += $gv;
            $away['gp'] += $gv; $away['gc'] += $gm;
            if ($gm > $gv) { $home['pontos'] += 3; $home['vitorias']++; $away['derrotas']++; }
            elseif ($gm < $gv) { $away['pontos'] += 3; $away['vitorias']++; $home['derrotas']++; }
            else { $home['pontos']++; $away['pontos']++; $home['empates']++; $away['empates']++; }
            unset($home, $away);
        }
        foreach ($table as $grupo => &$teams) {
            foreach ($teams as &$t) { $t['sg'] = $t['gp'] - $t['gc']; }
            uasort($teams, function($a,$b){
                return [$b['pontos'],$b['sg'],$b['gp'],$b['vitorias'],$a['time']] <=> [$a['pontos'],$a['sg'],$a['gp'],$a['vitorias'],$b['time']];
            });
            $pos=1; foreach ($teams as &$t) { $t['posicao']=$pos++; }
        }
        return $table;
    }

    public static function qualified_preview(int $bolao_id): array {
        $groups = self::group_standings($bolao_id);
        $direct=[]; $thirds=[];
        foreach ($groups as $grupo => $teams) {
            foreach (array_values($teams) as $t) {
                if ($t['posicao'] <= 2) $direct[] = $t;
                elseif ($t['posicao'] === 3) $thirds[] = $t;
            }
        }
        usort($thirds, function($a,$b){
            return [$b['pontos'],$b['sg'],$b['gp'],$b['vitorias'],$a['grupo']] <=> [$a['pontos'],$a['sg'],$a['gp'],$a['vitorias'],$b['grupo']];
        });
        return ['diretos'=>$direct, 'melhores_terceiros'=>array_slice($thirds, 0, 8), 'terceiros'=>$thirds];
    }
}
