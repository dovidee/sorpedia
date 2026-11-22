# 039 Deterministic product answers

Status: built. Priority: P1 (trust), P5 (public API).

## Problem

Features 034, 035 and 038 gave the model the right products and the right facts, and a guard that corrects prices and documented misfits sentence by sentence. Live runs still showed the model getting facts wrong with correct facts in front of it: a self-contradicting fit answer («Nei» followed by «usikker»), an invented store name, unrelated prices, an internal product ID in the text, a broken link, and internal labels from the prompt. A guard can only repair the patterns it knows. The model has to be taken out of the factual answers, not persuaded.

## Decision

Factual product questions are answered by deterministic PHP from the database. The model is asked only for advice and explanations.

Three approaches were compared:

| | A: server writes the answer | B: model explains a fixed answer | C: model answers, guard corrects (before 039) |
|---|---|---|---|
| Can the model state a wrong price, stock count, specification or fit? | No, it is not asked | Only in the explanation, which is checked by the guard | Yes, the guard catches known patterns only |
| Time per factual question | Milliseconds | One model call (tens of seconds on the demo PC) | One model call |
| Natural language | Fixed sentences | Natural | Natural |
| Unknown | Said as unknown, by template | Depends on the model keeping it | Depends on the model keeping it |

A is used for facts. C stays, with the guard, for advice and explanations («hvorfor», «hvordan», «anbefaler du»). B was not built: on deepseek-r1:8b it costs a model call per factual question and brings back the risk A removes; it can be revisited for a stronger model.

Approach A follows the intent-routing pattern: a deterministic layer decides the route before any model is called, and static routes answer what can be answered without generation. Structured output from the model (for example Ollama's `format` JSON schema) only fixes the shape, not whether the values are true, so it was not used for facts.

## Scope

`src/FactAnswer.php` answers, for the products the retriever identified by code, exact name, page, or the previous answer (never by a weak word hit):

1. Identification: item number (SKU) and brand.
2. Specifications: the verified value with its source; else a buyer's or a rule's value, said to be unchecked; else the value in Sør-Tre's own product text, said not to be a checked specification; else unknown. Burn time is never in the catalogue and is always unknown.
3. Price and stock: price by reference; cheaper or dearer with the difference in kroner; stock in the store named in the question, else the customer's store, else the default store, with the online stock as the second answer.
4. Comparison: the verified specifications that differ, and those only one product has.
5. Catalogue rankings: `CatalogIntent` now limits the list to the product type named before ranking. A word of four letters or more in the question, in its base form, must be in the product name, and products made for that type («batteripakke for … hodelykt») are left out. A named type with no match gives no list instead of the whole store. English type words are matched on the Norwegian word the search translates them to.
6. Fit: documented «passer ikke» means no; documented «passer sammen» means yes; rule-based means likely, not confirmed; no relation means unknown, with the fit properties both products have shown side by side without a conclusion.

The answer is one structure (`text`, `ids`, `kind`). The text uses `[[pris:ID]]` references that `ProductRef` fills in, so the price is today's. Both the shop chat (`public/index.php`) and the public API (`PublicApi::ask`) call it before `Chat::answer`. The API body keeps its fields; `model.used` is false and `notice` says the server answered.

Tracing: `src/Trace.php` writes one JSON line per stage, under one correlation ID, to `data/traces/`: the route with timings, the facts and guard context, the exact Ollama request body and what came back (thinking and text), each sentence the guard changed (model text and sent text), and the final answer. The API returns the ID in `X-Trace-Id`. Files are kept seven days and never leave the machine. `SOREN_TRACE=0` turns it off.

## Not in scope

No new model, no embeddings, no agents, no MCP server, no live lookups (rule 8 unchanged). The retriever is unchanged. The API contract is unchanged apart from the `X-Trace-Id` header.

## Acceptance criteria

1. «Hva veier den, og har dere den på lager?» on the H7R Core API: the weight from Sør-Tre's product text, marked as not a checked specification, and the stock count of the default store, without the model.
2. «Passer MH-ladekabelen til denne lykten?»: «Nei», with the documented misfit, in the chat and the API.
3. «Hva er den billigste hodelykten?» (also in English): only headlamps, cheapest first; no hook, bracket or battery pack.
4. «Er H5R Core billigere enn denne?»: both prices and the difference from the database; no internal ID in the text.
5. «Passer laderen til ML4 campinglykten til denne?»: unknown, said once, no yes and no no.
6. A specification the catalogue does not have is said to be unknown.
7. Advice and how-to questions still reach the model, with the guard.
8. A live API question has a trace with the exact Ollama request and response under the ID in `X-Trace-Id`.
