<?php
if (!defined('ABSPATH')) { exit; }

class RSB_Database {
    public static function create_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $sql = [];
        $sql[] = "CREATE TABLE " . rsb_table('temporadas') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'ativo',
            data_inicio DATE NULL,
            data_fim DATE NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";
        $sql[] = "CREATE TABLE " . rsb_table('boloes') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            temporada_id BIGINT UNSIGNED NULL,
            nome VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            valor_inscricao DECIMAL(10,2) NOT NULL DEFAULT 100.00,
            limite_participantes INT NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'ativo',
            data_inicio DATE NULL,
            data_fim DATE NULL,
            pontos_placar_exato INT NOT NULL DEFAULT 3,
            pontos_resultado_correto INT NOT NULL DEFAULT 1,
            pontos_erro INT NOT NULL DEFAULT 0,
            percentual_primeiro DECIMAL(5,2) NOT NULL DEFAULT 70.00,
            percentual_segundo DECIMAL(5,2) NOT NULL DEFAULT 20.00,
            percentual_terceiro DECIMAL(5,2) NOT NULL DEFAULT 10.00,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";
        $sql[] = "CREATE TABLE " . rsb_table('participantes') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bolao_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            nome VARCHAR(180) NOT NULL,
            email VARCHAR(190) NULL,
            telefone VARCHAR(60) NULL,
            status_pagamento VARCHAR(30) NOT NULL DEFAULT 'pendente',
            valor_pago DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            data_pagamento DATE NULL,
            observacoes TEXT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id), KEY bolao_id (bolao_id), KEY user_id (user_id)
        ) $charset;";
        $sql[] = "CREATE TABLE " . rsb_table('jogos') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bolao_id BIGINT UNSIGNED NOT NULL,
            codigo_jogo VARCHAR(40) NOT NULL,
            fase VARCHAR(100) NULL,
            grupo VARCHAR(30) NULL,
            rodada VARCHAR(80) NULL,
            local_jogo VARCHAR(180) NULL,
            data_jogo DATE NULL,
            hora_jogo TIME NULL,
            time_mandante VARCHAR(120) NOT NULL,
            time_visitante VARCHAR(120) NOT NULL,
            gols_mandante INT NULL,
            gols_visitante INT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'agendado',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id), KEY bolao_id (bolao_id), UNIQUE KEY jogo_unico (bolao_id,codigo_jogo)
        ) $charset;";
        $sql[] = "CREATE TABLE " . rsb_table('palpites') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bolao_id BIGINT UNSIGNED NOT NULL,
            jogo_id BIGINT UNSIGNED NOT NULL,
            participante_id BIGINT UNSIGNED NOT NULL,
            palpite_gols_mandante INT NOT NULL,
            palpite_gols_visitante INT NOT NULL,
            pontos INT NOT NULL DEFAULT 0,
            tipo_acerto VARCHAR(50) NOT NULL DEFAULT 'aguardar_resultado',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY palpite_unico (jogo_id,participante_id), KEY bolao_id (bolao_id)
        ) $charset;";
        $sql[] = "CREATE TABLE " . rsb_table('pagamentos') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bolao_id BIGINT UNSIGNED NOT NULL,
            participante_id BIGINT UNSIGNED NOT NULL,
            valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) NOT NULL DEFAULT 'pendente',
            forma_pagamento VARCHAR(80) NULL,
            data_pagamento DATE NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id), KEY participante_id (participante_id)
        ) $charset;";
        $sql[] = "CREATE TABLE " . rsb_table('ranking') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bolao_id BIGINT UNSIGNED NOT NULL,
            participante_id BIGINT UNSIGNED NOT NULL,
            posicao INT NOT NULL,
            total_pontos INT NOT NULL DEFAULT 0,
            placares_exatos INT NOT NULL DEFAULT 0,
            resultados_corretos INT NOT NULL DEFAULT 0,
            total_palpites INT NOT NULL DEFAULT 0,
            aproveitamento DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            premiacao_estimada DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY participante_bolao (bolao_id, participante_id)
        ) $charset;";
        $sql[] = "CREATE TABLE " . rsb_table('palpite_logs') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            palpite_id BIGINT UNSIGNED NULL,
            bolao_id BIGINT UNSIGNED NOT NULL,
            participante_id BIGINT UNSIGNED NOT NULL,
            jogo_id BIGINT UNSIGNED NOT NULL,
            valor_antigo LONGTEXT NULL,
            valor_novo LONGTEXT NULL,
            acao VARCHAR(80) NOT NULL,
            ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), KEY participante_id (participante_id), KEY jogo_id (jogo_id), KEY created_at (created_at)
        ) $charset;";
        $sql[] = "CREATE TABLE " . rsb_table('logs') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            acao VARCHAR(120) NOT NULL,
            modulo VARCHAR(80) NOT NULL,
            registro_id BIGINT UNSIGNED NULL,
            dados_antigos LONGTEXT NULL,
            dados_novos LONGTEXT NULL,
            ip VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), KEY modulo (modulo), KEY created_at (created_at)
        ) $charset;";
        foreach ($sql as $statement) { dbDelta($statement); }
    }

    public static function seed_defaults(): void {
        global $wpdb;
        $now = current_time('mysql');
        $exists = (int)$wpdb->get_var("SELECT COUNT(*) FROM " . rsb_table('boloes'));
        if ($exists > 0) { return; }
        $wpdb->insert(rsb_table('temporadas'), ['nome'=>'Copa do Mundo 2026','descricao'=>'Temporada inicial importada da planilha','status'=>'ativo','created_at'=>$now,'updated_at'=>$now]);
        $temporada_id = (int)$wpdb->insert_id;
        $wpdb->insert(rsb_table('boloes'), [
            'temporada_id'=>$temporada_id,'nome'=>'Bolão Copa do Mundo','descricao'=>'Resenha Sagrada — Bolão da Copa 26','valor_inscricao'=>100,
            'limite_participantes'=>0,'status'=>'ativo','pontos_placar_exato'=>3,'pontos_resultado_correto'=>1,'pontos_erro'=>0,
            'percentual_primeiro'=>70,'percentual_segundo'=>20,'percentual_terceiro'=>10,'created_at'=>$now,'updated_at'=>$now
        ]);
        $bolao_id = (int)$wpdb->insert_id;
        if (class_exists('RSB_WorldCup_2026_Seeder')) {
            RSB_WorldCup_2026_Seeder::import($bolao_id, false);
        }
    }


    public static function upgrade_2026_1(): void {
        global $wpdb;
        if (!class_exists('RSB_WorldCup_Format')) { return; }
        $table = rsb_table('jogos');
        $rows = $wpdb->get_results("SELECT id, fase, rodada FROM {$table}");
        foreach ($rows as $row) {
            $fase = RSB_WorldCup_Format::normalize_phase((string)$row->fase);
            $rodada = (string)$row->rodada;
            if (stripos($rodada, '32 avos') !== false || stripos($rodada, 'rodada de 32') !== false) {
                $rodada = '16-avos de final / Rodada de 32';
            }
            if ($fase !== $row->fase || $rodada !== $row->rodada) {
                $wpdb->update($table, ['fase'=>$fase, 'rodada'=>$rodada, 'updated_at'=>current_time('mysql')], ['id'=>(int)$row->id]);
            }
        }
        update_option('rsb_release_label', '2026.1');
    }

    public static function upgrade_2026_10_unlimited_participants(): void {
        global $wpdb;
        $wpdb->query(
            "UPDATE " . rsb_table('boloes') . " SET limite_participantes=0, updated_at='" . esc_sql(current_time('mysql')) . "' WHERE limite_participantes<>0"
        );
        update_option('rsb_release_label', '2026.10');
    }
}
