<?php
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
function wp_strip_all_tags($s){ return strip_tags($s); }
function remove_accents($s){ return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s) ?: $s; }
require __DIR__ . '/../privilege-semantic-indexer-ai/includes/class-similarity-guard.php';
class PSI_AI_Test_Similarity_Guard extends PSI_AI_Similarity_Guard { public function expose($s){ $r=new ReflectionClass('PSI_AI_Similarity_Guard'); $m=$r->getMethod('normalize'); $m->setAccessible(true); return $m->invoke($this,$s); } }
$g = new PSI_AI_Test_Similarity_Guard();
if ($g->expose('<p>Criação Ágil</p>') !== 'criacao agil') { fwrite(STDERR, "normalize failed\n"); exit(1); }
similar_text($g->expose('texto igual para comparar'), $g->expose('texto igual para comparar'), $pct);
if ($pct < 99) { fwrite(STDERR, "similarity baseline failed\n"); exit(1); }
echo "similarity-guard-ok\n";
