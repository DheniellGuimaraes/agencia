<?php
if (!defined('ABSPATH')) exit;
$fmt = class_exists('RSB_WorldCup_Format') ? RSB_WorldCup_Format::format_summary() : [];
$rsb_current_url = esc_url_raw(remove_query_arg(['rsb_auth'], home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''))));
$logout_url = wp_logout_url($rsb_current_url ?: home_url('/'));
$rsb_nav_icon = function(string $name): string {
    $icons = [
        'palpites' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.2 16.7 6v5.3L12 14.8 7.3 11.3V6L12 3.2Z"/><path d="m7.3 6 4.7 3.1L16.7 6M12 9.1v5.7"/><path d="M4.4 8.1 7.3 6M19.6 8.1 16.7 6M5.6 17.1l6.4 3.7 6.4-3.7M5.6 17.1V9.4M18.4 17.1V9.4"/></svg>',
        'meus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4.5h8.8l3.2 3.2v11.8H6Z"/><path d="M14.8 4.5v3.2H18M8.8 13.5l5.9-5.9 1.9 1.9-5.9 5.9-2.4.5Z"/><path d="M8.8 18h7.4"/></svg>',
        'ranking' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10v3.2a5 5 0 0 1-10 0Z"/><path d="M7 5.4H4.7v2.1A3.4 3.4 0 0 0 8 10.9M17 5.4h2.3v2.1a3.4 3.4 0 0 1-3.3 3.4"/><path d="M12 12.2v4.1M8.6 20h6.8M10 16.3h4"/></svg>',
        'premiacao' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 9.5c0-2.5 2.3-4.3 5.5-4.3s5.5 1.8 5.5 4.3-2.3 4.3-5.5 4.3-5.5-1.8-5.5-4.3Z"/><path d="M8.2 13.1v4.5c0 1.4 1.7 2.4 3.8 2.4s3.8-1 3.8-2.4v-4.5"/><path d="M12 7.1v5M13.7 8.3c-.4-.6-1-.9-1.8-.9-.9 0-1.6.5-1.6 1.2 0 .8.8 1 1.8 1.2 1 .2 1.8.5 1.8 1.3 0 .7-.7 1.2-1.7 1.2-.9 0-1.6-.3-2.1-1"/></svg>',
        'regras' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5h10a1.5 1.5 0 0 1 1.5 1.5v14L16 18.3 14 20l-2-1.7-2 1.7-2-1.7-2.5 1.7V6A1.5 1.5 0 0 1 7 4.5Z"/><path d="M8.5 8.2h7M8.5 11.5h7M8.5 14.8h4.5"/></svg>',
    ];
    return $icons[$name] ?? '';
};
?>
<div class="rsb-public rsb-app <?php echo !is_user_logged_in() ? 'rsb-guest' : 'rsb-auth'; ?>">
    <header class="rsb-world-header">
        <a class="rsb-header-logo" href="#rsb-palpites" aria-label="Resenha Sagrada - Bolao da Copa 26">
            <img src="https://www.resenhasagrada.com.br/wp-content/uploads/2026/06/resenha.png" alt="Resenha Sagrada">
        </a>
        <nav class="rsb-top-nav">
            <a class="rsb-nav-item rsb-nav-palpites" href="#rsb-palpites"><span class="rsb-nav-icon"><?php echo $rsb_nav_icon('palpites'); ?></span><span>Palpites</span></a>
            <a class="rsb-nav-item rsb-nav-meus" href="#rsb-meus"><span class="rsb-nav-icon"><?php echo $rsb_nav_icon('meus'); ?></span><span>Meus Palpites</span></a>
            <a class="rsb-nav-item rsb-nav-ranking" href="#rsb-ranking"><span class="rsb-nav-icon"><?php echo $rsb_nav_icon('ranking'); ?></span><span>Ranking</span></a>
            <a class="rsb-nav-item rsb-nav-premiacao" href="#rsb-premiacao"><span class="rsb-nav-icon"><?php echo $rsb_nav_icon('premiacao'); ?></span><span>Premia&ccedil;&atilde;o</span></a>
            <a class="rsb-nav-item rsb-nav-regras" href="#rsb-regras"><span class="rsb-nav-icon"><?php echo $rsb_nav_icon('regras'); ?></span><span>Regras</span></a>
            <?php if(is_user_logged_in()): ?>
                <a class="rsb-nav-item rsb-logout-link" href="<?php echo esc_url($logout_url); ?>" aria-label="Sair do bolao"><span class="rsb-nav-icon rsb-logout-icon" aria-hidden="true"></span><span>Sair</span></a>
            <?php endif; ?>
        </nav>
    </header>

    <section class="rsb-copa-hero">
        <div>
            <span class="rsb-kicker">Copa do Mundo 2026</span>
            <h2><?php echo esc_html($bolao->nome ?? 'Resenha Sagrada - Bolao da Copa 26'); ?></h2>
            <p>48 selecoes, 12 grupos, rodada de 32 e disputa ate a grande final. Faca seus palpites com antecedencia: o sistema bloqueia cada jogo 1h antes da partida.</p>
            <div class="rsb-hero-stats">
                <span><strong>104</strong> Jogos</span>
                <span><strong><?php echo esc_html($fmt['selecoes'] ?? 48); ?></strong> Selecoes</span>
                <span><strong><?php echo esc_html($fmt['grupos'] ?? 12); ?></strong> Grupos</span>
                <span><strong>32</strong> Mata-mata</span>
            </div>
        </div>
        <div class="rsb-host-flags">
            <span><?php echo rsb_flag_html('Estados Unidos'); ?><small>EUA</small></span>
            <span><?php echo rsb_flag_html('Canada'); ?><small>Canada</small></span>
            <span><?php echo rsb_flag_html('Mexico'); ?><small>Mexico</small></span>
        </div>
    </section>

    <?php if(!is_user_logged_in()): ?>
        <section class="rsb-mobile-start rsb-legacy-home" aria-label="Acesso ao bolao">
            <div class="rsb-legacy-home-bg-ball" aria-hidden="true"></div>
            <div class="rsb-mobile-logo">
                <img class="rsb-home-logo-img" src="https://www.resenhasagrada.com.br/wp-content/uploads/2026/06/resenha.png" alt="Resenha Sagrada">
                <img class="rsb-home-ball-img" src="https://www.resenhasagrada.com.br/wp-content/uploads/2026/06/cropped-bola.png" alt="Bola de futebol">
                <small>Bolao da Copa 26</small><em class="rsb-mobile-cup-tag">Brasil na resenha - Copa 2026</em>
                <span class="rsb-guest-badge">Bolao da Copa 26</span>
                <h1 class="rsb-guest-title">Entre na<br>Resenha</h1>
                <p class="rsb-guest-copy">Crie sua conta ou acesse para palpitar, acompanhar seus jogos, conferir o ranking e disputar a premiacao.</p>
            </div>
            <div class="rsb-mobile-actions">
                <a class="button rsb-create-account rsb-show-register" href="#rsb-register-form">Criar conta</a>
                <a class="button rsb-login rsb-show-login" href="#rsb-login-form">Entrar</a>
            </div>
        </section>
        <div class="rsb-card rsb-auth-card rsb-login-card" id="rsb-login-form">
            <h3>Entrar no bolao</h3>
            <p>Entre sem sair desta pagina para registrar e editar seus palpites ate 1 hora antes de cada jogo, pelo horario de Brasilia.</p>
            <form class="rsb-plugin-login-form">
                <label>E-mail ou usuario<input type="text" name="login" autocomplete="username" required></label>
                <label>Senha<input type="password" name="senha" autocomplete="current-password" required></label>
                <button type="submit" class="button">Entrar</button>
            </form>
            <p class="rsb-auth-links">Ainda nao e mau elemento? <a class="rsb-show-register" href="#rsb-register-form">Criar conta</a></p>
        </div>
        <div class="rsb-card rsb-auth-card rsb-register-card" id="rsb-register-form">
            <h3>Criar conta do mau elemento no bolao</h3>
            <p>Cadastro do mau elemento feito pelo proprio plugin. Apos criar a conta, voce entra automaticamente.</p>
            <form class="rsb-plugin-register-form">
                <label>Nome completo do mau elemento<input type="text" name="nome" minlength="3" autocomplete="name" required></label>
                <label>E-mail<input type="email" name="email" autocomplete="email" required></label>
                <label>Telefone / WhatsApp<input type="text" name="telefone" autocomplete="tel"></label>
                <label>Senha<input type="password" name="senha" minlength="6" autocomplete="new-password" required></label>
                <label>Confirmar senha<input type="password" name="senha2" minlength="6" autocomplete="new-password" required></label>
                <button type="submit" class="button rsb-create-account">Criar conta e entrar</button>
            </form>
            <p class="rsb-auth-links">Ja tenho conta. <a class="rsb-show-login" href="#rsb-login-form">Entrar</a></p>
        </div>
    <?php elseif(!$participant): ?>
        <div class="rsb-card rsb-alert">Seu usuario WordPress ainda nao esta vinculado a um mau elemento ativo deste bolao. Peca ao administrador para vincular seu cadastro.</div>
        <?php echo do_shortcode('[resenha_sagrada_ranking]'); ?>
    <?php else: ?>
        <div class="rsb-grid rsb-grid-4">
            <div class="rsb-card"><span>Status</span><strong><?php echo esc_html(ucfirst($participant->status_pagamento)); ?></strong></div>
            <div class="rsb-card"><span>Valor pago</span><strong><?php echo esc_html(rsb_money((float)$participant->valor_pago)); ?></strong></div>
            <div class="rsb-card"><span>Mau elemento</span><strong><?php echo esc_html($participant->nome); ?></strong></div>
            <div class="rsb-card"><span>Premiacao atual</span><strong><?php echo esc_html(rsb_money(((float)($premiacao['primeiro'] ?? 0)) + ((float)($premiacao['segundo'] ?? 0)) + ((float)($premiacao['terceiro'] ?? 0)))); ?></strong></div>
        </div>

        <section class="rsb-card rsb-profile-panel">
            <h3>Dados do Mau Elemento</h3>
            <form class="rsb-profile-form">
                <label>Nome do mau elemento no ranking<input type="text" name="nome" value="<?php echo esc_attr($participant->nome); ?>" required minlength="3"></label>
                <label>Telefone<input type="text" name="telefone" value="<?php echo esc_attr($participant->telefone); ?>"></label>
                <button type="submit" class="button">Salvar dados do mau elemento</button>
            </form>
            <small>O e-mail e o status de pagamento do mau elemento so podem ser alterados pelo administrador.</small>
        </section>

        <section id="rsb-palpites"><?php echo do_shortcode('[resenha_sagrada_palpites]'); ?></section>
        <section id="rsb-meus"><?php echo do_shortcode('[resenha_sagrada_meus_palpites]'); ?></section>
        <section id="rsb-ranking"><?php echo do_shortcode('[resenha_sagrada_ranking]'); ?></section>
        <section id="rsb-premiacao"><?php echo do_shortcode('[resenha_sagrada_premiacao]'); ?></section>
        <section id="rsb-regras"><?php echo do_shortcode('[resenha_sagrada_regras]'); ?></section>
    <?php endif; ?>

    <footer class="rsb-world-footer">
        <div><strong>Resenha Sagrada</strong><span>Aqui a resenha e sagrada, mas a disputa e seria.</span></div>
        <div class="rsb-footer-flags"><?php echo rsb_flag_html('Brasil'); ?><?php echo rsb_flag_html('Argentina'); ?><?php echo rsb_flag_html('Franca'); ?><?php echo rsb_flag_html('Alemanha'); ?><?php echo rsb_flag_html('Espanha'); ?></div>
        <div><small>Formato 2026: 2 primeiros de cada grupo + 8 melhores terceiros.</small></div>
    </footer>
</div>
