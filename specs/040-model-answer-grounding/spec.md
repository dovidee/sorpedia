# 040 Model answer grounding

Status: built. Priority: P1 (trust), P5 (public API).

## Problem

Feature 039 took the model out of factual answers. Advice and how-to questions still go to the model, and live runs showed the model inventing products that are not in the catalogue («Pentax K-5000»), recommending a flashlight for a headlamp question, claiming USB-C charging on a lamp the catalogue documents as magnetic, leaving unfilled notes in the text («[vekt fra fakta?]», «[[har Sør-Tre dem?] neir]»), and calling a cable that Sør-Tre documents as not fitting «perfekt» in the sentence after it was named. The public API also counted questions the server answers in milliseconds against the same limit as model questions.

## Decision

PHP decides what is true; the model may explain it. This is approach B from feature 039, combined with validation of the whole answer:

1. `FactAnswer::basis()` writes the factual part of an advice question with the same code as `FactAnswer::answer()` (no second answer engine). A charging question also gets the documented chargers, cables and misfits for the product, so a misfit is never left to the model. The route sends the basis first.
2. The model gets the basis in the facts as a fixed answer to explain, not to repeat, change or contradict.
3. `AnswerGuard::violation()` checks each answer sentence on the model's own text before it is sent. Reasons: `plassholder`, `oppdiktet_produkt`, `utenfor_utvalget`, `feil_type`, `udokumentert_spesifikasjon`, `motsier_passer_ikke`, `motsier_passer`.
4. The first invalid sentence stops the model. Nothing is cut out of a sentence to make it pass. The chat gets the event `answer_reset` and then the basis (or, without one, the catalogue's rule-based answer) with a sentence that says no more can be said for certain. The API sets `notice` with the reason. The trace has a `rejected` stage with the model's sentence and what replaced it.

## Checks

1. Products: a word with letters and digits («K-5000») or a brand-like word («CoStar», «LK-SLXW») that is not in the names of the products in the facts, the question, the page or the facts. Unknown to the whole catalogue: `oppdiktet_produkt`; in the catalogue but not among the products the answer is grounded on: `utenfor_utvalget`. Technical words (USB-C, IP67, 21700 cells, units) and compounds with a Norwegian word («3,7V-ladestykker») are not codes.
2. Product type: when the question asks for a recommendation and names a type («hodelykter», «headlamp» through the search's translation), every product the answer names must be of that type (`CatalogIntent::typeOf`) or related to the product in focus.
3. Specifications: a charging method (USB-C, solar, AA or AAA) about the named product, else the product named before, else the product in focus; an IP rating or a number with a unit (gram, kg, lumen, mAh, hours, volt) about a product the sentence names or points back to. The value must be in the product's evidence: its name, Sør-Tre's product text and its verified values. A number after «minst», «opptil», «at least» and similar is advice, not a claim. Unknown stays unknown.
4. Unfilled notes: any bracket left after the valid references (`[[pris:ID]]`, `[[lager:ID]]`, `[[sum:…]]`, bare product IDs, Markdown links), a question in parentheses, TODO, TBD, FIXME and upper-case prompt labels such as «VERIFISERT:». This is structural, not a list of forbidden notes.
5. Fit: a claim that a pair of products fit when Sør-Tre documents «passer ikke», and a denial when Sør-Tre documents «passer sammen». A sentence without a product name that points back («den», «denne kabelen», «it») is read against the products named in the sentence before.

A product counts as named in a sentence by its reference, its model code (a code that is only a number needs the name's first word), or one of its own name words that the product in focus does not have in its own text, so «magnetisk ladekabel» describes H7R Core's charging and doesn't name the MH cable.

## Rate limits

`PublicApi::limited($client, $bucket, $limit)` counts per hashed client and bucket in `api_question_log`. Every question counts in `question` (`API_QUESTION_LIMIT`, default 60 per 10 minutes). Only questions that reach the model count in `model` (`API_ASK_LIMIT`, default 10). Both give 429 with `Retry-After`; the problem detail says which limit was reached. The response body is unchanged.

## English

«Does the MH charging cable fit H7R Core?» names the cable through the search's English word list («charging cable» is «ladekabel»), so the answer is a direct documented no.

## Not in scope

No new model, no embeddings, no second fact engine, no change to the deterministic answers of feature 039 apart from charging questions listing the documented chargers. The validation reads meaning only through these claim types: a sentence that is wrong in another way («lades via et batteri») is not caught.

## Acceptance criteria

1. Each known failure from the 039 live runs is rejected by a regression test: invented products, a flashlight in a headlamp recommendation, USB-C on H7R Core, an unsupported weight, single and nested notes, drafting notes, a fit claim through a pronoun, a denial of a documented fit.
2. The MH cable is identified directly in English and Norwegian.
3. A server answer does not use the model allowance; a model question does; the general limit still applies.
4. Live API: factual answers unchanged and fast; model answers either pass validation or are replaced with a notice.
5. The shop chat answers in a real browser, with the server's part first.
