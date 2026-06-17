<?php
if (!defined('ABSPATH')) { exit; }

class PSI_AI_Local_Context_Generator {
    public function generate($city, $uf, $entities) {
        $city = $city ?: 'sua região';
        $suffix = $uf ? ' – ' . $uf : '';
        $seed = abs(crc32($city . $uf . $entities['category_key'] . $entities['profession']));
        $openings = array(
            'Em %s%s, muitos clientes preferem entender opções com calma antes de chamar no WhatsApp.',
            'Para quem atende em %s%s, uma página bem organizada ajuda o visitante a decidir se vale pedir orçamento.',
            'Em %s%s, a escolha por um fornecedor local costuma passar por confiança, clareza e resposta fácil.',
            'Quando alguém pesquisa por atendimento em %s%s, normalmente quer reduzir dúvidas antes da primeira conversa.',
            'Uma presença digital em %s%s precisa explicar o serviço sem complicar o caminho até o contato.'
        );
        $middles = array(
            'Isso é ainda mais importante quando o serviço envolve %s, porque o cliente quer ver sinais concretos de cuidado antes de avançar.',
            'O site deve mostrar %s de forma simples, para que a pessoa não dependa apenas de indicação ou conversa longa.',
            'A página funciona melhor quando antecipa perguntas sobre %s e deixa claro como pedir uma proposta.',
            'O conteúdo precisa conectar %s com exemplos, orientações e uma chamada direta para conversa comercial.'
        );
        return sprintf($this->pick($openings, $seed), esc_html($city), esc_html($suffix)) . ' ' . sprintf($this->pick($middles, $seed + 5), esc_html(implode(', ', array_slice($entities['services'], 0, 3))));
    }

    private function pick($arr, $seed) { return $arr[$seed % count($arr)]; }
}
