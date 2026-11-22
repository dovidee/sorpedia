# 034 Verification

## Baseline first

`evals/retrieval.json` was written by hand from the catalogue before `ProductRetriever` existed: IDs, SKUs, prices and the relations table were checked in the database for every case. The earlier orchestration was run on it and saved (`specs/034-product-retrieval/baseline.json`). One defect in the eval itself was found on the first run and fixed in both modes: an integer recall of 2/2 did not equal the float 1.0, so follow-ups and compatibility were under-reported. The expectations were not changed.

## Results

Same 28 questions, same database, same run (`specs/034-product-retrieval/after.json`):

| Metric | Before | After |
|---|---:|---:|
| Recall@5 | 0.631 | 1.000 |
| Precision@5 | 0.470 | 0.993 |
| Exact match (first product) | 7/12 | 12/12 |
| Follow-up resolution | 0/4 | 4/4 |
| Compatibility | 1/4 | 4/4 |
| Irrelevant-hit rate | 0.611 | 0.019 |
| Card consistency | 24/28 | 28/28 |
| Time per question | 6.4 ms | 3.7 ms |
| Database statements per question | 4.1 | 1.9 |

The 18 brief cases were used while the retriever was built, so its score on them is optimistic. The 10 held-out cases were written after the first round of tuning: on the first run the retriever passed 7 of 10 and the earlier code 2 of 10. Two of the three held-out misses exposed defects in mechanisms rather than in weights: products found through a use case («henge opp bilde» means «bildehempe») got no points because the search's expansion words were ignored, and an asked-for accessory ranked above the product it belongs to. Both were fixed as general rules, and the same fixes also changed the brief case «flere-krav». After the fixes the retriever passes 9 of 10 held-out cases. The remaining miss is kept: «Har dere Showa Temres 282 hansker?» also returns one Showa CS720 glove, because brand and the word «Showa» outweigh the relative cut-off.

Rejected in testing: matching the start of any compound word let «motorsag» pull in files for saw chains and «sagkjeden» count twice through the search's corrected word; the compound rule now needs the customer's whole word (five letters or more), each word counts once, and an unknown model code keeps word hits out. A synonym («fil» means «rundfil filsett») brought in the 4,8 mm file for a chain that takes 4,0 mm; when relations answer an accessory request, unrelated hits for the same accessory are left out.

## Memory and isolation

`ChatHistory::ensure()` added `product_ids` on the development database; running it twice did nothing the second time, and the 76 existing rows kept NULL. A scratch script (not in the repository) made two conversations with different owners and checked `lastProducts`: the owner got `[291482, 291483]` (a duplicate and a negative ID were dropped on save), another owner and another conversation got nothing, an ID not in the catalogue and a text value were dropped on read, an answer about no product ended the memory, and an expired conversation gave nothing. Its rows were deleted afterwards.

## In the app

Through `/api/chat` with Ollama (deepseek-r1:8b): «Hva koster H5R Core og H7R Core?» showed the H5R Core and H7R Core cards and stored `[291482, 291483]` on the answer; the follow-up «Hva koster de to sammen?» showed the same two cards, stored the same IDs, and the answer gave 1 399 kr and 1 699 kr from the database references. The model did not add the prices together, and it claimed the MH charging cable fits both lamps although the facts list it as «PASSER IKKE»; both are answer quality, not retrieval, and belong to the prompt work that follows. Through `/api/v1/products/291483/questions` («Passer batteriet til H5R Core i denne lykten?») the retriever gave H5R Core (model), H7R Core (page) and the documented 21700 battery (relation); the response listed H7R Core, since the API lists only products named in the answer, as before.

## Gates

`tests/run.php` 161 tests, 0 failed. `evals/run.php` 131 cases, 0 failed (includes the store search golden cases, so `Search::run` behaves as before). PHPStan level 3 no errors. `check_code` 0 findings, `check_docs` 0 findings. `php -l` on every changed PHP file.

## Known weaknesses

The weights are set by hand and tuned on a small set; 28 cases is not a benchmark. The Norwegian base forms are a few suffix rules, not a dictionary. Reference words such as «det» are common, so a question that uses «det» without referring back can still bring back the previous products when no new product is named. The public API has no product memory between questions. Model codes live only in product names; a product whose name lacks its code can only be found by SKU or words.
