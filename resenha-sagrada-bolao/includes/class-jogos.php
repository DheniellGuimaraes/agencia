<?php
if (!defined('ABSPATH')) { exit; }
class RSB_Jogos {
    public static function all(int $bolao_id): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM ".rsb_table('jogos')." WHERE bolao_id=%d ORDER BY COALESCE(data_jogo,'2099-12-31'), hora_jogo, id", $bolao_id));
    }

    public static function create(array $data): int {
        global $wpdb;
        $now=current_time('mysql');
        $fase = sanitize_text_field($data['fase'] ?? 'Fase de Grupos');
        $payload=[
            'bolao_id'=>(int)$data['bolao_id'],
            'codigo_jogo'=>sanitize_text_field($data['codigo_jogo']),
            'fase'=>class_exists('RSB_WorldCup_Format') ? RSB_WorldCup_Format::normalize_phase($fase) : $fase,
            'grupo'=>sanitize_text_field($data['grupo'] ?? ''),
            'rodada'=>sanitize_text_field($data['rodada'] ?? ''),
            'local_jogo'=>sanitize_text_field($data['local_jogo'] ?? ''),
            'data_jogo'=>empty($data['data_jogo'])?null:sanitize_text_field($data['data_jogo']),
            'hora_jogo'=>empty($data['hora_jogo'])?null:sanitize_text_field($data['hora_jogo']),
            'time_mandante'=>sanitize_text_field($data['time_mandante']),
            'time_visitante'=>sanitize_text_field($data['time_visitante']),
            'status'=>sanitize_text_field($data['status'] ?? 'agendado'),
            'created_at'=>$now,
            'updated_at'=>$now
        ];
        $wpdb->insert(rsb_table('jogos'),$payload);
        $id=(int)$wpdb->insert_id;
        rsb_log('criar','jogo',$id,null,$payload);
        return $id;
    }

    public static function update(int $id, array $data): bool {
        global $wpdb;
        $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".rsb_table('jogos')." WHERE id=%d", $id));
        if(!$old){return false;}
        $fase = sanitize_text_field($data['fase'] ?? $old->fase);
        $payload=[
            'codigo_jogo'=>sanitize_text_field($data['codigo_jogo'] ?? $old->codigo_jogo),
            'fase'=>class_exists('RSB_WorldCup_Format') ? RSB_WorldCup_Format::normalize_phase($fase) : $fase,
            'grupo'=>sanitize_text_field($data['grupo'] ?? $old->grupo),
            'rodada'=>sanitize_text_field($data['rodada'] ?? $old->rodada),
            'data_jogo'=>empty($data['data_jogo'])?null:sanitize_text_field($data['data_jogo']),
            'hora_jogo'=>empty($data['hora_jogo'])?null:sanitize_text_field($data['hora_jogo']),
            'local_jogo'=>sanitize_text_field($data['local_jogo'] ?? $old->local_jogo),
            'time_mandante'=>sanitize_text_field($data['time_mandante'] ?? $old->time_mandante),
            'time_visitante'=>sanitize_text_field($data['time_visitante'] ?? $old->time_visitante),
            'status'=>sanitize_text_field($data['status'] ?? $old->status),
            'updated_at'=>current_time('mysql')
        ];
        $ok=false!==$wpdb->update(rsb_table('jogos'),$payload,['id'=>$id]);
        if($ok){rsb_log('atualizar','jogo',$id,$old,$payload);}
        return $ok;
    }

    public static function lock_timestamp($jogo): ?int {
        if(!$jogo || empty($jogo->data_jogo) || empty($jogo->hora_jogo)){ return null; }
        try {
            $tz = function_exists('rsb_brasilia_timezone') ? rsb_brasilia_timezone() : new DateTimeZone('America/Sao_Paulo');
            $dt = new DateTimeImmutable($jogo->data_jogo.' '.$jogo->hora_jogo, $tz);
            return $dt->getTimestamp() - HOUR_IN_SECONDS;
        } catch (Exception $e) {
            return strtotime($jogo->data_jogo.' '.$jogo->hora_jogo) - HOUR_IN_SECONDS;
        }
    }

    public static function game_timestamp($jogo): ?int {
        if(!$jogo || empty($jogo->data_jogo) || empty($jogo->hora_jogo)){ return null; }
        try {
            $tz = function_exists('rsb_brasilia_timezone') ? rsb_brasilia_timezone() : new DateTimeZone('America/Sao_Paulo');
            $dt = new DateTimeImmutable($jogo->data_jogo.' '.$jogo->hora_jogo, $tz);
            return $dt->getTimestamp();
        } catch (Exception $e) {
            $ts = strtotime($jogo->data_jogo.' '.$jogo->hora_jogo);
            return $ts ? $ts : null;
        }
    }

    public static function lock_audit($jogo, ?int $now = null): array {
        $game_ts = self::game_timestamp($jogo);
        $lock_ts = self::lock_timestamp($jogo);
        $now = $now ?? time();
        $status = self::betting_status($jogo);

        return [
            'game_ts' => $game_ts,
            'lock_ts' => $lock_ts,
            'now_ts' => $now,
            'game_label' => $game_ts ? rsb_brasilia_datetime($game_ts) . ' BRT' : '-',
            'lock_label' => $lock_ts ? rsb_brasilia_datetime($lock_ts) . ' BRT' : '-',
            'status' => $status,
            'bettable' => self::is_bettable_at($jogo, $now),
        ];
    }

    public static function is_bettable($jogo): bool {
        return self::is_bettable_at($jogo, time());
    }

    public static function is_bettable_at($jogo, int $now): bool {
        if(!$jogo){return false;}
        if(!self::has_defined_teams($jogo)){ return false; }
        if(empty($jogo->data_jogo) || empty($jogo->hora_jogo)){ return false; }
        $game_ts = self::game_timestamp($jogo);
        $status = (string)($jogo->status ?? '');
        if($status === 'finalizado' && $game_ts && $now < $game_ts){ $status = 'agendado'; }
        if(!in_array($status, ['agendado', 'aguardando_classificados'], true)){ return false; }
        $lock = self::lock_timestamp($jogo);
        return $lock && $now < $lock;
    }

    public static function has_defined_teams($jogo): bool {
        if (!$jogo) { return false; }
        $home = trim((string)($jogo->time_mandante ?? ''));
        $away = trim((string)($jogo->time_visitante ?? ''));
        if ($home === '' || $away === '') { return false; }
        if (class_exists('RSB_Copa2026_Bracket')) {
            return !RSB_Copa2026_Bracket::is_empty_or_placeholder_team($home) && !RSB_Copa2026_Bracket::is_empty_or_placeholder_team($away);
        }
        return true;
    }

    public static function betting_status($jogo): string {
        if(!$jogo){ return 'indisponivel'; }
        if(($jogo->status ?? '') === 'aguardando_classificados' && !self::has_defined_teams($jogo)){ return 'aguardando_classificados'; }
        if(empty($jogo->data_jogo) || empty($jogo->hora_jogo)){ return 'indisponivel'; }
        try {
            $game_ts = self::game_timestamp($jogo);
        } catch (Exception $e) {
            $game_ts = strtotime($jogo->data_jogo.' '.$jogo->hora_jogo);
        }
        $lock_ts = self::lock_timestamp($jogo);
        $now = time();
        if(!$game_ts || !$lock_ts){ return 'indisponivel'; }
        if(($jogo->status ?? '') === 'finalizado' && $now >= $game_ts){ return 'finalizado'; }
        if($now >= $game_ts){ return 'jogo_iniciado'; }
        if($now >= $lock_ts){ return 'encerrado_1h'; }
        return 'aberto';
    }

    public static function set_result(int $jogo_id, int $g1, int $g2): bool {
        global $wpdb;
        $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".rsb_table('jogos')." WHERE id=%d", $jogo_id));
        $payload=['gols_mandante'=>$g1,'gols_visitante'=>$g2,'status'=>'finalizado','updated_at'=>current_time('mysql')];
        $ok=false!==$wpdb->update(rsb_table('jogos'),$payload,['id'=>$jogo_id]);
        if($ok && $old){ RSB_Ranking::recalculate_game((int)$old->bolao_id,$jogo_id); if (class_exists('RSB_Copa2026_Bracket')) { RSB_Copa2026_Bracket::propagate_from_result($jogo_id); } rsb_log('resultado','jogo',$jogo_id,$old,$payload); }
        return $ok;
    }

    public static function delete(int $id): bool {
        global $wpdb;
        $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".rsb_table('jogos')." WHERE id=%d", $id));
        $ok=false!==$wpdb->delete(rsb_table('jogos'),['id'=>$id]);
        if($ok){rsb_log('excluir','jogo',$id,$old,null);}
        return $ok;
    }
}
