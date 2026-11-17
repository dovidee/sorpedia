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

## Known weaknesses

1. Free text amounts from the model are not blocked at runtime.
2. The pre-model checks are regex patterns.
3. Personality, humor and latency are unmeasured against the real model.
4. The browser panel can show messages from a conversation that expired on the server.
