# 038 Verification

## Automated

New: `tests/ApiGroundingTest.php` (7 tests, read only, real records): the selected product alone reaches the facts and the prompt when the retriever returns nothing; «den billigste hodelykten» and «best vurdert» keep H7R Core first with the catalogue list after it, at most five and no duplicates; a named second product (H5R Core) is added, not swapped in; H7R Core's facts carry its verified specifications with source and a stock reference for a named store; the MH cable question puts the documented «PASSER IKKE» with the cable's ID in the facts and the pair in the guard context, and «MH-Serie ladekabelen passer fint til H7R Core.» is replaced; a price reference and an amount from H5R Core next to H7R Core are corrected; H7R Core and the ML4 camping lamp have no fit relation, nothing about a fit is stated in the facts and the guard adds no misfit; an empty bold mark from a removed reference is removed and real bold is kept.

Existing: `tests/run.php` 189 tests, 0 failed (182 before, 7 new). `evals/run.php` 131 cases, 0 failed. PHPStan level 3 no errors. `check_code` and `check_docs` 0 findings. `evals/retrieval.php` unchanged (recall 1, precision 0.993, exact match 12/12, follow-ups 4/4, compatibility 4/4, cards 28/28).

## Live public API (H7R Core, product 291483, Ollama deepseek-r1:8b)

Contract: unknown product 404, form body 415, empty question 400, all `application/problem+json`; answers 200 with the same fields as before (`_links`, `answer`, `model`, `notice`, `product_id`, `products`, `question`, `reasoning`).

The model input was printed for each question by running the same retriever and `PublicApi::grounding` on it:

| Question | Retriever | Given to the model and the guard |
|---|---|---|
| «Hva veier den, og har dere den på lager?» | H7R Core | H7R Core |
| «Passer MH-ladekabelen til denne lykten?» | H7R Core, H/HF/P cable | the same; the MH misfit is in the facts |
| «Hva er den billigste hodelykten?» | three S-hooks (catalogue list) | H7R Core first, then the list (before: the list only) |
| «Er H5R Core billigere enn denne?» | H5R Core, H7R Core | H7R Core, H5R Core |
| «Passer laderen til ML4 campinglykten til denne?» | ML4, H7R Core | H7R Core, ML4 |

Answers: the weight (250 g, from the product text) and the stock for the named store (Kristiansand) were right. The H5R price comparison used the right price for each lamp. The ML4 question said a fit is not stated. The model's wording was still poor: in the MH answer it did not state the documented misfit clearly and wrote a link with a price as its text; in the cheapest question it named an S-hook as a headlamp.

## Found, not fixed here

`CatalogIntent` answers «den billigste hodelykten» with S-hooks and marks its list as the answer, so the model is given wrong facts for that question in both the shop chat and the API. This is a defect in the catalogue-wide lookup, older than this feature, and needs its own task. Links with a non-web target («[der](129 kr)») are not removed by `ProductRef::clean`, which only strips web addresses.

## Known weaknesses

The guard enforces prices and documented misfits only; it does not stop a vague or contradictory fit answer. The answer quality of the 8B model is unchanged by this feature.
