<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Profession_Intelligence {
    public function categories() {
        return array(
            'jurídico', 'saúde', 'estética', 'engenharia', 'arquitetura', 'educação', 'indústria', 'agronegócio',
            'comércio', 'serviços locais', 'tecnologia', 'consultoria', 'imobiliário', 'alimentação',
            'moda e confecção', 'beleza', 'eventos', 'turismo', 'financeiro', 'outros',
        );
    }

    public function category_for($profession) {
        $p = strtolower(remove_accents((string) $profession));
        $map = array(
            'costureiro' => 'moda e confecção',
            'costura' => 'moda e confecção',
            'couro' => 'moda e confecção',
            'pele' => 'moda e confecção',
            'advogado' => 'jurídico',
            'medico' => 'saúde',
            'dentista' => 'saúde',
            'arquiteto' => 'arquitetura',
            'engenheiro' => 'engenharia',
            'imobiliaria' => 'imobiliário',
        );
        foreach ($map as $needle => $category) {
            if (false !== strpos($p, $needle)) {
                return $category;
            }
        }
        return 'serviços locais';
    }

    public function profile($profession, $category = '') {
        $p = strtolower(remove_accents((string) $profession));
        if (false !== strpos($p, 'costureiro') || false !== strpos($p, 'couro')) {
            return array(
                'related_terms' => array('atelier', 'confecção', 'ajustes', 'couro', 'pele', 'vestuário', 'reforma de roupas', 'peças sob medida', 'personalização', 'reparos'),
                'pains' => array('dependência de indicação', 'dificuldade de mostrar portfólio', 'baixa presença no Google', 'atendimento disperso no WhatsApp', 'pouca clareza sobre serviços oferecidos'),
                'objections' => array('Já uso Instagram', 'Recebo clientes por indicação', 'Meu serviço é muito específico', 'Prefiro atender pelo WhatsApp'),
                'structure' => array('página inicial', 'galeria de trabalhos', 'serviços de ajuste e reparo', 'peças sob medida', 'formulário de orçamento', 'botão de WhatsApp', 'localização', 'depoimentos'),
            );
        }
        return array(
            'related_terms' => array($profession, $category, 'atendimento local', 'orçamento', 'serviços especializados'),
            'pains' => array('baixa visibilidade em buscas locais', 'dificuldade de explicar diferenciais', 'dependência de indicação', 'orçamentos manuais no WhatsApp'),
            'objections' => array('Já recebo clientes por indicação', 'Não sei se site traz retorno', 'Prefiro atender pelo WhatsApp'),
            'structure' => array('apresentação do serviço', 'diferenciais', 'depoimentos', 'perguntas frequentes', 'formulário de orçamento', 'WhatsApp', 'localização'),
        );
    }
}
