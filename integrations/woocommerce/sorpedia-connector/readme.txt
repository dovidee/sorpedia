=== Sørpedia Connector ===
Requires at least: 6.4
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPL-2.0-or-later

Specifications with source, related products with level and a product assistant on WooCommerce product pages.

== Description ==

Reads the Sørpedia public API (/api/v1) with the shop's own product IDs and adds three things to the single product page:

1. A tab "Fakta og kilder": every known specification with where it comes from (confirmed by the store, approved by staff, derived from the product text, or from a buyer) and the filterable values that are still unknown.
2. "Passer sammen med": related products with how sure the relation is (documented, rule based, assumed). Names, links and prices are the shop's own.
3. "Spør Søren": questions about the product, forwarded by the shop server to the API. The browser never calls the guide or the language model directly. At most 5 questions per visitor per 10 minutes.

API answers are cached for five minutes (failures for one). When the API does not answer, the three parts are left out and the product page works as before.

== Installation ==

1. Copy the folder sorpedia-connector to wp-content/plugins and activate it.
2. Settings > Sørpedia: enter the API address, for example https://guide.example.no/api/v1. The page shows whether the API answers.
3. On the Sørpedia server, set API_ASK_LIMIT high enough for the shop, since every visitor's question comes from the shop server's address.

See docs/integration/woocommerce.md in the Sørpedia repository for the whole setup, including product sync by webhook.
