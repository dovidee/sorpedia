# 035 Verification

## Automated

`tests/AnswerGuardTest.php` (11 tests, read only): totals over a pair and a trio of products in øre, duplicates counted once, a product not in the catalogue gives no total (not zero kroner) and an empty sum reference, the total questions are recognised and only plain ones skip the model, quantities are not totals, a sum reference with a product outside the facts is removed, a unit after a reference is not doubled, a wrong amount becomes the named product's own price, the right amount and the right total are kept, quantities are left alone, a price reference on H7R Core that points to H5R Core is moved to H7R Core, H5R Core's amount written next to H7R Core is corrected, the MH cable fit claim is replaced for both lamps, a correct «passer ikke» and unrelated sentences are left alone, documented and rule-based fits appear with their level and are never treated as misfits, and a bought-together product is not listed as a fit.

`tests/run.php` 171 tests, 0 failed. `evals/run.php` 131 cases, 0 failed. PHPStan level 3 no errors. `check_code` and `check_docs` 0 findings. `evals/retrieval.php`: same results as after feature 034 (recall 1, precision 0.993, exact match 12/12, follow-ups 4/4, compatibility 4/4, cards 28/28; the one held-out miss is unchanged).

## In the app with Ollama (deepseek-r1:8b)

One conversation through `/api/chat`, then the public API:

1. «Hva koster H5R Core?»: «Den koster 1 399 kr.» Correct, card H5R Core.
2. «Hva koster H5R Core og H7R Core?»: 1 399 kr and 1 699 kr, each on the right lamp.
3. «Hva koster de to sammen?»: answered by the server in under a second, 3 098 kr with each price; product IDs stored as before.
4. «Passer MH-ladekabelen til begge lyktene?»: says it does not fit; then mentions other models without need.
5. «Hvilken lader passer til H7R Core?»: names the magnetic cable as fitting; the card list showed only the lamp.
6. Complete set with «Hva blir totalen?»: the model used the server's sum reference, so 2 167 kr is right, but the sentence around it is muddled.
7. Follow-up: the model wrote that the MH cable fits; the guard replaced it with the documented «passer ikke». It also quoted 99 kr (the MH cable's price, which is in the facts) as «the cheapest cable» although that cable was not among the cards; this is a true price on a misleading comparison, which the guard does not catch.
8. «Hva koster H9R Signature?» (not in the catalogue): no cards, no price, but the model's sentence ends with a colon after the reference was removed, instead of saying the product is not sold.

Public API, H7R Core, «Passer MH-ladekabelen til denne lykten?»: says it does not fit, then adds an unrelated remark.

## Known weaknesses

The guard checks prices and documented misfits only; it does not judge wording, comparisons or claims about products the sentence does not name. A product is identified in a sentence by its model code or distinctive name words, so a vague mention («kabelen») allows any price that is in the facts. A fit claim is recognised by verbs such as «passer» and «kompatibel», so a fit stated in other words is not caught. Every product has a price in the current catalogue, so a missing price was tested with an unknown ID only. The 8B model still writes muddled sentences; the facts it can state wrongly are now limited.
