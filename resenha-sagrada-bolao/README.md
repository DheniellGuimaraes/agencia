# Resenha Sagrada - Bolao da Copa 26

Plugin WordPress para gerenciar o Bolao da Copa 2026 com cadastro/login proprio, participantes, pagamentos, jogos oficiais, palpites, ranking, premiacao e logs.

Versao atual: **2026.11**

## Instalacao e atualizacao

1. Envie a pasta `resenha-sagrada-bolao` para `wp-content/plugins/` ou instale o ZIP pelo painel do WordPress.
2. Ative o plugin em **Plugins**.
3. Acesse **Resenha Sagrada** no menu administrativo.
4. Configure o bolao e, se necessario, use **Importar Copa 2026** e **Importar Paginas**.
5. Ao atualizar de uma versao anterior, limpe cache de pagina/CDN/minificacao para carregar `assets/css/public.css` e `assets/js/public.js` novos.

## Shortcodes preservados

- `[resenha_sagrada_participante]` ou `[resenha_sagrada_app]` - area completa do mau elemento.
- `[resenha_sagrada_palpites]` - jogos abertos para registrar ou editar palpites.
- `[resenha_sagrada_meus_palpites]` - historico individual de palpites e pontos.
- `[resenha_sagrada_jogos]` - calendario de jogos.
- `[resenha_sagrada_ranking]` - ranking geral.
- `[resenha_sagrada_premiacao]` - premiacao estimada.
- `[resenha_sagrada_pagamento]` - status de pagamento do participante logado.
- `[resenha_sagrada_regras]` - regras do bolao.

## Regras de palpites

- O mau elemento pode criar ou editar palpites somente ate 1 hora antes da partida, no horario de Brasilia.
- Se nao palpitar antes do prazo, perdeu o direito daquele jogo.
- A validacao e feita no servidor.
- Placar vazio, negativo, texto ou decimal nao e aceito.
- Cada mau elemento tem apenas um palpite por jogo.
- Toda criacao/edicao gera log com usuario, participante, jogo, IP, user agent e valores antigos/novos.
- Resultado oficial so pontua quando o jogo estiver `finalizado`.

## Premiacao

A premiacao nao deve ser exibida em duplicidade. Em caso de empate na mesma posicao, o sistema soma os premios das posicoes ocupadas e divide igualmente entre os empatados.

## Copa 2026

Fases padronizadas:

- `grupos` - Fase de Grupos.
- `rodada_32` - 16-avos de Final / Rodada de 32.
- `oitavas` - Oitavas de Final.
- `quartas` - Quartas de Final.
- `semifinal` - Semifinal.
- `terceiro_lugar` - Disputa de 3o Lugar.
- `final` - Final.

Formato:

- 48 selecoes.
- 12 grupos com 4 selecoes.
- 2 primeiros de cada grupo classificam.
- 8 melhores terceiros classificam.
- 32 classificados entram na Rodada de 32.
- Depois seguem oitavas, quartas, semifinais, terceiro lugar e final.

## Login e cadastro

O cadastro e o login publicos continuam internos do plugin por AJAX. O mau elemento nao deve ser enviado para `wp-login.php`, `wp-admin` ou `admin-ajax.php` como tela final.

## Icones das selecoes

A versao 2026.7 adiciona `RSB_Teams`, com mapeamento centralizado de selecoes, ISO, emoji/icone e fallback. Jogos, palpites e historico passam a renderizar icones circulares por `RSB_Teams::render_flag($team_name)`. Placeholders de mata-mata usam icone neutro circular.

## Relatorios e operacao premium

A versao 2026.8 adiciona exportacao em PDF por impressao do navegador na aba **Relatorios**, com identidade visual do plugin, logo oficial, cores verde/amarelo/azul, tabelas responsivas e opcoes para relatorio completo, dados/pagamentos, jogos, palpites e vencedores/premiacao.

Na aba **Palpites** do admin, agora ha filtros por participante, jogo especifico e busca livre por nome, email, fase ou selecao.

Na area publica de palpites, o mau elemento pode salvar cada placar sem recarregar a pagina, salvar todos os placares preenchidos com um clique e conta com autosave a cada 10 segundos para palpites completos e editaveis. A validacao final continua no servidor.

## Participantes

A partir da versao 2026.10, o bolao nao tem limite de maus elementos. O valor interno `limite_participantes=0` representa participantes ilimitados. Cadastros, pagamentos, palpites, ranking, premiacao e relatorios seguem usando as tabelas existentes e contagens dinamicas.

## Tabelas

- `wp_rs_temporadas`
- `wp_rs_boloes`
- `wp_rs_participantes`
- `wp_rs_jogos`
- `wp_rs_palpites`
- `wp_rs_pagamentos`
- `wp_rs_ranking`
- `wp_rs_logs`
- `wp_rs_palpite_logs`

## Versao 2026.10

