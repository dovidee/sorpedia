# 042 Verification

Status: implemented and verified: unit tested, run against the app over HTTP (shop chat and public API), latency measured.

## Changed

1. `src/QuickReply.php` (new).
2. `public/index.php` (shop chat) and `src/PublicApi.php::ask`: `QuickReply::match` after the blocked-input check; trace `route` with `ms`.
3. `src/Chat.php`: `Chat::$calls`, the number of calls to Ollama in the request.
4. `scripts/i18n_en.py`, `src/I18nEn.php`: English for the replies.
5. `tests/QuickReplyTest.php` (new, 4 tests).

## Gates (run)

`php tests/run.php`: 229 tests, 0 failed (with feature 041). `php evals/run.php`: 131 cases, 0 failed. PHPStan level 3: no errors. `check_code` and `check_docs`: 0 findings.

## Measured (dev server, HTTP round trip on the same machine, 30 requests each)

| Route | p50 | p95 | max |
|---|---|---|---|
| Shop chat, `POST /api/chat`, new conversation each time, 6 forms | 30.7 ms | 37.6 ms | 58.4 ms |
| Public API, `POST /api/v1/products/291483/questions`, 3 forms | 4.4 ms | 6.1 ms | 44.2 ms |

The chat time includes the session, the CSRF check, a new conversation and two history rows. The match itself took 2 to 6 ms in the trace. No Ollama call on a match: `Chat::$calls` is unchanged across four API questions in `tests/QuickReplyTest.php`, and the traces of these requests have no `ollama` stage.

## Evidence report: the most asked questions

The task was to derive fast paths for the 50 to 100 most asked customer questions from real data. The data does not support any.

### Sources

| Source | Content | Range | Usable |
|---|---|---|---|
| `chat_messages`, `role = 'user'` | Text of each chat question | 2026-11-21 17:25 to 2026-11-22 05:25 | 67 messages, 54 conversations, 40 owners, 36 distinct after normalization |
| `analytics_events`, `type = 'chat'` | Route and intent only, no text | 2026-11-17 to 2026-11-22 | 89 events; no text, so not usable for wording |
| `analytics_events`, `type IN ('search', 'search_zero')` | Search terms | 2026-11-17 to 2026-11-19 | 19 events, 6 sessions, 5 distinct terms |
| `support_messages` | Customer service thread | 2026-11-19 | 3 messages; customer service, not chat demand |
| `data/traces/*.jsonl` | Questions, model requests and answers | 2026-11-22 | Same questions as `chat_messages` plus API test runs; not counted again |

### Exclusions

1. Wording that appears verbatim in `tests/`, `evals/`, `scripts/`, `src/` or `templates/` is a test, an eval or text the app writes for the customer (a pre-filled button), not customer-authored.
2. Prompt-injection attempts and staff probes.
3. Rows created by the latency measurement above were made after the counts and then removed by ID: the 30 conversations of the measurement's single owner (60 messages) and the 30 `chat` events of its single session. The traces and the measurements are kept.
4. Real customer traffic cannot be told apart from development traffic: the demo runs locally, there has been no public deployment, and owners are anonymous hashes. No record could be confidently identified as a real customer, so the proportion of confirmed customer input is 0.

### Ranking (distinct conversations, then occurrences)

Reproducible with: `SELECT LOWER(TRIM(content)) q, COUNT(DISTINCT conversation_id) conv, COUNT(*) n FROM chat_messages WHERE role = 'user' AND created_at < '2026-11-22 05:30' GROUP BY q ORDER BY conv DESC, n DESC`. The questions are not quoted here because they were written during development.

1. 9 conversations (3 owners): the product guide's pre-filled follow-up question (`templates/guide_result.php`). App-authored: excluded.
2. 5 conversations: a question about a model the shop does not sell, worded as in `tests/UnknownModelTest.php` and `evals/retrieval.json`. Test: excluded.
3. Next 19: 2 conversations each (price questions, fit questions, injection and staff probes, multi-product totals). Below the three-session threshold.
4. The remaining 15: 1 conversation each.

Candidates with at least three distinct non-test sessions: 0. The list stops at 0; no question was added to fill it.

### Search terms (separate)

| Term | Sessions |
|---|---|
| hammer | 3 |
| hammmer | 2 |
| lomelykt, hodelykt, zzqxv | 1 each |

These are searches, not questions, and they go through the existing search (with its typo handling). No chat fast path was made from them.

### Not eligible

The product guide's pre-filled question is frequent because the app asks it. It needs the guide's products in context, so a fast path would be a deliberate business rule, not a most-asked question; not built.

### Fixed replies that were built

The greetings, thanks, identity, help, filler and weather and ferry replies in the spec are a fixed list, not derived from frequency. One greeting form («hei» with punctuation) occurs once in the data.

## Known weaknesses

1. With real traffic the ranking above should be run again; until then there is no evidence for a list of common product questions.
2. Weather and ferry words are matched only when the message names no product type; «regnvær» does not match «vær».
