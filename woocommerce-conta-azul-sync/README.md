# WooCommerce Conta Azul Sync

Plugin WordPress/WooCommerce para sincronizar pedidos, clientes, produtos e contas a receber com a **Conta Azul Pro** usando a **nova API Conta Azul** e OAuth2 Authorization Code com `refresh_token`.

A Conta Azul informou que lançou uma nova versão das APIs em março de 2025. Este plugin foi preparado para a nova API (`auth.contaazul.com` e `api-v2.contaazul.com`) e **não usa a documentação/API antiga**.

## Status de produção

O plugin está pronto para ser compactado em `.zip`, instalado e ativado no WordPress. Os endpoints OAuth2 e a base da API estão centralizados e configuráveis. Alguns paths de recursos de negócio ainda devem ser validados no Portal do Desenvolvedor antes de produção, pois a documentação pública pode mudar ou exigir formatos específicos por recurso.

## Requisitos

- PHP 8.1 ou superior
- WordPress atual
- WooCommerce atual
- Conta Azul Pro
- Aplicação criada no novo Portal do Desenvolvedor Conta Azul

## Estrutura de arquivos

```text
woocommerce-conta-azul-sync/
├── woocommerce-conta-azul-sync.php
├── includes/
│   ├── class-wcas-plugin.php
│   ├── class-wcas-admin.php
│   ├── class-wcas-auth.php
│   ├── class-wcas-api-client.php
│   ├── class-wcas-orders.php
│   ├── class-wcas-customers.php
│   ├── class-wcas-products.php
│   ├── class-wcas-logger.php
│   └── class-wcas-utils.php
├── assets/
│   ├── admin.css
│   └── admin.js
└── README.md
```

## Instalação

### Instalação manual por pasta

1. Copie a pasta `woocommerce-conta-azul-sync` para `wp-content/plugins/`.
2. Acesse **Plugins** no painel do WordPress.
3. Ative **WooCommerce Conta Azul Sync**.
4. Acesse **WooCommerce > Conta Azul**.

### Instalação por ZIP

1. Compacte a pasta do plugin:

   ```bash
   zip -r woocommerce-conta-azul-sync.zip woocommerce-conta-azul-sync
   ```

2. No WordPress, acesse **Plugins > Adicionar novo > Enviar plugin**.
3. Envie `woocommerce-conta-azul-sync.zip`.
4. Ative o plugin.
5. Acesse **WooCommerce > Conta Azul**.

O plugin deve ativar sem conexão OAuth. A conexão com Conta Azul só é exigida para chamadas de sincronização.

## Configuração no Portal do Desenvolvedor Conta Azul

1. Acesse o novo Portal do Desenvolvedor: <https://developers-portal.contaazul.com>.
2. Crie uma conta de desenvolvedor ou faça login.
3. Crie uma aplicação de **Desenvolvimento** ou **Produção**.
4. Copie o `client_id` e o `client_secret`.
5. Cadastre a **Redirect URI** exatamente igual ao campo **URL para cadastrar na Conta Azul** mostrado no plugin em **WooCommerce > Conta Azul > Logs / Diagnóstico > OAuth Endpoints**.
6. A URL deve usar o callback dedicado: `https://seu-dominio/wp-admin/admin-post.php?action=wcas_oauth_callback`. Não cadastre `wp-admin/admin.php?page=wcas-conta-azul` como callback OAuth.
7. Para desenvolvimento, use a conta teste disponibilizada pela Conta Azul quando aplicável.

## Configuração no WordPress

Em **WooCommerce > Conta Azul**, preencha:

- Ativar integração
- Client ID
- Client Secret
- Redirect URI, preenchida automaticamente pelo callback dedicado `admin-post.php?action=wcas_oauth_callback`
- Ambiente
- Sincronizar automaticamente pedidos
- Sincronizar produtos
- Criar cliente automaticamente
- Registrar pedido como venda/receita
- Registrar cobrança/conta a receber
- Log detalhado
- URLs OAuth/API e paths de recursos

O `Client Secret`, `access_token` e `refresh_token` não são exibidos na interface. Quando OpenSSL está disponível, esses valores são armazenados criptografados usando salts do WordPress; caso contrário, continuam protegidos por permissões do banco/WordPress e nunca são renderizados no admin.

## OAuth2

Endpoints padrão centralizados:

- Authorization URL: `https://auth.contaazul.com/login`
- Token URL: `https://auth.contaazul.com/oauth2/token`
- API Base URL: `https://api-v2.contaazul.com/v1`

Fluxo implementado:

1. O admin clica em **Conectar Conta Azul**.
2. O plugin gera um `state` aleatório e temporário para proteção CSRF.
3. O usuário é redirecionado para a Conta Azul.
4. A Conta Azul retorna `code` e `state` para `wp-admin/admin-post.php?action=wcas_oauth_callback`.
5. O hook `admin_post_wcas_oauth_callback` chama `WCAS_Auth::handle_admin_post_callback()`, valida o `state` e troca o `code` por `access_token` e `refresh_token`.
6. O plugin renova o token automaticamente antes da expiração.
7. Em HTTP 401, o client tenta renovar uma vez e reexecutar a chamada.

## Endpoints de recursos

Os paths de recursos ficam em **WooCommerce > Conta Azul > Endpoints configuráveis da nova API**.

Defaults atuais, mantidos como placeholders configuráveis:

- `/pessoas`
- `/produtos`
- `/vendas`
- `/vendas/{id}/cancelar`
- `/financeiro/eventos-financeiros/contas-a-receber`

