<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Quality_Score {
    public function calculate($context, $generated, $similarity_score) {
        $score = 0;
        $score += !empty($context['service']) ? 10 : 0;
        $score += !empty($context['profession']) || 'agência de marketing digital' === ($context['service'] ?? '') ? 10 : 0;
        $score += !empty($context['city']) ? 10 : 0;
        $score += !empty($context['profession_group']) ? 10 : 0;
        $score += false !== strpos(wp_strip_all_tags($generated['html']), (string) ($context['city'] ?? '')) ? 10 : 0;
        $score += preg_match('/<li>/', $generated['html']) ? 10 : 0;
        $score += false !== stripos($generated['html'], 'Estrutura') || false !== stripos($generated['html'], 'portfolio') ? 10 : 0;
        $score += !empty($generated['faq']) ? 10 : 0;
        $score += !empty($generated['links']) ? 10 : 0;
        $score += $similarity_score < 55 ? 5 : 0;
        $score += strlen(wp_strip_all_tags($generated['html'])) >= 3000 ? 5 : 0;
        return min(100, $score);
    }
}
