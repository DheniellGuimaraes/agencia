# Alcateia Delivery — SaaS Premium WooCommerce Plugin 2026

## 1) Visão geral
Alcateia Delivery entrega um método de frete premium para WooCommerce com experiência administrativa SaaS, Rule Engine extensível e base enterprise para comercialização.

## 2) Instalação e atualização
1. Compacte `alcateia-delivery/` em ZIP.
2. Envie em **Plugins > Adicionar novo**.
3. Ative WooCommerce e o plugin.
4. Acesse **WooCommerce > Alcateia Delivery** para onboarding operacional.

### Atualização segura
- Faça backup do banco.
- Atualize o ZIP.
- Reabra o painel para rodar migrations automáticas.

## 3) Arquitetura
- `includes/class-alcateia-delivery-plugin.php`: bootstrap, REST e integração principal.
- `includes/class-alcateia-delivery-shipping-method.php`: cálculo e tarifa Entrega Express.
- `includes/engine/*`: Rule Engine + Strategy Pattern.
- `includes/migrations/*`: versionamento de schema.
- `includes/services/*`: licença, update, telemetria, observabilidade e addons.
- `includes/class-alcateia-delivery-admin.php`: admin, AJAX, import/export.

## 4) Endpoints REST
- `GET /wp-json/alcateia-delivery/v1/health`
- `POST /wp-json/alcateia-delivery/v1/license`
- `GET|POST /wp-json/alcateia-delivery/v1/rates`
- `POST /wp-json/alcateia-delivery/v1/calculate`

## 5) Segurança
- Capability `manage_woocommerce` em operações sensíveis.
- Nonces em AJAX/Admin actions.
- Sanitização + escaping em entradas/saídas.
- Validação de tipo de arquivo para importação.
- Lock/rate-limit curto de import.

## 6) Troubleshooting
- Sem cobertura: revisar região/peso/qtd/subtotal.
- XLSX: instalar `phpoffice/phpspreadsheet`.
- Modo manutenção: bloqueia simulações temporariamente.

## 7) Guia de customização e addons
- Use `alcateia_delivery_update_endpoint` para conectar update remoto.
- Ative módulos em `addons/` com `bootstrap.php` e opção `alcateia_delivery_enabled_addons`.

## 8) Roadmap
- Assinatura criptográfica em updates.
- Gestão avançada de logs/timeline.
- Conectores nativos multi-transportadora.
- Pacote Composer/PSR-4 completo.

## 9) Changelog
### 1.4.0
- Master final review: hardening de permissões/nonces/REST, revisão de import lock, polishing UX SaaS admin e documentação técnica final.
