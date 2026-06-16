<?php
/**
 * Plugin settings.
 *
 * @package AlcateiaLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Handles settings persistence and rendering. */
class Alcateia_Settings {
	/** Register settings. */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/** Register option with sanitization callback. */
	public static function register_settings(): void {
		register_setting( 'alcateia_labels_settings_group', ALCATEIA_SETTINGS_OPTION, array( __CLASS__, 'sanitize' ) );
	}

	/** Default settings. */
	public static function defaults(): array {
		return array(
			'sender_name'      => get_bloginfo( 'name' ),
			'sender_email'     => get_option( 'admin_email' ),
			'default_message'  => __( 'Seu pedido foi enviado pela Alcateia Editorial e já está a caminho.', 'emissao-de-etiquetas-alcateia' ),
			'email_footer'     => __( 'Alcateia Editorial', 'emissao-de-etiquetas-alcateia' ),
			'auto_send'        => 'no',
			'auto_send_status' => 'completed',
		);
	}

	/** Get merged settings. */
	public static function get(): array {
		$settings = get_option( ALCATEIA_SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
	}

	/** Sanitize settings. */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$status   = sanitize_key( $input['auto_send_status'] ?? $defaults['auto_send_status'] );
		$statuses = array_keys( wc_get_order_statuses() );
		$status   = str_replace( 'wc-', '', $status );

		if ( ! in_array( 'wc-' . $status, $statuses, true ) ) {
			$status = $defaults['auto_send_status'];
		}

		return array(
			'sender_name'      => sanitize_text_field( wp_unslash( $input['sender_name'] ?? $defaults['sender_name'] ) ),
			'sender_email'     => sanitize_email( wp_unslash( $input['sender_email'] ?? $defaults['sender_email'] ) ),
			'default_message'  => sanitize_textarea_field( wp_unslash( $input['default_message'] ?? $defaults['default_message'] ) ),
			'email_footer'     => sanitize_text_field( wp_unslash( $input['email_footer'] ?? $defaults['email_footer'] ) ),
			'auto_send'        => isset( $input['auto_send'] ) && 'yes' === $input['auto_send'] ? 'yes' : 'no',
			'auto_send_status' => $status,
		);
	}

	/** Render settings section. */
	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = self::get();
		$statuses = wc_get_order_statuses();
		?>
		<form class="alcateia-settings alcateia-glass" method="post" action="options.php">
			<?php settings_fields( 'alcateia_labels_settings_group' ); ?>
			<h2><?php echo esc_html__( 'Configurações', 'emissao-de-etiquetas-alcateia' ); ?></h2>
			<div class="alcateia-settings-grid">
				<label><span><?php echo esc_html__( 'Nome do remetente', 'emissao-de-etiquetas-alcateia' ); ?></span><input type="text" name="<?php echo esc_attr( ALCATEIA_SETTINGS_OPTION ); ?>[sender_name]" value="<?php echo esc_attr( $settings['sender_name'] ); ?>"></label>
				<label><span><?php echo esc_html__( 'E-mail do remetente', 'emissao-de-etiquetas-alcateia' ); ?></span><input type="email" name="<?php echo esc_attr( ALCATEIA_SETTINGS_OPTION ); ?>[sender_email]" value="<?php echo esc_attr( $settings['sender_email'] ); ?>"></label>
				<label class="alcateia-settings-wide"><span><?php echo esc_html__( 'Mensagem padrão de rastreio', 'emissao-de-etiquetas-alcateia' ); ?></span><textarea name="<?php echo esc_attr( ALCATEIA_SETTINGS_OPTION ); ?>[default_message]" rows="3"><?php echo esc_textarea( $settings['default_message'] ); ?></textarea></label>
				<label><span><?php echo esc_html__( 'Texto do rodapé do e-mail', 'emissao-de-etiquetas-alcateia' ); ?></span><input type="text" name="<?php echo esc_attr( ALCATEIA_SETTINGS_OPTION ); ?>[email_footer]" value="<?php echo esc_attr( $settings['email_footer'] ); ?>"></label>
				<label><span><?php echo esc_html__( 'Envio automático de rastreio', 'emissao-de-etiquetas-alcateia' ); ?></span><select name="<?php echo esc_attr( ALCATEIA_SETTINGS_OPTION ); ?>[auto_send]"><option value="no" <?php selected( $settings['auto_send'], 'no' ); ?>><?php echo esc_html__( 'Desativado', 'emissao-de-etiquetas-alcateia' ); ?></option><option value="yes" <?php selected( $settings['auto_send'], 'yes' ); ?>><?php echo esc_html__( 'Ativado', 'emissao-de-etiquetas-alcateia' ); ?></option></select></label>
				<label><span><?php echo esc_html__( 'Status que dispara envio automático', 'emissao-de-etiquetas-alcateia' ); ?></span><select name="<?php echo esc_attr( ALCATEIA_SETTINGS_OPTION ); ?>[auto_send_status]">
					<?php foreach ( $statuses as $status_key => $status_label ) : $status_value = str_replace( 'wc-', '', $status_key ); ?>
						<option value="<?php echo esc_attr( $status_value ); ?>" <?php selected( $settings['auto_send_status'], $status_value ); ?>><?php echo esc_html( $status_label ); ?></option>
					<?php endforeach; ?>
				</select></label>
			</div>
			<?php submit_button( __( 'Salvar configurações', 'emissao-de-etiquetas-alcateia' ), 'primary alcateia-save-button' ); ?>
		</form>
		<?php
	}
}
