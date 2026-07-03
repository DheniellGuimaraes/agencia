# Qualidade e Recomendações Mercado Livre

## Arquitetura mapeada

O plugin é carregado por `mercado-pago-agencia-privilege.php`, instancia autenticação (`MPAP_Auth`), cliente REST (`MPAP_API`), sincronização de produtos (`MPAP_Product_Sync`), pedidos, webhooks, diagnósticos e admin. A sincronização monta payloads em `MPAP_Product_Sync::payload()`, cria anúncios com `POST /items`, atualiza anúncios com `PUT /items/{item_id}`, atualiza descrição com `PUT /items/{item_id}/description` e consulta categorias/atributos com `/categories/{category_id}` e `/categories/{category_id}/attributes`.

## Módulo de qualidade

A classe `MPAP_Quality` centraliza validações antes de publicar, atualizar ou reativar anúncios:

- normaliza títulos removendo HTML, acentos, espaços duplicados e diferença de caixa;
- cria hash SHA-256 para deduplicação por nome;
- escolhe canônico pelo primeiro produto com `_mpap_ml_item_id`; se não houver, usa o menor ID publicado;
- bloqueia produtos duplicados por nome e salva referência ao produto/item canônico;
- exige no mínimo 3 fotos únicas entre imagem destacada, galeria e imagens de variações;
- consulta atributos de categoria via `GET /categories/{category_id}/attributes` e identifica atributos regulatórios por texto (`ANATEL`, homologação, certificação, registro etc.);
- adiciona o valor `_ml_anatel_homologation_number` ao payload quando a categoria expõe atributo compatível;
- remove/omite `MANUFACTURING_TIME` de `sale_terms` quando o produto tem estoque imediato e não está marcado como sob encomenda.

## Produtos com poucas fotos

A aba **Qualidade dos Anúncios > Produtos com poucas fotos** lista produto, SKU, quantidade de fotos, item ML, status e ação sugerida. O envio ao Mercado Livre é bloqueado até haver 3 fotos únicas.

Checklist recomendado:

1. foto nítida;
2. produto centralizado;
3. sem marca d’água;
4. sem texto promocional;
5. sem selos do Mercado Livre;
6. sem telefone, WhatsApp, e-mail, QR Code ou link;
7. fundo branco/neutro;
8. mínimo 500x500 px;
9. recomendado 1200x1200 ou superior.

## Anatel

Preencha o campo **Número de Homologação Anatel** no produto WooCommerce. O plugin não hardcoda um ID único: ele consulta os atributos oficiais da categoria em `GET /categories/{category_id}/attributes` e envia o valor no primeiro atributo regulatório correspondente. Se o atributo for obrigatório e o campo estiver vazio, a sincronização é bloqueada.

## Prazo de disponibilidade

A configuração **Remover prazo de disponibilidade automaticamente quando houver estoque** fica habilitada por padrão. O plugin omite `MANUFACTURING_TIME` quando há estoque imediato. Para anúncios existentes, use primeiro dry-run e depois uma ação segura; se a API recusar remoção direta, o erro será registrado nos logs sem retry infinito.

## Parcelas sem juros

O plugin não envia campos falsos no payload do item e não altera preço para simular juros. Como a integração atual não usa endpoint oficial de campanhas/parcelamento sem juros, a tela mostra uma recomendação guiada para ativação na conta Mercado Livre/Mercado Pago e mantém um controle interno para marcar como concluído.

## Anúncios inativos

A aba **Anúncios inativos** cruza dados locais com `GET /items/{item_id}` quando disponível e mostra status, substatus, health/tags/warnings retornados pela API, além de motivos locais como poucas fotos, duplicidade, Anatel ausente, ficha técnica incompleta e prazo de disponibilidade.

## Botão para deletar/encerrar anúncios Mercado Livre

Mercado Livre não oferece “apagar produto WooCommerce” via plugin; a ação segura implementada é **encerrar anúncios vinculados** enviando `PUT /items/{item_id}` com `status=closed`. A tela sempre oferece uma prévia dry-run. Para executar, é necessário digitar `ENCERAR`, evitando acionamento destrutivo acidental.

## Endpoints usados/confirmados

- `POST /items` — criar item.
- `PUT /items/{item_id}` — atualizar item/status.
- `GET /items/{item_id}` — consultar item/status/health/tags/warnings quando presentes.
- `PUT /items/{item_id}/description` — atualizar descrição.
- `PUT /items/{item_id}/pictures` — atualizar fotos.
- `GET /sites/{site_id}/domain_discovery/search` — predição de categoria.
- `GET /categories/{category_id}` — dados da categoria.
- `GET /categories/{category_id}/attributes` — atributos obrigatórios/recomendados.
- `GET /categories/{category_id}/listing_types` — tipos de anúncio permitidos.

## Teste em ambiente seguro

1. Use uma conta de teste/sandbox ou vendedor de homologação.
2. Execute dry-run antes de sincronizar em massa.
3. Valide um produto por vez depois de configurar categoria e Anatel.
4. Nunca execute o encerramento em massa sem conferir a prévia.
5. Verifique os logs sanitizados em **Logs** para respostas 400/403/429/5xx.


## Auditoria pré-produção

Consulte `docs/auditoria-tecnica-3.7.0.md` para o relatório técnico, bugs encontrados, correções aplicadas e roteiro objetivo de teste local.
