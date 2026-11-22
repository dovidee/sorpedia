# 023 Byggelab: verification

## Tested

1. Engine run from the command line for the examples in all seven builders. Light: documented pairs (Core cable with H5R, 21700 battery with H7R), rule pairs (3xAAA with AAA), documented not compatible (MH cable with H5R Core) giving the verdict Konflikt, missing D batteries, runtime only where `brenntid_t` is stored, unknown lumen shown as unknown. Lifting: lowest known working load while the chain lacks one, different dimensions left unknown. Forestry: chain against file diameter.
2. API with curl: page 200 for all seven builders, 404 for an unknown builder, `/byggelab` redirects to `/byggelab/lys`, check without CSRF gives 400, product IDs outside the category are dropped, the cart endpoint returns the new count, `lab_test` and `lab_cart` rows are written.
3. Headless Chromium (SwiftShader WebGL) screenshots at 1440, 820 and 390 px: workbench, labels, test run with beams and step list, lifting run with the weakest link ring, help menu, compare bar on the home page. No console errors, no horizontal scroll.
4. Control Tower as the seeded admin: the Byggelab station shows the test run parcels and the log lists `lab_test` and `lab_cart`.
5. English: page and report strings come through `scripts/i18n_en.py`.
6. `php tests/run.php` 127 passed, `php evals/run.php` 131 passed, PHPStan level 3 no errors.

## Not verified

1. Drag and drop with a real mouse, touch rotation on a phone, and the Søren answer to the prefilled question (Ollama is not installed on the development server).
2. Screen reader output for the live region and the step list.

## Known weaknesses

1. Lanes for light come from which specifications are stored; a lamp with neither lumen nor IP rating lands under power and charging.
2. Many pairs are unknown because the catalogue has few documented relations; that is shown, not hidden.
3. Product labels on the workbench can overlap when many products share a lane.

## Review fixes

1. Every pair without a relation or a category rule is now listed as unknown, and any unknown pair keeps the verdict at "mangler". Run over all presets: every pair has an entry, "Feil kabel" is still "konflikt", no preset with two or more products reaches "klar" unless every pair is documented or rule based.
2. Lifting: the weakest link is only named when every working load comes from Sør-Tre or staff. Otherwise the label is "Laveste kjente arbeidslast", the source is shown in the note and a warning names the unconfirmed parts. Tested by setting one stored value to `utledet` inside a transaction that was rolled back.
3. Touch drag from the inventory cancels `touchmove` once the hold has started, so the page no longer scrolls under the drag. Syntax checked, not tried on a phone.
4. The small screen rule hides button labels visually instead of with `display: none`, so the icon buttons keep their accessible names. Not checked with a screen reader.
5. `Tower::ensure` uses `ADD COLUMN IF NOT EXISTS` and `ADD KEY IF NOT EXISTS`, so two first requests cannot fail on a duplicate column. Run twice on a temporary table in MariaDB 10.11.
6. `scripts/manual_pdfs.php` exits with an error and lists the files when a PDF fails to print. Syntax checked, not run with a failing browser.

## Channel view and separation from the Control Tower

1. `/byggelab/{theme}` renders without the demo bar, shop header, category bar, footer, compare bar and chat launcher. Checked with curl: none of those classes are in the page, the channel classes are. "Spør Søren" in the action bar still opens the chat (the launcher lookup is optional in `app.js`); not clicked in a browser.
2. Screenshots in headless Chromium at 1440 x 900 and 390 x 844: the lab fills the viewport under one bar (back link, DEMO marker, cart). No horizontal page scroll at 390. The 3D canvas is blank in that headless build; the product labels and the side panel render, as before this change.
3. The Byggelab station is gone from `Floor::STATIONS` and `tower.js`; a test run no longer writes `lab_test`. `lab_cart` still ends at Handel.

Known weakness: four `lab_test` rows from the earlier curl checks (19 November 2026) remain in the local `analytics_events` table. They now count as unknown events in the 30 day window until they age out. They were not deleted.

## Review fixes, second round

1. Relation reasons were stored and shown in Norwegian only, also with English chosen: on product cards, the product page, the cart and in Byggelab. `I18n::reason()` now translates the known sentence shapes written by `scripts/import.php`, at display time, in `Catalog::relations()` and in the Byggelab check. Product names and Sør-Tre's own quoted words stay as written. Run over every distinct reason in the local database: 70 of 70 translated, Norwegian unchanged. Checked over the API in English for six builders, and on a product page ("You need a hammer to drive in nails"). A new reason shape added to the import later shows in Norwegian until a pattern is added.
2. Control Tower: a change of window, station or type started new requests, but a slower response from the old filter could land last and overwrite the new view. The same was possible in the inspector when one product, shipment or ticket was picked right after another. Responses that started under an older filter or an older pick are now dropped. Tested in headless Chromium: switching from 30 days to today in one step shows today's count (0), and two quick picks leave the second product in the inspector. The race itself was not reproduced with a slow network.

## Setup-tool layout pass

The three panels and the action bar now share one framed window with 1px seams. The centre stage is a light studio backdrop (dark in dark mode) drawn by CSS behind a transparent WebGL canvas, the bench reads as a steel base plate, and part labels are callout cards with a leader line and a dot on the part. The product list is a grid of picture tiles; a part in the configuration gets the builder accent border and a count. Compatibility pairs are divided rows instead of boxed cards. Checked in headless Chromium at 1440x900 (light and dark) and 390x844: WebGL on, no console errors, no horizontal scroll, and a test run on the "Hodelykt med reserve" example completes its steps. Not checked: Safari, a real touch device, and screen reader output for the tile grid.

Found in the same pass and fixed: on Løfteoppsett, Utebygger and Overflateplan the two generated examples could get the same label ("Rundt CAL Kjetting" twice), because size variants share a short name. `Byggelab::presets()` now skips a hub whose short name is already used. Checked over HTTP: all seven builders list distinct example names. The count on a tile in the configuration now sits on the price line (one row, 19 px high).

## Chat launcher in Byggelab

Without the launcher, closing the chat in Byggelab left only «Spør Søren» in the action bar, which always fills in a question. The round launcher now shows in Byggelab too: above the action bar from 768 px, beside it on phones (the bar keeps room at its right end), so it never covers «Legg oppsettet i kurven». Run in the app with Playwright at 1366, 1920 and 390 px: open with «Spør Søren», close, the launcher is visible and on top, and opens the chat with an empty field.
