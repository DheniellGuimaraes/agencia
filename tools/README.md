# Enriquecimento offline por relatório XLSX

Este diretório contém o gerador mais rápido para o fluxo solicitado: receber o relatório `enriquecer.xlsx` com milhares de URLs, gerar um JSON portátil fora do WordPress e importar esse JSON no menu **SEO Enrichment Studio → Importar/Exportar**. O fluxo foi ajustado para os dois padrões principais: `criação-de-sites-para-{profissao}-em-{cidade}` e `agencia-de-marketing-digital-em-{cidade}-agencia-privilege`.

## Uso

```bash
python3 tools/generate_enrichment_payload.py enriquecer.xlsx --output ses-enrichment-payload.json --company "Studio Privilege"

# ou baixar direto de uma URL do GitHub/liberada:
python3 tools/generate_enrichment_payload.py --xlsx-url "https://github.com/DheniellGuimaraes/agencia/blob/main/enriquecer.xlsx" --output ses-enrichment-payload.json --company "Studio Privilege"

# ou gerar a partir de uma lista simples de URLs:
python3 tools/generate_enrichment_payload.py --url-list payloads/padroes-enriquecimento.txt --output payloads/ses-enrichment-payload.json --company "Studio Privilege"
```

Para o lote real com mais de 100 mil páginas, use o XLSX completo, um TXT com uma URL por linha ou o sitemap index. O script não limita a 8 páginas e exclui slugs protegidos quando `--protected-pages` é informado.

```bash
python3 tools/generate_enrichment_payload.py --sitemap payloads/sitemap-index.xml --protected-pages payloads/protected-pages.tsv --output ses-enrichment-payload.json --company "Studio Privilege"
```

Depois, no site de destino:

1. Acesse **SEO Enrichment Studio → Importar/Exportar**.
2. Envie `ses-enrichment-payload.json` em **Importar no site de destino**.
3. Use o modo **Apenas metadado seguro** para gravar `_ses_enriched_html` sem reescrever o conteúdo principal.

## Colunas aceitas

O script detecta automaticamente colunas com nomes relacionados a:

- `url`, `link`, `pagina`, `page`, `permalink`, `caminho`, `path` ou `slug` para localizar a página.
- `titulo`, `title`, `h1` ou `nome` para complementar o título.

Não há dependências externas: o XLSX é lido com a biblioteca padrão do Python.

## Build do plugin

Arquivos binários não são versionados. Para gerar o pacote instalável do WordPress localmente, rode:

```bash
python3 tools/build_plugin_zip.py --output seo.zip
```
