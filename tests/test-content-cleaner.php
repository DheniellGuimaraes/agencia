<?php
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
require __DIR__ . '/../privilege-semantic-indexer-ai/includes/class-content-cleaner.php';
$cleaner = new PSI_AI_Content_Cleaner();
$input = '<div class="budget">Solicite Orçamento Express<form>ok</form></div><div class="ses-enriched-content" data-ses-version="1.0.8"><div>old</div></div><footer>keep</footer>';
$out = $cleaner->remove_old($input);
if (strpos($out, 'ses-enriched-content') !== false) { fwrite(STDERR, "old block not removed\n"); exit(1); }
if (strpos($out, '<form>ok</form>') === false || strpos($out, '<footer>keep</footer>') === false) { fwrite(STDERR, "protected surrounding html removed\n"); exit(1); }
$inserted = $cleaner->insert_after_budget($out, '<section data-psi-ai-kind="semantic-enrichment">new</section>');
if (strpos($inserted, 'semantic-enrichment') === false) { fwrite(STDERR, "new block not inserted\n"); exit(1); }
echo "content-cleaner-ok\n";
