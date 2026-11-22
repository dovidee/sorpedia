# 036 Verification

## Automated

`tests/UnknownModelTest.php` (11 tests, read only): «Har dere H9R Signature?» and «Hva koster H9R Signature?» give `unknown` = H9R Signature and no products, and the exact sentence; H7R Core is found first with nothing unknown and no notice; H7R Core and H9R Signature together keep H7R Core and name H9R Signature, and the fact line forbids describing or comparing it; H9R and XT40 are both named in one sentence; three descriptions without a code give nothing unknown; H7RR, HR7, H7 and HF6 (typos and parts of catalogue codes) give nothing unknown; E27, IP67, P120, K80, 18V, 2x4, CR2032, EN352, M8 and GU10 give nothing unknown; a follow-up «Er H9R bedre enn den?» with H7R Core in memory keeps H7R Core as the only product and names H9R; the sentence never has a colon, an ID or a reference and ends with «i sortimentet.»; the brand before a code is kept, a sentence-initial word is not, and markup is removed; on the API's product page path the endpoint product stays and H9R is named.

`tests/run.php` 182 tests, 0 failed. `evals/run.php` 131 cases, 0 failed. PHPStan level 3 no errors. `check_code` and `check_docs` 0 findings. `evals/retrieval.php`: unchanged (recall 1, precision 0.993, exact match 12/12, follow-ups 4/4, compatibility 4/4, cards 28/28).

## In the app with Ollama (deepseek-r1:8b)

Through `/api/chat`:

1. «Har dere H9R Signature?»: «Vi har dessverre ikke H9R Signature i sortimentet.» in under a second, no cards, the model was not asked.
2. «Hva koster H9R Signature?»: the same sentence, no price, no colon.
3. «Har dere H7R Core?»: normal pipeline (63 s), H7R Core card, product ID stored.
4. «Hva er forskjellen på H7R Core og H9R Signature?»: the sentence first, then the model (52 s) with the H7R Core card. It gave no details for H9R Signature, but wrote «varenr.» in front of a price and mentioned its rules; wording, not facts.
5. «Har dere en kraftig hodelykt?»: normal pipeline, no notice. The model's answer gave a product a name and numbers that are not in the facts; this is the model's answer quality and was the same before this feature.
6. «Har dere H7RR Core?» (typo): no notice, the model answered (37 s) that it is not a product here.
7. «Er H9R bedre enn den?» after the typo question: that answer stored no products, so there was nothing in memory and the sentence was the whole answer. The follow-up with a product in memory is covered by the automated test, not run against Ollama.

Public API, H7R Core, «Er denne bedre enn H9R Signature?»: 200 with the same fields, the sentence first, then the model's answer about H7R Core (119 s). The model also said that «many say» a 500 lumen lamp is weaker, which is not from the facts.

## Known weaknesses

The confidence rules are hand-made: a real model with one letter and digits (a «P7» lamp) is never reported, and a code one edit from a catalogue code with the same digits is treated as a typo, so HF7R is not reported. A model named by words only («Signature» without H9R) is not detected. A brand is only recognised as a capitalised word right before the code. The model's part of a mixed answer can still be vague or wordy; it is told not to describe the missing model, and the server does not check that beyond the existing price and fit checks.
