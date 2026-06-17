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
	public function init_form_fields(){ $fields=array('enabled'=>array('title'=>'Ativar','type'=>'checkbox','default'=>'yes'),'title'=>array('title'=>'Título','type'=>'text','default'=>'Entrega Express'),'default_days'=>array('title'=>'Prazo padrão','type'=>'number','default'=>7),'extra_days'=>array('title'=>'Dias extras','type'=>'number','default'=>0,'description'=>'Quantidade de dias úteis adicionada ao prazo encontrado na regra.'),'extra_fixed'=>array('title'=>'Taxa fixa','type'=>'price','default'=>0),'extra_percent'=>array('title'=>'Taxa %','type'=>'number','default'=>0),'min_cost'=>array('title'=>'Mínimo','type'=>'price','default'=>0),'max_cost'=>array('title'=>'Máximo','type'=>'price','default'=>0),'calculation_mode'=>array('title'=>'Modo','type'=>'select','default'=>'weight','options'=>array('weight'=>'Peso','quantity'=>'Quantidade','both'=>'Ambos')),'debug_mode'=>array('title'=>'Debug','type'=>'checkbox','default'=>'no')); $this->form_fields=$fields; $this->instance_form_fields=$fields; }
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
		$qty=(int)($data['qty']??1); $weight=(float)($data['weight']??0.1); $region=sanitize_text_field($data['region']??'R4'); $subtotal=round((float)($data['subtotal']??0),2);
		$dashboard_settings = wp_parse_args( (array) get_option( 'alcateia_delivery_settings', array() ), array( 'default_days' => 7, 'extra_days' => 0 ) );
		$settings = wp_parse_args( (array) $settings, array( 'calculation_mode' => 'weight', 'extra_fixed' => 0, 'extra_percent' => 0, 'min_cost' => 0, 'max_cost' => 0 ) );
		$settings['default_days'] = max( 1, absint( $dashboard_settings['default_days'] ) );
		$settings['extra_days'] = max( 0, absint( $dashboard_settings['extra_days'] ) );
		$key='alcateia_rate_'.md5(wp_json_encode($data).wp_json_encode($settings).Alcateia_Delivery_Plugin::cache_version());
		if ( false !== ( $cached = get_transient( $key ) ) ) { return $cached; }
		$mode = isset($settings['calculation_mode']) ? $settings['calculation_mode'] : 'weight';
		$queries = array();
		if ( 'both' === $mode ) {
			$queries[] = $wpdb->prepare("SELECT * FROM {$table} WHERE active=1 AND region=%s AND weight_from<=%f AND weight_to>=%f AND qty_from<=%d AND qty_to>=%d AND subtotal_from<=%f AND subtotal_to>=%f ORDER BY priority ASC, weight_to ASC LIMIT 1",$region,$weight,$weight,$qty,$qty,$subtotal,$subtotal);
			$queries[] = $wpdb->prepare("SELECT * FROM {$table} WHERE active=1 AND region=%s AND weight_from<=%f AND weight_to>=%f AND subtotal_from<=%f AND subtotal_to>=%f ORDER BY priority ASC, weight_to ASC LIMIT 1",$region,$weight,$weight,$subtotal,$subtotal);
			$queries[] = $wpdb->prepare("SELECT * FROM {$table} WHERE active=1 AND region=%s AND qty_from<=%d AND qty_to>=%d AND subtotal_from<=%f AND subtotal_to>=%f ORDER BY priority ASC, qty_to ASC LIMIT 1",$region,$qty,$qty,$subtotal,$subtotal);
		} elseif ( 'quantity' === $mode ) {
			$queries[] = $wpdb->prepare("SELECT * FROM {$table} WHERE active=1 AND region=%s AND qty_from<=%d AND qty_to>=%d AND subtotal_from<=%f AND subtotal_to>=%f ORDER BY priority ASC, qty_to ASC LIMIT 1",$region,$qty,$qty,$subtotal,$subtotal);
		} else {
			$queries[] = $wpdb->prepare("SELECT * FROM {$table} WHERE active=1 AND region=%s AND weight_from<=%f AND weight_to>=%f AND subtotal_from<=%f AND subtotal_to>=%f ORDER BY priority ASC, weight_to ASC LIMIT 1",$region,$weight,$weight,$subtotal,$subtotal);
		}
		$rule = null;
		foreach($queries as $qsql){ $rule = $wpdb->get_row($qsql); if($rule){ break; } }
		if(!$rule){Alcateia_Delivery_Plugin::log('Sem regra de frete',array('mode'=>$mode,'region'=>$region,'qty'=>$qty,'weight'=>$weight,'subtotal'=>$subtotal),'warning'); return false;}
		$cost=(float)$rule->shipping_cost + (float)($settings['extra_fixed']??0); $cost += $cost * ((float)($settings['extra_percent']??0)/100);
		if(!empty($settings['min_cost']) && $cost<(float)$settings['min_cost']){$cost=(float)$settings['min_cost'];}
		if(!empty($settings['max_cost']) && $cost>(float)$settings['max_cost']){$cost=(float)$settings['max_cost'];}
		$base_days=(int)($rule->delivery_days ?: ($settings['default_days']??7)); $extra_days=max(0,(int)($settings['extra_days']??0)); $days=max(1,$base_days+$extra_days);
		$result=array('cost'=>$cost,'days'=>$days,'rule_id'=>(int)$rule->id,'message'=>'Entrega Express — Receba em até '.$days.' dias úteis');
		set_transient($key,$result,15 * MINUTE_IN_SECONDS);
		Alcateia_Delivery_Plugin::log('Regra aplicada',array('rule_id'=>$rule->id,'cost'=>$cost));
		return $result;
	}
}
