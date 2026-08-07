<?php
if (!defined('ABSPATH')) { exit; }

class PSI_AI_Category_Intelligence {
    private $categories = array(
        'saude' => array('label'=>'Saúde','terms'=>'medic|odont|clinica|terap|psicolog|fisio|nutri|fono|enferm|esteticista','tone'=>'confiança, agendamento, especialidade, localização, procedimentos, ética e clareza','icons'=>array('pulse','calendar','shield','pin')),
        'direito' => array('label'=>'Direito','terms'=>'advog|jurid|direito|previd|trabalhista|tribut|civil|criminal','tone'=>'autoridade, áreas de atuação, dúvidas frequentes, urgência, atendimento inicial e credibilidade','icons'=>array('scale','document','clock','shield')),
        'moda' => array('label'=>'Moda e confecção','terms'=>'moda|costur|roupa|couro|pele|alfaiat|bordad|tecid|confec','tone'=>'portfólio, acabamento, personalização, fotos antes/depois, materiais, orçamento por imagem e atendimento artesanal','icons'=>array('needle','spark','image','chat')),
        'construcao' => array('label'=>'Construção civil','terms'=>'constr|obra|engenh|arquitet|pedreir|pintor|eletric|marcen|serralh','tone'=>'obra, orçamento, prazos, portfólio, regiões atendidas, confiança técnica e vistoria','icons'=>array('tool','ruler','home','clock')),
        'educacao' => array('label'=>'Educação','terms'=>'escola|curso|professor|aula|educa|idioma|trein|faculdade','tone'=>'metodologia, matrícula, cursos, resultados, localização, dúvidas e atendimento','icons'=>array('book','route','chat','pin')),
        'alimentacao' => array('label'=>'Alimentação','terms'=>'restaur|bar|pizz|food|lanch|buffet|bolo|doc|padar|confeit','tone'=>'cardápio, encomendas, delivery, fotos, localização, avaliações e pedidos','icons'=>array('menu','delivery','camera','pin')),
        'beleza' => array('label'=>'Beleza e estética','terms'=>'beleza|estetic|cabelo|manicure|barbear|maqui|depila|sobrancelh|spa','tone'=>'procedimento, resultado visual, agenda, prova social, biossegurança e atendimento','icons'=>array('spark','calendar','image','shield')),
        'servicos_tecnicos' => array('label'=>'Serviços técnicos','terms'=>'tecnic|manuten|assist|refrig|informat|conserto|instala|chaveir|mecan','tone'=>'diagnóstico, urgência, manutenção, orçamento, atendimento local e garantia','icons'=>array('tool','clock','shield','chat')),
        'servicos_locais' => array('label'=>'Serviços locais','terms'=>'.*','tone'=>'clareza de serviço, confiança, atendimento local, orçamento e contato rápido','icons'=>array('pin','chat','shield','route')),
    );

    public function classify($profession, $fallback = '') {
        $haystack = strtolower(remove_accents($profession . ' ' . $fallback));
        foreach ($this->categories as $key => $data) {
            if ($key !== 'servicos_locais' && preg_match('/' . $data['terms'] . '/u', $haystack)) {
                return array_merge(array('key'=>$key), $data, $this->specifics($key));
            }
        }
        return array_merge(array('key'=>'servicos_locais'), $this->categories['servicos_locais'], $this->specifics('servicos_locais'));
    }

    private function specifics($key) {
        $sets = array(
            'saude'=>array('services'=>array('especialidades','formas de agendamento','orientações pré-atendimento','localização e horários'),'proofs'=>array('credenciais claras','explicação ética dos procedimentos','depoimentos reais quando existirem','canais de agendamento')),
            'direito'=>array('services'=>array('áreas de atuação','primeira conversa','documentos necessários','prazos de retorno'),'proofs'=>array('clareza sobre experiência','conteúdo educativo','processo de atendimento','credibilidade institucional')),
            'moda'=>array('services'=>array('ajustes e reparos','peças atendidas','personalizações','orçamento por imagem'),'proofs'=>array('galeria antes/depois','detalhes de acabamento','materiais atendidos','depoimentos de clientes')),
            'construcao'=>array('services'=>array('vistoria','orçamento de obra','execução por etapa','regiões atendidas'),'proofs'=>array('portfólio de obras','prazos explicados','responsável técnico quando houver','fotos do processo')),
            'educacao'=>array('services'=>array('cursos','metodologia','matrícula','atendimento a dúvidas'),'proofs'=>array('grade ou método','resultados possíveis sem promessas','depoimentos reais','estrutura de atendimento')),
            'alimentacao'=>array('services'=>array('cardápio','encomendas','delivery ou retirada','pedidos por WhatsApp'),'proofs'=>array('fotos reais','opções e tamanhos','área de entrega','avaliações reais')),
            'beleza'=>array('services'=>array('procedimentos','agenda','cuidados antes/depois','orçamento inicial'),'proofs'=>array('resultados visuais','biossegurança','depoimentos reais','orientações de preparo')),
            'servicos_tecnicos'=>array('services'=>array('diagnóstico','manutenção','instalação','atendimento emergencial'),'proofs'=>array('explicação do processo','garantia quando aplicável','região atendida','fotos de serviços')),
            'servicos_locais'=>array('services'=>array('serviços principais','orçamento','atendimento por região','dúvidas frequentes'),'proofs'=>array('portfólio','depoimentos reais','processo de atendimento','contato facilitado')),
        );
        return $sets[$key];
    }
}
