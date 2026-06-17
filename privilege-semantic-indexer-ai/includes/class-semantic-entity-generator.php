<?php
if (!defined('ABSPATH')) { exit; }

class PSI_AI_Semantic_Entity_Generator {
    private $category_intelligence;
    public function __construct($category_intelligence) { $this->category_intelligence = $category_intelligence; }

    public function build($detected) {
        $profession = $detected['profession'] ?: 'profissionais';
        $category = $this->category_intelligence->classify($profession, $detected['macro_category'] ?? '');
        $seed = abs(crc32(($detected['post_id'] ?? 0) . $profession . ($detected['city'] ?? '') . $category['key']));
        return array(
            'profession'=>$profession,
            'city'=>$detected['city'] ?: 'sua região',
            'uf'=>$detected['uf'] ?: '',
            'category_key'=>$category['key'],
            'macro_category'=>$category['label'],
            'subcategory'=>$this->subcategory($profession, $category['key']),
            'digital_service'=>'criação de site profissional com estrutura para orçamento e contato',
            'commercial_intent'=>'avaliar uma empresa antes de pedir orçamento ou chamar no WhatsApp',
            'tone'=>$category['tone'],
            'services'=>$this->rotate($category['services'], $seed),
            'proofs'=>$this->rotate($category['proofs'], $seed + 7),
            'objections'=>$this->objections($category['key'], $profession, $seed),
            'pains'=>$this->pains($category['key'], $profession, $seed),
            'icons'=>$this->rotate($category['icons'], $seed + 11),
            'seed'=>$seed,
        );
    }

    private function subcategory($profession, $key) {
        $p = strtolower(remove_accents($profession));
        if ($key === 'moda' && preg_match('/couro|pele/u', $p)) return 'reparos e peças em couro';
        if ($key === 'saude' && preg_match('/odont/u', $p)) return 'atendimento odontológico';
        if ($key === 'direito' && preg_match('/previd/u', $p)) return 'direito previdenciário';
        if ($key === 'construcao' && preg_match('/eletric/u', $p)) return 'serviços elétricos';
        if ($key === 'alimentacao' && preg_match('/bolo|confeit/u', $p)) return 'encomendas e confeitaria';
        return 'atendimento especializado';
    }

    private function objections($key, $profession, $seed) {
        $sets = array(
            'moda'=>array('não saber se o acabamento ficará bom','precisar enviar foto antes do orçamento','ter receio de deixar uma peça de valor','não entender prazos e possibilidades de ajuste'),
            'saude'=>array('querer entender especialidade antes de agendar','ter dúvidas sobre preparo e atendimento','buscar sinais de confiança e ética','precisar de localização e canais claros'),
            'direito'=>array('não saber se o caso se encaixa na área','ter urgência e medo de perder prazos','precisar entender documentos iniciais','buscar credibilidade antes do primeiro contato'),
            'construcao'=>array('não saber estimar prazo e etapas','querer ver obras anteriores','precisar de vistoria antes do orçamento','buscar confiança técnica'),
            'alimentacao'=>array('querer ver fotos reais','comparar cardápio e formas de pedido','precisar entender entrega ou retirada','tirar dúvidas antes de encomendar'),
            'beleza'=>array('querer ver resultados reais','precisar entender cuidados e agenda','buscar segurança no procedimento','comparar atendimento antes de marcar'),
            'servicos_tecnicos'=>array('ter urgência no diagnóstico','querer saber se há garantia','precisar explicar o problema rapidamente','comparar atendimento local'),
        );
        return $this->rotate($sets[$key] ?? array('querer entender o serviço antes do contato','comparar fornecedores locais','precisar de provas de confiança','buscar resposta rápida'), $seed);
    }

    private function pains($key, $profession, $seed) {
        $sets = array(
            'moda'=>array('mostrar acabamento e material com detalhes','organizar fotos antes/depois','explicar orçamento por imagem','criar confiança para peças de valor','separar tipos de serviço com clareza','levar o cliente ao WhatsApp sem pressão'),
            'saude'=>array('explicar especialidades sem promessas','facilitar agendamento','mostrar localização e horários','responder dúvidas iniciais','transmitir confiança com linguagem ética','orientar próximos passos'),
            'direito'=>array('explicar áreas atendidas','organizar dúvidas frequentes','mostrar processo de atendimento inicial','criar autoridade sem exagero','facilitar envio de informações','orientar urgências com clareza'),
            'construcao'=>array('mostrar portfólio de obras','explicar vistoria e orçamento','separar serviços por etapa','dar clareza sobre regiões atendidas','reduzir dúvidas sobre prazos','facilitar pedido de avaliação'),
            'alimentacao'=>array('mostrar fotos reais do produto','organizar cardápio e encomendas','explicar entrega ou retirada','facilitar pedido pelo WhatsApp','destacar opções e tamanhos','reduzir dúvidas antes do pedido'),
            'beleza'=>array('mostrar resultados visuais','explicar procedimentos e cuidados','facilitar agenda','destacar biossegurança','organizar depoimentos reais','responder dúvidas antes da marcação'),
            'servicos_tecnicos'=>array('explicar diagnóstico','destacar manutenção e instalação','facilitar chamada urgente','orientar fotos do problema','mostrar região atendida','esclarecer garantia quando aplicável'),
        );
        return $this->rotate($sets[$key] ?? array('explicar serviços com clareza','mostrar provas reais','facilitar orçamento','reduzir dúvidas no WhatsApp','organizar atendimento local','destacar diferenciais sem exagero'), $seed);
    }

    private function rotate($arr, $seed) { $n=count($arr); if(!$n) return $arr; $r=$seed % $n; return array_merge(array_slice($arr,$r), array_slice($arr,0,$r)); }
}
