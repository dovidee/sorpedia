# 044 Conversation routing and Søren's voice

Status: implemented and verified. Priority: P1 (trust), P2 (conversation quality).

## Problem

Conversational messages went to the product search: insults, «er du?», arithmetic and questions about a person or the store returned the product on the page or a generic onboarding text. A product named by its exact title returned a list of three similar products. A follow-up about the products shown («hva er forskjellen på dem?») searched for the words in the question. A one word question on a product page («batteri») returned a product that only shared the word. Product lines listed raw metadata («Merke ikke registrert; Materiale ikke registrert»), and every reply was the same fixed sentence.

## Decision

Facts stay deterministic and from the database; what changes is the decision about what the customer means.

1. Intent gate in PHP before the search (`QuickReply::match`, after the blocked-input check). Short forms match the whole normalized message; longer patterns need a verb phrase, so a word such as «person» inside a product question never triggers them. Kinds: greeting, thanks, identity, personality, help, small talk, filler, arithmetic (computed in PHP), rudeness, stop, a request for a human, store information, a joke, weather and ferries. Opening hours are checked before the product type, because «åpner» is also in product names. Profanity gets a neutral redirect (`Chat::blocked`).
2. A request for a human sends the event `handoff`; the chat panel then opens the same customer service flow as «Snakk med et menneske i stedet». The reply says the case will be created; the panel says it exists only after `POST /api/support` succeeds.
3. Store information comes from `warehouses` (`is_real = 1`). The catalogue holds no address or opening hours, so the reply says so and points to sor-tre.no and customer service.
4. A product named by its exact title (retriever signals `exact_name`, `sku`, `model`) is answered alone, before any type search. «Fortell om», «forklar», «hva bør jeg tenke på» ask for an overview (`FactAnswer::overview`), not one fact or one pairing.
5. A question about the set shown before («disse forslagene», «forskjellen på dem», `FactAnswer::aboutSet`) compares the earlier products (retriever signal `history`): the ranking rule that picked them and their verified values. With no earlier products, Søren asks which products are meant. Follow-up words (passer, forslagene, forskjellen, disse, hvorfor) are never a product type (`CatalogIntent::strip`).
6. A one word question that names a type, on a product or theme page, uses the page: the active product's verified value for that word if it has one, then a question back (the part for the product, or a product of its own), with no product guide button. A longer question that clearly starts a new search leaves the page.
7. Product lines are sentences: «Sør-Tre oppgir diameter 6 mm, lengde 16 mm og materiale Messing». A missing value is named only when the customer asked about it. For eye screws, hooks, shackles, chains, straps and rope with no verified working load, Søren says no working load is given and never states one.
8. Søren's voice: each conversational kind has a few wordings, and product answers get one short lead-in sentence. None of them states a fact from the catalogue. Warm and lightly playful, never scolding, and never pretending to be human (feature 015).
9. The product guide button is offered only when Søren asks what the customer needs (`avklaring` with no products), and is shown on its own line.

## Acceptance criteria

1. The handoff wordings (et or en menneske, person, noen, medarbeider, kundeservice, hjelp fra en person) start the support flow; «person» in a product question does not.
2. Identity, personality, rudeness, «er du?», «1 + 1», profanity, stop and store questions get a short reply with no product cards, also on a product page.
3. The three exact titles from the problem each give that product alone, with no metadata list.
4. The comparison follow-ups use the earlier products and never search for «passer».
5. «batteri» on a light's page gives the light's verified battery value and a question back, never an unrelated battery pack.
6. A genuine product request («Jeg trenger en hammer») still reaches the product search.

Regression tests: `tests/ConversationRoutingTest.php`.
