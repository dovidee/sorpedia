# 034 Product retrieval for the chat

## Goal

Søren talks about the right products for the right reasons. One ranked product list per question drives three things: the facts the model gets, the product cards beside the answer, and what the conversation remembers for the next question. Nothing here calls the language model.

## Before

`Chat::context` put the page's products first and filled up to five with `Search::run` on the whole question; `Chat::cardIds` ran a second search for the cards. The two could disagree. A follow-up was searched on its own, so «hva koster de to sammen?» found unrelated products. Words that only state intent («komplett», «sett», «sammen») found balcony stands, a crab cooker and a folding grill. A model code such as H5R scored like any other word. Related products reached the model by name only, without a price reference, so the model wrote prices of its own. The same orchestration was copied in `public/index.php` and `src/PublicApi.php`.

## Design

`src/ProductRetriever.php`, one entry point: `ProductRetriever::forChat($messages, $page, $history)` returns `ids` (for the model), `cards` (the same list), `why` (score and signals per product), and `intent` and `kind` when the question is catalogue-wide.

Signals and points, in the `WEIGHTS` constant: exact SKU 100, own model code in the name 100, whole product name 70, product on the page 50, product of the previous answer 45, documented or rule-based relation for an asked-for accessory 35, same category for «noe lignende» 30, specific word in the name 30, best accessory match without a relation 25, use case 20, synonym 15, model code mentioned in another product's name 40 (a battery «for H7R Core»), brand named 10, generic word (in more than ten names) 2. Penalties: another model code than the one asked for −40, a word hit outside the theme of the products in focus in a question about them −40, an asked-for accessory that is not related when related ones exist −40.

Selection: at least 25 points; products with word signals only also need half the best score; at most five. The products in focus (named, on the page, or referred back to) come before what was found around them. A model code the catalogue doesn't have keeps word hits out, so an unknown model gets no substitute. A code shared by more than six names is a brand or series («1852»), not a model.

References: «de to», «begge», «dem», «disse», «de», «den», «det», «samme», «den andre», an asked-for accessory or «noe lignende» bring back the previous answer's products. A product named outright that wasn't in the previous answer is a new topic, unless the customer points back with a plural reference. «Den billigste av dem» orders the previous products by price instead of searching the catalogue.

Relations keep their meaning: «passer sammen» at level dokumentert or regelutledet can bring an accessory in; «passer ikke» keeps a product out as an accessory and is shown to the model as a warning; «kjøpt sammen» is never used for fit. A fallback accessory (no relation) is only a candidate: the facts state the level of every relation, so an undocumented fit is not presented as one.

`Search::run` is unchanged and still serves the store search; the retriever calls it for words, typos, synonyms and use cases. `CatalogIntent` is unchanged and is called from inside the retriever.

## Memory (migration)

`chat_messages.product_ids JSON NULL`, added by `ChatHistory::ensure()` with `ADD COLUMN IF NOT EXISTS` (safe to run again). Only the answer row gets a value: the final list given to the model and the cards, at most five IDs. Old rows stay NULL and mean no memory. `ChatHistory::lastProducts()` reads only the latest answer in the owner's own, unexpired conversation, ignores anything that isn't a positive integer, and drops IDs no longer in the catalogue. Rollback: `ALTER TABLE chat_messages DROP COLUMN product_ids` and revert the code; conversations and catalogue data are untouched.

## Facts

Related products in `Chat::facts` carry their ID, SKU, a price reference and a stock reference, the relation level in words (dokumentert av Sør-Tre, or regelbasert, ikke bekreftet av Sør-Tre) and the reason. The rules tell the model that a product without its own price reference has no price here, and that a price is never worked out from another product.

## Public API

`/api/v1/products/{id}/questions` uses the same retriever. Its contract is unchanged: it keeps no conversation, so it has no product memory between questions; the product of the endpoint gets the page signal.

## Evaluation

`evals/retrieval.json` holds the cases with hand-set expectations from the catalogue: 18 cases from the brief's scenarios and 10 held-out cases written after the first tuning round. `evals/retrieval.php` runs the earlier orchestration (kept in the eval for comparison) and the retriever on the same questions and reports Recall@5, Precision@5, exact match, follow-up resolution, compatibility, irrelevant-hit rate, card consistency, time and database statements per question. Read only.

## Acceptance criteria

Exact model names and SKUs find their product first. Generic words don't fill the slots. Follow-ups keep the previous products; a new product named outright starts a new topic. Complete setups bring related accessories, and «passer ikke» never brings a product in. Cards and model context are the same list. Both entry points use the same retriever. The store search page behaves as before. No model calls are added.
