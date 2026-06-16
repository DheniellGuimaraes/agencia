# Emissão de Etiquetas Alcateia

Plugin gratuito para WordPress/WooCommerce que gera etiquetas internas de expedição, gerencia rastreamento manual, envia e-mails de rastreio, cria picking lists e gera romaneios simples.

## Versão atual

1.2.0

## Requisitos

- WordPress 6.0 ou superior
- WooCommerce 7.0 ou superior
- PHP 8.0 ou superior
- Dompdf é opcional. Sem Dompdf, o plugin usa HTML imprimível.

## Instalação

1. Compacte a pasta `emissao-de-etiquetas-alcateia` em um arquivo ZIP.
2. No painel do WordPress, acesse **Plugins > Adicionar novo > Enviar plugin**.
3. Envie o ZIP, instale e ative.
4. Garanta que o WooCommerce esteja ativo.
5. Acesse **Etiquetas Alcateia** no menu administrativo.

## Funcionalidades

- Etiqueta interna 10x15 cm para impressão térmica.
- Geração individual no pedido WooCommerce.
- Geração em lote na listagem de pedidos.
- Fallback HTML imprimível quando não houver Dompdf.
- Cadastro manual de código de rastreio, transportadora, link, data de envio e observações internas.
- Envio de e-mail de rastreio ao cliente com controle contra duplicidade.
- Envio automático opcional quando o pedido muda para um status configurado.
- Coluna e filtros de rastreio na listagem de pedidos.
- Picking list com total de produtos por pedido selecionado.
- Romaneio simples com dados de cliente, destino, transportadora e rastreio.
- Tela de configurações, diagnóstico, ajuda e logs internos.
- Compatibilidade declarada com WooCommerce HPOS.

## Como usar

### Gerar etiqueta individual

Abra um pedido no WooCommerce e clique em **Gerar Etiqueta Alcateia** ou **Imprimir etiqueta**.

### Gerar etiquetas em lote

Na listagem de pedidos, selecione os pedidos desejados e use a ação em massa **Gerar Etiquetas Alcateia**.

### Cadastrar rastreio

Abra um pedido e preencha o painel **Rastreamento Alcateia**. Salve o pedido para gravar os metadados.

### Enviar rastreio ao cliente

Após cadastrar um código de rastreio, clique em **Enviar rastreio ao cliente** no painel do pedido. Se o rastreio já foi enviado, o plugin exigirá uma ação explícita de reenvio.

### Gerar picking list

Na listagem de pedidos, selecione os pedidos e use a ação em massa **Gerar picking list Alcateia**.

### Gerar romaneio

Na listagem de pedidos, selecione os pedidos e use a ação em massa **Gerar romaneio Alcateia**.

## Limitações

- O rastreio é cadastrado manualmente pelo administrador.
- O plugin não consulta APIs externas.
- O plugin não integra Melhor Envio, Frenet, Correios ou transportadoras neste momento.
- O plugin não emite etiqueta oficial dos Correios ou de transportadora. Ele gera apenas etiqueta interna de separação, embalagem e expedição.

## Changelog

### 1.2.0

- Adiciona diagnóstico, logs internos, ajuda, README e uninstall seguro.
- Declara compatibilidade com WooCommerce HPOS.
- Melhora proteção contra envio duplicado de rastreio.
- Refina layout de etiqueta e e-mail de rastreio.

### 1.1.0

- Adiciona rastreamento manual, envio de e-mail, configurações, picking list e romaneio.

### 1.0.0

- Versão inicial com geração de etiquetas, painel administrativo Glassmorphism Ultra, botão por pedido e ação em massa.
