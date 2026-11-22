# 041 Verification

Status: implemented and verified: unit tested, run against the app (public API and the shop chat in a headless Chromium), first with `deepseek-r1:8b`, then with `qwen2.5:3b-instruct`.

## Changed

1. `src/Understand.php` (new): schema from `spec_keys`, `read`, `validate`, `rules`, `requirements`, `keys`.
2. `src/ShopAnswer.php` (new): recommendation, product in focus, cart, orders, clarification, `line`, `meets`.
3. `src/Chat.php`: `structured()`; `answer()` no longer takes a basis and has `$think`; `Chat::$calls` counts calls to Ollama.
4. `src/FactAnswer.php`: `focus()` public; a question with a budget or a limit on a value is a search, not a question about the product on the page.
5. `src/CatalogIntent.php`: `ofType()` prefers a type word that `Usage` knows («hodelykt» over «vanntett»).
6. `public/index.php` and `src/PublicApi.php`: the pipeline above; the API reads by rules past the model allowance; two unused helpers removed from the API.
7. `src/bootstrap.php`, `docker-compose.yml`, `README.md`, `src/Presentation.php`, `docs/architecture/architecture.md`: default model `qwen2.5:3b-instruct`. `docs/api/openapi.json`, `templates/api-docs.html`, `docs/integration/woocommerce.md`: the new answer path and limit.
8. `scripts/i18n_en.py`, `src/I18nEn.php`: English for every new template sentence.
9. `tests/ShopAnswerTest.php` (new, 14 tests); `tests/ModelAnswerTest.php`: the model allowance test expects a rule reading with 200 instead of 429.

## Gates (run)

`php tests/run.php`: 229 tests, 0 failed (with feature 042). `php evals/run.php`: 131 cases, 0 failed. PHPStan level 3: no errors. `check_code` and `check_docs`: 0 findings.

## Live (deepseek-r1:8b, before the model change)

Public API, product 291483, trace IDs in `data/traces/2026-11-22.jsonl`:

1. «Passer MH-ladekabelen til denne lykten?»: server, 173 ms, direct documented no.
2. «Hvordan lader jeg den?»: model reading 74 s; the reading had a requirement the customer never wrote (`krav:batteri:verdi`), which was dropped; answer: magnetic charging and the documented MH misfit.
3. «Hvilken av dere sine hodelykter anbefaler du til fjellturer?»: model failed (format error after 116 s), read by rules; three headlamps, no flashlight, HF8R Core's IP rating said as not registered.
4. «Jeg trenger en vanntett hodelykt med minst 500 lumen under 1000 kr»: read by rules after the model timed out; Ledlenser HF6R Core (889 kr, 500 lm, IP68), the only headlamp that meets all three on verified values.
5. «Hvor er bestillingen min?»: model reading 23 s; login prompt and customer service.

Shop chat in Chromium: the headlamp recommendation, the charging question and a greeting; no JavaScript errors; every answer from the database; each took over 90 s because of the reading. The greeting is now answered by feature 042.

## Rejected

1. The model choosing product IDs from an enum of eligible products: rejected in favour of PHP ranking, so the model never chooses which records are the answer.
2. Disabling deepseek-r1's reasoning by prefilling an empty think block: 0.9 s, but Ollama refuses it together with `format` («Failed to initialize samplers»).

## qwen2.5:3b-instruct (measured)

Reading only (`Understand::read`, warm model, 11 questions): 949 ms to 3462 ms; most between 1 and 3.5 s. Cold start: the first four readings after the model was installed hit the 10 s cap while the model loaded, and were read by rules. The chat loads the model when it opens (`/api/chat/warm`); the public API has no warm-up.

What the live readings showed, and what was changed (not individual claim patches, but the validation rules in the spec):

1. A cart question read as small talk: a cart or order word now sets the intent.
2. «minst 500 lumen under 1000 kr» read as 500 to 1000 lumen: a number for a property with a unit now counts only next to the unit.
3. An English use («hiking») or a free word («fjellturer») as a filter left nothing: such a value is now not used and the answer says so.
4. Earlier messages leaked into the reading, so a charging question and small talk were answered as the previous recommendation: the reading now uses the current message only.
5. Small talk written by the 3B model claimed that Sør-Tre does not sell headlamps: small talk is now a fixed reply.
6. Product cards from earlier in the conversation were shown with a cart answer and small talk: the cards are now always the answer's own products.
7. «den» after several products answered for all of them: now the page's product, else a question back.

Shop chat in Chromium after the changes, times including the browser: «Hei» 1 s, headlamp advice 4 s, «Hvordan lader jeg den?» 5 s (H7R Core only, magnetic charging, MH misfit), small talk 3 s (fixed reply), cart 2 s (no cards), the headlamp with limits 5 s (HF6R Core). No JavaScript errors.

## Known weaknesses

1. A follow-up that relies on an earlier message («og under 500 kr?») is read without it and gets a clarification or a broader list.
2. The ranking is fixed (stock, then price); a better product for the use is not preferred unless the customer names a requirement.
3. Text properties are matched by their label's first seven letters («vanntett» for «Vanntetthet (IP)»), so a property described in other words is not read by the rules.
