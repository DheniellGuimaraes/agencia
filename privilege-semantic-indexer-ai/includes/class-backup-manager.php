<?php
if (!defined('ABSPATH')) { exit; }
class PSI_AI_Backup_Manager {
    public function create($post_id) { global $wpdb; $post=get_post($post_id); if(!$post) return false; $meta=array(); foreach (array('_yoast_wpseo_title','_yoast_wpseo_metadesc','_yoast_wpseo_canonical','_yoast_wpseo_meta-robots-noindex') as $k) $meta[$k]=get_post_meta($post_id,$k,true); return $wpdb->insert($wpdb->prefix.'psi_ai_backups', array('post_id'=>$post_id,'post_content'=>$post->post_content,'seo_meta'=>wp_json_encode($meta),'content_hash'=>hash('sha256',$post->post_content),'plugin_version'=>PSI_AI_VERSION,'created_at'=>current_time('mysql')), array('%d','%s','%s','%s','%s','%s')); }
    public function restore($backup_id) { global $wpdb; $b=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}psi_ai_backups WHERE id=%d", absint($backup_id))); if(!$b) return false; return wp_update_post(array('ID'=>$b->post_id,'post_content'=>$b->post_content), true); }
}
