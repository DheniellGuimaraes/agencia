<?php
if (!defined('ABSPATH')) { exit; }

class RSB_Teams {
    public static function all(): array {
        return [
            'Brasil'=>['iso'=>'BR'],'Argentina'=>['iso'=>'AR'],'França'=>['iso'=>'FR'],'Alemanha'=>['iso'=>'DE'],'Espanha'=>['iso'=>'ES'],'Portugal'=>['iso'=>'PT'],'Inglaterra'=>['iso'=>'GB-ENG','emoji'=>'&#127988;&#917605;&#917614;&#917607;&#917631;'],'Estados Unidos'=>['iso'=>'US'],'Canadá'=>['iso'=>'CA'],'México'=>['iso'=>'MX'],
            'Japão'=>['iso'=>'JP'],'Coreia do Sul'=>['iso'=>'KR'],'Arábia Saudita'=>['iso'=>'SA'],'Austrália'=>['iso'=>'AU'],'Marrocos'=>['iso'=>'MA'],'Tunísia'=>['iso'=>'TN'],'Senegal'=>['iso'=>'SN'],'África do Sul'=>['iso'=>'ZA'],'Costa do Marfim'=>['iso'=>'CI'],'Gana'=>['iso'=>'GH'],
            'Camarões'=>['iso'=>'CM'],'Nigéria'=>['iso'=>'NG'],'Egito'=>['iso'=>'EG'],'Uruguai'=>['iso'=>'UY'],'Colômbia'=>['iso'=>'CO'],'Equador'=>['iso'=>'EC'],'Paraguai'=>['iso'=>'PY'],'Chile'=>['iso'=>'CL'],'Peru'=>['iso'=>'PE'],'Bolívia'=>['iso'=>'BO'],
            'Venezuela'=>['iso'=>'VE'],'Holanda'=>['iso'=>'NL'],'Bélgica'=>['iso'=>'BE'],'Croácia'=>['iso'=>'HR'],'Sérvia'=>['iso'=>'RS'],'Suíça'=>['iso'=>'CH'],'Dinamarca'=>['iso'=>'DK'],'Suécia'=>['iso'=>'SE'],'Noruega'=>['iso'=>'NO'],'Polônia'=>['iso'=>'PL'],
            'Tchéquia'=>['iso'=>'CZ'],'Áustria'=>['iso'=>'AT'],'Turquia'=>['iso'=>'TR'],'Escócia'=>['iso'=>'GB-SCT','emoji'=>'&#127988;&#917619;&#917603;&#917620;&#917631;'],'País de Gales'=>['iso'=>'GB-WLS','emoji'=>'&#127988;&#917623;&#917612;&#917619;&#917631;'],'Irlanda'=>['iso'=>'IE'],'Irlanda do Norte'=>['iso'=>'GB-NIR','emoji'=>'&#127988;'],'Nova Zelândia'=>['iso'=>'NZ'],'Irã'=>['iso'=>'IR'],'Catar'=>['iso'=>'QA'],
            'Emirados Árabes Unidos'=>['iso'=>'AE'],'Iraque'=>['iso'=>'IQ'],'Uzbequistão'=>['iso'=>'UZ'],'Jordânia'=>['iso'=>'JO'],'China'=>['iso'=>'CN'],'Tailândia'=>['iso'=>'TH'],'Indonésia'=>['iso'=>'ID'],'Costa Rica'=>['iso'=>'CR'],'Panamá'=>['iso'=>'PA'],'Jamaica'=>['iso'=>'JM'],
            'Honduras'=>['iso'=>'HN'],'El Salvador'=>['iso'=>'SV'],'Guatemala'=>['iso'=>'GT'],'Haiti'=>['iso'=>'HT'],'República Dominicana'=>['iso'=>'DO'],'Curaçao'=>['iso'=>'CW'],'Suriname'=>['iso'=>'SR'],'Cabo Verde'=>['iso'=>'CV'],'Bósnia e Herzegovina'=>['iso'=>'BA'],'RD Congo'=>['iso'=>'CD'],'Argélia'=>['iso'=>'DZ'],
            'Itália'=>['iso'=>'IT'],'Ucrânia'=>['iso'=>'UA'],'Grécia'=>['iso'=>'GR'],'Eslováquia'=>['iso'=>'SK'],'Eslovênia'=>['iso'=>'SI'],'Romênia'=>['iso'=>'RO'],'Hungria'=>['iso'=>'HU'],'Finlândia'=>['iso'=>'FI'],'Albânia'=>['iso'=>'AL'],'Geórgia'=>['iso'=>'GE'],
        ];
    }

    public static function get_team_meta(string $team_name): array {
        $team = trim($team_name);
        $team = self::canonical_name($team);
        $map = self::all();
        if (isset($map[$team])) {
            return ['name'=>$team, 'type'=>'flag', 'label'=>self::get_flag($team), 'iso'=>$map[$team]['iso'], 'class'=>'rsb-flag-country'];
        }
        if (self::is_placeholder($team)) {
            return ['name'=>$team, 'type'=>'placeholder', 'label'=>self::placeholder_symbol($team), 'iso'=>'', 'class'=>'rsb-flag-placeholder'];
        }
        return ['name'=>$team, 'type'=>'fallback', 'label'=>self::initials($team), 'iso'=>'', 'class'=>'rsb-flag-initials'];
    }

