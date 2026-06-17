<?php
if (!defined('ABSPATH')) { exit; }
class PSI_AI_Schema_Generator {
    public function print_schema() {
        if (!is_singular()) return; $post = get_post(); if (!$post || strpos($post->post_content, 'data-psi-ai-kind="semantic-enrichment"') === false) return;
        preg_match_all('/<details>\s*<summary>(.*?)<\/summary>\s*<p>(.*?)<\/p>/is', $post->post_content, $m, PREG_SET_ORDER);
        if (!$m) return; $items = array(); foreach ($m as $row) { $items[] = array('@type'=>'Question','name'=>wp_strip_all_tags($row[1]),'acceptedAnswer'=>array('@type'=>'Answer','text'=>wp_strip_all_tags($row[2]))); }
        echo '<script type="application/ld+json">' . wp_json_encode(array('@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$items), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . '</script>';
    }
}
