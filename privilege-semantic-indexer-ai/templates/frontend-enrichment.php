<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="privilege-semantic-block psi-ai-glass" data-psi-ai-kind="semantic-enrichment">
    <div class="psi-ai-hero-compact">
        <div><div class="psi-ai-kicker">Planejamento digital para atendimento local</div>
        <h2 class="bt_bb_headline psi-ai-title">Site para <?php echo esc_html($profession); ?> em <?php echo esc_html($city . $uf); ?> com mais clareza antes do orçamento</h2>
        <p class="bt_bb_text psi-ai-lead">Um bom site deve ajudar o visitante a entender o serviço, confiar no atendimento e saber exatamente como iniciar uma conversa comercial. Para <?php echo esc_html($entities['subcategory']); ?>, isso significa apresentar o que seu cliente quer saber sem transformar a página em um texto repetitivo.</p></div>
        <?php echo wp_kses($media_svg, $this->allowed_svg()); ?>
    </div>

    <article class="psi-ai-local-context psi-ai-panel"><h3>Como a decisão acontece em <?php echo esc_html($city . $uf); ?></h3><p><?php echo esc_html($local_context); ?></p></article>

    <div class="psi-ai-grid psi-ai-pain-grid"><h3 class="psi-ai-grid-title">O que o cliente costuma avaliar antes do contato</h3>
        <?php foreach (array_slice($entities['pains'], 0, 6) as $index => $item) : ?>
            <article class="psi-ai-card"><span class="psi-ai-icon"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span><h4><?php echo esc_html(ucfirst($item)); ?></h4><p>Esse ponto precisa aparecer de forma simples para reduzir insegurança e facilitar o pedido de orçamento.</p></article>
        <?php endforeach; ?>
    </div>

    <div class="psi-ai-two-col">
        <div class="psi-ai-panel"><h3>Serviços que precisam aparecer com clareza</h3><ul><?php foreach (array_slice($entities['services'], 0, 5) as $item) : ?><li><?php echo esc_html(ucfirst($item)); ?></li><?php endforeach; ?></ul></div>
        <div class="psi-ai-panel"><h3>Prova e confiança antes do WhatsApp</h3><ul><?php foreach (array_slice($entities['proofs'], 0, 5) as $item) : ?><li><?php echo esc_html(ucfirst($item)); ?></li><?php endforeach; ?></ul></div>
    </div>

    <div class="psi-ai-difference"><h3>Como o site reduz dúvidas antes do orçamento</h3><p>Quando a página mostra serviços, exemplos, forma de atendimento e próximos passos, o visitante chega ao WhatsApp com menos perguntas básicas e maior chance de explicar o que precisa. O foco é criar uma experiência útil, visível e segura, sem promessa de indexação automática.</p></div>

    <div class="bt_bb_accordion psi-ai-faq"><h3>Perguntas frequentes de quem está comparando opções</h3><?php foreach ($faq as $item) : ?><details><summary><?php echo esc_html($item['q']); ?></summary><p><?php echo esc_html($item['a']); ?></p></details><?php endforeach; ?></div>

    <nav class="psi-ai-links" aria-label="Links internos recomendados">
        <a href="/servicos/">Serviços</a><a href="/desenvolvimento-de-sites-lojas-virtuais/">Sites e lojas virtuais</a><a href="/sites-em-wordpress/">Sites em WordPress</a><a href="/landing-page/">Landing pages</a><a href="/seo-marketing-digital/">Marketing digital</a><a href="/orcamentos/">Orçamentos</a>
    </nav>

    <div class="psi-ai-cta"><h3>Quer transformar busca local por <?php echo esc_html($profession); ?> em <?php echo esc_html($city); ?> em conversa comercial?</h3><p>Receba uma orientação objetiva para uma página mais clara, visual e preparada para gerar confiança antes do primeiro contato.</p><a class="bt_bb_button psi-ai-button" href="https://wa.me/5532988167666?text=<?php echo rawurlencode('Olá! Quero uma proposta para criação de site para '.$profession.' em '.$city.$uf); ?>" rel="nofollow noopener">Solicitar proposta pelo WhatsApp</a></div>
</div>
