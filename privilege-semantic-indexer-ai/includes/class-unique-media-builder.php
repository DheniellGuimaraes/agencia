<?php
if (!defined('ABSPATH')) { exit; }

class PSI_AI_Unique_Media_Builder {
    public function build($post_id, $entities) {
        $hash = substr(hash('sha256', $post_id . $entities['profession'] . $entities['city'] . $entities['category_key']), 0, 12);
        $hue = hexdec(substr($hash, 0, 2)) % 360;
        $hue2 = ($hue + 115) % 360;
        $icons = $entities['icons'];
        $alt = sprintf('Mapa visual para site de %s em %s com foco em confiança, serviços e contato', $entities['profession'], $entities['city']);
        $nodes = '';
        $labels = array_slice(array_merge($entities['services'], $entities['proofs']), 0, 4);
        foreach ($labels as $i => $label) {
            $x = 90 + ($i % 2) * 230; $y = 86 + floor($i / 2) * 118;
            $nodes .= '<g class="psi-ai-svg-node"><rect x="'.$x.'" y="'.$y.'" width="180" height="62" rx="18"/><text x="'.($x+18).'" y="'.($y+36).'">'.esc_html(wp_trim_words($label, 3, '')).'</text></g>';
        }
        return '<figure class="psi-ai-media" role="img" aria-label="'.esc_attr($alt).'" data-psi-ai-media-hash="'.esc_attr($hash).'"><svg viewBox="0 0 520 340" xmlns="http://www.w3.org/2000/svg" focusable="false"><defs><linearGradient id="psiGrad'.esc_attr($hash).'" x1="0" x2="1"><stop offset="0%" stop-color="hsl('.esc_attr($hue).',72%,42%)"/><stop offset="100%" stop-color="hsl('.esc_attr($hue2).',82%,52%)"/></linearGradient><filter id="psiBlur'.esc_attr($hash).'"><feGaussianBlur stdDeviation="18"/></filter></defs><rect width="520" height="340" rx="34" fill="url(#psiGrad'.esc_attr($hash).')" opacity=".28"/><circle cx="110" cy="70" r="64" fill="#03d0ad" opacity=".34" filter="url(#psiBlur'.esc_attr($hash).')"/><circle cx="430" cy="270" r="82" fill="#411468" opacity=".42" filter="url(#psiBlur'.esc_attr($hash).')"/><path d="M180 118 C260 70 302 250 382 210" stroke="rgba(255,255,255,.55)" stroke-width="3" fill="none" stroke-dasharray="8 10"/>'.$nodes.'<text x="36" y="304" class="psi-ai-svg-caption">'.esc_html($entities['macro_category']).' • '.esc_html($icons[0] ?? 'site').'</text></svg><figcaption>'.esc_html($alt).'</figcaption></figure>';
    }
}
