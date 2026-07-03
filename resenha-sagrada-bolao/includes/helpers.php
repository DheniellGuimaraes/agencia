<?php
if (!defined('ABSPATH')) { exit; }

function rsb_table(string $name): string {
    global $wpdb;
    return $wpdb->prefix . 'rs_' . $name;
}

function rsb_admin_cap(): string { return 'manage_options'; }

function rsb_money($value): string {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function rsb_brasilia_timezone(): DateTimeZone {
    return new DateTimeZone('America/Sao_Paulo');
}

function rsb_brasilia_datetime($timestamp, string $format = 'd/m/Y H:i'): string {
    return wp_date($format, (int) $timestamp, rsb_brasilia_timezone());
}

function rsb_clean_text($value): string {
    return sanitize_text_field(wp_unslash((string)$value));
}

function rsb_result_type(int $g1, int $g2): string {
    if ($g1 > $g2) { return 'mandante'; }
    if ($g1 < $g2) { return 'visitante'; }
    return 'empate';
}

function rsb_log(string $acao, string $modulo, int $registro_id = 0, $old = null, $new = null): void {
    global $wpdb;
    $wpdb->insert(rsb_table('logs'), [
        'user_id' => get_current_user_id(),
        'acao' => sanitize_text_field($acao),
        'modulo' => sanitize_text_field($modulo),
        'registro_id' => $registro_id,
        'dados_antigos' => $old === null ? null : wp_json_encode($old),
        'dados_novos' => $new === null ? null : wp_json_encode($new),
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        'created_at' => current_time('mysql'),
    ]);
}

function rsb_country_flag(string $team): string {
    if (class_exists('RSB_Teams')) {
        return RSB_Teams::get_flag($team);
    }
    $map = [
        'Brasil'=>'🇧🇷','Argentina'=>'🇦🇷','França'=>'🇫🇷','Alemanha'=>'🇩🇪','Espanha'=>'🇪🇸','Portugal'=>'🇵🇹','Inglaterra'=>'🏴','Itália'=>'🇮🇹','Holanda'=>'🇳🇱','Bélgica'=>'🇧🇪','Uruguai'=>'🇺🇾','México'=>'🇲🇽','Estados Unidos'=>'🇺🇸','Canadá'=>'🇨🇦','Japão'=>'🇯🇵','Coreia do Sul'=>'🇰🇷','Arábia Saudita'=>'🇸🇦','Irã'=>'🇮🇷','Austrália'=>'🇦🇺','Nova Zelândia'=>'🇳🇿','Marrocos'=>'🇲🇦','Senegal'=>'🇸🇳','Tunísia'=>'🇹🇳','Egito'=>'🇪🇬','África do Sul'=>'🇿🇦','Costa do Marfim'=>'🇨🇮','Gana'=>'🇬🇭','Camarões'=>'🇨🇲','Nigéria'=>'🇳🇬','Catar'=>'🇶🇦','Suíça'=>'🇨🇭','Dinamarca'=>'🇩🇰','Suécia'=>'🇸🇪','Noruega'=>'🇳🇴','Croácia'=>'🇭🇷','Sérvia'=>'🇷🇸','Tchéquia'=>'🇨🇿','Polônia'=>'🇵🇱','Paraguai'=>'🇵🇾','Equador'=>'🇪🇨','Colômbia'=>'🇨🇴','Chile'=>'🇨🇱','Peru'=>'🇵🇪','Haiti'=>'🇭🇹','Escócia'=>'🏴','Turquia'=>'🇹🇷','Curaçao'=>'🇨🇼','Cabo Verde'=>'🇨🇻','Bósnia e Herzegovina'=>'🇧🇦','RD Congo'=>'🇨🇩'
    ];
    return $map[$team] ?? '⚽';
}

function rsb_flag_html(string $team): string {
    if (class_exists('RSB_Teams')) {
        return RSB_Teams::render_flag($team);
    }
    return '<span class="rsb-flag" title="'.esc_attr($team).'">'.esc_html(rsb_country_flag($team)).'</span>';
}

function rsb_match_lock_label($jogo): string {
    $lock = class_exists('RSB_Jogos') ? RSB_Jogos::lock_timestamp($jogo) : null;
    return $lock ? rsb_brasilia_datetime($lock) . ' BRT' : '';
}
