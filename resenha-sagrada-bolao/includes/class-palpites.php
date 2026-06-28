<?php
if (!defined('ABSPATH')) { exit; }
class RSB_Palpites {
    public static function all(int $bolao_id): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT p.*, j.codigo_jogo, j.fase, j.grupo, j.rodada, j.data_jogo, j.hora_jogo, j.time_mandante, j.time_visitante, j.gols_mandante, j.gols_visitante, j.status, part.nome participante, part.email participante_email FROM ".rsb_table('palpites')." p JOIN ".rsb_table('jogos')." j ON j.id=p.jogo_id JOIN ".rsb_table('participantes')." part ON part.id=p.participante_id WHERE p.bolao_id=%d ORDER BY p.created_at DESC", $bolao_id));
    }

    public static function filtered(int $bolao_id, array $filters = []): array {
        global $wpdb;

        $where = ['p.bolao_id=%d'];
        $params = [$bolao_id];

        $participante_id = isset($filters['participante_id']) ? (int) $filters['participante_id'] : 0;
        if ($participante_id > 0) {
            $where[] = 'p.participante_id=%d';
            $params[] = $participante_id;
        }

        $jogo_id = isset($filters['jogo_id']) ? (int) $filters['jogo_id'] : 0;
        if ($jogo_id > 0) {
            $where[] = 'p.jogo_id=%d';
            $params[] = $jogo_id;
        }

        $busca = isset($filters['busca']) ? trim((string) $filters['busca']) : '';
        if ($busca !== '') {
            $like = '%' . $wpdb->esc_like($busca) . '%';
            $where[] = '(part.nome LIKE %s OR part.email LIKE %s OR j.codigo_jogo LIKE %s OR j.time_mandante LIKE %s OR j.time_visitante LIKE %s OR j.fase LIKE %s OR j.grupo LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }

        $sql = "SELECT p.*, j.codigo_jogo, j.fase, j.grupo, j.rodada, j.data_jogo, j.hora_jogo, j.time_mandante, j.time_visitante, j.gols_mandante, j.gols_visitante, j.status, part.nome participante, part.email participante_email FROM "
            . rsb_table('palpites') . " p JOIN " . rsb_table('jogos') . " j ON j.id=p.jogo_id JOIN "
            . rsb_table('participantes') . " part ON part.id=p.participante_id WHERE " . implode(' AND ', $where)
            . " ORDER BY COALESCE(j.data_jogo,'2099-12-31'), j.hora_jogo, part.nome";

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    public static function stats_by_participant(int $bolao_id): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT part.id participante_id, part.nome participante, part.email participante_email, part.ativo, part.status_pagamento,
                COUNT(p.id) total_palpites,
                COALESCE(SUM(CASE WHEN p.id IS NOT NULL AND j.status='finalizado' THEN 1 ELSE 0 END),0) palpites_finalizados,
                COALESCE(SUM(CASE WHEN p.id IS NOT NULL AND (j.status<>'finalizado' OR j.status IS NULL) THEN 1 ELSE 0 END),0) palpites_pendentes,
                COALESCE(SUM(p.pontos),0) total_pontos,
                MAX(p.updated_at) ultimo_palpite
            FROM " . rsb_table('participantes') . " part
            LEFT JOIN " . rsb_table('palpites') . " p ON p.participante_id=part.id AND p.bolao_id=part.bolao_id
            LEFT JOIN " . rsb_table('jogos') . " j ON j.id=p.jogo_id AND j.bolao_id=part.bolao_id
            WHERE part.bolao_id=%d
            GROUP BY part.id, part.nome, part.email, part.ativo, part.status_pagamento
            ORDER BY total_palpites DESC, part.nome",
            $bolao_id
        ));
    }

    public static function stats_by_game(int $bolao_id): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT j.id jogo_id, j.codigo_jogo, j.fase, j.grupo, j.rodada, j.data_jogo, j.hora_jogo, j.time_mandante, j.time_visitante, j.status,
                COUNT(p.id) total_palpites,
                COUNT(DISTINCT p.participante_id) participantes_com_palpite,
                MAX(p.updated_at) ultimo_palpite
            FROM " . rsb_table('jogos') . " j
            LEFT JOIN " . rsb_table('palpites') . " p ON p.jogo_id=j.id AND p.bolao_id=j.bolao_id
            WHERE j.bolao_id=%d
            GROUP BY j.id, j.codigo_jogo, j.fase, j.grupo, j.rodada, j.data_jogo, j.hora_jogo, j.time_mandante, j.time_visitante, j.status
            ORDER BY COALESCE(j.data_jogo,'2099-12-31'), j.hora_jogo, j.id",
            $bolao_id
        ));
    }

    public static function by_participant(int $bolao_id, int $participante_id): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT p.*, j.codigo_jogo, j.fase, j.grupo, j.rodada, j.data_jogo, j.hora_jogo, j.time_mandante, j.time_visitante, j.gols_mandante, j.gols_visitante, j.status FROM ".rsb_table('palpites')." p JOIN ".rsb_table('jogos')." j ON j.id=p.jogo_id WHERE p.bolao_id=%d AND p.participante_id=%d ORDER BY COALESCE(j.data_jogo,'2099-12-31'), j.hora_jogo", $bolao_id, $participante_id));
    }

    public static function indexed_by_jogo(array $palpites): array {
        $out=[]; foreach($palpites as $p){ $out[(int)$p->jogo_id]=$p; } return $out;
    }

    public static function log_change(?int $palpite_id, int $bolao_id, int $participante_id, int $jogo_id, $old, $new, string $acao): void {
        global $wpdb;
        $wpdb->insert(rsb_table('palpite_logs'), [
            'palpite_id'=>$palpite_id,
            'bolao_id'=>$bolao_id,
            'participante_id'=>$participante_id,
            'jogo_id'=>$jogo_id,
            'valor_antigo'=> $old === null ? null : wp_json_encode($old, JSON_UNESCAPED_UNICODE),
            'valor_novo'=> $new === null ? null : wp_json_encode($new, JSON_UNESCAPED_UNICODE),
            'acao'=>$acao,
            'ip'=>sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent'=>substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'created_at'=>current_time('mysql'),
        ]);
    }

    public static function upsert(array $data) {
        global $wpdb;
        $jogo=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".rsb_table('jogos')." WHERE id=%d", (int)$data['jogo_id']));
        if(!$jogo){ return new WP_Error('jogo_invalido', 'Jogo não encontrado.'); }
        if(!RSB_Jogos::is_bettable($jogo)){
            return new WP_Error('palpite_bloqueado', 'Palpites encerram 1 hora antes da partida, pelo horário de Brasília. Não é possível criar ou editar este palpite.');
        }
        if(!isset($data['palpite_gols_mandante'], $data['palpite_gols_visitante']) || $data['palpite_gols_mandante'] === '' || $data['palpite_gols_visitante'] === ''){
            return new WP_Error('placar_obrigatorio', 'Informe os dois placares antes do prazo. Palpite vazio não poderá ser preenchido depois.');
        }
        if(!is_numeric($data['palpite_gols_mandante']) || !is_numeric($data['palpite_gols_visitante'])){
            return new WP_Error('placar_invalido', 'Use apenas números inteiros nos placares.');
        }
        $gm = (int)$data['palpite_gols_mandante'];
        $gv = (int)$data['palpite_gols_visitante'];
        if((string)$gm !== (string)$data['palpite_gols_mandante'] || (string)$gv !== (string)$data['palpite_gols_visitante'] || $gm < 0 || $gv < 0){
            return new WP_Error('placar_invalido', 'Use apenas números inteiros iguais ou maiores que zero.');
        }
        $now=current_time('mysql');
        $payload=[
            'bolao_id'=>(int)$jogo->bolao_id,
            'jogo_id'=>(int)$jogo->id,
            'participante_id'=>(int)$data['participante_id'],
            'palpite_gols_mandante'=>$gm,
            'palpite_gols_visitante'=>$gv,
            'updated_at'=>$now
        ];
        $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".rsb_table('palpites')." WHERE jogo_id=%d AND participante_id=%d", (int)$jogo->id, (int)$data['participante_id']));
        if($existing){
            $wpdb->update(rsb_table('palpites'),$payload,['id'=>(int)$existing->id]);
            self::log_change((int)$existing->id, (int)$jogo->bolao_id, (int)$data['participante_id'], (int)$jogo->id, $existing, $payload, 'editar');
            rsb_log('atualizar','palpite',(int)$existing->id,$existing,$payload);
            if (class_exists('RSB_Ranking')) { RSB_Ranking::recalculate((int)$jogo->bolao_id); }
            return (int)$existing->id;
        }
        $payload['created_at']=$now;
        $wpdb->insert(rsb_table('palpites'),$payload);
        $id=(int)$wpdb->insert_id;
        self::log_change($id, (int)$jogo->bolao_id, (int)$data['participante_id'], (int)$jogo->id, null, $payload, 'criar');
        rsb_log('criar','palpite',$id,null,$payload);
        if (class_exists('RSB_Ranking')) { RSB_Ranking::recalculate((int)$jogo->bolao_id); }
        return $id;
    }
}
