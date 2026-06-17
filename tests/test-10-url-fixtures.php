<?php
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
define('PSI_AI_DIR', __DIR__ . '/../privilege-semantic-indexer-ai/');
function remove_accents($s){ return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s) ?: $s; }
function esc_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rawurlencode_stub($s){ return rawurlencode($s); }
function wp_trim_words($text,$num_words=55,$more=null){ $w=preg_split('/\s+/', trim($text)); return implode(' ', array_slice($w,0,$num_words)); }
function wp_kses($html,$allowed){ return $html; }
function wp_kses_allowed_html($context='post'){ return array('a'=>array('href'=>true,'class'=>true),'div'=>array('class'=>true,'data-psi-visual-layout'=>true,'data-psi-visual-score'=>true),'section'=>array('class'=>true,'aria-label'=>true),'article'=>array('class'=>true),'span'=>array('class'=>true),'h2'=>array(),'h3'=>array(),'h4'=>array(),'p'=>array(),'strong'=>array(),'small'=>array(),'details'=>array(),'summary'=>array()); }
function esc_url($s){ return $s; }
function wp_strip_all_tags($s){ return strip_tags($s); }
require __DIR__ . '/../privilege-semantic-indexer-ai/includes/class-category-intelligence.php';
require __DIR__ . '/../privilege-semantic-indexer-ai/includes/class-semantic-entity-generator.php';
require __DIR__ . '/../privilege-semantic-indexer-ai/includes/class-local-context-generator.php';
require __DIR__ . '/../privilege-semantic-indexer-ai/includes/class-unique-media-builder.php';
require __DIR__ . '/../privilege-semantic-indexer-ai/includes/class-privilege-visual-builder.php';
require __DIR__ . '/../privilege-semantic-indexer-ai/includes/class-semantic-generator.php';
$gen = new PSI_AI_Semantic_Generator(new PSI_AI_Semantic_Entity_Generator(new PSI_AI_Category_Intelligence()), new PSI_AI_Local_Context_Generator(), new PSI_AI_Unique_Media_Builder(), new PSI_AI_Privilege_Visual_Builder());
$fixtures = array(
    array('post_id'=>1,'profession'=>'Costureiro de roupa de couro e pele','city'=>'Dourados','uf'=>'MS','macro_category'=>'moda'),
    array('post_id'=>2,'profession'=>'Advogado previdenciário','city'=>'Curitiba','uf'=>'PR','macro_category'=>'direito'),
    array('post_id'=>3,'profession'=>'Dentista','city'=>'Belo Horizonte','uf'=>'MG','macro_category'=>'saúde'),
    array('post_id'=>4,'profession'=>'Eletricista residencial','city'=>'Campinas','uf'=>'SP','macro_category'=>'serviços técnicos'),
    array('post_id'=>5,'profession'=>'Confeiteiro','city'=>'Recife','uf'=>'PE','macro_category'=>'alimentação'),
    array('post_id'=>6,'profession'=>'Arquiteto','city'=>'Goiânia','uf'=>'GO','macro_category'=>'construção'),
    array('post_id'=>7,'profession'=>'Escola de idiomas','city'=>'Niterói','uf'=>'RJ','macro_category'=>'educação'),
    array('post_id'=>8,'profession'=>'Clínica de estética','city'=>'Maringá','uf'=>'PR','macro_category'=>'beleza'),
    array('post_id'=>9,'profession'=>'Técnico em informática','city'=>'Santos','uf'=>'SP','macro_category'=>'serviços técnicos'),
    array('post_id'=>10,'profession'=>'Marceneiro','city'=>'Joinville','uf'=>'SC','macro_category'=>'construção'),
);
$hashes = array();
foreach ($fixtures as $fixture) {
    $result = $gen->generate($fixture);
    if (strpos($result['html'], 'palavras-chave') !== false || strpos($result['html'], 'Intenção de busca') !== false) { fwrite(STDERR, "robotic wording found\n"); exit(1); }
    if (strpos($result['html'], '<svg') === false || strpos($result['html'], 'psi-visual') === false || strpos($result['html'], 'psi-visual-cta') === false) { fwrite(STDERR, "svg missing\n"); exit(1); }
    preg_match('/data-psi-visual-layout="([^"]+)"/', $result['html'], $m); $hashes[] = hash('sha1', $result['html'] . ($m[1] ?? '')); 
}
if (count(array_unique($hashes)) !== 10) { fwrite(STDERR, "SVG hashes are not unique\n"); exit(1); }
echo "ten-url-fixtures-ok\n";
