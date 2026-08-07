<?php
if (!defined('ABSPATH')) { exit; }

class PSI_AI_Semantic_Generator {
    private $entity_generator;
    private $local_context;
    private $media_builder;
    private $visual_builder;

    public function __construct($entity_generator, $local_context, $media_builder, $visual_builder = null) {
        $this->entity_generator = $entity_generator;
        $this->local_context = $local_context;
        $this->media_builder = $media_builder;
        $this->visual_builder = $visual_builder;
    }

    public function generate($data, $variation = 0) {
        $entities = $this->entity_generator->build($data);
        if ($variation > 0) { $entities['seed'] += absint($variation) * 37; $entities['pains'] = $this->rotate($entities['pains'], $entities['seed']); $entities['services'] = $this->rotate($entities['services'], $entities['seed'] + 3); $entities['proofs'] = $this->rotate($entities['proofs'], $entities['seed'] + 9); }
        $profession = $entities['profession'];
        $city = $entities['city'];
        $uf = $entities['uf'] ? ' – ' . $entities['uf'] : '';
        $local_context = $this->local_context->generate($city, $entities['uf'], $entities);
        $media_svg = $this->media_builder->build($data['post_id'] ?? 0, $entities);
        $faq = $this->faq($entities);
        $context = array('post_id'=>$data['post_id'] ?? 0, 'entities'=>$entities, 'profession'=>$profession, 'city'=>$city, 'uf'=>$uf, 'local_context'=>$local_context, 'media_svg'=>$media_svg, 'faq'=>$faq);
        if ($this->visual_builder) {
            $html = $this->visual_builder->build($context);
            $visual = $this->visual_builder->visual_score_preview(array_merge($context, array('html'=>$html)));
        } else {
            ob_start(); include PSI_AI_DIR . 'templates/frontend-enrichment.php'; $html = ob_get_clean();
            $visual = array('visual_score'=>70, 'reading_score'=>82, 'aiko_score'=>80, 'warnings'=>array());
        }
        return array('html'=>$html, 'entities'=>$entities, 'faq'=>$faq, 'local_context'=>$local_context, 'visual'=>$visual);
    }

    public function allowed_svg() {
        if ($this->visual_builder) { return $this->visual_builder->allowed_svg(); }
        return wp_kses_allowed_html('post');
    }

    private function faq($entities) {
        $profession = $entities['profession']; $city = $entities['city']; $uf = $entities['uf'] ? ' – ' . $entities['uf'] : '';
        $templates = array(
            array('O que precisa aparecer em um site para %s em %s?', 'Serviços, provas de confiança, região atendida, respostas para dúvidas comuns e um caminho simples para pedir orçamento.'),
            array('Como o site ajuda a reduzir dúvidas antes do orçamento?', 'Ele antecipa perguntas sobre atendimento, exemplos, prazos e próximos passos, evitando conversas longas apenas para explicar o básico.'),
            array('Quais provas aumentam a confiança nesse tipo de serviço?', 'Para este nicho, funcionam bem: %s.'),
            array('Quando vale investir em um site profissional?', 'Quando o atendimento depende de confiança, comparação entre fornecedores e clareza antes do primeiro contato.'),
            array('O WhatsApp deve aparecer logo no começo?', 'Pode aparecer, mas funciona melhor quando acompanha uma explicação clara do serviço e não substitui as informações essenciais.'),
            array('Essa melhoria garante indexação no Google?', 'Não. Nenhuma alteração garante indexação; o objetivo é tornar a página mais útil, específica e segura para o visitante.'),
            array('Como manter a página com aparência humana?', 'Usando exemplos reais do serviço, linguagem simples, perguntas úteis e evitando blocos feitos apenas para robôs.'),
        );
        $templates = $this->rotate($templates, $entities['seed']);
        $out = array();
        foreach (array_slice($templates, 0, 6) as $t) { $answer = sprintf($t[1], implode(', ', array_slice($entities['proofs'], 0, 3))); $out[] = array('q'=>sprintf($t[0], $profession, $city . $uf), 'a'=>$answer); }
        return $out;
    }

    private function rotate($arr, $seed) { $n=count($arr); if(!$n) return $arr; $r=$seed % $n; return array_merge(array_slice($arr,$r), array_slice($arr,0,$r)); }
}
