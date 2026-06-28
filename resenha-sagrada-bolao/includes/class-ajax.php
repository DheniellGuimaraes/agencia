<?php
if (!defined('ABSPATH')) { exit; }
class RSB_Ajax {

    private static function auth_redirect_url(): string {
        $posted = isset($_POST['current_url']) ? esc_url_raw(wp_unslash($_POST['current_url'])) : '';
        if ($posted && strpos($posted, 'admin-ajax.php') === false && strpos($posted, 'wp-admin') === false && wp_validate_redirect($posted, false)) {
            return remove_query_arg(['rsb_auth'], $posted);
        }
        $ref = wp_get_referer();
        if ($ref && strpos($ref, 'admin-ajax.php') === false && strpos($ref, 'wp-admin') === false && wp_validate_redirect($ref, false)) {
            return remove_query_arg(['rsb_auth'], $ref);
        }
        return home_url('/');
    }
    public static function register_ajax(): void {
        add_action('wp_ajax_rsb_recalculate',[__CLASS__,'ajax_recalculate']);
        add_action('wp_ajax_rsb_save_bet',[__CLASS__,'ajax_save_bet']);
        add_action('wp_ajax_nopriv_rsb_save_bet',[__CLASS__,'ajax_save_bet']);
        add_action('wp_ajax_rsb_update_profile',[__CLASS__,'ajax_update_profile']);
        add_action('wp_ajax_nopriv_rsb_plugin_register',[__CLASS__,'ajax_plugin_register']);
        add_action('wp_ajax_nopriv_rsb_plugin_login',[__CLASS__,'ajax_plugin_login']);
    }
    public static function ajax_recalculate(): void { check_ajax_referer('rsb_nonce','nonce'); if(!current_user_can(rsb_admin_cap())){wp_send_json_error('Acesso negado',403);} $bolao=RSB_Bolao::current(); if(!$bolao){wp_send_json_error('Bolão não encontrado');} $summary = RSB_Ranking::recalculate_all_scores((int)$bolao->id); wp_send_json_success(['message'=>'Pontuação e ranking recalculados: '.(int)$summary['palpites'].' palpites, '.(int)$summary['finalizados'].' jogos finalizados.']); }
    public static function ajax_save_bet(): void { check_ajax_referer('rsb_public_nonce','nonce'); if(!is_user_logged_in()){wp_send_json_error('Login obrigatório',401);} $bolao=RSB_Bolao::current(); if(!$bolao){wp_send_json_error('Bolão não encontrado');} global $wpdb; $part=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".rsb_table('participantes')." WHERE bolao_id=%d AND user_id=%d",(int)$bolao->id,get_current_user_id())); if(!$part){wp_send_json_error('Mau elemento não vinculado ao usuário logado',403);} $id=RSB_Palpites::upsert(['jogo_id'=>(int)$_POST['jogo_id'],'participante_id'=>(int)$part->id,'palpite_gols_mandante'=>isset($_POST['gols_mandante']) ? sanitize_text_field(wp_unslash($_POST['gols_mandante'])) : '','palpite_gols_visitante'=>isset($_POST['gols_visitante']) ? sanitize_text_field(wp_unslash($_POST['gols_visitante'])) : '']); if(is_wp_error($id)){wp_send_json_error($id->get_error_message());} if(!$id){wp_send_json_error('Não foi possível salvar.');} wp_send_json_success(['id'=>$id,'message'=>'Palpite salvo.','updated_at'=>current_time('mysql')]); }
    public static function ajax_update_profile(): void {
        check_ajax_referer('rsb_public_nonce','nonce');
        if(!is_user_logged_in()){wp_send_json_error('Login obrigatório',401);}
        $bolao=RSB_Bolao::current();
        if(!$bolao){wp_send_json_error('Bolão não encontrado');}
        global $wpdb;
        $part=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".rsb_table('participantes')." WHERE bolao_id=%d AND user_id=%d", (int)$bolao->id, get_current_user_id()));
        if(!$part){wp_send_json_error('Mau elemento não vinculado ao usuário logado',403);}
        $nome = isset($_POST['nome']) ? sanitize_text_field(wp_unslash($_POST['nome'])) : '';
        $telefone = isset($_POST['telefone']) ? sanitize_text_field(wp_unslash($_POST['telefone'])) : '';
        if(mb_strlen($nome) < 3){ wp_send_json_error('Informe um nome com pelo menos 3 caracteres.'); }
        $payload = ['nome'=>$nome, 'telefone'=>$telefone, 'updated_at'=>current_time('mysql')];
        $ok = false !== $wpdb->update(rsb_table('participantes'), $payload, ['id'=>(int)$part->id, 'user_id'=>get_current_user_id()]);
        if(!$ok){ wp_send_json_error('Não foi possível atualizar seus dados.'); }
        wp_update_user(['ID'=>get_current_user_id(), 'display_name'=>$nome]);
        rsb_log('atualizar_meus_dados','participante',(int)$part->id,$part,$payload);
        wp_send_json_success(['message'=>'Dados atualizados com sucesso.']);
    }


    public static function ajax_plugin_register(): void {
        check_ajax_referer('rsb_public_nonce','nonce');
        $bolao = RSB_Bolao::current();
        if (!$bolao) { wp_send_json_error('Bolão não encontrado.'); }

        $nome = isset($_POST['nome']) ? sanitize_text_field(wp_unslash($_POST['nome'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $telefone = isset($_POST['telefone']) ? sanitize_text_field(wp_unslash($_POST['telefone'])) : '';
        $senha = isset($_POST['senha']) ? (string)wp_unslash($_POST['senha']) : '';
        $senha2 = isset($_POST['senha2']) ? (string)wp_unslash($_POST['senha2']) : '';

        if (mb_strlen($nome) < 3) { wp_send_json_error('Informe o nome completo do mau elemento.'); }
        if (!is_email($email)) { wp_send_json_error('Informe um e-mail válido.'); }
        if (strlen($senha) < 6) { wp_send_json_error('A senha precisa ter pelo menos 6 caracteres.'); }
        if ($senha !== $senha2) { wp_send_json_error('As senhas não conferem.'); }
        if (email_exists($email)) { wp_send_json_error('Este e-mail já possui conta. Use o botão Entrar.'); }

        global $wpdb;

        $base_user = sanitize_user(current(explode('@', $email)), true);
        if (!$base_user) { $base_user = 'mau-elemento'; }
        $username = $base_user;
        $suffix = 1;
        while (username_exists($username)) { $username = $base_user . $suffix; $suffix++; }

        $user_id = wp_create_user($username, $senha, $email);
        if (is_wp_error($user_id)) { wp_send_json_error($user_id->get_error_message()); }
        wp_update_user(['ID'=>$user_id, 'display_name'=>$nome, 'first_name'=>$nome]);

        $now = current_time('mysql');
        $payload = [
            'bolao_id'=>(int)$bolao->id,
            'user_id'=>(int)$user_id,
            'nome'=>$nome,
            'email'=>$email,
            'telefone'=>$telefone,
            'status_pagamento'=>'pendente',
            'valor_pago'=>0,
            'ativo'=>1,
            'created_at'=>$now,
            'updated_at'=>$now,
        ];
        $ok = $wpdb->insert(rsb_table('participantes'), $payload);
        if (!$ok) {
            require_once ABSPATH.'wp-admin/includes/user.php';
            wp_delete_user($user_id);
            wp_send_json_error('Não foi possível criar sua inscrição no bolão.');
        }
        $participante_id = (int)$wpdb->insert_id;
        rsb_log('cadastro_publico','participante',$participante_id,null,$payload);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', $username, get_user_by('id', $user_id));

        wp_send_json_success(['message'=>'Conta criada com sucesso.','redirect'=>self::auth_redirect_url()]);
    }

    public static function ajax_plugin_login(): void {
        check_ajax_referer('rsb_public_nonce','nonce');
        $login = isset($_POST['login']) ? sanitize_text_field(wp_unslash($_POST['login'])) : '';
        $senha = isset($_POST['senha']) ? (string)wp_unslash($_POST['senha']) : '';
        if ($login === '' || $senha === '') { wp_send_json_error('Informe e-mail/usuário e senha.'); }
        if (is_email($login)) {
            $found = get_user_by('email', $login);
            if ($found) { $login = $found->user_login; }
        }
        $creds = ['user_login'=>$login, 'user_password'=>$senha, 'remember'=>true];
        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) { wp_send_json_error('Dados de acesso inválidos.'); }
        wp_send_json_success(['message'=>'Login realizado com sucesso.','redirect'=>self::auth_redirect_url()]);
    }

    public static function register_rest_routes(): void {
        register_rest_route('resenha/v1','/ranking',['methods'=>'GET','callback'=>[__CLASS__,'rest_ranking'],'permission_callback'=>'__return_true']);
        register_rest_route('resenha/v1','/jogos',['methods'=>'GET','callback'=>[__CLASS__,'rest_jogos'],'permission_callback'=>'__return_true']);
    }
    public static function rest_ranking(){ $bolao=RSB_Bolao::current(); return rest_ensure_response($bolao?RSB_Ranking::get((int)$bolao->id):[]); }
    public static function rest_jogos(){ $bolao=RSB_Bolao::current(); return rest_ensure_response($bolao?RSB_Jogos::all((int)$bolao->id):[]); }
}