**TODO obrigatório antes de produção:** validar no Portal do Desenvolvedor Conta Azul os paths, campos obrigatórios e formatos finais de clientes, produtos, vendas e contas a receber. Não trate esses placeholders como definitivos sem validação oficial.

## Eventos sincronizados

Hooks WooCommerce usados:

- `woocommerce_order_status_processing`
- `woocommerce_order_status_completed`
- `woocommerce_order_status_cancelled`
- `woocommerce_refund_created`

Quando um pedido entra em `processing` ou `completed`, o plugin pode:

1. Buscar/criar cliente por CPF/CNPJ ou e-mail.
2. Buscar/criar produtos por SKU/external ID.
3. Criar venda/receita na Conta Azul.
4. Criar conta a receber, se habilitado.
5. Salvar metadados de sincronização no pedido.

Metadados usados:

- Pedido: `_wcas_customer_id`, `_wcas_sale_id`, `_wcas_receivable_id`, `_wcas_sync_status`, `_wcas_last_sync_at`, `_wcas_last_error`
- Produto: `_wcas_product_id`
- Usuário: `_wcas_customer_id`

## Logs

Acesse **WooCommerce > Conta Azul > Logs**.

O plugin registra:

- Requisições e respostas quando log detalhado está ativo
- Erros HTTP
- Erros de autenticação
- Cliente criado
- Produto criado
- Pedido sincronizado
- Pedido cancelado
- Reembolso criado

Dados sensíveis como tokens, secrets, autorização, senha, CPF/CNPJ, documento, e-mail e telefone são mascarados antes da gravação do contexto do log. A tela mostra uma versão resumida e segura do contexto.

## Rotina de teste manual

### 1. Teste de instalação/ativação

- [ ] Compacte a pasta do plugin em `.zip`.
- [ ] Instale pelo painel do WordPress.
- [ ] Ative o plugin.
- [ ] Confirme que não há erro fatal.
- [ ] Confirme que aparece o menu **WooCommerce > Conta Azul**.
- [ ] Confirme que a tela abre mesmo desconectada da Conta Azul.

### 2. Teste de configuração segura

- [ ] Salve Client ID, Client Secret e Redirect URI.
- [ ] Reabra a tela e confirme que o Client Secret aparece apenas mascarado/placeholder.
- [ ] Confirme que nenhum token aparece na tela.
- [ ] Ative e desative opções de sincronização e salve.
- [ ] Confirme que os endpoints configuráveis persistem corretamente.

### 3. Teste OAuth2

- [ ] Cadastre no Portal do Desenvolvedor a URL exibida em **URL para cadastrar na Conta Azul** (`wp-admin/admin-post.php?action=wcas_oauth_callback`).
- [ ] Clique em **Conectar Conta Azul**.
- [ ] Autorize com uma conta ERP válida.
- [ ] Confirme retorno para o WordPress com status **Conectado**.
- [ ] Aguarde ou force a expiração do token em ambiente de teste e confirme renovação por `refresh_token`.
- [ ] Desconecte e reconecte para validar limpeza de tokens.

### 4. Teste de sincronização de cliente/produto/pedido

- [ ] Valide os paths reais da API Conta Azul no Portal do Desenvolvedor.
- [ ] Ajuste os endpoints configuráveis no plugin.
- [ ] Crie um produto WooCommerce com SKU.
- [ ] Crie um pedido com nome, e-mail, telefone, CPF/CNPJ e endereço completo.
- [ ] Altere o pedido para `processing`.
- [ ] Verifique logs e metadados do pedido.
- [ ] Confirme no ERP Conta Azul se cliente, produto, venda e conta a receber foram criados conforme opções habilitadas.
- [ ] Altere o pedido para `completed` e confirme que o plugin não duplica uma venda já sincronizada.

### 5. Teste de cancelamento e reembolso

- [ ] Cancele um pedido sincronizado.
- [ ] Confirme registro de log.
- [ ] Se o endpoint de cancelamento estiver validado e configurado, confirme atualização/cancelamento no ERP.
- [ ] Crie um reembolso e confirme log seguro.

### 6. Checklist de segurança

- [ ] Nenhum token aparece no admin.
- [ ] Nenhum secret aparece no admin.
- [ ] Logs mostram `***` para credenciais e dados sensíveis.
- [ ] Apenas usuários com `manage_woocommerce` acessam a tela.
- [ ] Formulários exigem nonce.
- [ ] A Redirect URI cadastrada no Portal é idêntica à do plugin.

## Problemas comuns

### Redirect URI inválida

A URL cadastrada no Portal do Desenvolvedor deve ser exatamente igual à **URL para cadastrar na Conta Azul** exibida no plugin e deve terminar em `wp-admin/admin-post.php?action=wcas_oauth_callback`.

### Falha ao obter token OAuth2

Verifique Client ID, Client Secret, Redirect URI e se a aplicação pertence ao novo Portal do Desenvolvedor.

### HTTP 401

O plugin tenta renovar o token automaticamente uma vez. Se continuar falhando, desconecte e conecte novamente.

### Endpoint não encontrado

Atualize os paths configuráveis conforme a documentação atual da Conta Azul. Os defaults de recursos são placeholders seguros para adaptação.

### Cliente/produto não criado

Confirme que as opções **Criar cliente automaticamente** e **Sincronizar produtos** estão ativas e que os campos obrigatórios exigidos pela API real foram mapeados.

## Internacionalização

Text domain: `woocommerce-conta-azul-sync`.
