<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Internal_Links {
    private $settings;

    public function __construct($settings = array()) {
        $this->settings = $settings;
    }

    public function suggest($context) {
        if (empty($this->settings['internal_links_enabled'])) {
            return array();
        }
        $links = array();
        $contact = $this->settings['contact_url'] ?? '';
        if ($contact) {
            $links[] = array('label' => 'Atendimento e contato', 'url' => $contact);
        }
        $service_url = home_url('/criacao-de-sites/');
        if (!empty($context['service']) && false !== stripos($context['service'], 'marketing')) {
            $service_url = home_url('/marketing-digital/');
        }
        $links[] = array('label' => 'Conheca a solucao de ' . ($context['service'] ?: 'presenca digital'), 'url' => $service_url);
        if (!empty($context['city'])) {
            $links[] = array('label' => 'Projetos digitais em ' . ucwords($context['city']), 'url' => home_url('/criacao-de-sites-em-' . sanitize_title($context['city']) . '/'));
        }
        if (!empty($context['profession_group'])) {
            $links[] = array('label' => 'Sites para ' . $context['profession_group'], 'url' => home_url('/sites-para-' . sanitize_title($context['profession_group']) . '/'));
        }
        return array_slice($links, 0, absint($this->settings['max_internal_links'] ?? 6));
    }
}
