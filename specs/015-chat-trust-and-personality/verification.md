# Verification: 015 chat trust and personality

Levels used below: tested (automated test passed), run in the app (the HTTP route was exercised with curl against `php -S`), not verified.

## Phase 1: server-owned history

Implemented in `src/ChatHistory.php`, tables `chat_conversations` and `chat_messages` in `db/schema.sql` (created in existing databases by `ChatHistory::ensure()`), route `POST /api/chat` and `POST /api/support` in `public/index.php`, browser in `public/assets/app.js` (storage key bumped to `sorpedia-chat-v3`, so old browser history is dropped, not migrated).

Tested (`tests/ChatHistoryTest.php`, 14 tests): the request body gives only `message`; forged and edited assistant messages never reach the model; another owner cannot open, read or write a conversation; `forget()` gives a new owner and conversation; expired conversations are not used; `fresh` starts a new one; an empty answer saves nothing; fixed safety replies are kept for support but not sent to the model; history for the model is ordered and limited to 7; storage is limited to 40 messages; two PHP processes writing 8 pairs each into one conversation give 32 rows, seq 1 to 32, every answer directly after its own question; earlier user messages keep the user role and stay outside the instructions block.

Run in the app: a request with a forged `messages` array was ignored and nothing from it was stored; an override message got the fixed reply and was stored with `for_model = 0`; a second session got its own conversation with a different owner; `/api/support` built the case log from `chat_messages` and ignored a forged `log`; a request without `message` got 400.

Not verified: the browser side (panel, "Ny samtale", language switch, logout) was not clicked through in a browser. Login as a second account on the same session calls `ChatHistory::forget()`; that path has no test.

## Phase 2: factual integrity

Implemented in `src/ProductRef.php`. `Chat::facts`, `Chat::storeContext`, `CatalogIntent` and `Guide::brief` give the model `[[pris:ID]]` and `[[lager:ID]]` instead of numbers, with price order and stock in words. While streaming, references to products not in the facts are removed and the rest are filled in from the database. Answers are stored with references. `Chat::$lastOpen` and `Chat::$lastTrusted` were removed; the doc comments on `Chat` and `Chat::answer` now describe the filters that exist.

Tested (`tests/ProductRefTest.php`, 6 tests): no amount in kroner and no stock count in the facts; only referenced products resolve; price and stock equal the database; a stored answer shows a changed price; the guide summary and catalog overview carry references.

Run in the app against a stub of Ollama's `/api/chat` stream: the customer saw "Den koster 439 kr og vi har den på nettlager" (database values for product 298381), `chat_messages` stored `[[pris:298381]]` and `[[lager:298381]]`, and `[[pris:1]]` (not in the facts) was removed.

Rejected: a regex that blocks amounts in free text. An amount the model writes in its own words still reaches the customer; `evals/soren.php` flags it.

## Phase 3: personality

Implemented: new persona and nine rules in `src/Chat.php`. Factual and safety rules are kept; small talk, jokes and mascot humor are allowed; the six-sentence cap and "no talk about himself" are gone. The fixed safety checks moved into `Chat::blocked()`.

Tested (`evals/run.php`, 19 cases from `evals/soren.json`): 6 messages get the expected fixed reply, 13 casual and product messages reach the model.

Not verified: the 13 model cases in `evals/soren.php` were not run against deepseek-r1:8b, because Ollama is not installed on the development server. The runner exits with code 2 and says so. Against the stub it ran and its price check flagged the invented "99 kr".

## Phase 4: context

Implemented: the guide summary follows into the chat for three hours, until the customer starts a new conversation (`ChatHistory::carriesGuide`). Search context was left as it was: the search page context uses the same search as the search page.

Tested (`tests/PageContextTest.php`, 6 tests). Prompt size, measured with `Chat::prompt` for the same three questions, HEAD versus now: greeting 2404 to 3012 characters, price question on a product page 3806 to 4610, flashlight question on the search page 7263 to 8534. The growth is the longer persona and rules and the references. Latency was not measured: no model.

