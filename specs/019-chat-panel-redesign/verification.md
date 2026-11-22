# Verification: 019 chat panel redesign

Levels used below: run in the app (`php -S` on a separate port, headless Chromium), tested, syntax checked, inspected, not verified.

## Run in the app

A product page was loaded at 390, 820 and 1366 px in light and dark color scheme. At every width and scheme the launcher opened the panel, the panel background was pine-charcoal (rgb 24 34 31), focus landed on the message field, the context field showed the product name, `scrollWidth` equalled `clientWidth` for both the page and the panel, and Escape closed the panel and moved focus back to the launcher. No page errors were logged. The header status line stays on one line at every width; on phones the static part is cut with an ellipsis when it does not fit, and the live status after it is never cut.

A message was sent on `/tema/lys` at 1366 px with Ollama running (`deepseek-r1:8b`). The answer streamed with the reasoning block, four product cards appeared under «Fra produktkatalogen» with name, price and stock, and the customer message was amber and right aligned. The log had `aria-busy="true"` while the answer streamed and `false` when it was done.

On-screen keyboard, simulated by shrinking the viewport height from 844 to 500 px with the panel open at 390 px: the panel followed `visualViewport` and the message field ended at 468 px, inside the 500 px visible area.

Phone run (headless Chromium with touch and mobile emulation, 390 x 844 and 320 px wide, scale 3):
1. Launcher over the dark `/tema/lys` band: `elementFromPoint` under the launcher is the band (oklch 19%), the birch ring is in the computed `box-shadow`, and a screenshot shows the outline against the band.
2. At 320 px the page was 15 px wider than the screen, caused by the header search keeping its natural width. `.search` now has `min-width: 0`; `scrollWidth` equals the screen width at 320 and 390 px on `/`, `/tema/lys` and `/tema/skog`.
3. Opened by tap: the panel fills the screen and focus is on the message field. With the viewport shrunk to 480 px the message field ends at 448 px.
4. Reduced motion emulated: the panel's `chat-in` runs for 0.01 ms (global rule) and opacity is 1 at once.
5. Accessibility tree: region «Chat med Søren», buttons «Ny samtale», «Lukk», «bare produktinfo» and «Send», a log, and the message field labelled «Spør Søren».
6. The «bare produktinfo» label is now a button. A tap opens the explanation below it and sets `aria-expanded="true"`. The tap area is 44 px tall. On a customer's own order (logged in demo user, `/ordre/{id}`) the label reads «dine ordrer». Its explanation lists what Søren gets: items, date, status, payment method and return deadline, never name, email, phone, address, password or the amount. The intro text says the same. `PageContext` was read to confirm the order context has no name, email, phone, address or amount.
7. English (`/sprak/en`): `lang="en"`, the intro, context field, placeholder and explanation are English, and an English question got an English answer from Ollama.
8. With Ollama: the robbery question got the fixed reply, and «what are my name and address» on an order page got an answer without them.

## Tested

The first streamed answer showed raw markers without a product ID («[[id]]», «[[pris]]»). `ProductRef::clean` now removes such markers and any brackets they leave empty; valid `[[pris:ID]]` and `[[lager:ID]]` references to allowed products are kept and still filled in from the database. Checked with a direct call on a sample string, then `php tests/run.php` (105 tests, 0 failed) and `php evals/run.php` (131 cases, 0 failed). A second streamed answer had no raw markers.

## Syntax checked

`node --check public/assets/app.js`, `php -l src/ProductRef.php`, and `php -l src/I18nEn.php` after regenerating the English strings with `scripts/i18n_en.py`.

## Inspected

`public/assets/soren.js` and the `s-*` keyframes are untouched. The `chat-in` keyframes and the panel's `animation` value are the same as before. The shared `.msg` rules used by the support desk are kept; the chat look is applied through `.chat` scoped rules. The launcher has a faint birch outer ring so it stays visible on the dark category bands.

## Not verified

A real phone keyboard (iOS Safari, Android Chrome); the keyboard was simulated by shrinking the viewport. Safe area insets on a notched device: Chromium reports 0 for `env(safe-area-inset-*)`, so only the CSS was inspected. Spoken screen reader output; the accessibility tree and `aria-busy` were checked.

## Known weaknesses

When the model refers to a product it was not given, the reference is removed. Brackets left empty or with only a lead-in («(som for eksempel )») and placeholders like «[product]» are removed too, but the rest of the sentence stays. An answer that passes 1200 characters is cut back to its last whole sentence. The model sometimes states facts it was not given (an order said to be paid with «Vipps-kortbetaling»). On phones the status line reads «KI-assistent, kjører …» when it does not fit.

## Glass panel and head launcher

Run in the app with headless Chromium: the panel opened from the head on `/tema/skog` (light) and `/tema/lys` (dark emulated), with the page visible through the glass, and at 390 px it filled the screen with the input at the bottom. No sideways scroll at 390 and 1366 px. `node --check` passed on `app.js` and PHP lint on the changed PHP files.

Not verified: the open and close animation was not watched frame by frame, only the end states; closing by clicking the head and focus return were not clicked through; a streamed answer with product cards was not rendered against Ollama.

## Round launcher, taller panel, lower placement

Run in the app (PHP dev server, local MariaDB) with headless Chromium through the DevTools protocol: on `/tema/skog` the launcher is the head in a round badge with a red ring and no label, 16 px from the bottom right corner. The open panel measured 780 px tall at a 1000 px viewport and was capped to the viewport (596 px) at 700 px, sitting just above the launcher. At 390 px the panel filled the screen. No sideways scroll at 390 and 1366 px.

Not verified: the open and close animation from the new launcher position, keyboard focus return, and dark mode with the panel open.

## Panel size reverted

The panel is back to 360 by 520 px at 112 px from the bottom. The round head launcher stays low in the corner.

## Send button while Søren answers

A question sent while an answer was still streaming was dropped without any sign, so the send button looked broken. The button now shows a waiting state (`aria-disabled`, muted colour, progress cursor, title «Vent til Søren har svart») until the answer ends, and pressing it puts the same line in the status. The text in the field is kept. Run in the app with Playwright: from «Spør Søren om forslagene» a click on the send button posts to `/api/chat` and clears the field; no element covers the button at 1920, 1536 or 1280 px. Not tested on a touch device.
