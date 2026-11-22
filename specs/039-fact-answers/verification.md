# 039 Verification

Status: implemented, unit tested, run against the app (public API). Shop chat route wired but not run in the browser. Not finished: see Open.

## Changed

1. `src/CatalogIntent.php`: `ofType()` limits rankings to products whose name has the type word (base form), excluding products made for it («… for … hodelykt»). A named type with no match gives no list, never the whole store. English words use the search's translation. Stop words added to `scope()`.
2. `src/FactAnswer.php` (new): deterministic answers for price, cheaper/dearer, stock per store, specifications with level, differences, SKU/brand, fit (documented no / documented yes / rule-based likely / unknown), catalogue rankings. Returns `{text, ids, kind}` or null (model). Advice words («hvorfor», «hvordan», «anbefal…») go to the model.
3. `src/AnswerGuard.php`: public `mentioned()` wrapper around `named()`.
4. `src/Trace.php` (new), `config()['trace']` (`SOREN_TRACE`, default on), `.gitignore` `data/traces/`.
5. `src/Chat.php`: `streamOllama` wraps new `streamRaw` and traces the exact request body, thinking, text, timing; `answer()` traces grounding (facts, guard context) and each guard change (model text vs sent text) and the final answer.
6. `public/index.php` chat route and `src/PublicApi.php::ask`: `FactAnswer` before `Chat::answer`; route trace with timings; API returns `X-Trace-Id`; body fields unchanged (`model.used=false`, notice).
7. `src/Search.php`: empty-query return and docblock include `translated`.
8. `scripts/i18n_en.py` regenerated into `src/I18nEn.php`: English for every new sentence; spec labels and values through `I18n::spec`/`I18n::value`.
9. `tests/FactAnswerTest.php` (new, 10 tests): the six regression scenarios plus source level, store stock, routing to model, determinism.
10. Docs: `specs/039-fact-answers/spec.md`, row in `specs/README.md`, items 10 and 11 in `docs/architecture/architecture.md`.

## Gates (run)

`php tests/run.php`: 199 tests, 0 failed. `php evals/run.php`: 131 cases, 0 failed. PHPStan level 3: no errors. `check_code`: 0 findings. `check_docs`: 0 findings (after rewording one line). Retrieval eval: recall@5 1, precision@5 0.993 (unchanged).

## Live API run (H7R Core, product 291483, dev server, deepseek-r1:8b)

Answered by the server, model not called (total request time from curl): weight and stock 97 ms; MH cable 88 ms («Nei … passer ikke … dokumentert»); cheapest headlamp 45 ms (three headlamps, no hook or battery pack); H5R cheaper 64 ms (both prices, 300 kr difference); difference 58 ms; ML4 fit 88 ms (unknown, no yes/no); burn time 45 ms (unknown). In-process FactAnswer time 0.3 to 17 ms.

Model path: «Hvordan lader jeg den?» took 53.5 s (Ollama 53.4 s, retrieval 24.6 ms). The trace holds the real request (2 messages, think on, num_predict 4742) and response. The guard removed an internal ID and an empty reference. The answer is still wrong in substance: it recommends the MH cable, which Sør-Tre documents as not fitting H7R Core, and says it «passer perfekt»; the misfit guard did not catch it because the sentence names the cable without a fit verb next to both products in one sentence. It also shows SKU 600791 and the phrase «Lykken din».

The ninth question (mountain headlamp advice, model path) was still running when this was written.

Logs: `data/traces/2026-11-22.jsonl` (trace IDs in the run output); run results were kept outside the repository.

## Live rerun (Ollama online, same day)

Server path, public API, H7R Core: weight and stock 53 ms, MH cable «Nei» 32 ms, cheapest headlamp 15 ms (Norwegian) and 24 ms (English, headlamps only), H5R Core cheaper 22 ms, ML4 unknown 35 ms, burn time unknown 14 ms. All `model.used=false`. English «Does the MH charging cable fit H7R Core?» still lists the documented fits and misfits instead of naming the cable directly (Open 2).

Model path: «Hvordan lader jeg den?» 58.7 s (trace `a9b6ea218290faa4`). No MH cable recommendation this time, but the answer claims USB-C charging directly on the lamp, which the catalogue does not say (Lading: Magnetisk ladekabel); the guard does not check specification claims. The mountain-headlamp question first got HTTP 429 (the API allows `API_ASK_LIMIT`, default 10, questions per 10 minutes per client, and server answers count too). Run once more after the window: 70.3 s (trace `6dd6f67bb1313b2a`). Prices for HF8R Core (1 599 kr) and Osean Fokus 7000 (1 009 kr) match the database. Wrong: Fokus 7000 is a flashlight, not a headlamp; «Pentax K-5000» and «CoStar LK-SLXW» are not in the catalogue; placeholder text reached the customer («[vekt fra fakta?]», «([.]])», «[[har Sør-Tre dem?] neir]»), because `ProductRef::clean` removes double-bracket notes and a fixed list of single-bracket words, not free single-bracket notes or nested brackets.

Guard, offline against the first run's facts: the sentence «… anbefaler vi … ved hjelp av MH-serien – Ledlenser Ladekabel …» is now replaced by the documented misfit. The next sentence, «Denne kabelen … passer perfekt …», names no product and passes unchanged (Open 1 still applies to a fit claim made through a pronoun).

The empty `body` fields in the first run's results came from the run script's response parsing; the API returned a full JSON body when called directly.

## Open

1. Guard gap: a fit claim spread over two sentences on the model path (how-to answer above). Fix candidate: when the question is about charging or fit and a documented misfit exists for the focus product, the server states the fit part itself and the model only explains.
2. English «Does the MH charging cable fit H7R Core?» does not identify the cable by name; it falls back to listing H7R Core's documented fits and misfits (correct content, less direct).
3. Shop chat route not run in the browser.
4. Approach B (model explains a fixed answer) not built; see spec.
5. Chat-log markdown for the live run not written yet.

Open 1, 2 and 4 are handled in feature 040 (`specs/040-model-answer-grounding/verification.md`).
