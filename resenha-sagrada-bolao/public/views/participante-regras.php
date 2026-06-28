<?php if (!defined('ABSPATH')) exit; ?>
<div class="rsb-public rsb-section"><h3>Regras do Bolão</h3>
<ul>
<li><strong>Formato Copa 2026:</strong> 48 seleções, 12 grupos de 4 equipes, 2 primeiros de cada grupo + 8 melhores terceiros avançam.</li>
<li><strong>Mata-mata:</strong> começa na fase de 16-avos de final / Rodada de 32, depois oitavas, quartas, semifinal, 3º lugar e final.</li>
<li><strong>Prazo dos palpites:</strong> cada jogo bloqueia 1 hora antes do horário oficial da partida, pelo horário de Brasília.</li>
<li><strong>Sem palpite no prazo:</strong> quem deixar vazio perde o direito daquele jogo; não existe inclusão depois.</li>
<li><strong>Edição:</strong> o mau elemento pode editar o próprio palpite apenas enquanto o jogo estiver aberto.</li>
<li><strong>Pontuação:</strong> placar exato vale <?php echo esc_html($bolao->pontos_placar_exato ?? 3); ?> pontos; resultado correto vale <?php echo esc_html($bolao->pontos_resultado_correto ?? 1); ?> ponto; erro vale 0.</li>
<li><strong>Auditoria:</strong> toda criação e edição de palpite registra data, hora, mau elemento, usuário, IP e navegador.</li>
</ul></div>
