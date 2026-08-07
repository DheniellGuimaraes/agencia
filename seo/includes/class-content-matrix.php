<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Content_Matrix {
    public function generate($context, $settings) {
        $service = $context['service'] ?: 'presenca digital';
        $profession = $context['profession'] ?: 'negocios locais';
        $city = $context['city'] ?: ($settings['main_city'] ?? 'sua cidade');
        $category = $context['profession_group'] ?: 'servicos locais';
        $local = (new SES_Local_Intelligence())->city_data($city);
        $profile = (new SES_Profession_Intelligence())->profile($profession, $category);
        $links = (new SES_Internal_Links($settings))->suggest($context);
        $cta = $this->cta($context);
        $faq = $this->faq($service, $profession, $city, $category, $profile);
        $headings = $this->headings($category);

        $blocks = array();
        $blocks[] = $this->section($headings[0], array($this->opening($service, $profession, $city, $category, $local)));
        $blocks[] = $this->section($headings[1], array(
            'Em ' . ucwords($city) . ', a decisao de chamar um profissional costuma passar por uma pesquisa rapida, comparacao visual e avaliacao de confianca. A pagina precisa responder o que a pessoa quer saber antes do contato: quais servicos sao atendidos, que tipo de resultado pode esperar, onde o atendimento acontece e qual e o melhor caminho para pedir um orcamento.',
            'Quando esse contexto aparece com clareza, a pagina deixa de ser apenas uma variacao de cidade e nicho. Ela passa a funcionar como uma resposta local mais completa, com informacoes uteis para quem esta avaliando uma contratacao real.',
        ));
        $blocks[] = $this->list_section($headings[2], $profile['pains'], 'Essas barreiras precisam aparecer no conteudo porque representam problemas comerciais concretos do nicho:');
        $blocks[] = $this->section($headings[3], array(
            'Um projeto digital para ' . $profession . ' deve organizar prova visual, explicacao dos servicos, perguntas comuns e caminhos rapidos para contato. A estrutura ideal reduz mensagens repetidas no WhatsApp e ajuda o visitante a entender se aquele profissional atende exatamente o que ele procura.',
            'Para esse tipo de pagina, a prioridade nao e repetir muitas vezes a palavra-chave principal. O melhor resultado vem de combinar contexto local, termos relacionados, detalhes do trabalho e uma apresentacao que facilite a tomada de decisao.',
        ));
        $blocks[] = $this->list_section('Estrutura recomendada para a pagina', $profile['structure'], 'A pagina pode ganhar mais utilidade quando apresenta secoes como:');
        $blocks[] = $this->section('Diferenciais que ajudam na conversao', array(
            $this->conversion($profession, $city, $profile),
            'Tambem e importante mostrar detalhes práticos: tipos de atendimento, criterios para orcamento, especialidades, formas de contato, localizacao e exemplos de demandas comuns. Esses elementos deixam a pagina menos parecida com outras paginas programaticas e mais proxima de uma pagina realmente util para o usuario.',
        ));
        $blocks[] = $this->section('Como o conteudo melhora a qualidade percebida', array(
            'O enriquecimento semantico adiciona contexto que o template original normalmente nao cobre. Em vez de apenas trocar profissao e cidade, a pagina passa a explicar o mercado, as dores do cliente, a estrutura de servico e os motivos para iniciar uma conversa.',
            'Esse tipo de complemento ajuda a reduzir a aparencia de conteudo duplicado, principalmente em sites com muitas URLs programaticas. A diferenca nao esta em aumentar volume de texto por volume, mas em tornar cada pagina mais especifica, mais local e mais alinhada a uma necessidade comercial concreta.',
        ));

        $html = '<section class="ses-section">';
        $html .= implode('', $blocks);
        $html .= '<h2>Perguntas frequentes</h2><div class="ses-faq">';
        foreach ($faq as $item) {
            $html .= '<details><summary>' . esc_html($item['q']) . '</summary><p>' . esc_html($item['a']) . '</p></details>';
        }
        $html .= '</div>';

        if ($links) {
            $html .= '<h2>Caminhos relacionados</h2><ul class="ses-links">';
            foreach ($links as $link) {
                $html .= '<li><a href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a></li>';
            }
            $html .= '</ul>';
        }

        $html .= '<p class="ses-cta">' . esc_html($cta) . '</p>';
        $html .= '</section>';
        $html = $this->ensure_minimum_length($html, $context, $settings, $profile, $local);

        return array('html' => wp_kses_post($html), 'faq' => $faq, 'links' => $links);
    }

    private function section($heading, $paragraphs) {
        $html = '<h2>' . esc_html($heading) . '</h2>';
        foreach ($paragraphs as $paragraph) {
            $html .= '<p>' . esc_html($paragraph) . '</p>';
        }
        return $html;
    }

    private function list_section($heading, $items, $intro) {
        $html = '<h2>' . esc_html($heading) . '</h2><p>' . esc_html($intro) . '</p><ul>';
        foreach (array_slice((array) $items, 0, 8) as $item) {
            $html .= '<li>' . esc_html(ucfirst($item)) . '</li>';
        }
        return $html . '</ul>';
    }

    private function ensure_minimum_length($html, $context, $settings, $profile, $local) {
        $minimum = max(3000, absint($settings['minimum_content_length'] ?? 3000));
        if ($this->text_length($html) >= $minimum) {
            return $html;
        }

        $profession = $context['profession'] ?: 'negocios locais';
        $city = $context['city'] ?: ($settings['main_city'] ?? 'sua cidade');
        $category = $context['profession_group'] ?: 'servicos locais';
        $supplements = array(
            'No planejamento da pagina, o conteudo deve deixar evidente como o visitante pode reconhecer qualidade antes de entrar em contato. Para ' . $profession . ', isso inclui exemplos, clareza sobre o tipo de demanda atendida, orientacoes sobre orcamento e uma chamada para conversa que nao pareca generica.',
            'O contexto de ' . ucwords($city) . ' tambem precisa aparecer de maneira natural. O texto pode mencionar comportamento de busca local, importancia da proximidade, confianca regional e relacao com clientes que procuram referencias antes de tomar uma decisao.',
            'Dentro da categoria de ' . $category . ', paginas muito parecidas tendem a perder forca quando repetem sempre a mesma promessa. Por isso, este bloco complementa o conteudo com dores, criterios de escolha, estrutura recomendada e argumentos práticos de conversao.',
            'A presenca de FAQ, links internos e explicacoes de servico ajuda o usuario a navegar pelo site sem depender apenas do menu principal. Esses elementos tambem criam conexoes internas mais coerentes entre pagina de servico, cidade, categoria e contato.',
            'Outro ponto importante e evitar exagero de palavra-chave. A pagina pode usar variacoes como projeto digital, site profissional, pagina institucional, portfolio online, atendimento local e captacao qualificada, mantendo a leitura natural e comercial.',
        );

        $i = 0;
        while ($this->text_length($html) < $minimum && $i < 10) {
            $html .= '<h2>' . esc_html('Complemento de contexto comercial') . '</h2>';
            $html .= '<p>' . esc_html($supplements[$i % count($supplements)]) . '</p>';
            if (!empty($local['segments'])) {
                $html .= '<p>' . esc_html('Esse contexto conversa com segmentos como ' . implode(', ', array_slice($local['segments'], 0, 4)) . ', tornando o conteudo mais conectado ao mercado local e menos dependente de uma estrutura repetida.') . '</p>';
            }
            if (!empty($profile['related_terms'])) {
                $html .= '<p>' . esc_html('Termos relacionados que ajudam a ampliar a cobertura semantica: ' . implode(', ', array_slice($profile['related_terms'], 0, 8)) . '.') . '</p>';
            }
            $i++;
        }

        return $html;
    }

    private function opening($service, $profession, $city, $category, $local) {
        if ('moda e confeccao' === remove_accents($category)) {
            return 'Um site profissional para ' . $profession . ' em ' . ucwords($city) . ' precisa mostrar a qualidade artesanal do trabalho, os tipos de peca atendidos e a diferenca entre um reparo simples e um servico especializado. No contexto local, onde reputacao e indicacao importam, uma pagina bem estruturada transforma pesquisas em contatos mais qualificados.';
        }
        return 'Uma pagina de ' . $service . ' para ' . $profession . ' em ' . ucwords($city) . ' precisa ir alem de repetir cidade e servico. Ela deve explicar o valor do atendimento, conectar o negocio ao mercado local e mostrar por que aquele profissional e uma escolha confiavel.';
    }

    private function conversion($profession, $city, $profile) {
        if (in_array('galeria de trabalhos', $profile['structure'], true)) {
            return 'Para ' . $profession . ', imagens, antes e depois, galeria organizada e botao de WhatsApp com contexto reduzem atrito. O visitante chega entendendo o tipo de trabalho realizado e pode pedir orcamento com mais seguranca.';
        }
        return 'A conversao melhora quando a pagina combina clareza de servicos, prova de confianca, atendimento local em ' . ucwords($city) . ' e um CTA coerente com a etapa de decisao do visitante.';
    }

    private function faq($service, $profession, $city, $category, $profile) {
        return array(
            array('q' => 'Por que ' . $profession . ' precisa de um site em ' . ucwords($city) . '?', 'a' => 'Porque muitos clientes pesquisam antes de chamar. O site organiza portfolio, servicos, localizacao e formas de orcamento em um unico lugar.'),
            array('q' => 'O site pode ajudar no atendimento pelo WhatsApp?', 'a' => 'Sim. A pagina pode filtrar duvidas, mostrar exemplos e levar o visitante para uma conversa mais objetiva no WhatsApp.'),
            array('q' => 'Quais secoes sao recomendadas para este nicho?', 'a' => 'As secoes mais importantes sao: ' . implode(', ', array_slice($profile['structure'], 0, 5)) . '.'),
            array('q' => 'Esse conteudo substitui indicacoes?', 'a' => 'Nao. Ele complementa as indicacoes e ajuda pessoas que ainda nao conhecem o profissional a entenderem seu trabalho.'),
            array('q' => 'Como evitar que a pagina fique generica?', 'a' => 'O ideal e combinar servico, cidade, categoria profissional, dores especificas, estrutura recomendada e exemplos de busca local.'),
        );
    }

    private function cta($context) {
        $options = array(
            'Solicite uma analise para seu projeto digital.',
            'Peca uma proposta personalizada para sua presenca online.',
            'Receba um diagnostico digital para melhorar sua captacao local.',
            'Fale com a equipe e veja o melhor caminho para seu site.',
        );
        $seed = abs(crc32(wp_json_encode($context)));
        return $options[$seed % count($options)];
    }

    private function headings($category) {
        if ('moda e confeccao' === remove_accents($category)) {
            return array('Presenca digital para trabalhos artesanais', 'Como clientes pesquisam servicos especializados', 'Dores comuns de quem trabalha com couro e ajustes', 'Estrutura ideal para apresentar portfolio e orcamentos');
        }
        return array('Contexto comercial da pagina', 'Demanda local e comportamento de busca', 'Principais barreiras comerciais', 'Estrutura recomendada do site');
    }

    private function text_length($html) {
        $text = wp_strip_all_tags((string) $html);
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }
}