Rejected: front page context. Not needed for any case in the spec.

## Phase 5: development workflow

Moved to user level: the working rules hook. Removed from the project: the model-based personal context check that duplicated the user level one, and the attribution check in `.claude/hooks/check_code.py` with its two doc rows. Added: PHPStan level 3 (`phpstan.neon`, `scripts/tools.sh` with a pinned version and SHA-256), a formatting check in `.claude/hooks/check_code.py`, PHPStan in the finish gate. PHPStan found four issues at level 3, fixed: a possibly undefined variable in `public/index.php`, a missing `unit` key in `scripts/import.php`, and two unused closure captures.

Run: `scripts/tools.sh` downloaded and verified the checksum; the finish gate was run by hand and reported stale doc references, which were then fixed; the formatting check fired on a probe file.

## Phase 6: mascot as a vector figure

Søren is drawn as SVG in `public/assets/soren.js`: a trunk, separate leaves in the crown, arms on shoulder pivots and props per pose. All motion is CSS transforms in `app.css`. Poses: default (front page, waves on load), wave, thinking and pointing in the chat, celebrating on success, shrug on fallback or error, cart (empty cart), hammer (404), lumber (product guide and empty inbox). `soren-art.js` is no longer loaded.

Rejected: the raster sprite sheets. They carry a fading watermark in the last five frames and dark matte specks around the hands and feet that change per frame.

Run: all nine poses and the chat head rendered in headless Chromium on #221C1C and white, at rest and mid-animation; `/guide` and the 404 page served by the dev server and rendered with the right pose. The empty inbox was not opened (needs a logged in user without mail); its template passes `php -l`.

## Phase 7: violence check, product knowledge, charging

Violence check: `Chat::violence()` (harm word within 20 characters of a fixed person list) is replaced by `src/Violence.php`. A clause is a threat only with a harm verb, a person as its object (pronouns, relationships, occupations including compounds, people in general, capitalized names that are not catalog brands) and intent (asks how, wants to, will, an order). Negation, prevention and protection before the verb, reflexive accidents («skadet seg»), third party reports without intent, fiction without «på ekte», and harm to objects go to the model. `Chat::blocked()` calls `Violence::threat()`.

Product knowledge: `src/Usage.php` gives each product type a main use, what else is needed (linked to catalog products by name, or stated as not sold here) and the properties that decide between similar products. Rule based on the name, so Søren gets it marked as derived. `Chat::storeContext` compares on those properties first, and links products across the store only on properties that decide fit (socket, battery, charging, chain and file sizes, arbor hole, glass height), not length or weight. The product page shows unknown rows only for those properties when a rule knows the type. `Extract::charging` reduces Sør-Tre's charging sentence to the method (USB-C cable, magnetic cable, solar cell); the 13 existing `lading` values were rewritten in the database with it. `ProductRef::clean` removes bare product IDs the model copies from the facts.

Tested: `tests/ChatSafetyTest.php` (29 tests) and `tests/UsageTest.php` (10 tests), 98 tests in total pass. `evals/violence.json`, 50 labeled cases in Norwegian and English (threat, harmless, protective, negation, object, accident, fiction, ambiguous, regression): precision 1.00, recall 1.00; `evals/run.php` 131 cases pass. PHPStan level 3: no errors.

Run against Ollama (deepseek-r1:8b): `evals/soren.php` for the three new cases passed the deterministic checks (42 to 119 s). Søren now names the right charging methods for headlamps, the real price of the plug for an eye screw, and no longer claims the store sells screwdrivers. The threat message got the fixed reply; the tree felling safety question reached the model. Product pages for the stove, a headlamp and an eye screw were served by `php -S` with status 200.

Not verified: the chat panel in a browser. Answer quality on review is weak: the model invented accessories («stopper» for a hammer) and wrote uneven Norwegian, and the tree felling question got a product search hit on «formtre» because search matches «tre» inside words.

