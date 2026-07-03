<?php
if (!defined('ABSPATH')) { exit; }
class RSB_Pagamentos {
    public static function total(int $bolao_id): float { global $wpdb; return (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(valor_pago),0) FROM ".rsb_table('participantes')." WHERE bolao_id=%d AND status_pagamento='pago'",$bolao_id)); }
    public static function mark(int $participante_id, string $status, float $valor, ?string $data): bool { global $wpdb; $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".rsb_table('participantes')." WHERE id=%d",$participante_id)); if(!$old){return false;} $payload=['status_pagamento'=>sanitize_text_field($status),'valor_pago'=>$valor,'data_pagamento'=>$data,'updated_at'=>current_time('mysql')]; $ok=false!==$wpdb->update(rsb_table('participantes'),$payload,['id'=>$participante_id]); if($ok){ rsb_log('pagamento','participante',$participante_id,$old,$payload); } return $ok; }
}
