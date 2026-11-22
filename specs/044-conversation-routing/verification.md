# 044 Verification

## Implemented

`src/QuickReply.php` (intent gate, wordings, arithmetic, store from `warehouses`), `src/Chat.php` (profanity reply), `public/index.php` (event `handoff`, guide button only on `avklaring`), `public/assets/app.js` (handoff opens the support flow), `public/assets/app.css` (guide button on its own line), `src/ShopAnswer.php` (named product first, comparison, one word follow-up on a page, sentences instead of metadata, working load caution, lead-in sentence), `src/FactAnswer.php` (`overview`, `aboutSet`), `src/CatalogIntent.php` (follow-up words stripped; a plural found in a single name falls back to its base form), `src/I18nEn.php` (English for the new texts).

## Tested

`php tests/run.php`: 239 tests, 0 failed, including the 9 in `tests/ConversationRoutingTest.php` and the updated sentence check in `tests/ShopAnswerTest.php`. `php evals/run.php`: 131 cases, 0 failed. PHPStan level 3: no errors.

Run in the app (headless Chromium against `POST /api/chat` on the eye screw's product page): rudeness, the handoff (with the `handoff` event), the store question and a joke each got a fixed reply with no product; «Fortell meg kort om Øyeskrue 6 x 16 x 2.8 MS P6» gave that product alone with its verified values.

## Known weaknesses

The understanding in the tests runs without the model, so a material filter such as «øyeskruer i messing» is only applied when the model reads it. The comparison explains the ranking rule, not the customer's original words, because the earlier question is not kept with the product IDs. The store reply has no address or opening hours, because the catalogue does not hold them. The wordings are picked at random, so two answers in a row can repeat.
