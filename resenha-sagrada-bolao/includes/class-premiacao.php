<?php
if (!defined('ABSPATH')) { exit; }

class RSB_Premiacao {
    public static function prize_pool(int $bolao_id): float {
        return class_exists('RSB_Pagamentos') ? (float) RSB_Pagamentos::total($bolao_id) : 0.0;
    }

    public static function get_bolao(int $bolao_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . rsb_table('boloes') . " WHERE id=%d",
            $bolao_id
        ));
    }

    /**
     * Compatibilidade para os shortcodes públicos.
     * Retorna o resumo da premiação e, se já existir ranking, aplica a divisão por empate.
     */
    public static function calculate(int $bolao_id): array {
        self::apply($bolao_id);
        return self::summary($bolao_id);
    }

    public static function apply(int $bolao_id): void {
        global $wpdb;

        $bolao = self::get_bolao($bolao_id);
        if (!$bolao) { return; }

        $pool = self::prize_pool($bolao_id);
        $prizes = [
            1 => $pool * ((float) $bolao->percentual_primeiro / 100),
            2 => $pool * ((float) $bolao->percentual_segundo / 100),
            3 => $pool * ((float) $bolao->percentual_terceiro / 100),
        ];

        $ranking = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . rsb_table('ranking') . " WHERE bolao_id=%d ORDER BY posicao ASC, total_pontos DESC, placares_exatos DESC, resultados_corretos DESC",
            $bolao_id
        ));

        if (!$ranking) { return; }

        // Zera premiação antes de recalcular.
        $wpdb->query($wpdb->prepare(
            "UPDATE " . rsb_table('ranking') . " SET premiacao_estimada=0, updated_at=%s WHERE bolao_id=%d",
            current_time('mysql'),
            $bolao_id
        ));

        $groups = [];
        foreach ($ranking as $r) {
            $pos = (int) $r->posicao;
            if ($pos >= 1 && $pos <= 3) {
                $groups[$pos][] = $r;
            }
        }

        foreach ($groups as $pos => $items) {
            $count = count($items);
            if ($count <= 0) { continue; }

            $sum = 0.0;
            for ($i = $pos; $i < ($pos + $count); $i++) {
                $sum += $prizes[$i] ?? 0.0;
            }

            $each = $sum / $count;
            foreach ($items as $r) {
                $wpdb->update(
                    rsb_table('ranking'),
                    ['premiacao_estimada' => $each, 'updated_at' => current_time('mysql')],
                    ['id' => (int) $r->id],
                    ['%f', '%s'],
                    ['%d']
                );
            }
        }
    }

    public static function summary(int $bolao_id): array {
        $bolao = self::get_bolao($bolao_id);
        $pool = self::prize_pool($bolao_id);

        if (!$bolao) {
            return ['total' => $pool, 'primeiro' => 0, 'segundo' => 0, 'terceiro' => 0];
        }

        return [
            'total' => $pool,
            'primeiro' => $pool * ((float) $bolao->percentual_primeiro / 100),
            'segundo' => $pool * ((float) $bolao->percentual_segundo / 100),
            'terceiro' => $pool * ((float) $bolao->percentual_terceiro / 100),
        ];
    }
}
