<?php
if (!defined('ABSPATH')) { exit; }
class PSI_AI_Sitemap_Reader {
    private $logger;
    public function __construct($logger) { $this->logger = $logger; }
    public function read_all($sitemaps) {
        $urls = array();
        foreach ((array) $sitemaps as $sitemap) { $urls = array_merge($urls, $this->read($sitemap)); }
        return array_values(array_unique(array_filter($urls)));
    }
    public function read($url) {
        $url = esc_url_raw($url);
        $response = wp_remote_get($url, array('timeout'=>20, 'redirection'=>3));
        if (is_wp_error($response)) { $this->logger->log('error', 'Falha ao ler sitemap', array('url'=>$url,'error'=>$response->get_error_message())); return array(); }
        $body = wp_remote_retrieve_body($response);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if (!$xml) { $this->logger->log('error', 'XML inválido no sitemap', array('url'=>$url)); return array(); }
        $urls = array();
        foreach ($xml->url as $item) { $urls[] = esc_url_raw((string) $item->loc); }
        foreach ($xml->sitemap as $item) { $urls = array_merge($urls, $this->read((string) $item->loc)); }
        return $urls;
    }
}
