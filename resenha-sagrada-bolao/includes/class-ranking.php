<?php
if (!defined('ABSPATH')) { exit; }
class RSB_Ranking {
    private static function bolao(int $bolao_id) {
        global $wpdb;
        $current = RSB_Bolao::current();
        if ($current && (int) $current->id === $bolao_id) { return $current; }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . rsb_table('boloes') . " WHERE id=%d", $bolao_id));
    }

    public static function score_bet(object $jogo, object $palpite, object $bolao): array {
        $official_score = self::official_regulation_score($jogo);
        if (!$official_score) {
            return ['pontos'=>0, 'tipo_acerto'=>'aguardar_resultado'];
        }
        [$gm, $gv] = $official_score;
        $pm=(int)$palpite->palpite_gols_mandante; $pv=(int)$palpite->palpite_gols_visitante;
        if ($gm === $pm && $gv === $pv) { return ['pontos'=>(int)$bolao->pontos_placar_exato, 'tipo_acerto'=>'placar_exato']; }
        if (rsb_result_type($gm,$gv) === rsb_result_type($pm,$pv)) { return ['pontos'=>(int)$bolao->pontos_resultado_correto, 'tipo_acerto'=>'resultado_correto']; }
        return ['pontos'=>(int)$bolao->pontos_erro, 'tipo_acerto'=>'erro'];
    }

    public static function official_regulation_score(object $jogo): ?array {
        $status = strtolower(trim((string) ($jogo->status ?? '')));
        if ($status !== 'finalizado' || $jogo->gols_mandante === null || $jogo->gols_visitante === null || $jogo->gols_mandante === '' || $jogo->gols_visitante === '') {
            return null;
        }
        // Regra do bolao: em mata-mata a pontuacao usa exclusivamente o placar dos 90 minutos + acrescimos.
        // Prorrogacao e penaltis nao devem ser gravados em gols_mandante/gols_visitante nem entrar no ranking.
        return [(int)$jogo->gols_mandante, (int)$jogo->gols_visitante];
    }

    public static function recalculate_game(int $bolao_id, int $jogo_id): void {
        global $wpdb;
        $bolao = self::bolao($bolao_id);
        $jogo = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . rsb_table('jogos') . " WHERE id=%d AND bolao_id=%d", $jogo_id, $bolao_id));
        if (!$bolao || !$jogo) { return; }
        $palpites = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . rsb_table('palpites') . " WHERE jogo_id=%d AND bolao_id=%d", $jogo_id, $bolao_id));
        $now = current_time('mysql');
        foreach ($palpites as $p) {
            $score = self::score_bet($jogo, $p, $bolao);
            $wpdb->update(rsb_table('palpites'), ['pontos'=>$score['pontos'], 'tipo_acerto'=>$score['tipo_acerto'], 'updated_at'=>$now], ['id'=>(int)$p->id]);
        }
        self::recalculate($bolao_id);
    }

    public static function recalculate_all_scores(int $bolao_id): array {
        global $wpdb;
        $bolao = self::bolao($bolao_id);
        if (!$bolao) { return ['palpites'=>0, 'jogos'=>0, 'finalizados'=>0]; }
        $jogos = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . rsb_table('jogos') . " WHERE bolao_id=%d", $bolao_id));
        $jogos_by_id = [];
        $finalizados = 0;
        foreach ($jogos as $j) {
            $jogos_by_id[(int)$j->id] = $j;
            if (self::official_regulation_score($j)) { $finalizados++; }
        }
        $palpites = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . rsb_table('palpites') . " WHERE bolao_id=%d", $bolao_id));
        $now = current_time('mysql');
        foreach ($palpites as $p) {
            $jogo = $jogos_by_id[(int)$p->jogo_id] ?? null;
            if (!$jogo) { continue; }
            $score = self::score_bet($jogo, $p, $bolao);
            $wpdb->update(rsb_table('palpites'), ['pontos'=>$score['pontos'], 'tipo_acerto'=>$score['tipo_acerto'], 'updated_at'=>$now], ['id'=>(int)$p->id]);
        }
        self::recalculate($bolao_id);
        $summary = ['palpites'=>count($palpites), 'jogos'=>count($jogos), 'finalizados'=>$finalizados];
        rsb_log('recalcular','pontuacao',$bolao_id,null,$summary);
        return $summary;
    }

    public static function recalculate(int $bolao_id): void { global $wpdb; $rows=$wpdb->get_results($wpdb->prepare("SELECT part.id participante_id, COALESCE(SUM(p.pontos),0) total_pontos, SUM(CASE WHEN p.tipo_acerto='placar_exato' THEN 1 ELSE 0 END) placares_exatos, SUM(CASE WHEN p.tipo_acerto='resultado_correto' THEN 1 ELSE 0 END) resultados_corretos, COUNT(p.id) total_palpites FROM ".rsb_table('participantes')." part LEFT JOIN ".rsb_table('palpites')." p ON p.participante_id=part.id AND p.bolao_id=part.bolao_id WHERE part.bolao_id=%d AND part.ativo=1 GROUP BY part.id ORDER BY total_pontos DESC, placares_exatos DESC, resultados_corretos DESC, total_palpites DESC",$bolao_id)); $wpdb->delete(rsb_table('ranking'),['bolao_id'=>$bolao_id]); $now=current_time('mysql'); $pos=0; $last=null; $rank=0; foreach($rows as $r){ $pos++; $key=$r->total_pontos.'|'.$r->placares_exatos.'|'.$r->resultados_corretos.'|'.$r->total_palpites; if($key!==$last){$rank=$pos;$last=$key;} $max=(int)max(1,$r->total_palpites)*3; $aproveitamento=$max>0?(((int)$r->total_pontos/$max)*100):0; $wpdb->insert(rsb_table('ranking'),['bolao_id'=>$bolao_id,'participante_id'=>(int)$r->participante_id,'posicao'=>$rank,'total_pontos'=>(int)$r->total_pontos,'placares_exatos'=>(int)$r->placares_exatos,'resultados_corretos'=>(int)$r->resultados_corretos,'total_palpites'=>(int)$r->total_palpites,'aproveitamento'=>$aproveitamento,'premiacao_estimada'=>0,'created_at'=>$now,'updated_at'=>$now]); } RSB_Premiacao::apply($bolao_id); delete_transient('rsb_ranking_'.$bolao_id); rsb_log('recalcular','ranking',$bolao_id,null,['rows'=>count($rows)]); }
    public static function get(int $bolao_id): array { global $wpdb; $cache=get_transient('rsb_ranking_'.$bolao_id); if($cache!==false){return $cache;} $rows=$wpdb->get_results($wpdb->prepare("SELECT r.*, p.nome FROM ".rsb_table('ranking')." r JOIN ".rsb_table('participantes')." p ON p.id=r.participante_id WHERE r.bolao_id=%d ORDER BY r.posicao ASC, r.total_pontos DESC, r.placares_exatos DESC, r.resultados_corretos DESC",$bolao_id)); set_transient('rsb_ranking_'.$bolao_id,$rows,60); return $rows; }
}
