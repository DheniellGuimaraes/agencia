=== Sitemap Agencia Privilége ===
Contributors: agencia-privilege
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Gerador premium de sitemaps XML físicos, salvos em wp-content/uploads/sitemap-agencia-privilege/.

== Instalação ==
1. Criar a pasta sitemap-agencia-privilege.
2. Colocar os arquivos dentro dela.
3. Compactar como .zip.
4. Instalar no WordPress em Plugins > Adicionar novo > Enviar plugin.
5. Ativar.
6. Acessar o menu Sitemap Agencia Privilége.
7. Clicar em Verificar Permissões.
8. Clicar em Gerar Sitemaps Físicos.
9. Enviar a URL do arquivo sitemap.xml ao Google Search Console.

== Recursos ==
* Geração física incremental via AJAX.
* Sitemap index físico e sitemaps filhos por tipo.
* Cursores por ID para post types.
* Configurações persistentes, progresso, logs e diagnóstico.

== Google Search Console ==
Quando a raiz do WordPress tiver permissão de escrita, o plugin espelha sitemap.xml e os arquivos sitemap-*.xml na raiz do site para que o Google consiga buscar /sitemap.xml e todos os filhos (/sitemap-pages-1.xml, /sitemap-pages-2.xml etc.). Se a raiz não tiver escrita, os arquivos continuam disponíveis na pasta de uploads do plugin.
