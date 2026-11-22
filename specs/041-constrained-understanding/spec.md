# 041 Constrained understanding

Status: built. Priority: P1 (trust).

## Problem

Through features 015 to 040 the language model wrote the product answers as free text, and each fix stopped one pattern of false claim (invented products, a flashlight recommended as a headlamp, USB-C on a lamp documented as magnetic, a documented misfit called «perfekt», unfilled notes). The set of ways a model can phrase a false claim is open, so validating its text does not converge. A JSON schema fixes the shape of the output, not whether its values are true.

## Decision

The model reads; PHP decides and writes. The model never produces a product ID, name, price, specification, fit claim, order status or a factual sentence that the customer sees.

1. `Understand::read($q, $context, $useModel)` asks Ollama (`Chat::structured`: `/api/chat`, `format` with a JSON schema, `think` false, `stream` false, temperature 0, 10 s) for six fields: `intent` (finn_produkt, om_produktet, handlekurv, ordre, smaprat, uklart), `produkttype`, `krav` (a property key, min, max, value), `budsjett_kr`, `mangler`, `trenger_avklaring`. The schema has no free-text reasoning field. The property keys in the schema are the filterable keys in `spec_keys` (`is_facet = 1`), so a new specification needs no prompt change.
2. `Understand::validate` keeps only what the message supports and records what it dropped: a product type must be written in the message (or in its English translation) and exist in the catalogue (`CatalogIntent::typeOf`); a requirement needs a known key, its number in the text and the direction the customer wrote («minst», «over», «at least» give a minimum; «under», «maks», «høyst», «opptil» a maximum); a budget must be in the text; a cart or order intent needs cart or order words; two readings of one property are both dropped. Schema compliance is not taken as a correct reading.
3. The catalogue's own reading always counts as well: the product type from product names, and requirements stated with a unit from `spec_keys` («500 lumen», «200 g») or a text property named by its label («vanntett»).
4. Without an answer from the model, the same fields are read by `Understand::rules`. Past the public API's model allowance the API reads by rules instead of returning 429.
5. `ShopAnswer::answer` owns every customer-facing product, cart and order answer:
   1. Recommendation: the products of the named type, filtered on the budget and on verified values for each requirement, ranked by stock in the store, then web stock, then price. Each product is written by a template with its price and stock as references (`[[pris:ID]]`, `[[lager:ID]]`) and the values of the keys that decide for its type (`Usage::keys`), each with its source; a missing value is written as not registered. The type's general use text (`Usage::rule`) is labelled as derived. Without requirements the answer asks which deciding property matters most.
   2. A product in focus: the server's factual answer (`FactAnswer::basis`), else a summary of verified values, the documented fits and misfits, and whether a guide exists.
   3. Cart: `cart_items` for the session, summed with today's prices. Orders: the logged-in customer's orders from `orders`, else a login prompt; withdrawal, returns and refunds go to customer service.
   4. Nothing names a product or a type: a clarification listing the shop's themes.
6. Small talk that names no product, no type and no requirement gets a fixed reply from PHP. The model writes nothing the customer sees: a 3B model writing small talk with the conversation as context made product claims in testing.
7. The reading uses the current message only. Words from earlier messages are not carried over, so «hvordan lader jeg den?» after a recommendation is not read as the recommendation again; which product «den» means comes from the retriever's memory. When it means more than one earlier product and none is on the page, the customer is asked which one.
8. A number for a property with a unit counts only next to that unit, as `Understand::requirements` reads it («minst 500 lumen under 1000 kr» is 500 lumen and a 1000 kr budget). A cart or order word in the message sets the intent, whatever the model said. A text value that no product of the type has verified («fjellturer», «hiking») is not used to filter and is left out of the answer, also from the per product lines.

The model for the reading is set with `OLLAMA_MODEL`, by default `qwen2.5:3b-instruct`. A reasoning model is the wrong fit for this task: `deepseek-r1:8b` reasons before the JSON even with `think` false when `format` is set, which took 23 to 116 s per question in the traces. The Qwen2.5 3B model is published under the Qwen Research License (non-commercial); a commercial deployment needs another licence or an Apache-2.0 model of similar size.

## Pipeline order (shop chat and public API)

1. Blocked input (crisis, violence, crime, profanity, injection): fixed answer.
2. Fixed replies (`QuickReply`, feature 042).
3. Retriever, products not sold, product guide, totals and the server's factual answers (features 034 to 039).
4. `Understand::read`, then `ShopAnswer::answer`.
5. Small talk: a fixed reply.

## Not in scope

No new framework or dependency. The model does not choose or rank products. Adding to cart and placing orders stay in the shop's own flows; the chat never changes cart or order state. `AnswerGuard` (feature 040) is no longer on any customer path; no new guard rules.

## Acceptance criteria

1. A product type, number, budget, key or cart or order intent the customer did not write is dropped and listed in `dropped`.
2. A headlamp recommendation lists only headlamps, chosen and ranked by PHP, and nothing is left unresolved in the text.
3. Requirements filter on verified values; a missing value is said as not registered.
4. «Hvordan lader jeg den?» on H7R Core gives the documented charging method and misfit, with no USB-C claim.
5. A product question the model labels small talk is not answered as small talk.
6. A question with nothing to go on gets a clarification.
7. With the API's model allowance used up, the question is answered with 200, read by rules.