- Removida a trava de 30 maus elementos no cadastro publico.
- Bolao passa a operar com `limite_participantes=0`, interpretado como sem limite.
- Adicionada rotina de upgrade para converter bolões existentes para sem limite sem apagar participantes, pagamentos, jogos, palpites ou ranking.
- Tela de configuracoes passa a exibir aviso claro de participantes ilimitados.
- Mantidas validacoes de e-mail unico, senha, vinculo do usuario ao participante, logs e fluxo interno de cadastro/login.
- Impacto revisado: relatorios, ranking, pagamentos e palpites usam contagens dinamicas e continuam funcionais com mais participantes.

## Versao 2026.9

- Corrigida a conferencia de palpites nos relatorios PDF para contar diretamente da tabela `wp_rs_palpites`.
- Adicionada conferencia por participante com total real de palpites, faltantes, pontos e ultimo envio.
- Adicionada conferencia por jogo com quantidade de palpites, faltantes entre participantes ativos, fechamento em Brasilia e status do bloqueio.
- Ranking e premiacao derivados sao recalculados antes da exportacao de relatorios.
- Ranking tambem passa a recalcular depois de criar/editar palpites, evitando contagem antiga em telas publicas e PDF.
- Reforcada a regra de fechamento 1h antes com auditoria explicita em `America/Sao_Paulo` e labels `BRT`.
- Mantidos os palpites ja cadastrados; nao ha exclusao nem reinstalacao de dados.

## Versao 2026.8

- Atualizado cabecalho do plugin e constante `RSB_VERSION`.
- Adicionada exportacao de relatorios em layout pronto para PDF/impressao, com logo, fontes, cores e visual Resenha Sagrada.
- Criados relatorios separados para completo, dados/pagamentos, jogos, palpites e vencedores/premiacao.
- Adicionados filtros administrativos de palpites por participante, jogo e busca livre.
- Palpites publicos agora salvam via AJAX sem reload.
- Adicionado botao "Salvar todos" para gravar todos os placares preenchidos com um clique.
- Adicionado autosave a cada 10 segundos para palpites completos, pendentes e ainda editaveis.
- Adicionados indicadores por jogo: pendente, salvando, salvo e erro.
- Mantida a validacao no servidor e preservadas todas as tabelas/dados existentes.

## Versao 2026.7

- Atualizado cabecalho do plugin e constante `RSB_VERSION`.
- Home publica mantem logo oficial e bola oficial com visual Copa/Brasil e animacao suave.
- Adicionado helper `RSB_Teams` para flags, metadados e placeholders.
- Adicionados aliases para nomes sem acento, variacoes comuns e nomes com codificacao quebrada, evitando selecoes sem bandeira.
- Adicionado link de sair no header logado, com icone visual de logout.
- Header logado redesenhado com logo oficial centralizado, menu em botoes glassmorphism ultra, icones SVG e faixas abstratas em verde, amarelo e azul.
- Home deslogada redesenhada no desktop e mobile com card central, logo grande, chamada "Entre na Resenha", botoes internos do plugin e bola oficial no canto inferior direito.
- Bola oficial da home reduzida e animada lateralmente para nao cobrir informacoes importantes no desktop ou mobile.
- Melhorada a responsividade de confrontos, inputs, botoes, tabelas e nomes longos.
- JS publico agora trata JSON invalido, erro de rede e retorno `0` do WordPress com mensagem clara.
- README atualizado com instalacao, shortcodes, regras, premiacao, Copa 2026, login/cadastro e cache.

## Changelog

### 2026.11

- Adicionada atualizacao automatica segura dos confrontos futuros do mata-mata (`rodada_32`, `oitavas`, `quartas`, `semifinal`, `terceiro_lugar` e `final`) a partir de jogos oficiais finalizados.
- Adicionada rotina administrativa para recalcular confrontos futuros sem alterar placares, palpites, ranking, pagamentos, participantes ou logs existentes.
- Adicionado filtro por jogo no relatorio de palpites, respeitado tambem pela impressao/PDF do navegador.
- Mantida a preservacao de IDs dos jogos, placares oficiais, palpites registrados, pagamentos, ranking e logs.
- Reforcada a blindagem para preservar selecoes preenchidas manualmente nos slots do mata-mata e nao alterar status, fase, data ou horario dos jogos durante o recálculo.
- O gatilho automatico ao salvar resultado agora propaga apenas para fases futuras dependentes do jogo alterado, sem reconstruir fases anteriores ou confrontos independentes.
- Adicionada aba administrativa **Atualizar confrontos** para recalcular manualmente o mata-mata apos o fim da fase de grupos, usando os resultados oficiais ja cadastrados.
- A aba **Atualizar confrontos** agora busca placares na API gratuita configurada (`https://worldcup26.ir/get/games` por padrao), importa apenas placares ainda vazios e depois recalcula ranking e chaveamento.
- Jogos futuros com status `aguardando_classificados` ficam liberados para palpite assim que as duas selecoes reais forem preenchidas pelo chaveamento automatico.
- Recalculo de pontuacao agora reprocessa todos os palpites contra todos os resultados oficiais finalizados antes de refazer o ranking.

