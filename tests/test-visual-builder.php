<?php
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
function esc_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s){ return $s; }
function wp_kses($html,$allowed){ return $html; }
function wp_kses_allowed_html($context='post'){ return array(); }
require __DIR__ . '/../privilege-semantic-indexer-ai/includes/class-privilege-visual-builder.php';
$b = new PSI_AI_Privilege_Visual_Builder();
$entities = array('profession'=>'Costureiro de couro','city'=>'Dourados','uf'=>'MS','category_key'=>'moda','macro_category'=>'Moda e confecção','pains'=>array('mostrar acabamento','orçamento por imagem','peças de valor','galeria antes/depois','confiança antes do contato','materiais atendidos'),'services'=>array('ajustes','reparos','orçamento'),'proofs'=>array('galeria','depoimentos','localização'),'icons'=>array('spark','image','chat'));
$html = $b->build(array('post_id'=>123,'entities'=>$entities,'faq'=>array(array('q'=>'Pergunta?','a'=>'Resposta útil.')),'local_context'=>'Em Dourados, clientes comparam opções antes do contato.','media_svg'=>'<svg viewBox="0 0 10 10"></svg>'));
foreach (array('psi-visual-hero','psi-visual-card','psi-visual-flow','psi-visual-faq','psi-visual-links','psi-visual-cta','api.whatsapp.com/send?phone=5532988167666') as $needle) { if (strpos($html,$needle)===false) { fwrite(STDERR,"missing $needle\n"); exit(1); } }
if ($b->should_block($html)) { fwrite(STDERR,"visual builder unexpectedly blocked premium html\n"); exit(1); }
echo "visual-builder-ok\n";
