<?php
if (!defined('ABSPATH')) { exit; }
class PSI_AI_Page_Detector {
    private $protection;
    public function __construct($protection) { $this->protection = $protection; }
    public function detect_by_url($url) {
        $post_id = url_to_postid(esc_url_raw($url));
        if (!$post_id) { return new WP_Error('not_found', 'URL não localizada no WordPress.'); }
        if ($this->protection->is_protected($post_id)) { return new WP_Error('protected', 'Página protegida.'); }
        $post = get_post($post_id);
        $parts = $this->extract_parts($post);
        return array_merge(array('post'=>$post,'post_id'=>$post_id,'uses_boldbuilder'=>$this->uses_boldbuilder($post->post_content),'has_old_enrichment'=>strpos($post->post_content, 'ses-enriched-content') !== false), $parts);
    }
    public function uses_boldbuilder($content) { return strpos($content, 'bt_bb_') !== false || strpos($content, '[bt_bb') !== false; }
    public function extract_parts($post) {
        $slug = sanitize_title($post->post_name);
        $text = preg_replace('/^criacao-de-sites-para-/', '', $slug);
        $city = ''; $profession = $text; $uf = '';
        if (preg_match('/(.+)-em-([a-z0-9-]+)$/', $text, $m)) { $profession = $m[1]; $city = $m[2]; }
        if (preg_match('/[-–]\s*([A-Z]{2})\b/u', $post->post_title, $m)) { $uf = $m[1]; }
        return array('profession'=>ucwords(str_replace('-', ' ', $profession)), 'city'=>ucwords(str_replace('-', ' ', $city)), 'uf'=>$uf, 'macro_category'=>$this->category_for($profession), 'intent'=>'contratar criação de site profissional local');
    }
    private function category_for($profession) {
        $p = strtolower($profession);
        $map = array('moda|costur|roupa|couro'=>'moda, confecção e serviços artesanais','medic|odont|clinica|terapia'=>'saúde e bem-estar','advog|contab|consult'=>'serviços profissionais','restaur|bar|pizz|food'=>'alimentação e atendimento local','imovel|constr|engenh|arquitet'=>'imóveis, construção e projetos');
        foreach ($map as $rx=>$cat) { if (preg_match('/'.$rx.'/u', $p)) return $cat; }
        return 'serviços locais e atendimento especializado';
    }
}
