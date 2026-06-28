<?php
if (!defined('ABSPATH')) { exit; }
class RSB_Bolao {
    public static function current(): ?object {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM " . rsb_table('boloes') . " ORDER BY id ASC LIMIT 1");
    }
    public static function update_current(array $data): bool {
        global $wpdb;
        $bolao = self::current(); if (!$bolao) { return false; }
        $percent_sum = (float)$data['percentual_primeiro'] + (float)$data['percentual_segundo'] + (float)$data['percentual_terceiro'];
        if ($percent_sum > 100) { return false; }
        $payload = [
            'nome'=>sanitize_text_field($data['nome']),
            'descricao'=>sanitize_textarea_field($data['descricao']),
            'valor_inscricao'=>(float)$data['valor_inscricao'],
            'limite_participantes'=>isset($data['limite_participantes']) ? max(0, (int)$data['limite_participantes']) : 0,
            'status'=>sanitize_text_field($data['status']),
            'pontos_placar_exato'=>(int)$data['pontos_placar_exato'],
            'pontos_resultado_correto'=>(int)$data['pontos_resultado_correto'],
            'percentual_primeiro'=>(float)$data['percentual_primeiro'],
            'percentual_segundo'=>(float)$data['percentual_segundo'],
            'percentual_terceiro'=>(float)$data['percentual_terceiro'],
            'updated_at'=>current_time('mysql'),
        ];
        $ok = false !== $wpdb->update(rsb_table('boloes'), $payload, ['id'=>(int)$bolao->id]);
        if ($ok) { rsb_log('atualizar', 'bolao', (int)$bolao->id, $bolao, $payload); }
        return $ok;
    }
}
