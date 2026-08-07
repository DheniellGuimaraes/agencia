<?php
if (!defined('ABSPATH')) { exit; }
class PSI_AI_Content_Cleaner {
    public function remove_old($content) {
        $content = preg_replace('/<!--\s*SES[^>]*-->.*?<!--\s*\/SES[^>]*-->/is', '', $content);
        return $this->remove_div_by_marker($content, 'ses-enriched-content');
    }
    private function remove_div_by_marker($html, $class) {
        $pos = strpos($html, $class);
        while ($pos !== false) {
            $start = strripos(substr($html, 0, $pos), '<div');
            if ($start === false) { break; }
            $depth = 0; $end = null;
            if (preg_match_all('/<\/??div\b[^>]*>/i', substr($html, $start), $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $tag = $match[0]; $offset = $start + $match[1];
                    if (stripos($tag, '</div') === 0) { $depth--; } else { $depth++; }
                    if ($depth === 0) { $end = $offset + strlen($tag); break; }
                }
            }
            if (!$end) { break; }
            $html = substr($html, 0, $start) . substr($html, $end);
            $pos = strpos($html, $class);
        }
        return $html;
    }
    public function insert_after_budget($content, $block) {
        $markers = array('Solicite Orçamento Express', 'ENTRAMOS EM CONTATO', 'avatars.png');
        $last = false;
        foreach ($markers as $marker) { $p = strripos($content, $marker); if ($p !== false) { $last = max($last ?: 0, $p); } }
        if ($last !== false && preg_match('/<\/div>/', substr($content, $last), $m, PREG_OFFSET_CAPTURE)) {
            $insert = $last + $m[0][1] + strlen($m[0][0]);
            return substr($content,0,$insert) . "\n" . $block . "\n" . substr($content,$insert);
        }
        return rtrim($content) . "\n" . $block;
    }
}
