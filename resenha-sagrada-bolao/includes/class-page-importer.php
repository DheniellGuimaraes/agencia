<?php
if (!defined('ABSPATH')) { exit; }

class RSB_Page_Importer {
    public static function pages(): array {
        return [
            [
                'title' => 'Resenha Sagrada — Área do Apostador',
                'slug' => 'resenha-sagrada-area-do-apostador',
                'shortcode' => '[resenha_sagrada_participante]',
                'description' => 'Página principal com visão geral, acesso ao bolão, ranking, premiação, status e atalhos.',
                'template' => 'principal',
            ],
            [
                'title' => 'Resenha Sagrada — Palpites',
                'slug' => 'resenha-sagrada-palpites',
                'shortcode' => '[resenha_sagrada_palpites]',
                'description' => 'Página para registrar e editar palpites antes do início dos jogos.',
                'template' => 'palpites',
            ],
            [
                'title' => 'Resenha Sagrada — Meus Palpites',
                'slug' => 'resenha-sagrada-meus-palpites',
                'shortcode' => '[resenha_sagrada_meus_palpites]',
                'description' => 'Histórico dos palpites enviados, pontuação e tipo de acerto.',
                'template' => 'simples',
            ],
            [
                'title' => 'Resenha Sagrada — Jogos da Copa 2026',
                'slug' => 'resenha-sagrada-jogos-copa-2026',
                'shortcode' => '[resenha_sagrada_jogos]',
                'description' => 'Tabela pública de jogos oficiais, fases, grupos, horários e status.',
                'template' => 'simples',
            ],
            [
                'title' => 'Resenha Sagrada — Ranking',
                'slug' => 'resenha-sagrada-ranking',
                'shortcode' => '[resenha_sagrada_ranking]',
                'description' => 'Ranking geral do bolão com pontos, critérios e posição dos participantes.',
                'template' => 'simples',
            ],
            [
                'title' => 'Resenha Sagrada — Premiação',
                'slug' => 'resenha-sagrada-premiacao',
                'shortcode' => '[resenha_sagrada_premiacao]',
                'description' => 'Premiação atual calculada pela arrecadação e regras de empate.',
                'template' => 'simples',
            ],
            [
                'title' => 'Resenha Sagrada — Pagamento',
                'slug' => 'resenha-sagrada-pagamento',
                'shortcode' => '[resenha_sagrada_pagamento]',
                'description' => 'Status de pagamento do apostador e orientação para regularização.',
                'template' => 'simples',
            ],
            [
                'title' => 'Resenha Sagrada — Regras',
                'slug' => 'resenha-sagrada-regras',
                'shortcode' => '[resenha_sagrada_regras]',
                'description' => 'Regras do bolão, pontuação, bloqueio de palpites, ranking e premiação.',
                'template' => 'simples',
            ],
        ];
    }

    public static function all_shortcode_reference(): array {
        return [
            '[resenha_sagrada_participante]' => 'Área completa do apostador',
            '[resenha_sagrada_app]' => 'Alias da área completa do apostador',
            '[resenha_sagrada_palpites]' => 'Formulário de palpites',
            '[resenha_sagrada_meus_palpites]' => 'Histórico de palpites do apostador',
            '[resenha_sagrada_jogos]' => 'Lista de jogos oficiais',
            '[resenha_sagrada_ranking]' => 'Ranking público',
            '[resenha_sagrada_premiacao]' => 'Premiação pública',
            '[resenha_sagrada_pagamento]' => 'Status de pagamento',
            '[resenha_sagrada_regras]' => 'Regras do bolão',
        ];
    }

