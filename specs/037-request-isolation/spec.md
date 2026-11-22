# 037 Request isolation for the not-in-range notice

## Question

A manual test log for feature 036 showed a public API answer about H7R Core that started with «Vi har dessverre ikke H9R Signature i sortimentet.», under the heading of a question about a charging cable. Could a notice from one request reach another request or conversation?

## Finding

No. The log was wrong, not the server. The test script for 036 replaced the API question with «Er denne bedre enn H9R Signature?» in the request body, but the heading in the log was a separate string that was not replaced. The answer belonged to the question that was actually sent, so the notice was correct.

## Lifecycle checked

1. The notice is made in the request that needs it: `ProductRetriever::forChat` reads only the last user message (`Chat::lastUser`) and returns the unknown codes in `unknown`; `AnswerGuard::notSold` turns them into the sentence; `public/index.php` and `src/PublicApi.php` use it in the same request.
2. Persistence: in the shop chat the sentence is saved as part of that conversation's own answer in `chat_messages`, owned by the conversation's owner hash. Nothing reads saved answers back to make a notice; a later question gets a notice only if it names an unknown code itself. The public API saves nothing about questions except a rate-limit row (`api_question_log`: client hash and time); its history comes only from the request body.
3. Shared state: PHP resets static variables for every request, also with `PHP_CLI_SERVER_WORKERS`. The two static caches in `ProductRetriever` (`catalog()` and `surelyModel()`) hold catalogue data only. `Chat::$lang`, `$stop`, `$lastError`, `$lastThinking` and `$cutShort` are set per request. Nothing is cached between requests.

## Change

None to the code. The isolation scenarios were run against the running app (see `verification.md`).

## Candidates for a separate task (answer quality, not isolation)

The model's own text can still contain claims attributed to unnamed people, a price written where a product number belongs, statements about models the shop doesn't sell, specifications that are not in the facts, and clumsy wording in answers that mix a notice with a known product. The price and fit checks (035) and the notice (036) are not affected.
