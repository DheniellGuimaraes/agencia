<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Yoast_Integration {
    public function is_active() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
    }

    public function update_meta($post_id, $context) {
        if (get_post_meta($post_id, '_ses_protected', true) || !$this->is_active()) {
            return;
        }
        $profession = $context['profession'] ?: 'negocios locais';
        $city = $context['city'] ?: '';
        $short = $this->short_profession($profession);
        $title = 'Site para ' . $short . ($city ? ' em ' . ucwords($city) : '') . ' | Portfólio, WhatsApp e SEO local';
        $desc = 'Crie um site para divulgar serviços de ' . $profession . ($city ? ' em ' . ucwords($city) : '') . ', com portfólio, WhatsApp e estrutura para buscas locais.';
        update_post_meta($post_id, '_wpseo_title', wp_trim_words($title, 14, ''));
        update_post_meta($post_id, '_wpseo_metadesc', wp_trim_words($desc, 26, ''));
        update_post_meta($post_id, '_wpseo_canonical', get_permalink($post_id));
    }

    private function short_profession($profession) {
        $profession = preg_replace('/\s+/', ' ', (string) $profession);
        $profession = str_replace(array('roupa de couro e pele'), array('couro'), $profession);
        return trim($profession);
    }
}
