<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Alcateia_Delivery_Shipping_Method extends WC_Shipping_Method {
	public function __construct( $instance_id = 0 ) {
		$this->id = 'alcateia_delivery';
		$this->instance_id = absint( $instance_id );
		$this->method_title = 'Alcateia Delivery';
		$this->method_description = 'Entrega Express enterprise';
		$this->supports = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );
		$this->init();
	}
	public function init(){ $this->init_form_fields(); $this->init_settings(); $this->enabled=$this->get_option('enabled','yes'); $this->title=$this->get_option('title','Entrega Express'); add_action('woocommerce_update_options_shipping_'.$this->id,array($this,'process_admin_options')); }
	public function init_form_fields(){ $this->form_fields=array('enabled'=>array('title'=>'Ativar','type'=>'checkbox','default'=>'yes'),'title'=>array('title'=>'Título','type'=>'text','default'=>'Entrega Express'),'default_days'=>array('title'=>'Prazo padrão','type'=>'number','default'=>7),'extra_fixed'=>array('title'=>'Taxa fixa','type'=>'price','default'=>0),'extra_percent'=>array('title'=>'Taxa %','type'=>'number','default'=>0),'min_cost'=>array('title'=>'Mínimo','type'=>'price','default'=>0),'max_cost'=>array('title'=>'Máximo','type'=>'price','default'=>0),'calculation_mode'=>array('title'=>'Modo','type'=>'select','default'=>'both','options'=>array('weight'=>'Peso','quantity'=>'Quantidade','both'=>'Ambos')),'debug_mode'=>array('title'=>'Debug','type'=>'checkbox','default'=>'no')); }
	public function calculate_shipping( $package = array() ) {
		$qty=0; $weight=0.0;
		foreach ( $package['contents'] as $item ) { $qty += (int) $item['quantity']; $weight += (float) wc_get_weight( $item['data']->get_weight(), 'kg' ) * (int) $item['quantity']; }
		$region = self::state_to_region( $package['destination']['state'] ?? '' );
		$result = self::calculate_estimate( array( 'qty'=>$qty,'weight'=>$weight,'region'=>$region,'subtotal'=>(float)($package['contents_cost']??0),'destination'=>$package['destination']??array(),'contents'=>$package['contents']??array() ), $this->settings );
		if ( ! $result ) { return; }
		$this->add_rate(array('id'=>$this->id,'label'=>$this->title . ' — Receba em até ' . (int)$result['days'] . ' dias úteis','cost'=>(float)$result['cost'],'meta_data'=>array('rule_id'=>$result['rule_id'])));
	}
	public static function state_to_region( $state ) { $state = strtoupper( (string) $state ); $map=array('SP'=>'R1','RJ'=>'R2','ES'=>'R2','MG'=>'R2','PR'=>'R2','SC'=>'R2','RS'=>'R2','GO'=>'R3','DF'=>'R3','MT'=>'R3','MS'=>'R3','PA'=>'R3','AM'=>'R3'); return $map[$state]??'R4'; }
	public static function calculate_estimate( $data, $settings = array() ) {
		global $wpdb; $table=Alcateia_Delivery_DB::table_name();
		$key='alcateia_rate_'.md5(wp_json_encode($data).wp_json_encode($settings).Alcateia_Delivery_Plugin::cache_version());
		if ( false !== ( $cached = get_transient( $key ) ) ) { return $cached; }
		$qty=(int)($data['qty']??1); $weight=(float)($data['weight']??0.1); $region=sanitize_text_field($data['region']??'R4'); $subtotal=(float)($data['subtotal']??0);
		$sql=$wpdb->prepare("SELECT * FROM {$table} WHERE active=1 AND region=%s AND weight_from<=%f AND weight_to>=%f AND qty_from<=%d AND qty_to>=%d AND subtotal_from<=%f AND subtotal_to>=%f ORDER BY priority ASC, weight_to ASC LIMIT 1",$region,$weight,$weight,$qty,$qty,$subtotal,$subtotal);
		$rule=$wpdb->get_row($sql);
		if(!$rule){Alcateia_Delivery_Plugin::log('Sem regra de frete',array('data'=>$data),'warning'); return false;}
		$cost=(float)$rule->shipping_cost + (float)($settings['extra_fixed']??0); $cost += $cost * ((float)($settings['extra_percent']??0)/100);
		if(!empty($settings['min_cost']) && $cost<(float)$settings['min_cost']){$cost=(float)$settings['min_cost'];}
		if(!empty($settings['max_cost']) && $cost>(float)$settings['max_cost']){$cost=(float)$settings['max_cost'];}
		$result=array('cost'=>$cost,'days'=>(int)($rule->delivery_days ?: ($settings['default_days']??7)),'rule_id'=>(int)$rule->id,'message'=>'Entrega Express — Receba em até '.(int)$rule->delivery_days.' dias úteis');
		set_transient($key,$result,15 * MINUTE_IN_SECONDS);
		Alcateia_Delivery_Plugin::log('Regra aplicada',array('rule_id'=>$rule->id,'cost'=>$cost));
		return $result;
	}
}
