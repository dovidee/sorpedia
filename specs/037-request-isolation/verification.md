# 037 Verification

## Reproduction

A script (not in the repository) sent every request with the text it printed, each chat scenario in a new browser session and a new conversation, and the API calls with no history, all against H7R Core (product 291483) on the development server with four PHP workers and Ollama (deepseek-r1:8b).

| Scenario | Request | H9R in the answer |
|---|---|---|
| A | API: «Passer MH-ladekabelen til denne lykten?», nothing before | no |
| B | Chat: «Har dere H9R Signature?», then the API cable question | chat yes, API no |
| C | API cable question, then the chat H9R question in a new conversation | API no, chat yes |
| D | One conversation: H9R question, «Hva koster H9R Signature da?», «Hva koster H7R Core?» | yes, yes, no |
| E | Two conversations at the same time: H9R question and «Har dere H7R Core?» | yes, no |

In D the follow-up that names H9R again gets the notice from its own words, and the H7R Core question in the same conversation gets a normal answer with the H7R Core card and no notice. In E the H9R answer came back at once while the H7R Core answer was still being written by the model, so the two requests ran at the same time in separate workers.

The test script for 036 was checked: the API request body held «Er denne bedre enn H9R Signature?» and the log heading held the cable question, so the 036 log paired an answer with a question that was not sent.

## Gates

No code changed. `tests/run.php` 182 tests, 0 failed. `evals/run.php` 131 cases, 0 failed. PHPStan level 3 no errors. `check_code` and `check_docs` 0 findings.

## Known weaknesses

Concurrency was tested with two requests, not under load. The API cable answers were correct about the fit but wordy, and one ended with a greeting; that belongs to the answer-quality candidates in `spec.md`.
