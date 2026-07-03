<?php
if (!defined('ABSPATH')) { exit; }

class RSB_WorldCup_2026_API {
    public const DEFAULT_GAMES_URL = 'https://worldcup26.ir/get/games';

    public static function sync_results(int $bolao_id): array {
        global $wpdb;
        $url = apply_filters('rsb_worldcup_2026_api_games_url', get_option('rsb_worldcup_2026_api_games_url', self::DEFAULT_GAMES_URL));
        $response = wp_remote_get(esc_url_raw($url), ['timeout'=>20, 'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($response)) {
            return ['updated'=>0, 'skipped'=>0, 'errors'=>[$response->get_error_message()], 'source'=>$url];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return ['updated'=>0, 'skipped'=>0, 'errors'=>['API retornou HTTP ' . $code], 'source'=>$url];
        }
        $payload = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($payload)) {
            return ['updated'=>0, 'skipped'=>0, 'errors'=>['Resposta da API nao e JSON valido.'], 'source'=>$url];
        }
        $remote_games = self::extract_games($payload);
        $local_games = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . rsb_table('jogos') . " WHERE bolao_id=%d", $bolao_id));
        $repaired = self::repair_future_finished_games($local_games);
        if ($repaired > 0) {
            $local_games = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . rsb_table('jogos') . " WHERE bolao_id=%d", $bolao_id));
        }
        $by_code = [];
        foreach ($local_games as $game) { $by_code[(string)$game->codigo_jogo] = $game; }
        $updated = 0;
        $skipped = 0;
        $errors = [];
        foreach ($remote_games as $remote) {
            $result = self::remote_result($remote);
            if (!$result || !$result['finished']) { $skipped++; continue; }
            $local = self::match_local_game($remote, $by_code, $local_games);
            if (!$local) { $skipped++; continue; }
            if (self::is_future_local_game($local)) { $skipped++; continue; }
            if ($local->gols_mandante !== null || $local->gols_visitante !== null) { $skipped++; continue; }
            $old = $local;
            $ok = false !== $wpdb->update(rsb_table('jogos'), [
                'gols_mandante'=>(int)$result['home'],
                'gols_visitante'=>(int)$result['away'],
                'status'=>'finalizado',
                'updated_at'=>current_time('mysql'),
            ], ['id'=>(int)$local->id]);
            if ($ok) {
                $updated++;
                rsb_log('api_resultado','jogo',(int)$local->id,$old,['origem'=>'worldcup_2026_api','source'=>$url,'gols_mandante'=>(int)$result['home'],'gols_visitante'=>(int)$result['away']]);
            } else {
                $errors[] = 'Falha ao atualizar ' . $local->codigo_jogo;
            }
        }
        if ($updated > 0 && class_exists('RSB_Ranking')) { RSB_Ranking::recalculate_all_scores($bolao_id); }
        if ($updated > 0 && class_exists('RSB_Copa2026_Bracket')) { RSB_Copa2026_Bracket::recalculate_bracket($bolao_id); }
        return ['updated'=>$updated, 'skipped'=>$skipped, 'repaired'=>$repaired, 'errors'=>$errors, 'source'=>$url];
    }


    private static function is_future_local_game($game): bool {
        if (!$game || empty($game->data_jogo) || empty($game->hora_jogo)) { return false; }
        $ts = class_exists('RSB_Jogos') ? RSB_Jogos::game_timestamp($game) : strtotime($game->data_jogo . ' ' . $game->hora_jogo);
        return $ts && time() < $ts;
    }

    private static function repair_future_finished_games(array $local_games): int {
        global $wpdb;
        $count = 0;
        foreach ($local_games as $game) {
            if (($game->status ?? '') !== 'finalizado' || !self::is_future_local_game($game)) { continue; }
            $new_status = class_exists('RSB_Jogos') && RSB_Jogos::has_defined_teams($game) ? 'agendado' : 'aguardando_classificados';
            $old = $game;
            $ok = false !== $wpdb->update(rsb_table('jogos'), [
                'gols_mandante'=>null,
                'gols_visitante'=>null,
                'status'=>$new_status,
                'updated_at'=>current_time('mysql'),
            ], ['id'=>(int)$game->id]);
            if ($ok) {
                $count++;
                rsb_log('corrigir_futuro_finalizado','jogo',(int)$game->id,$old,['status'=>$new_status,'origem'=>'worldcup_2026_api_guard']);
            }
        }
        return $count;
    }

    private static function extract_games(array $payload): array {
        foreach (['games','matches','data','results'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) { return array_values($payload[$key]); }
        }
        return self::is_list($payload) ? $payload : [];
    }

    private static function is_list(array $array): bool {
        $i = 0;
        foreach ($array as $key => $_value) {
            if ($key !== $i++) { return false; }
        }
        return true;
    }

    private static function remote_result(array $game): ?array {
        $status = strtolower((string) self::first_value($game, ['status','match_status','state','stage_status']));
        $finished = in_array($status, ['finished','finish','final','finalizado','completed','complete','full_time','ft'], true) || (bool) self::first_value($game, ['finished','is_finished','completed']);

        // Regra do bolao: a pontuacao das fases de mata-mata usa o placar do tempo regulamentar
        // (90 minutos + acrescimos). Quando a API expuser prorrogação/penaltis separados, eles sao ignorados.
        $score = self::regulation_score_from_remote($game);
        if (!$score) { return null; }
        return ['home'=>$score[0], 'away'=>$score[1], 'finished'=>$finished];
    }

    private static function regulation_score_from_remote(array $game): ?array {
        $pairs = [
            [['home_score_90','homeScore90','home_goals_90','homeGoals90','home_regulation_score','homeRegularTimeScore','home_score_regular','homeScoreRegular','home_score_ft','homeScoreFT','home_full_time_score','homeFullTimeScore'], ['away_score_90','awayScore90','away_goals_90','awayGoals90','away_regulation_score','awayRegularTimeScore','away_score_regular','awayScoreRegular','away_score_ft','awayScoreFT','away_full_time_score','awayFullTimeScore']],
        ];
        foreach ($pairs as $pair) {
            $score = self::score_pair_from_keys($game, $pair[0], $pair[1]);
            if ($score) { return $score; }
        }
        foreach (['score_90','score90','regular_time','regularTime','regulation','full_time','fullTime','ft'] as $key) {
            if (isset($game[$key]) && is_array($game[$key])) {
                $score = self::score_pair_from_keys($game[$key], ['home','home_score','team1'], ['away','away_score','team2']);
                if ($score) { return $score; }
            }
        }
        $has_extra_info = self::has_any_key($game, ['home_penalties','away_penalties','penalties','home_penalty_score','away_penalty_score','home_extra_time_score','away_extra_time_score','extra_time','extraTime']);
        if (isset($game['score']) && is_array($game['score'])) {
            $has_extra_info = $has_extra_info || self::has_any_key($game['score'], ['penalties','extra_time','extraTime','home_penalties','away_penalties']);
        }
        if ($has_extra_info) { return null; }
        $score = self::score_pair_from_keys($game, ['home_score','homeScore','home_goals','homeGoals','team1_score','score1','home_score_current'], ['away_score','awayScore','away_goals','awayGoals','team2_score','score2','away_score_current']);
        if ($score) { return $score; }
        if (isset($game['score']) && is_array($game['score'])) {
            return self::score_pair_from_keys($game['score'], ['home','home_score','team1'], ['away','away_score','team2']);
        }
        return null;
    }

    private static function score_pair_from_keys(array $data, array $home_keys, array $away_keys): ?array {
        $home = self::first_value($data, $home_keys);
        $away = self::first_value($data, $away_keys);
        if ($home === null || $away === null || !is_numeric($home) || !is_numeric($away)) { return null; }
        return [(int)$home, (int)$away];
    }

    private static function has_any_key(array $data, array $keys): bool {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') { return true; }
        }
        return false;
    }

    private static function match_local_game(array $remote, array $by_code, array $local_games) {
        $number = self::first_value($remote, ['match_number','matchNumber','game_number','gameNumber','number','id']);
        if (is_numeric($number)) {
            $code = 'M' . str_pad((string)(int)$number, 3, '0', STR_PAD_LEFT);
            if (isset($by_code[$code])) { return $by_code[$code]; }
        }
        $code = strtoupper((string) self::first_value($remote, ['code','codigo_jogo','match_code']));
        if ($code && isset($by_code[$code])) { return $by_code[$code]; }
        $home = self::normalize_team((string) self::first_value($remote, ['home_team','homeTeam','team1','home_name','home']));
        $away = self::normalize_team((string) self::first_value($remote, ['away_team','awayTeam','team2','away_name','away']));
        $date = substr((string) self::first_value($remote, ['date','match_date','kickoff','datetime']), 0, 10);
        foreach ($local_games as $game) {
            if ($date && (string)$game->data_jogo !== $date) { continue; }
            if ($home && $away && self::normalize_team((string)$game->time_mandante) === $home && self::normalize_team((string)$game->time_visitante) === $away) { return $game; }
        }
        return null;
    }

    private static function first_value(array $data, array $keys) {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if (is_array($value)) {
                    foreach (['name','team','value','score','goals'] as $nested) {
                        if (isset($value[$nested]) && !is_array($value[$nested])) { return $value[$nested]; }
                    }
                } elseif ($value !== '') {
                    return $value;
                }
            }
        }
        return null;
    }

    private static function normalize_team(string $team): string {
        $team = trim(preg_replace('/\s+/', ' ', $team));
        if (function_exists('remove_accents')) { $team = remove_accents($team); }
        return strtolower($team);
    }
}
