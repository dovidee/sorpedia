# 024 Public REST API

## Goal

Let another shop system reuse the knowledge layer and the product assistant without knowing the internal data model. A web shop that already has the product IDs (the IDs are the shop's own) can show specifications with source and level, related products with level and reason, and reviews from verified buyers by calling one versioned API.

## Scope

1. API under `/api/v1`, read-only except for questions to the assistant, implemented in `src/PublicApi.php` and answered before any session starts.
2. Resources: `/api/v1` (entry point), `/api/v1/products` (search with `q`, filter with `theme` and `sku`, pages with `page` and `per_page`), one product, its specifications, relations (filter with `kind`) and reviews under `/api/v1/products/{id}`, questions to the assistant (`POST /api/v1/products/{id}/questions`), `/api/v1/themes`, one theme, `/api/v1/specification-keys`, `/api/v1/openapi.json`.
3. Contract, not tables: field names and values are the API's own (English: `store`, `derived`, `buyer`, `staff`; `verified`, `open`; `goes_with`, `does_not_fit`, `alternative`, `bought_together`; `documented`, `rule_based`, `assumed`). User IDs, revision IDs, sessions, stock, orders and staff data are never sent. Review authors are aliases.
4. Documentation at `/api/docs`, rendered from the OpenAPI 3.1 document in `docs/api/openapi.json`, with a console that sends real requests, follows `_links`, repeats a request with `If-None-Match` and copies it as curl. `/api` redirects there.

## REST rules

1. Uniform interface: GET, HEAD and OPTIONS on every resource except questions, which takes POST and OPTIONS. Any other method gets 405 with `Allow` for that path.
2. Stateless: no cookie, no session, no key.
3. Cacheable: every 200 has `ETag`, `Cache-Control: public, max-age=60` and `Vary: Accept`; a matching `If-None-Match` gives 304 with no body.
4. Self-describing: every body has `_links`; collections send an RFC 8288 `Link` header (self, first, prev, next, last) and `X-Total-Count`; the entry point links `service-desc` (OpenAPI) and `service-doc` (documentation).
5. Errors are RFC 9457 problem details (`application/problem+json`, `no-store`): 400 for an invalid parameter or body, 404, 405, 406 when `Accept` rules out JSON, 415 when a question is not JSON, 429 with `Retry-After` when the question limit is used, 500 with a log reference and no internals.
6. CORS open for reading, so a shop page on another domain can call it.
7. Versioned path. New specification keys are rows and show up in `/api/v1/specification-keys` without a new version.

## Data rules kept

1. `confirmed` is true only for the verified version from the store or staff. Derived and buyer values are never confirmed (constitution rule 2).
2. `certain` is true only for a documented relation. Assumed is never certain (rule 3).
3. Filterable keys without a value are listed under `unknown`.
4. Name, ID and price come from the database. Prices include VAT, in NOK.
5. Questions run the shop chat's pipeline (`Chat::blocked`, `Chat::answer`, `ProductRef`): blocked input gets the fixed answer without the model; the model's reasoning is returned separately; without a model answer the rule-based answer is returned with a `notice`, and `model.used` is false. Answers are `no-store`. At most 10 questions per 10 minutes per client, counted on a hash of the IP address in `api_question_log`. Ollama is never exposed directly.

## Acceptance

1. `curl -i /api/v1` returns 200 JSON with links and no `Set-Cookie`.
2. `/api/v1/products?q=hammmer` finds the hammer and reports `"corrected": "hammer"`.
3. `/api/v1/products?theme=lys&page=2&per_page=5` returns a `Link` header with prev and next and `X-Total-Count`.
4. Repeating a request with its `ETag` in `If-None-Match` gives 304.
5. `POST` and `DELETE` on a read resource give 405 with `Allow: GET, HEAD, OPTIONS`; `GET` on questions gives 405 with `Allow: POST, OPTIONS`; an unknown product gives 404 problem JSON; `page=0` gives 400.
6. `/api/docs` lists every operation from the OpenAPI document and its console shows status, headers and body. No horizontal scroll at 390 px.
7. A question as JSON gets 200 with `answer`, `model` and `products`; a form body gets 415; an empty question 400; the eleventh question within 10 minutes 429 with `Retry-After`; a prompt injection gets the fixed answer with `model.used` false.
