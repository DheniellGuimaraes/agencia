<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Settings {
    const OPTION = 'ses_settings';

    public static function protected_paths_defaults() {
        return array(
            'home',
            'a-empresa',
            'servicos',
            'portfolio',
            'fale-conosco',
            'orcamentos',
            'noticias',
            'servicos/desenvolvimento-de-sites-lojas-virtuais',
            'servicos/criacao-de-marcas-logotipos',
            'servicos/design-digital',
            'servicos/design-grafico',
            'servicos/identidade-visual',
            'servicos/intranets',
            'servicos/lojas-virtuais-e-commerce',
            'servicos/midia-impressa',
            'servicos/naming',
            'servicos/redes-sociais',
            'servicos/redesenhoreformulacao-de-marca',
            'servicos/revitalizacao-de-marcas',
            'servicos/sinalizacao-de-frota',
            'servicos/sinalizacao-e-ambientacao',
            'servicos/sistemas-especiais',
            'servicos/tagline',
            'servicos/websites',
            'servicos/landing-page',
            'servicos/desenvolvimento-de-sites-para-empresas',
            'servicos/otimizacao-de-sites',
            'servicos/seo-marketing-digital',
            'servicos/sites-para-iimobiliaria-e-corretor-de-imoveis',
            'servicos/site-para-medicos',
            'servicos/anuncios-google-ads',
            'servicos/sistemas-web',
            'servicos/hospedagem-de-sites',
            'servicos/sites-em-wordpress',
            'servicos/marketing-digital',
            'servicos/inbound-marketing',
            'servicos/midia-paga',
            'servicos/web-design',
            'servicos/desenvolvimento-front-end',
            'servicos/desenvolvimento-back-end',
            'politica-de-privacidade',
            'politica-de-cookies',
            'termos-de-uso',
        );
    }

    public static function protected_contains_defaults() {
        return array('case-studies');
    }

    public static function defaults() {
        return array(
            'post_types' => array('page'),
            'slug_patterns' => array(
                'criacao-de-sites-para-',
                'criação-de-sites-para-',
                'agencia-de-marketing-digital-em-',
                'agência-de-marketing-digital-em-',
            ),
            'protected_slugs' => array(
                'home', 'sobre', 'contato', 'atendimento', 'servicos', 'serviços', 'politica', 'privacidade',
                'termos', 'blog', 'portfolio', 'a-empresa', 'fale-conosco', 'orcamentos', 'noticias',
                'case-studies',
            ),
            'protected_paths' => self::protected_paths_defaults(),
            'protected_contains' => self::protected_contains_defaults(),
            'minimum_score' => 75,
            'batch_size' => 250,
            'render_mode' => 'safe',
            'yoast_enabled' => 1,
            'schema_faq_enabled' => 1,
            'sitemap_enabled' => 0,
            'redirects_enabled' => 0,
            'internal_links_enabled' => 1,
            'company_name' => get_bloginfo('name'),
            'whatsapp' => '',
            'contact_url' => home_url('/contato/'),
            'main_city' => '',
            'tone' => 'comercial, profissional e natural',
            'minimum_content_length' => 3000,
            'max_internal_links' => 6,
            'similarity_limit' => 75,
            'rewrite_similarity_limit' => 55,
            'write_to_post_content' => 0,
            'enrich_from_sitemaps' => 1,
            'sync_sitemaps_before_enrich' => 1,
            'sitemap_sources' => array(
                'https://www.studioprivilege.com.br/sitemap-1.xml',
                'https://www.studioprivilege.com.br/sitemap-2.xml',
                'https://www.studioprivilege.com.br/sitemap-3.xml',
                'https://www.studioprivilege.com.br/sitemap-4.xml',
                'https://www.studioprivilege.com.br/sitemap-5.xml',
                'https://www.studioprivilege.com.br/sitemap-6.xml',
            ),
        );
    }

    public static function install_defaults() {
        if (!get_option(self::OPTION)) {
            add_option(self::OPTION, self::defaults(), '', false);
        }
    }

    public function all() {
        $settings = wp_parse_args((array) get_option(self::OPTION, array()), self::defaults());
        $defaults = self::defaults();
        $settings['protected_slugs'] = $this->merge_unique_lines($settings['protected_slugs'] ?? array(), $defaults['protected_slugs']);
        $settings['protected_paths'] = $this->merge_unique_lines($settings['protected_paths'] ?? array(), self::protected_paths_defaults());
        $settings['protected_contains'] = $this->merge_unique_lines($settings['protected_contains'] ?? array(), self::protected_contains_defaults());
        $settings['minimum_content_length'] = max(3000, absint($settings['minimum_content_length'] ?? 3000));
        $sitemap_sources = $settings['sitemap_sources'] ?? self::defaults()['sitemap_sources'];
        $settings['sitemap_sources'] = SES_Security::clean_lines(is_array($sitemap_sources) ? implode("\n", $sitemap_sources) : $sitemap_sources);
        return $settings;
    }

    public function get($key, $default = null) {
        $settings = $this->all();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function update($input) {
        $defaults = self::defaults();
        $clean = $this->all();
        $post_types_raw = $input['post_types'] ?? $defaults['post_types'];
        if (is_string($post_types_raw)) {
            $post_types_raw = preg_split('/[\s,]+/', $post_types_raw);
        }
        $clean['post_types'] = array_values(array_filter(array_map('sanitize_key', (array) $post_types_raw)));
        if (!$clean['post_types']) {
            $clean['post_types'] = array('page');
        }
        $clean['slug_patterns'] = SES_Security::clean_lines($input['slug_patterns'] ?? implode("\n", $defaults['slug_patterns']));
        $clean['protected_slugs'] = $this->merge_unique_lines(SES_Security::clean_lines($input['protected_slugs'] ?? implode("\n", $defaults['protected_slugs'])), $defaults['protected_slugs']);
        $clean['protected_paths'] = $this->merge_unique_lines(SES_Security::clean_lines($input['protected_paths'] ?? implode("\n", $defaults['protected_paths'])), self::protected_paths_defaults());
        $clean['protected_contains'] = $this->merge_unique_lines(SES_Security::clean_lines($input['protected_contains'] ?? implode("\n", $defaults['protected_contains'])), self::protected_contains_defaults());
        $clean['sitemap_sources'] = SES_Security::clean_lines($input['sitemap_sources'] ?? implode("\n", $defaults['sitemap_sources']));
        $clean['minimum_score'] = max(0, min(100, absint($input['minimum_score'] ?? 75)));
        $clean['batch_size'] = max(1, min(2000, absint($input['batch_size'] ?? 250)));
        $clean['render_mode'] = in_array(($input['render_mode'] ?? 'safe'), array('safe', 'shortcode', 'written'), true) ? $input['render_mode'] : 'safe';
        foreach (array('yoast_enabled', 'schema_faq_enabled', 'sitemap_enabled', 'redirects_enabled', 'internal_links_enabled', 'write_to_post_content', 'enrich_from_sitemaps', 'sync_sitemaps_before_enrich') as $flag) {
            $clean[$flag] = empty($input[$flag]) ? 0 : 1;
        }
        foreach (array('company_name', 'whatsapp', 'contact_url', 'main_city', 'tone') as $field) {
            $clean[$field] = sanitize_text_field($input[$field] ?? '');
        }
        $clean['contact_url'] = esc_url_raw($clean['contact_url']);
        $clean['minimum_content_length'] = max(3000, absint($input['minimum_content_length'] ?? 3000));
        $clean['max_internal_links'] = max(1, min(8, absint($input['max_internal_links'] ?? 6)));
        $clean['similarity_limit'] = max(1, min(100, absint($input['similarity_limit'] ?? 75)));
        $clean['rewrite_similarity_limit'] = max(1, min(100, absint($input['rewrite_similarity_limit'] ?? 55)));
        update_option(self::OPTION, $clean, false);
    }

    private function merge_unique_lines($current, $required) {
        $items = array_merge((array) $current, (array) $required);
        $clean = array();
        foreach ($items as $item) {
            $item = trim((string) $item, " \t\n\r\0\x0B/");
            if ('' === $item) {
                continue;
            }
            $key = sanitize_title(remove_accents($item));
            $clean[$key] = $item;
        }
        return array_values($clean);
    }
}