    public static function get_flag(string $team_name): string {
        $map = self::all();
        $meta = $map[self::canonical_name(trim($team_name))] ?? null;
        if (!$meta) { return self::placeholder_symbol($team_name); }
        if (!empty($meta['emoji'])) { return html_entity_decode($meta['emoji'], ENT_QUOTES, 'UTF-8'); }
        return self::iso_to_emoji((string)$meta['iso']);
    }

    public static function render_flag(string $team_name): string {
        $meta = self::get_team_meta($team_name);
        return '<span class="rsb-flag '.esc_attr($meta['class']).'" title="'.esc_attr($meta['name']).'" aria-label="'.esc_attr($meta['name']).'">'.esc_html($meta['label']).'</span>';
    }

    private static function iso_to_emoji(string $iso): string {
        $iso = strtoupper(substr($iso, 0, 2));
        if (!preg_match('/^[A-Z]{2}$/', $iso)) { return html_entity_decode('&#9917;', ENT_QUOTES, 'UTF-8'); }
        $out = '';
        foreach (str_split($iso) as $char) {
            $out .= html_entity_decode('&#'.(127397 + ord($char)).';', ENT_QUOTES, 'UTF-8');
        }
        return $out;
    }

    private static function is_placeholder(string $team): bool {
        $t = mb_strtolower($team);
        return $team === '' || strpos($t, 'grupo ') !== false || strpos($t, 'jogo ') !== false || strpos($t, 'terceiro') !== false || strpos($t, 'vencedor') !== false || strpos($t, 'perdedor') !== false || strpos($t, 'classificado') !== false;
    }

    private static function canonical_name(string $team): string {
        $team = trim(preg_replace('/\s+/', ' ', $team));
        $map = self::all();
        $candidates = [$team];
        $fixed = self::repair_mojibake($team);
        if ($fixed !== $team) { $candidates[] = $fixed; }
        $aliases = self::aliases();
        foreach ($candidates as $candidate) {
            if (isset($map[$candidate])) { return $candidate; }
            $key = self::alias_key($candidate);
            if (isset($aliases[$key])) { return $aliases[$key]; }
        }
        return $team;
    }

    private static function aliases(): array {
        return [
            'eua'=>'Estados Unidos','usa'=>'Estados Unidos','estados unidos da america'=>'Estados Unidos','united states'=>'Estados Unidos',
            'canada'=>'Canadá','mexico'=>'México','franca'=>'França','japao'=>'Japão','arabia saudita'=>'Arábia Saudita','australia'=>'Austrália','tunisia'=>'Tunísia','africa do sul'=>'África do Sul',
            'camaroes'=>'Camarões','nigeria'=>'Nigéria','colombia'=>'Colômbia','bolivia'=>'Bolívia','belgica'=>'Bélgica','croacia'=>'Croácia','servia'=>'Sérvia','suica'=>'Suíça','suecia'=>'Suécia','polonia'=>'Polônia',
            'tchequia'=>'Tchéquia','republica tcheca'=>'Tchéquia','czechia'=>'Tchéquia','austria'=>'Áustria','escocia'=>'Escócia','pais de gales'=>'País de Gales','ira'=>'Irã','iraque'=>'Iraque',
            'emirados arabes unidos'=>'Emirados Árabes Unidos','uzbequistao'=>'Uzbequistão','jordania'=>'Jordânia','panama'=>'Panamá','republica dominicana'=>'República Dominicana','curacao'=>'Curaçao',
            'bosnia e herzegovina'=>'Bósnia e Herzegovina','argelia'=>'Argélia','nova zelandia'=>'Nova Zelândia','cabo verde'=>'Cabo Verde','rd congo'=>'RD Congo','congo dr'=>'RD Congo','republica democratica do congo'=>'RD Congo',
            'holanda'=>'Holanda','paises baixos'=>'Holanda','netherlands'=>'Holanda','italia'=>'Itália','ucrania'=>'Ucrânia','grecia'=>'Grécia','eslovaquia'=>'Eslováquia','eslovenia'=>'Eslovênia',
            'romenia'=>'Romênia','hungria'=>'Hungria','finlandia'=>'Finlândia','albania'=>'Albânia','georgia'=>'Geórgia',
        ];
    }

    private static function alias_key(string $team): string {
        $team = trim(preg_replace('/\s+/', ' ', $team));
        if (function_exists('remove_accents')) { $team = remove_accents($team); }
        return mb_strtolower($team);
    }

    private static function repair_mojibake(string $team): string {
        if (!preg_match('/[ÃÂâ]/u', $team) || !function_exists('mb_convert_encoding')) { return $team; }
        $fixed = @mb_convert_encoding($team, 'ISO-8859-1', 'UTF-8');
        return is_string($fixed) && $fixed !== '' && mb_check_encoding($fixed, 'UTF-8') ? $fixed : $team;
    }

    private static function placeholder_symbol(string $team): string {
        $t = mb_strtolower($team);
        if (strpos($t, 'vencedor') !== false || strpos($t, 'final') !== false) { return html_entity_decode('&#127942;', ENT_QUOTES, 'UTF-8'); }
        return html_entity_decode('&#9917;', ENT_QUOTES, 'UTF-8');
    }

    private static function initials(string $team): string {
        $team = trim(preg_replace('/\s+/', ' ', $team));
        if ($team === '') { return '?'; }
        $parts = explode(' ', $team);
        $initials = mb_substr($parts[0], 0, 1);
        if (count($parts) > 1) { $initials .= mb_substr(end($parts), 0, 1); }
        return mb_strtoupper($initials);
    }
}
