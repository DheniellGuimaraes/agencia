=== Privilege Semantic Indexer AI ===
Contributors: studioprivilege
Tags: seo, semantic, local seo, bold builder, yoast
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later

Plugin premium criado para o site https://www.studioprivilege.com.br/ com foco em enriquecer semanticamente páginas programáticas de criação de sites por profissão/cidade sem cloaking, conteúdo oculto ou alterações no layout principal.

== Instalação ==
1. Envie a pasta privilege-semantic-indexer-ai para wp-content/plugins/.
2. Ative o plugin no painel WordPress.
3. Acesse Privilege Semantic Indexer.
4. Clique em Ler sitemaps.
5. Rode uma simulação antes do processamento real.

== Uso seguro ==
- O plugin preserva IDs protegidos configurados no código.
- Antes de editar, cria backup do post_content e metas Yoast relevantes.
- O modo simulação permite pontuar páginas antes de publicar.
- O processamento em lote evita timeout e pode ser agendado por WP-Cron.
- Não promete indexação garantida; melhora a utilidade e diferenciação percebida.

== SASS ==
Arquivos fonte ficam em assets/scss/.
Compile com sua ferramenta preferida, por exemplo: sass assets/scss/frontend.scss assets/css/frontend.css e sass assets/scss/admin.scss assets/css/admin.css.

== Reversão ==
Use a tabela wp_psi_ai_backups para restaurar backups via método PSI_AI_Backup_Manager::restore(). Mantenha cópia do banco antes de processar em massa.

== Search Console ==
Após processar lotes pequenos, reenvie URLs para inspeção, acompanhe cobertura e compare páginas atualizadas com páginas pendentes antes de ampliar a escala.

== Módulos complementares ==
- Semantic Entity Generator: classifica profissão, categoria, subcategoria, objeções, provas, serviços e dores reais do nicho.
- Local Context Generator: cria contexto comercial por cidade sem inventar estatísticas, rankings, população ou dados econômicos.
- Unique Media Builder: gera SVG inline visível e único por página com hash de post, profissão, cidade e categoria.
- Category Intelligence: mapeia saúde, direito, moda/confecção, construção civil, educação, alimentação, beleza/estética, serviços técnicos e serviços locais.
- Similarity Guard: compara conteúdo novo com a página atual, enriquecimentos recentes e páginas relacionadas; acima de 65% tenta nova versão, acima de 80% bloqueia publicação automática, acima de 90% recomenda noindex/consolidação.
- Progressive Rollout: lote teste de 500, pequeno de 2.000, médio de 10.000, avançado personalizado e total com confirmação dupla.
- Search Console Validation Planner: registra datas sugeridas para inspeção, solicitação de indexação e reavaliações de 7, 15 e 30 dias.

== Teste antes de escalar ==
1. Importe os sitemaps.
2. Execute simulação em 10 URLs ou em lote teste.
3. Revise páginas marcadas como Revisão Manual ou Noindex/consolidar.
4. Publique primeiro até 500 páginas.
5. Aguarde 10 a 15 dias e compare cobertura, impressões e cliques no Search Console antes de lotes maiores.

== Privilege Visual Builder ==
O módulo includes/class-privilege-visual-builder.php transforma o enriquecimento em uma seção premium com classes isoladas .psi-visual*, hero com gradiente, robô/IA em SVG, cards de diagnóstico, contexto local editorial, dores em cards, fluxo visual, FAQ premium, links internos em cards e CTA duplo.

O builder possui 6 variações visuais: hero escuro com glass, clean premium, SaaS com fluxo, editorial minimalista, neon tecnológico e branco premium com formas orgânicas. A escolha usa categoria, post ID, cidade e profissão.

A aba Design do Enriquecimento permite ativar visual premium, escolher estilo predominante, ativar SVG exclusivo, robô/IA, cards internos e FAQ premium, pré-visualizar em desktop/tablet/mobile, testar em 10 URLs e exportar HTML preview.

Bloqueio visual: se o HTML final não tiver SVG, CTA, cards suficientes, estilo premium ou apresentar padrão de texto puro, o lote marca a página como Revisão visual obrigatória e bloqueia publicação automática.

== Execução resiliente anti-timeout ==
Após análise das recomendações sobre Cloudflare 524 e processamento em massa no WordPress, o plugin não executa importação, simulação ou publicação pesada diretamente no admin-post. As ações são enfileiradas em background via Action Scheduler quando disponível e, na ausência dele, por WP-Cron com eventos únicos.

O runner resiliente processa fatias curtas com trava transitória, orçamento de tempo, limite de memória e re-enfileiramento automático do restante. Isso evita requisições HTTP longas no wp-admin, reduz risco de erro 524 e permite continuar o lote até terminar.

Recomendação operacional: em sites com dezenas de milhares de URLs, usar Action Scheduler + WP-CLI para escoar filas grandes, por exemplo `wp action-scheduler run --hooks=psi_ai_resilient_process_queue --batch-size=25`, quando disponível no servidor.
