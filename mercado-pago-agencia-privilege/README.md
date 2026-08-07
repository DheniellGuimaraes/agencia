# Mercado Pago Agência Privilege

Versão: 3.6.1

Plugin WordPress/WooCommerce para integrar produtos, estoque, pedidos e webhooks com Mercado Livre, com painel administrativo glassmorphism preto, verde `#00e400` e fundo branco.

## Recursos incluídos

- OAuth 2.0 Authorization Code com PKCE opcional no modo manual.
- Modo **Privilege Connect** para conexão sem digitar Client ID/Client Secret no WordPress, usando um broker OAuth seguro semelhante ao fluxo usado por ERPs/conectores como Tiny/Bling.
- Tokens e Client Secret criptografados com AES-256-CBC quando OpenSSL está disponível.
- Wrapper da API Mercado Livre com `Authorization: Bearer` no modo manual e proxy seguro via broker no modo Connect.
- Renovação automática de token no modo manual; no modo Connect o broker gerencia tokens Mercado Livre.
- Sincronização de produtos simples WooCommerce para Mercado Livre.
- Estrutura inicial para produtos variáveis.
- Sincronização de estoque em lote.
- Metabox no produto WooCommerce.
- REST webhook em `/wp-json/mpap/v1/notifications`.
- Importação de pedidos Mercado Livre para WooCommerce.
- Logs profissionais, dashboard, webhooks, pedidos, produtos, configurações e diagnóstico de conexão.
- Aba **Categorias ML** para buscar o ID correto da categoria Mercado Livre usando o preditor oficial via API autenticada.
- Compatibilidade declarada com HPOS do WooCommerce.

## Modos de conexão

### 1. Privilege Connect — sem credenciais no WordPress

Este é o modo recomendado para operação comercial. O WordPress redireciona o usuário para um broker OAuth da Agência Privilege. O broker guarda o `Client Secret`, faz a troca do `code` por `access_token`/`refresh_token` e devolve ao WordPress apenas um `connection_token`.

Fluxo:

1. Configure `URL do broker Privilege Connect`.
2. Selecione `Privilege Connect: sem digitar credenciais`.
3. Clique em `Conectar sem credenciais`.
4. Autorize no Mercado Livre.
5. O broker retorna ao WordPress com `install_code`.
6. O plugin troca `install_code` por `connection_token`.
7. Chamadas autenticadas ao Mercado Livre são feitas via `/v1/ml/proxy` do broker.

### 2. Manual — credenciais locais

Use quando quiser cadastrar uma aplicação Mercado Livre específica para o site.

1. Informe `Client ID / App ID Mercado Livre` e `Client Secret`.
2. Copie a `Redirect URI exata` para o app no Mercado Livre Developers.
3. Clique em `Conectar manualmente`.

## URLs importantes

- OAuth callback manual: exibido em **Configurações > Redirect URI exata para aplicação manual**.
- Callback Connect: exibido em **Configurações > Callback do broker para voltar ao WordPress**.
- Webhook: exibido em **Configurações > Webhook URL**.

## Atenção antes de produção

Valide payloads obrigatórios por categoria, política de imagens, variações, logística, garantia, regras fiscais e permissões da aplicação Mercado Livre. Use ambiente de staging e conta de teste antes de anunciar produtos reais.

## 3.6.0 — Buscador de Categoria ML

- Adicionada a aba **Categorias ML** ao painel do plugin.
- O plugin consulta o preditor de categorias com termos como `template wordpress elementor` e retorna sugestões com `category_id` no formato `MLB...`.
- Cada sugestão mostra caminho da categoria, domínio, atributos obrigatórios e tipos de anúncio aceitos, quando a API retorna esses dados.
- Botão **Usar como categoria padrão** grava o ID na configuração `Categoria padrão ML`.
- A tela Configurações agora tem um mini buscador para descobrir e aplicar categoria sem sair da página.

## 3.3.0 — Diagnóstico OAuth aprimorado

- Hotfix do teste público: `PolicyAgent 403` agora é registrado somente em `diagnostics` como aviso, sem gerar `http_api_error` na fonte `api`.
- Adicionada orientação clara quando o OAuth está `idle`: credenciais prontas, mas conexão ainda não iniciada pelo botão.
- Botões de diagnóstico agora permitem iniciar OAuth com ou sem PKCE diretamente pela tela Diagnóstico.
- Adicionado status persistente de OAuth: iniciado, callback recebido, troca de token, erro, conectado e desconectado.
- Adicionado botão **Validar OAuth Local** para conferir Client ID, Client Secret, Redirect URI e URL de autorização antes de abrir o Mercado Livre.
- Adicionado botão **Conectar sem PKCE** para diagnóstico quando a autorização para antes do callback.
- O diagnóstico não marca mais “Token OAuth ausente” como erro crítico antes da conexão; agora é aviso quando as credenciais estão prontas.
- O teste de API pública foi reclassificado como teste opcional de conectividade. Bloqueios `PolicyAgent` são registrados como aviso, não como falha de OAuth.
- Logs OAuth agora registram status, headers sanitizados, request_id, state hash, Redirect URI e resposta completa sanitizada da troca de token.


### 3.3.0
- Altera o Redirect URI manual para endpoint REST limpo: `/wp-json/mpap/v1/oauth/callback`.
- Mantém o callback admin-post antigo apenas como rota legada, mas recomenda cadastrar a URL limpa no Mercado Livre Developers.
- Permite receber o retorno OAuth sem depender de `action=mpap_oauth_callback` no Redirect URI cadastrado.


## 3.6.0 - Atributos obrigatórios para software

- Preenche automaticamente os atributos obrigatórios DEVELOPER, VERSION e SOFTWARE_NAME quando a categoria Mercado Livre exigir dados de software.
- Extrai versão do título do produto quando encontrar padrões como v2.31.0.
- Usa o nome do site como desenvolvedor padrão quando o produto não informar desenvolvedor.
- Mantém logs detalhados quando o Mercado Livre retornar erro de validação.

## 3.6.1 - Diagnóstico de payload

Esta versão grava o evento `product_payload_ready` antes do envio para o Mercado Livre, exibindo `plugin_version`, `attribute_ids` e uma prévia dos atributos. Se o log HTTP ainda mostrar `User-Agent: MPAP/3.5.0` ou `attributes: []`, o WordPress ainda está executando uma versão antiga do plugin.
