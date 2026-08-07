=== Mercado Pago Agência Privilege ===
Contributors: agenciaprivilege
Tags: woocommerce, mercado livre, marketplace, integração, produtos, estoque, pedidos
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 3.6.1
License: GPLv2 or later

Integra WooCommerce ao Mercado Livre para produtos, estoque, pedidos e webhooks, com painel glassmorphism, modo Connect sem credenciais e diagnóstico OAuth avançado.

== Description ==

Mercado Pago Agência Privilege é um plugin WordPress/WooCommerce para integração com Mercado Livre.

Recursos:

* Painel admin glassmorphism preto com verde #00e400.
* OAuth 2.0 manual com PKCE opcional.
* Modo Privilege Connect para conectar sem digitar Client ID/Client Secret no WordPress.
* Proxy seguro para chamadas autenticadas no modo Connect.
* Logs profissionais filtráveis e exportáveis.
* Diagnóstico de conexão.
* Buscador de categoria Mercado Livre com preditor, caminho, atributos obrigatórios e botão para aplicar categoria padrão.
* Sincronização de produtos e estoque.
* Importação básica de pedidos.
* Webhooks REST.
* Criptografia local de tokens e secrets no modo manual.
* Compatibilidade HPOS declarada.

== Installation ==

1. Gere o ZIP fora do controle de versão com `scripts/build-plugin-zip.sh` e instale `dist/mercado-pago-agencia-privilege.zip` em Plugins > Adicionar novo > Enviar plugin.
2. Ative o WooCommerce.
3. Acesse Mercado Pago Agência Privilege > Configurações.
4. Escolha o modo de conexão.

== Privilege Connect ==

Para conectar sem digitar credenciais, é necessário hospedar um broker OAuth que contenha o Client ID/Client Secret da aplicação Mercado Livre. O plugin conversa com esse broker através dos endpoints:

* GET /v1/mercadolivre/connect
* POST /v1/wordpress/exchange
* POST /v1/ml/proxy
* POST /v1/wordpress/disconnect

== Manual ==

No modo manual, informe Client ID/App ID e Client Secret da aplicação Mercado Livre Developers, copie a Redirect URI exata e conecte a conta.

== Changelog ==
= 3.6.1 =
* Hotfix de diagnóstico: grava product_payload_ready antes de enviar para o Mercado Livre.
* Confirma no log os atributos DEVELOPER, VERSION e SOFTWARE_NAME gerados pelo plugin.
* Facilita detectar quando o site ainda está executando versão antiga, como 3.5.0, que enviava attributes vazio.


= 3.6.0 =
* Adicionada aba Categorias ML.
* Consulta autenticada ao preditor de categorias do Mercado Livre por título de produto.
* Exibição do ID MLB, caminho completo, domínio, atributos obrigatórios e tipos de anúncio aceitos.
* Botão para aplicar a categoria sugerida como categoria padrão do plugin.
* Mini buscador de categoria dentro da tela Configurações.

= 3.3.0 =
* Hotfix do teste público: PolicyAgent 403 vira aviso em diagnostics e não gera http_api_error em api.
* Diagnóstico mostra ação pendente quando OAuth está idle com credenciais prontas.
* Tela Diagnóstico inclui links para iniciar OAuth com e sem PKCE.
* Status persistente de OAuth: iniciado, callback, troca de token, erro e conexão.
* Novo botão Validar OAuth Local.
* Novo botão Conectar sem PKCE para diagnóstico.
* Teste de API pública reclassificado como conectividade opcional; PolicyAgent vira aviso.
* Logs OAuth com response headers sanitizados, request_id e state hash.
* Token OAuth ausente antes da conexão vira aviso quando as credenciais estão prontas.


= 3.0.1 =
* Adicionado modo Privilege Connect.
* Adicionado proxy gerenciado para chamadas autenticadas.
* Melhorias nos logs, diagnóstico e painel de configuração.

= 2.0.0 =
* Logs profissionais, diagnóstico e estrutura modular.

= 1.0.0 =
* Versão inicial.


### 3.3.0
- Altera o Redirect URI manual para endpoint REST limpo: `/wp-json/mpap/v1/oauth/callback`.
- Mantém o callback admin-post antigo apenas como rota legada, mas recomenda cadastrar a URL limpa no Mercado Livre Developers.
- Permite receber o retorno OAuth sem depender de `action=mpap_oauth_callback` no Redirect URI cadastrado.


## 3.6.0 - Atributos obrigatórios para software

- Preenche automaticamente os atributos obrigatórios DEVELOPER, VERSION e SOFTWARE_NAME quando a categoria Mercado Livre exigir dados de software.
- Extrai versão do título do produto quando encontrar padrões como v2.31.0.
- Usa o nome do site como desenvolvedor padrão quando o produto não informar desenvolvedor.
- Mantém logs detalhados quando o Mercado Livre retornar erro de validação.
