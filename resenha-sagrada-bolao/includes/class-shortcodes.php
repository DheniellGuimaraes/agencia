<?php
if (!defined('ABSPATH')) { exit; }

class RSB_Shortcodes {
    public static function register(): void {
        add_shortcode('resenha_sagrada_participante',[__CLASS__,'participante']);
        add_shortcode('resenha_sagrada_app',[__CLASS__,'participante']);
        add_shortcode('resenha_sagrada_palpites',[__CLASS__,'palpites']);
        add_shortcode('resenha_sagrada_jogos',[__CLASS__,'jogos']);
        add_shortcode('resenha_sagrada_meus_palpites',[__CLASS__,'meus_palpites']);
        add_shortcode('resenha_sagrada_ranking',[__CLASS__,'ranking']);
        add_shortcode('resenha_sagrada_premiacao',[__CLASS__,'premiacao']);
        add_shortcode('resenha_sagrada_pagamento',[__CLASS__,'pagamento']);
        add_shortcode('resenha_sagrada_regras',[__CLASS__,'regras']);
    }

    public static function current_participant($bolao = null) {
        if (!is_user_logged_in() || !$bolao) { return null; }
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM ".rsb_table('participantes')." WHERE bolao_id=%d AND user_id=%d AND ativo=1",
            (int)$bolao->id,
            get_current_user_id()
        ));
    }

    public static function participante(): string {
        ob_start();
        $bolao = RSB_Bolao::current();
        $participant = self::current_participant($bolao);
        $ranking = $bolao ? RSB_Ranking::get((int)$bolao->id) : [];
        $premiacao = $bolao ? RSB_Premiacao::calculate((int)$bolao->id) : [];
        include RSB_PATH.'public/views/participante-dashboard.php';
        return ob_get_clean();
    }

    public static function jogos(): string {
        ob_start();
        $bolao = RSB_Bolao::current();
        $jogos = $bolao ? RSB_Jogos::all((int)$bolao->id) : [];
        include RSB_PATH.'public/views/participante-jogos.php';
        return ob_get_clean();
    }

    public static function palpites(): string {
        ob_start();
        $bolao = RSB_Bolao::current();
        $participant = self::current_participant($bolao);
        $jogos = $bolao ? RSB_Jogos::all((int)$bolao->id) : [];
        $meus_palpites = ($bolao && $participant) ? RSB_Palpites::by_participant((int)$bolao->id, (int)$participant->id) : [];
        include RSB_PATH.'public/views/participante-palpites.php';
        return ob_get_clean();
    }

    public static function meus_palpites(): string {
        ob_start();
        $bolao = RSB_Bolao::current();
        $participant = self::current_participant($bolao);
        $meus_palpites = ($bolao && $participant) ? RSB_Palpites::by_participant((int)$bolao->id, (int)$participant->id) : [];
        include RSB_PATH.'public/views/participante-meus-palpites.php';
        return ob_get_clean();
    }

    public static function ranking(): string {
        ob_start();
        $bolao=RSB_Bolao::current();
        $ranking=$bolao?RSB_Ranking::get((int)$bolao->id):[];
        include RSB_PATH.'public/views/participante-ranking.php';
        return ob_get_clean();
    }

    public static function premiacao(): string {
        ob_start();
        $bolao=RSB_Bolao::current();
        $premiacao=$bolao?RSB_Premiacao::calculate((int)$bolao->id):[];
        include RSB_PATH.'public/views/participante-premiacao.php';
        return ob_get_clean();
    }

    public static function pagamento(): string {
        ob_start();
        $bolao=RSB_Bolao::current();
        $participant = self::current_participant($bolao);
        include RSB_PATH.'public/views/participante-pagamento.php';
        return ob_get_clean();
    }

    public static function regras(): string {
        ob_start();
        $bolao=RSB_Bolao::current();
        include RSB_PATH.'public/views/participante-regras.php';
        return ob_get_clean();
    }
}
