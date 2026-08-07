<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Slug_Parser {
    public function parse($slug, $configured_patterns = array()) {
        $original = sanitize_title($slug);
        $has_accent = (bool) preg_match('/[^\x20-\x7E]/u', (string) $slug);
        $clean = sanitize_title(remove_accents($slug));
        $data = array(
            'is_programmatic' => 0,
            'service' => '',
            'profession' => '',
            'city' => '',
            'state' => '',
            'profession_group' => '',
            'slug_has_accent' => $has_accent ? 1 : 0,
        );

        $patterns = array(
            'criacao-de-sites-para-' => 'criação de sites',
            'agencia-de-marketing-digital-em-' => 'agência de marketing digital',
            'marketing-digital-em-' => 'marketing digital',
            'trafego-pago-para-' => 'tráfego pago',
            'seo-local-para-' => 'SEO local',
        );
        foreach ((array) $configured_patterns as $pattern) {
            $key = sanitize_title(remove_accents($pattern));
            if ($key && !isset($patterns[$key])) {
                $patterns[$key] = $this->humanize_service($key);
            }
        }

        foreach ($patterns as $prefix => $service) {
            if (0 === strpos($clean, $prefix)) {
                $tail = $this->strip_known_suffixes(substr($clean, strlen($prefix)));
                $data['service'] = $service;
                $data['is_programmatic'] = 1;
                if ('agencia-de-marketing-digital-em-' === $prefix || 'marketing-digital-em-' === $prefix) {
                    $data['city'] = $this->humanize($tail);
                } else {
                    $parts = $this->split_profession_city($tail);
                    $data['profession'] = $parts['profession'];
                    $data['city'] = $parts['city'];
                }
                break;
            }
        }

        if (!$data['is_programmatic']) {
            return $data;
        }

        $profession_intel = new SES_Profession_Intelligence();
        $data['profession_group'] = $profession_intel->category_for($data['profession']);
        $local_intel = new SES_Local_Intelligence();
        $city = $local_intel->city_data($data['city']);
        $data['state'] = $city['state'] ?? '';
        return $data;
    }

    private function strip_known_suffixes($value) {
        $suffixes = array(
            '-agencia-privilege',
            '-studio-privilege',
            '-privilege',
        );
        foreach ($suffixes as $suffix) {
            if (substr($value, -strlen($suffix)) === $suffix) {
                return substr($value, 0, -strlen($suffix));
            }
        }
        return $value;
    }

    private function split_profession_city($tail) {
        $markers = array('-em-', '-na-', '-no-');
        foreach ($markers as $marker) {
            $pos = strrpos($tail, $marker);
            if (false !== $pos) {
                return array(
                    'profession' => $this->humanize(substr($tail, 0, $pos)),
                    'city' => $this->humanize(substr($tail, $pos + strlen($marker))),
                );
            }
        }
        return array('profession' => $this->humanize($tail), 'city' => '');
    }

    private function humanize($value) {
        $value = trim(str_replace('-', ' ', (string) $value));
        return preg_replace('/\s+/', ' ', $value);
    }

    private function humanize_service($prefix) {
        $service = preg_replace('/-(para|em|na|no)-?$/', '', (string) $prefix);
        return $this->humanize($service);
    }
}
