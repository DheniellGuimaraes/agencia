<?php
if (!defined('ABSPATH')) { exit; }

class PSI_AI_Privilege_Visual_Builder {
    private $layouts = array(
        1 => 'psi-visual-layout-dark-glass',
        2 => 'psi-visual-layout-clean-gradient',
        3 => 'psi-visual-layout-saas-flow',
        4 => 'psi-visual-layout-editorial',
        5 => 'psi-visual-layout-neon',
        6 => 'psi-visual-layout-white-organic',
    );

    public function build($context) {
        $entities = $context['entities'];
        $layout_id = $this->choose_layout($entities, $context['post_id'] ?? 0);
        $layout_class = $this->layouts[$layout_id];
        $profession = $entities['profession'];
        $city = $entities['city'];
        $uf = $entities['uf'] ? ' – ' . $entities['uf'] : '';
        $faq = $context['faq'];
        $local_context = $context['local_context'];
        $main_svg = $context['media_svg'];
        $robot_svg = $this->robot_svg($entities, $layout_id);
        $map_svg = $this->map_svg($entities, $layout_id);
        $links = $this->internal_links($entities);

        ob_start();
        ?>
        <div class="psi-visual <?php echo esc_attr($layout_class); ?>" data-psi-visual-layout="<?php echo esc_attr($layout_id); ?>" data-psi-visual-score="<?php echo esc_attr($this->visual_score_preview($context)['visual_score']); ?>">
            <section class="psi-visual-section psi-visual-hero">
                <div class="psi-visual-hero-copy">
                    <span class="psi-visual-kicker">IA criativa aplicada à presença local</span>
                    <h2>Presença digital inteligente para <?php echo esc_html($profession); ?> em <?php echo esc_html($city . $uf); ?></h2>
                    <p>Uma página bem estruturada ajuda o visitante a entender seus serviços, confiar no seu trabalho e iniciar uma conversa com mais segurança.</p>
                    <div class="psi-visual-mini-cards">
                        <?php foreach (array('Clareza antes do orçamento','Provas certas no momento certo','Contato simples pelo WhatsApp') as $value) : ?>
                            <span><?php echo $this->icon('spark'); ?><?php echo esc_html($value); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="psi-visual-hero-art"><?php echo wp_kses($robot_svg . $main_svg, $this->allowed_svg()); ?></div>
            </section>

            <section class="psi-visual-section psi-visual-grid psi-visual-diagnostics" aria-label="Diagnóstico visual">
                <?php foreach ($this->diagnostic_cards($entities) as $i => $card) : ?>
                    <article class="psi-visual-card psi-visual-card-<?php echo esc_attr(($i % 3) + 1); ?>"><?php echo $this->icon($card['icon']); ?><h3><?php echo esc_html($card['title']); ?></h3><p><?php echo esc_html($card['text']); ?></p></article>
                <?php endforeach; ?>
            </section>

            <section class="psi-visual-section psi-visual-local">
                <div class="psi-visual-local-copy"><span class="psi-visual-kicker">Contexto local sem dados inventados</span><h3>Como clientes de <?php echo esc_html($city); ?> costumam decidir?</h3><p><?php echo esc_html($local_context); ?></p></div>
                <div class="psi-visual-map"><?php echo wp_kses($map_svg, $this->allowed_svg()); ?></div>
            </section>

            <section class="psi-visual-section"><div class="psi-visual-section-head"><span class="psi-visual-kicker">Dores reais do nicho</span><h3>O que precisa ficar claro antes do contato</h3></div><div class="psi-visual-grid psi-visual-pains">
                <?php foreach (array_slice($entities['pains'], 0, 6) as $i => $pain) : ?>
                    <article class="psi-visual-card psi-visual-pain-card"><?php echo $this->icon($entities['icons'][$i % max(1, count($entities['icons']))] ?? 'pin'); ?><h4><?php echo esc_html($this->short_title($pain)); ?></h4><p><?php echo esc_html(ucfirst($pain)); ?> ajuda o visitante a entender valor e reduzir insegurança antes de pedir orçamento.</p></article>
                <?php endforeach; ?>
            </div></section>

            <section class="psi-visual-section psi-visual-interface"><div><span class="psi-visual-kicker">Interface de conversão</span><h3>Estrutura ideal do site</h3><p>O caminho precisa ser simples, visual e orientado à decisão.</p></div><div class="psi-visual-flow">
                <?php foreach ($this->flow_steps($entities) as $step) : ?>
                    <article><?php echo $this->icon($step['icon']); ?><strong><?php echo esc_html($step['title']); ?></strong><span><?php echo esc_html($step['text']); ?></span></article>
                <?php endforeach; ?>
            </div></section>

            <section class="psi-visual-section psi-visual-faq"><div class="psi-visual-section-head"><span class="psi-visual-kicker">Perguntas de quem compara fornecedores</span><h3>Dúvidas que o site deve responder</h3></div><?php foreach ($faq as $item) : ?><details><summary><?php echo esc_html($item['q']); ?></summary><p><?php echo esc_html($item['a']); ?></p></details><?php endforeach; ?></section>

            <section class="psi-visual-section psi-visual-links"><div class="psi-visual-section-head"><span class="psi-visual-kicker">Próximos passos</span><h3>Continue explorando soluções relacionadas</h3></div><div class="psi-visual-grid">
                <?php foreach ($links as $link) : ?><a class="psi-visual-card psi-visual-link-card" href="<?php echo esc_url($link['url']); ?>"><?php echo $this->icon($link['icon']); ?><strong><?php echo esc_html($link['title']); ?></strong><span><?php echo esc_html($link['desc']); ?></span></a><?php endforeach; ?>
            </div></section>

            <section class="psi-visual-section psi-visual-cta">
                <div><span class="psi-visual-kicker">Pronto para uma página mais convincente?</span><h3>Quer transformar buscas por <?php echo esc_html($profession); ?> em <?php echo esc_html($city); ?> em conversas reais?</h3><p>Receba uma proposta humana, clara e com foco em confiança, visual premium e contato sem fricção.</p><small>Sem promessa de indexação garantida: o foco é melhorar utilidade, clareza e experiência.</small></div>
                <div class="psi-visual-cta-actions"><a class="psi-visual-button" href="https://api.whatsapp.com/send?phone=5532988167666" rel="nofollow noopener">Falar no WhatsApp</a><a class="psi-visual-button psi-visual-button-ghost" href="/orcamentos/">Solicitar orçamento</a></div>
                <div class="psi-visual-cta-art"><?php echo wp_kses($robot_svg, $this->allowed_svg()); ?></div>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public function choose_layout($entities, $post_id) { return (abs(crc32($post_id . $entities['category_key'] . $entities['city'] . $entities['profession'])) % 6) + 1; }

    public function visual_score_preview($context) {
        $html = $context['html'] ?? '';
        $score = 45;
        $score += strpos($html, 'psi-visual-hero') !== false ? 10 : 0;
        $score += substr_count($html, 'psi-visual-card') >= 8 ? 15 : 0;
        $score += strpos($html, '<svg') !== false ? 15 : 0;
        $score += strpos($html, 'psi-visual-cta') !== false ? 10 : 0;
        $score += strpos($html, 'psi-visual-flow') !== false ? 5 : 0;
        return array('visual_score'=>min(100, $score), 'reading_score'=>88, 'aiko_score'=>94, 'warnings'=>$this->visual_warnings($html));
    }

    public function visual_warnings($html) {
        $warnings = array();
        if (preg_match('/<p[^>]*>.*<\/p>\s*<p[^>]*>.*<\/p>\s*<p[^>]*>.*<\/p>/is', $html)) $warnings[] = 'mais_de_2_paragrafos_seguidos';
        if (strpos($html, '<svg') === false) $warnings[] = 'sem_svg';
        if (strpos($html, 'psi-visual-cta') === false) $warnings[] = 'sem_cta';
        if (substr_count($html, 'psi-visual-card') < 4) $warnings[] = 'sem_cards_suficientes';
        if (strpos($html, 'psi-visual') === false) $warnings[] = 'sem_estilo_premium';
        return $warnings;
    }

    public function should_block($html) { return count($this->visual_warnings($html)) > 0; }

    public function preview($context) {
        $html = $this->build($context);
        $scores = $this->visual_score_preview(array_merge($context, array('html'=>$html)));
        return array('desktop'=>$html, 'tablet'=>'<div class="psi-visual-preview-tablet">'.$html.'</div>', 'mobile'=>'<div class="psi-visual-preview-mobile">'.$html.'</div>', 'scores'=>$scores, 'css_handle'=>'psi-ai-frontend');
    }

    public function allowed_svg() {
        return array_merge(wp_kses_allowed_html('post'), array(
            'svg'=>array('viewbox'=>true,'xmlns'=>true,'focusable'=>true,'aria-hidden'=>true,'role'=>true,'class'=>true), 'defs'=>array(), 'lineargradient'=>array('id'=>true,'x1'=>true,'x2'=>true,'y1'=>true,'y2'=>true), 'radialgradient'=>array('id'=>true), 'stop'=>array('offset'=>true,'stop-color'=>true,'stop-opacity'=>true), 'filter'=>array('id'=>true), 'fegaussianblur'=>array('stddeviation'=>true), 'rect'=>array('width'=>true,'height'=>true,'rx'=>true,'fill'=>true,'opacity'=>true,'x'=>true,'y'=>true,'stroke'=>true,'stroke-width'=>true), 'circle'=>array('cx'=>true,'cy'=>true,'r'=>true,'fill'=>true,'opacity'=>true,'filter'=>true,'stroke'=>true,'stroke-width'=>true), 'path'=>array('d'=>true,'stroke'=>true,'stroke-width'=>true,'fill'=>true,'stroke-dasharray'=>true,'opacity'=>true), 'line'=>array('x1'=>true,'x2'=>true,'y1'=>true,'y2'=>true,'stroke'=>true,'stroke-width'=>true,'opacity'=>true), 'g'=>array('class'=>true,'opacity'=>true,'transform'=>true), 'text'=>array('x'=>true,'y'=>true,'class'=>true,'fill'=>true,'font-size'=>true), 'figure'=>array('class'=>true,'role'=>true,'aria-label'=>true,'data-psi-ai-media-hash'=>true), 'figcaption'=>array(), 'span'=>array('class'=>true), 'small'=>array(),
        ));
    }

    private function diagnostic_cards($entities) {
        return array(
            array('icon'=>'search','title'=>'O que o cliente procura','text'=>'Informações objetivas sobre serviços, atendimento e próximos passos antes de iniciar conversa.'),
            array('icon'=>'shield','title'=>'O que gera confiança','text'=>'Provas visuais, explicações simples e sinais de cuidado específicos para '.$entities['macro_category'].'.'),
            array('icon'=>'grid','title'=>'O que precisa aparecer','text'=>'Serviços, região atendida, dúvidas frequentes, exemplos e chamada clara para orçamento.'),
            array('icon'=>'chat','title'=>'Como facilitar o orçamento','text'=>'O WhatsApp entra melhor depois que a página já respondeu as dúvidas que travam a decisão.'),
        );
    }

    private function flow_steps($entities) {
        return array(
            array('icon'=>'search','title'=>'Busca local','text'=>'A pessoa encontra uma opção relevante.'),
            array('icon'=>'grid','title'=>'Página clara','text'=>'Serviços e diferenciais aparecem rápido.'),
            array('icon'=>'image','title'=>'Prova visual','text'=>ucfirst($entities['proofs'][0] ?? 'exemplos reais ajudam na decisão.')),
            array('icon'=>'chat','title'=>'WhatsApp','text'=>'A conversa começa com mais contexto.'),
            array('icon'=>'spark','title'=>'Orçamento','text'=>'O pedido chega mais organizado.'),
        );
    }

    private function internal_links($entities) {
        return array(
            array('title'=>'Criação de Sites','desc'=>'Estrutura completa para presença digital profissional.','url'=>'/desenvolvimento-de-sites-lojas-virtuais/','icon'=>'grid'),
            array('title'=>'Sites em WordPress','desc'=>'Base flexível para crescer com segurança.','url'=>'/sites-em-wordpress/','icon'=>'spark'),
            array('title'=>'Landing Page','desc'=>'Página focada em conversão e campanhas.','url'=>'/landing-page/','icon'=>'route'),
            array('title'=>'SEO Marketing Digital','desc'=>'Melhor organização para busca e conteúdo.','url'=>'/seo-marketing-digital/','icon'=>'search'),
            array('title'=>'Orçamentos','desc'=>'Solicite uma proposta clara e humana.','url'=>'/orcamentos/','icon'=>'chat'),
        );
    }

    private function robot_svg($entities, $layout_id) {
        $hash = substr(hash('sha256', $layout_id . $entities['profession'] . $entities['city']), 0, 8);
        return '<svg class="psi-visual-robot" viewBox="0 0 220 220" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true"><defs><linearGradient id="robot'.$hash.'" x1="0" x2="1"><stop offset="0" stop-color="#03d0ad"/><stop offset=".52" stop-color="#00c8ff"/><stop offset="1" stop-color="#ff1b8d"/></linearGradient></defs><rect x="48" y="58" width="124" height="104" rx="34" fill="url(#robot'.$hash.')" opacity=".92"/><rect x="68" y="82" width="84" height="44" rx="18" fill="#050505" opacity=".82"/><circle cx="92" cy="104" r="7" fill="#03d0ad"/><circle cx="128" cy="104" r="7" fill="#ffdd55"/><path d="M91 137 C108 148 126 148 141 137" stroke="#fff" stroke-width="6" fill="none" stroke-linecap="round"/><path d="M110 58 V32" stroke="#fff" stroke-width="7" stroke-linecap="round"/><circle cx="110" cy="25" r="10" fill="#ff1b8d"/><path d="M48 116 H25 M172 116 H195" stroke="#fff" stroke-width="8" stroke-linecap="round"/></svg>';
    }

    private function map_svg($entities, $layout_id) {
        $hash = substr(hash('sha256', 'map'.$layout_id.$entities['city'].$entities['category_key']), 0, 8);
        return '<svg class="psi-visual-map-svg" viewBox="0 0 360 220" xmlns="http://www.w3.org/2000/svg" focusable="false" role="img"><defs><linearGradient id="map'.$hash.'" x1="0" x2="1"><stop offset="0" stop-color="#411468"/><stop offset="1" stop-color="#03d0ad"/></linearGradient></defs><rect x="12" y="12" width="336" height="196" rx="34" fill="#f4f4f4"/><path d="M52 154 C92 64 151 173 203 86 S290 66 316 146" stroke="url(#map'.$hash.')" stroke-width="5" fill="none" stroke-dasharray="10 12"/><circle cx="92" cy="92" r="10" fill="#ff1b8d"/><circle cx="204" cy="86" r="10" fill="#00c8ff"/><circle cx="284" cy="118" r="10" fill="#03d0ad"/><path d="M176 58 l18 36 h-36 z" fill="#411468" opacity=".88"/><text x="34" y="190" fill="#411468" font-size="14">'.esc_html($entities['city']).'</text></svg>';
    }

    private function icon($name) {
        $paths = array('search'=>'M20 20 L30 30 M18 10 a8 8 0 1 0 0.1 0','shield'=>'M20 4 L32 9 V20 C32 28 25 33 20 36 C15 33 8 28 8 20 V9 Z','grid'=>'M6 6 H18 V18 H6 Z M22 6 H34 V18 H22 Z M6 22 H18 V34 H6 Z M22 22 H34 V34 H22 Z','chat'=>'M6 8 H34 V27 H16 L8 34 V27 H6 Z','spark'=>'M20 4 L24 16 L36 20 L24 24 L20 36 L16 24 L4 20 L16 16 Z','image'=>'M6 8 H34 V32 H6 Z M10 28 L17 20 L22 25 L27 17 L34 28','route'=>'M8 30 C16 6 25 34 34 10','pin'=>'M20 4 C13 4 9 9 9 15 C9 25 20 36 20 36 C20 36 31 25 31 15 C31 9 27 4 20 4 Z');
        $d = $paths[$name] ?? $paths['spark'];
        return '<svg class="psi-visual-icon" viewBox="0 0 40 40" aria-hidden="true" focusable="false"><path d="'.esc_attr($d).'" fill="none" stroke="currentColor" stroke-width="2.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    private function short_title($text) { $words = preg_split('/\s+/', trim($text)); return ucfirst(implode(' ', array_slice($words, 0, 3))); }
}
