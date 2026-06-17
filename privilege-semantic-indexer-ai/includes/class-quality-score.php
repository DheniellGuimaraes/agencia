<?php
if (!defined('ABSPATH')) { exit; }
class PSI_AI_Quality_Score {
    public function score($html, $existing_samples = array()) {
        $text = wp_strip_all_tags($html); $score = 50; $flags = array();
        if (str_word_count($text) > 250) $score += 12; else $flags[]='conteudo_curto';
        if (substr_count($html, '<details') >= 5) $score += 10; else $flags[]='faq_insuficiente';
        if (substr_count($html, '<a ') >= 3 && substr_count($html, '<a ') <= 6) $score += 8; else $flags[]='links_internos_revisar';
        if (strpos($html, 'display:none') === false) $score += 8; else $flags[]='conteudo_oculto';
        if (strpos($html, 'psi-ai-card') !== false) $score += 7;
        $similarity = $this->max_similarity($text, $existing_samples); if ($similarity > 86) { $score -= 30; $flags[]='duplicidade_alta'; }
        return array('score'=>max(0,min(100,$score)), 'flags'=>$flags, 'similarity'=>$similarity);
    }
    private function max_similarity($text, $samples) { $max = 0; foreach ((array)$samples as $sample) { similar_text($text, wp_strip_all_tags($sample), $pct); $max=max($max,$pct); } return round($max,2); }
}
