<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Local_Intelligence {
    public function city_data($city) {
        $key = strtolower(remove_accents((string) $city));
        $cities = array(
            'dourados' => array(
                'city' => 'Dourados',
                'state' => 'MS',
                'region' => 'Mato Grosso do Sul',
                'profile' => 'mercado regional com comércio forte, serviços especializados e influência do agronegócio',
                'nearby' => array('Itaporã', 'Ponta Porã', 'Maracaju', 'Rio Brilhante'),
                'segments' => array('comércio local', 'serviços profissionais', 'agronegócio', 'moda e confecção'),
            ),
        );
        if (isset($cities[$key])) {
            return $cities[$key];
        }
        return array(
            'city' => ucwords((string) $city),
            'state' => '',
            'region' => '',
            'profile' => 'mercado local com buscas cada vez mais orientadas por reputação, proximidade e clareza de serviços',
            'nearby' => array(),
            'segments' => array('serviços locais', 'comércio', 'atendimento especializado'),
        );
    }
}