    public static function import(bool $overwrite = false): array {
        if (!current_user_can(rsb_admin_cap())) {
            return ['created'=>0,'updated'=>0,'skipped'=>0,'items'=>[],'error'=>'Acesso negado.'];
        }
        $created = 0; $updated = 0; $skipped = 0; $items = [];
        foreach (self::pages() as $page) {
            $existing = get_page_by_path($page['slug'], OBJECT, 'page');
            $content = self::content($page);
            if ($existing && !$overwrite) {
                $skipped++;
                $items[] = ['title'=>$page['title'],'status'=>'existente','url'=>get_permalink($existing->ID),'id'=>$existing->ID];
                continue;
            }
            $postarr = [
                'post_title' => $page['title'],
                'post_name' => $page['slug'],
                'post_content' => $content,
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ];
            if ($existing) {
                $postarr['ID'] = (int)$existing->ID;
                $post_id = wp_update_post($postarr, true);
                $action = 'atualizada';
            } else {
                $post_id = wp_insert_post($postarr, true);
                $action = 'criada';
            }
            if (is_wp_error($post_id)) {
                $skipped++;
                $items[] = ['title'=>$page['title'],'status'=>'erro: '.$post_id->get_error_message(),'url'=>'','id'=>0];
                continue;
            }
            update_post_meta((int)$post_id, '_rsb_imported_page', '1');
            update_post_meta((int)$post_id, '_rsb_shortcode', $page['shortcode']);
            if ($existing) { $updated++; } else { $created++; }
            $items[] = ['title'=>$page['title'],'status'=>$action,'url'=>get_permalink((int)$post_id),'id'=>(int)$post_id];
        }
        self::maybe_create_menu();
        return ['created'=>$created,'updated'=>$updated,'skipped'=>$skipped,'items'=>$items];
    }

    public static function content(array $page): string {
        $nav = self::navigation_block();
        $shortcode = $page['shortcode'];
        $intro = '<!-- wp:group {"className":"rsb-wp-page-shell"} --><div class="wp-block-group rsb-wp-page-shell">';
        $intro .= '<!-- wp:heading {"level":1} --><h1>'.esc_html($page['title']).'</h1><!-- /wp:heading -->';
        $intro .= '<!-- wp:paragraph --><p>'.esc_html($page['description']).'</p><!-- /wp:paragraph -->';
        $intro .= $nav;
        $intro .= '<!-- wp:shortcode -->'.$shortcode.'<!-- /wp:shortcode -->';
        $intro .= '</div><!-- /wp:group -->';
        return $intro;
    }

    public static function navigation_block(): string {
        $links = [];
        foreach (self::pages() as $p) {
            $url = home_url('/'.$p['slug'].'/');
            $label = str_replace('Resenha Sagrada — ', '', $p['title']);
            $links[] = '<a href="'.esc_url($url).'">'.esc_html($label).'</a>';
        }
        return '<!-- wp:html --><nav class="rsb-public-nav" style="display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 24px">'.implode('', $links).'</nav><!-- /wp:html -->';
    }

    public static function maybe_create_menu(): void {
        $menu_name = 'Resenha Sagrada — Bolão';
        $menu = wp_get_nav_menu_object($menu_name);
        if (!$menu) {
            $menu_id = wp_create_nav_menu($menu_name);
        } else {
            $menu_id = (int)$menu->term_id;
        }
        if (is_wp_error($menu_id) || !$menu_id) { return; }
        $existing_items = wp_get_nav_menu_items($menu_id) ?: [];
        $existing_urls = [];
        foreach ($existing_items as $item) { $existing_urls[] = untrailingslashit((string)$item->url); }
        foreach (self::pages() as $p) {
            $page = get_page_by_path($p['slug'], OBJECT, 'page');
            if (!$page) { continue; }
            $url = untrailingslashit(get_permalink($page->ID));
            if (in_array($url, $existing_urls, true)) { continue; }
            wp_update_nav_menu_item($menu_id, 0, [
                'menu-item-title' => str_replace('Resenha Sagrada — ', '', $p['title']),
                'menu-item-object' => 'page',
                'menu-item-object-id' => (int)$page->ID,
                'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish',
            ]);
        }
    }
}
