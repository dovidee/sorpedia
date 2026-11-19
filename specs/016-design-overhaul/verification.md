# Verification: 016 design overhaul

Levels used below: run in the app (page rendered by `php -S` and captured with headless Chromium in light and dark color scheme), tested (automated test passed), not verified.

## Run in the app

Home at 390, 820 and 1366 px: split hero, figures from the database (496 products, 7 categories, 12 stores), numbered category list, product grid in two columns on phones. Logo wordmark light on dark, dark on light.

Signed in as the seeded admin at 1366 px: product guide (step 1 and 2), cart with an item, checkout, settings, KPI dashboard, merge queue, customer service, inbox, assembly guide, search results, compare (empty), 404, login, and the open chat panel. At 390 px: home, guide, cart, settings and the open chat panel.

Found and fixed during this pass:

1. Text inputs in dark mode used the browser's grey field. Form controls now take theme colors from a zero specificity base rule, so component rules still win.
2. Signed in on a 390 px phone the header broke into three rows. The row gap and the logo height were reduced so the tools fit next to the logo.
3. The footer rule ran past the content edge. The rule now sits on the paragraph inside the gutter.
4. Headings inside boxed sections inherited the large page heading margin. The first child of a box now has no top margin.
5. Guide step labels, guide questions, KPI numbers and manual step titles used the old type. They now use the mono label and display heading styles.
6. Settings tabs were round pills and the selected choice had a red tint. Tabs use the shared radius; the selected choice uses a raised surface with a red edge.

## Tested

`php tests/run.php`: 105 tests, 0 failed. PHP lint passes on the changed PHP files.

## Not verified

Order page, staff review actions after a click, register form submit, the language toggle to English on the new home strings, and keyboard focus order were not checked. On the merge queue page fonts once rendered with the system fallback in a capture taken before the font files had loaded; a later capture showed the right fonts.

## Known weaknesses

The chat panel keeps its own red gradient header and round corners until the chat redesign. Product cards still show the compare checkbox until the Byggelab feature replaces it.

## Second pass (DESIGN-OVERHAUL-PLAN.md)

Run in the app: home, `/tema/lys`, `/tema/kjetting`, `/tema/maling`, `/tema/skog`, one product page, `/guide`, `/handlekurv` and `/sok?q=hammer`, each at 390, 820 and 1366 px in light and dark color scheme. `scrollWidth` equals `clientWidth` on all of them (checked through the Chrome DevTools protocol). Tab through the header and filters on `/tema/lys`: every stop shows an outline. Loaded fonts on the page: Archivo Black, Archivo, Hanken Grotesk, IBM Plex Mono.

Found and fixed: the category band first bled with an absolutely positioned layer and caused horizontal scroll; it now uses a clipped spread shadow. The search field showed no focus ring because the ring sat on the wrapper; the wrapper now draws it on `:focus-within`.

Tested: `php tests/run.php` 105 tests, 0 failed. `php evals/run.php` 131 cases, 0 failed. PHPStan level 3, no errors. PHP lint on changed PHP files.

Not verified: reduced motion was not captured in a browser (only the CSS rules were inspected). The English toggle was not opened on the new strings. Search suggest, filter auto submit and add to cart were not clicked in this pass.

Known weaknesses: the compare checkbox stays on cards and the product page until Byggelab replaces it. The per-category bands from spec 018 are replaced by one shared category header; category specific pages are a later feature. Product photos sit on a light plate in dark mode because Sør-Tre images have white backgrounds.

## Hero photo trio and header without the guide link (reverted)

Run in the app: the home hero shows the text over three faded store photos, readable in light and dark mode at 1366 px and at 390 px, no sideways scroll. The mascot is gone from the hero, and the header category row no longer has the product guide link. The hero keeps the red "Start produktguiden" button and the leaf on "Spør Søren" only.

Not verified: the photo hover with a real pointer. `public/assets/irl/warehouse.png` is about 1.3 MB; no image tool was available to compress it.

## Back to the split hero, guide link in the header

The photo trio and its hover were removed again. The hero is the split layout with the staff photo and Søren on its bottom left corner. The header has the product guide link again, as a red button in every state with no leaf. The two extra photos were deleted from `public/assets/irl/`.

## Home banner: saw and sawdust sequence

The home hero is now a full width banner on `lowsaw.png` with the headline and the two actions in white Archivo Black. The lead paragraph and the three figures were removed from the hero. Three sawdust layers (`spread1` to `spread3`) grow from the blade toward the viewer in sequence; the third peaks past the banner edges, the photo crossfades to `highsaw.png` at that peak, and the dust fades out. The layer never takes clicks. The sequence starts only after all five images have loaded. With reduced motion the dust is hidden and `highsaw.png` shows directly.

The spread files were delivered with a light checkerboard painted into the pixels. The copies in `public/assets/saw/` have that background keyed out to real transparency (same file names and sizes); the saw photos are unchanged copies.

Run in the app (`php -S 127.0.0.1:8099 -t public`, headless Chromium). The start class is set after load. Captures checked by eye: 1366 px start and end state (low saw, then high saw with no dust left), 390 px end state, and a frozen frame at the dust peak at 1366 and 390 px showing the dust past the banner top and bottom. Horizontal overflow at the peak is limited to the scrollbar width and is clipped by `body { overflow-x: clip }`.

Known weaknesses: the five banner images total about 11 MB of PNG. At the peak the dust briefly covers the headline and buttons. Not verified: playback in a real browser at normal speed, Safari, the English banner.

## Home banner: split layout, single slow dust layer

The home banner now uses the same split banner as the category pages: saw photo on the left half fading into the dark background, headline and actions on the right half. The mascot is removed from the banner. Only `spread1.png` is used: small, on the right behind the text, growing from 70 to 115 percent over 18 seconds while it fades out. The photo crossfades to `highsaw.png` over 4 seconds, starting 5 seconds after load. `spread2.png` and `spread3.png` are no longer published.

Run in the app with headless Chromium: 1366 and 390 px captures of the banner, and a frozen frame of the dust at 2.7 s (small, right side, behind the text). Not verified: full playback at real speed in a normal browser.