Follow up: search, IP rating, product page.

Search: words under four letters must be a whole word in the product text and start a word in the name, so «tre» no longer finds «formtre». A typo is never corrected to a stop word («bor» became «for»), and «feller» is a chat stop word («filler»). IP rating: `Extract` reads «IPx-4», «iP67» and «IP 54», and maps Beskyttelsesgrad, Beskyttelsesklasse, Vannresistens and similar labels to `ip_klasse`. The existing database got 5 `ip_klasse` rows from the stored product HTML and lost 8 duplicate `x_` rows. Product page: a «Bruk og tilbehør» section shows the main use and needs from `Usage::needs`, with store products as cards and a «Selges ikke her» line, marked Utledet. A product type with a rule shows unknown rows only for its deciding properties, and none when it has none.

Tested: `tests/SearchTest.php` (5) and two new `tests/UsageTest.php` cases, 105 tests pass; `evals/run.php` 131 pass; PHPStan no errors. Run in the app: the toaster and eye screw pages rendered in headless Chromium with the new section; the headlamp has no unknown IP row; the English labels show with the English cookie. The rule texts themselves stay Norwegian in English mode.

Follow up: crime, rule references, thinking time.

Crime: `Violence::crime` blocks a customer who asks how to, or says they will, rob, steal, break in, defraud, launder or smuggle, with a fixed reply before the model. Same clause logic as the violence check: intent is required, and «hindre», «unngå», «beskytte» and similar turn it into prevention. Only verbs are listed, so «innbruddsalarm», «tyverisikring» and «ransel» pass, and a victim («ble svindlet», «ble stjålet») reaches the model. The safety rule in the prompt names crime too. Rule references: the prompt rules are no longer numbered, and `Chat::hideRules` removes «regel 6» style references from the reasoning and the answer before they are sent. Thinking time: the thinking has a time budget (`SOREN_MAX_THINK_S`, 240 s) instead of the old 1500 character cap. Thinking and answer share the tokens left in the context window. When the thinking is stopped or runs out of tokens, a second call writes the full answer from it (1200 tokens, 180 s). Text cut by a deadline or the token budget ends at the last whole sentence (`Chat::wholeSentences`). `SOREN_FIRST_TOKEN_S` is 60 and `SOREN_MAX_ANSWER_S` 480, a guard against a hung model only. `ProductRef::clean` also removes markers without an ID («[[pris]]») and brackets they leave empty or with only a lead-in.

Tested: 15 sample messages against `Violence::crime` (robbery, theft, breaking in, fraud blocked; alarms, theft protection, a backpack, a victim and fiction pass), all as expected; `Chat::wholeSentences` and `ProductRef::clean` on sample strings; `tests/run.php` 105 pass; `evals/run.php` 131 pass. Run against Ollama (`deepseek-r1:8b`) through `/api/chat`: a normal question finished in 54 s with no cut and no fallback; with `SOREN_MAX_THINK_S=15` the thinking was stopped, the `think_cut` note was sent, and the second call wrote a complete answer ending on a whole sentence, with no catalogue fallback; two crime questions got the fixed reply.

## Known weaknesses

1. Free text amounts from the model are not blocked at runtime.
2. The pre-model checks are rule based. The violence check covers the people, verbs and intents in its lists; a threat in words it does not know reaches the model, which has its own rule.
3. Personality, humor and latency are unmeasured against the real model.
4. The browser panel can show messages from a conversation that expired on the server.
5. The mascot motion was checked as still screenshots, not watched live in a browser.
6. Main use and needs come from name rules; product types no rule knows get none.
7. The use and needs texts on the product page are Norwegian only.
8. The crime check covers the verbs in its list; other wording reaches the model, which has the safety rule.
9. The model sometimes wraps a price reference in extra brackets («([1 269 kr])») or writes a product number that is then removed, leaving «varenr.».
