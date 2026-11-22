# 040 Verification

Status: implemented, unit tested, run against the app (public API and the shop chat in a headless Chromium). Limitations below.

## Changed

1. `src/AnswerGuard.php`: `grounding()` (question type, documented fits, evidence per product, known words), `violation()` with the checks in the spec, `placeholder()`, `clearly()` (a product is named, not described), English fit and negation words.
2. `src/FactAnswer.php`: `basis()` for the factual part of advice questions; charging questions list the documented chargers and misfits; «lader» is a charging word; English fit questions are matched on the translated question.
3. `src/CatalogIntent.php`: public `typeOf()`; the stop word list is one function (`strip()`) and drops recommendation words.
4. `src/Chat.php`: `answer()` takes the basis and the text already shown, sends the basis first, validates each answer sentence, stops and replaces on the first invalid one (`answer_reset`), traces `rejected`, `Chat::$rejected`.
5. `public/index.php` (shop chat) and `src/PublicApi.php::ask`: pass the basis; the API handles `answer_reset`, sets `notice` on a rejection, collapses blank lines; two limits (`question`, `model`) through `PublicApi::limited()`; the table is only created when missing, so tests can run in a transaction.
6. `src/bootstrap.php` and `docker-compose.yml`: `API_QUESTION_LIMIT` (60). `docs/api/openapi.json`, `templates/api-docs.html`, `docs/integration/woocommerce.md`: the two limits.
7. `scripts/i18n_en.py` and `src/I18nEn.php`: «charging cable», «charge cable», «cable»; the English fallback sentence.
8. `tests/ModelAnswerTest.php` (new, 12 tests).
9. Docs: this spec, row in `specs/README.md`, item 12 in `docs/architecture/architecture.md`.

## Gates (run)

`php tests/run.php`: 211 tests, 0 failed. `php evals/run.php`: 131 cases, 0 failed. PHPStan level 3: no errors. `check_code`: 0 findings. `check_docs`: 0 findings after rewording one line.

## Regression tests (tests/ModelAnswerTest.php, real catalogue products)

Rejected: «Pentax K-5000», «CoStar LK-SLXW» (Norwegian and English); Osean Fokus 7000 in a headlamp recommendation (Norwegian and English); USB-C on H7R Core (Norwegian and English); a charging claim without a named product; 180 gram, 12 hours, 1200 lumen on H7R Core; IP67 on HF8R Core; seven placeholder forms; the MH cable «passer perfekt» in the sentence after it was named, «It fits your lamp», a recommendation of the MH cable; «Batteri 21700 passer ikke» (documented fit). Passing: products in the facts, 250 gram and 1000 lumen from Sør-Tre's text, «minst 500 lumen», magnetic charging and a denial of USB-C, IP67 on H7R Core, valid references, the misfit said as a misfit, the documented battery fit, «3,7V-batteri». The MH cable is answered by name with a direct no in English and Norwegian. «Hvordan lader jeg den?» still goes to the model and gets a basis with the charging method and the documented misfit. With the model allowance one short of full, a server answer leaves the last slot free, the next model question gets 429 naming the language model, server answers still get 200 with every response field; with the general allowance full, a server answer gets 429.

## Live API (dev server, deepseek-r1:8b, product 291483)

Server path, unchanged: weight and stock 74 ms (`6ad1482566e18990`), MH cable «Nei» 39 ms (`4a3052aad176eff6`), cheapest headlamp in English 22 ms, headlamps only (`71e78de8d8a00a88`), MH cable in English «No, … does not fit» 33 ms (`02e77dc1ba3ec588`), burn time unknown 12 ms (`66e3b5d0fd4915c2`). All `model.used=false`, same response fields.

Model path:
1. «Hvordan lader jeg den?» 51.1 s (`2f7f4510f9483537`): basis first; the model's text passed then, but contained a solar charging claim in a sentence without a product name. The check was widened (charging claims fall back to the product in focus) and a test added. Rerun 32.5 s (`afb40158980f7e12`): rejected (`oppdiktet_produkt` on «3,7V-ladestykker», a false positive fixed afterwards and tested; the same sentence also claimed USB-C). The customer got the basis and the fallback sentence, `notice` set.
2. «How do I charge it?» 39.5 s (`9bca17221775965b`): rejected (`udokumentert_spesifikasjon`), English basis and fallback sentence.
3. «Hvilken av dere sine hodelykter anbefaler du til fjellturer?» 44.3 s (`1cb278985172a731`): rejected (`plassholder`), catalogue answer shown. Rerun 84.4 s (`9aec08add194e8fb`): passed, recommends HF8R Core, a headlamp in the facts, but says it is IP67, which the catalogue doesn't have for HF8R Core. The IP rating check was added after this run and is covered by a test; that question was not run live again.
4. Two calls in between failed with `nede` in 0 ms because the connection to Ollama was down; the API then gave the basis or the rule-based answer. Rerun once Ollama answered (items 2 and 3).

Rejections were common in these runs: on this model most advice answers end up as the server's answer.

## Browser (headless Chromium through Playwright, shop chat on the H7R Core page)

«Passer MH-ladekabelen til denne lykten?»: answer in 2 s, «Nei … passer ikke … dokumentert», product cards shown. «Hvordan lader jeg den?»: 47 s, the reasoning panel, then the server's charging facts (method, no documented charger, MH cable does not fit), then the model's text. No console errors. Screenshots were kept outside the repository. A rejection and its `answer_reset` were not triggered in the browser run; the event handler is the existing one in `public/assets/app.js`.

## Known weaknesses

1. The checks cover the claim types above. A model sentence that is wrong in another way passes: in the browser run the model wrote that H7R Core «kan ladet via et batteri med 21700-batteri … varenr. 339 kr», which is garbled but names no invented product, method or unit.
2. A product named only by generic words is not treated as named, so a claim about it through those words is not checked against its relations.
3. «lader» as a product type: a recommendation question about chargers limits the answer to products with «lader» in the name.
4. The English basis keeps Norwegian specification values («Magnetisk ladekabel») where `I18n::value` has no translation.
5. A widened check can also reject a general remark («Solceller er fint på tur») when the question is about a single product.

## Superseded

Feature 041 removed the model from product answers. The basis and validation path above is no longer on a customer path.
