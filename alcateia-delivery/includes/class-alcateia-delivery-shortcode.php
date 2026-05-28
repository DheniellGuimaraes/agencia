<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Alcateia_Delivery_Shortcode {
	public function hooks(){ add_shortcode('alcateia_delivery_simulator',array($this,'render')); }
	public function render(){ wp_enqueue_script('alcateia-delivery-frontend', ALCATEIA_DELIVERY_URL . 'assets/js/frontend.js', array('jquery'), ALCATEIA_DELIVERY_VERSION, true); wp_localize_script('alcateia-delivery-frontend','alcateiaDelivery',array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('alcateia_delivery_simulate'))); wp_enqueue_style('alcateia-design-system', ALCATEIA_DELIVERY_URL . 'assets/css/admin-design-system.css', array(), ALCATEIA_DELIVERY_VERSION); update_option('alcateia_delivery_sim_total',(int)get_option('alcateia_delivery_sim_total',0)+1,false); ob_start(); ?>
	<form method="post" class="alcateia-delivery-simulator ad-glass" aria-label="<?php echo esc_attr__('Simulador de frete Alcateia Delivery', 'alcateia-delivery'); ?>">
		<label><?php echo esc_html__('Quantidade de livros', 'alcateia-delivery'); ?><input aria-label="<?php echo esc_attr__('Quantidade', 'alcateia-delivery'); ?>" name="qty" type="number" min="1" value="1"></label>
		<label><?php echo esc_html__('Peso total (kg)', 'alcateia-delivery'); ?><input aria-label="<?php echo esc_attr__('Peso total', 'alcateia-delivery'); ?>" name="weight" type="number" step="0.01" min="0.01" value="0.30"></label>
		<label><?php echo esc_html__('Região', 'alcateia-delivery'); ?><select aria-label="<?php echo esc_attr__('Região', 'alcateia-delivery'); ?>" name="region"><option value="R1">R1</option><option value="R2">R2</option><option value="R3">R3</option><option value="R4">R4</option></select></label>
		<div class="alcateia-result" role="status"><?php echo esc_html__('Preencha os dados para simular.', 'alcateia-delivery'); ?></div>
	</form>
	<?php return ob_get_clean(); }
}
