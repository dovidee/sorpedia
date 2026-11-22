# 038 Public API product-question grounding

## Goal

An answer from `POST /api/v1/products/{id}/questions` is grounded in the database facts of the product the endpoint names, and the answer guard (feature 035) checks it against those facts, just as in the shop chat.

## Findings (before the change)

Execution path: `PublicApi::serve` matches the path, `PublicApi::ask` validates the ID (`find`, 404 when missing), the content type (415) and the question (400), applies the rate limit (429), then builds the page context `PageContext::resolve('/produkt/{slug}')`, runs `ProductRetriever::forChat`, and calls `Chat::answer`, the same path as the shop chat. `Chat::answer` builds the facts with `Chat::facts($ids)`, the prompt with the page text («Kunden står nå på produktsiden til [ID] …»), and the guard context with `AnswerGuard::context($ids, $facts)`; every sentence goes through `ProductRef::clean`, `AnswerGuard::check` and `ProductRef::resolve`.

1. The response field `products` lists only the products the answer names by name (`PublicApi::named`). An empty list does not mean that no product was given to the model.
2. For ordinary questions the selected product was in the facts: the page signal puts it first, with its specifications, references and relations, including the documented «PASSER IKKE» for the MH charging cable.
3. Gap: for catalogue-wide questions («den billigste», «best vurdert») the retriever returns the list from `CatalogIntent`, which replaced the selected product. The model and the guard then had no facts about the product the endpoint is about.
4. Gap: the API runs without a session, so stock is for the default store (`warehouse_id()`, store 1), but the facts said «Lager i valgt varehus» without naming the store.
5. Gap: a reference the model wrote in bold that was removed left «****» in the answer.
6. Gap in the guard: a product was recognised by the first word of its name only in that exact form, so «MH-ladekabelen» did not identify «Ladekabel … MH-Serie» and a fit claim about it was not checked.

## Change

1. `PublicApi::grounding($id, $found)`: the endpoint's product first, then what the retriever found, without duplicates, at most five, for both the facts and the cards. Catalogue-wide lists still come after it, with their own facts from `CatalogIntent`.
2. `Chat::facts`: the stock line names the store: «Lager i varehuset Kristiansand: …». Shop chat and API both use it.
3. `ProductRef::clean`: empty bold marks left by a removed reference are removed.
4. `AnswerGuard`: the first word of a name also matches its inflected forms (-en, -et, -a, -ene, -er, -ne, -s) for words of four letters or more; the second, distinctive word is still required.

Unchanged: the retriever's scoring, `Search::run`, catalogue and relations data, the price and fit checks, the not-in-range notice, and the public API contract (method, path, fields, status codes, errors, rate limit, no stored conversation).

## Not handled here

The guard does not detect an answer that says both that something fits and that it can't be confirmed; only documented misfits and prices are enforced. Wording quality is left for a separate task.
