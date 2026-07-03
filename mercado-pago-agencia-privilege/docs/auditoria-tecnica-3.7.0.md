# Auditoria técnica pré-produção — Mercado Pago Agência Privilege 3.7.0

## Escopo auditado

Auditoria da implementação do módulo **Qualidade dos Anúncios Mercado Livre** antes de produção, cobrindo ZIP final, bootstrap, compatibilidade PHP, includes, hooks admin, nonces/capabilities, dry-run, encerramento em massa, deduplicação, fotos, Anatel, `MANUFACTURING_TIME`, logs e testes.

## Resultado por requisito

1. **ZIP final contém arquivos alterados** — validado por `unzip -t` e pelo teste estático `tests/quality-tests.php`, que confere presença de `class-mpap-quality.php`, documentação, JS e testes dentro do ZIP.
2. **Bootstrap carrega `MPAP_Quality`** — `mercado-pago-agencia-privilege.php` inclui `includes/class-mpap-quality.php` antes de `class-mpap-product-sync.php`.
3. **PHP 7.4+ / 8.x** — todos os arquivos PHP passam em `php -l`; a implementação evita sintaxe exclusiva de PHP 8.
4. **Classes novas com require correto** — `MPAP_Plugin` instancia `MPAP_Quality` e injeta no sincronizador/admin.
5. **Hooks admin** — submenu e AJAX são registrados em hooks existentes e preservam o carregamento condicional de assets.
6. **Nonces e capabilities** — ações sensíveis usam `check_ajax_referer( 'mpap_admin_nonce', 'nonce' )` e `current_user_can( mpap_capability() )`; salvamento de metabox usa nonce próprio e `edit_post`.
7. **Encerramento exige confirmação real** — execução real exige digitar exatamente `ENCERAR` no JavaScript e no PHP; caso contrário executa apenas dry-run.
8. **Deduplicação não bloqueia canônico** — o canônico é o primeiro com `_mpap_ml_item_id` ou o menor ID, e só os não canônicos são bloqueados.
9. **Dry-run sem POST/PUT/DELETE** — `dry_run_sync_all()` monta payload/diagnóstico e não chama `create_item()` nem `update_item()`; pode consultar metadados/categoria para diagnóstico.
10. **Fotos duplicadas** — contagem usa URL normalizada como chave, evitando contar anexos/URLs repetidos como fotos únicas.
11. **Menos de 3 fotos bloqueia** — checklist bloqueia antes de criar/atualizar anúncio.
12. **Anatel obrigatório apenas quando categoria exige** — bloqueio ocorre somente quando atributo dinâmico retornado por `/categories/{category_id}/attributes` tem tag obrigatória.
13. **`MANUFACTURING_TIME`** — remoção só ocorre quando há estoque imediato, configuração habilitada e produto não está marcado como sob encomenda (`_mpap_made_to_order`).
14. **Tokens/secrets em logs** — o novo módulo não loga access token, refresh token, client secret ou Authorization; usa IDs/status/respostas sanitizadas.
15. **Testes executáveis** — `tests/quality-tests.php` agora é um teste estático executável via PHP, não apenas uma lista de exemplos. Ele valida guardrails críticos sem exigir bootstrap WordPress.

## Bugs encontrados nesta revisão

- A confirmação anterior usava `ENCERRAR`; o requisito atual exige exatamente `ENCERAR`.
- O encerramento em massa não salvava backup local do status anterior do item antes do `PUT status=closed`.
- A prévia não destacava explicitamente a lista agregada de `item_id` afetados.
- A contagem de fotos deduplicava por ID de anexo, mas poderia contar a mesma URL em anexos diferentes.
- Os testes eram apenas cenários documentados, não executáveis.

## Correções aplicadas

- Confirmação alterada para `ENCERAR` em PHP e JS.
- Execução real agora consulta `GET /items/{item_id}` e salva `_mpap_ml_status_before_close` antes de enviar `PUT /items/{item_id}` com `status=closed`.
- Dry-run/execução retornam `affected_item_ids` e detalhes por produto.
- Produtos sem `item_id` são ignorados explicitamente e registrados como `skipped`.
- Aviso irreversível exibido no admin, prompt JS e resposta AJAX: anúncio encerrado no Mercado Livre pode não ser restaurável.
- Fotos únicas agora são chaveadas por URL normalizada.
- `tests/quality-tests.php` passou a executar asserts estáticos de segurança e empacotamento.

## Como testar em WordPress local

1. Gere o ZIP localmente com `scripts/build-plugin-zip.sh` e instale `dist/mercado-pago-agencia-privilege.zip` em uma instalação local com WooCommerce.
2. Ative o plugin e conecte uma conta Mercado Livre de teste/homologação.
3. Crie dois produtos publicados com o mesmo nome e SKUs diferentes; execute **Qualidade dos Anúncios > Dry-run sincronização** e confira que apenas o canônico fica apto a criar/atualizar.
4. Crie um produto com 1 foto e confira bloqueio em **Produtos com poucas fotos**.
5. Adicione 3 URLs/fotos realmente únicas e confira mudança para aprovado/aprovado com recomendações.
6. Configure uma categoria que retorne atributo obrigatório de Anatel e teste campo vazio/preenchido.
7. Para encerramento, clique primeiro em **Prévia: deletar/encerrar todos anúncios ML** e confira `affected_item_ids`.
8. Só em ambiente de teste, clique em executar e digite exatamente `ENCERAR`; confirme que `_mpap_ml_status_before_close` foi salvo e o status local virou `closed`.
9. Verifique logs em **Logs** filtrando fontes `products`, `quality` e `api`.

## Comandos de validação fora do WordPress

```bash
find mercado-pago-agencia-privilege -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l
node --check mercado-pago-agencia-privilege/assets/js/admin.js
php mercado-pago-agencia-privilege/tests/quality-tests.php
scripts/build-plugin-zip.sh && unzip -t dist/mercado-pago-agencia-privilege.zip
```

## Política de binários

Arquivos binários não são compatíveis com este repositório. O ZIP instalável e imagens de preview foram removidos do controle de versão. Para gerar o pacote instalável localmente, use `scripts/build-plugin-zip.sh`; o resultado fica em `dist/mercado-pago-agencia-privilege.zip` e não deve ser commitado.
