# 025 WooCommerce connector

## Goal

Let a WooCommerce shop adopt the knowledge layer without moving off WooCommerce: products stay in step by webhook, and the shop's own product pages show specifications with source, related products with level and the product assistant.

## Scope

1. `POST /api/integrations/woocommerce/webhook` (`src/WooSync.php`): WooCommerce's standard product webhooks, signed with a shared secret (`WC_WEBHOOK_SECRET`).
2. A WordPress plugin in `integrations/woocommerce/sorpedia-connector` that reads the public API (feature 024).
3. A setup guide in `docs/integration/woocommerce.md`.

## Rules

1. A delivery without a valid signature is rejected (401); without a secret on the server the endpoint answers 503. The ping a new webhook sends is answered 200.
2. A delivery ID is applied once; a repeat answers 200 with `duplicate`.
3. The shop is the source of ID, name, SKU and price (constitution rule 1). Specifications from the shop and derived ones are rebuilt from the new text; values from buyers, staff and revisions are kept.
4. A new product outside the guide's categories is skipped.
5. The plugin labels each value by source and never calls a derived or buyer value confirmed; an assumed relation says so (rules 2 and 3).
6. Names, links and prices in the plugin are the shop's own. The visitor's browser never calls Sørpedia or Ollama; questions go through the shop server, with a per visitor limit.
7. When the API does not answer, the plugin adds nothing and the product page works as before.

## Acceptance

1. A signed `product.updated` updates the product; a wrong signature gives 401; the same delivery ID twice is applied once.
2. A signed `product.created` for a product in a guide category creates it with specifications read from its text, and it shows in `/api/v1`.
3. Other topics are answered `ignored`.
4. The plugin passes `php -l` and, run against the API, renders the specifications tab with source labels and the unknown list, the related products with level, and forwards a question.
