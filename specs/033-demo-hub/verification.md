# 033 Verification

Run in the app as admin on the development server with Playwright (Chromium).

At 1440×900 `/presentasjon` returned 200 with no console errors. The overview showed 496 products, 465 categories, 1 749 verified values, 3 067 relations, 7 builders, 94 routes, 18 features with a verification and 161 unit tests. Key 2 scrolled to the inventory and the navigation marked Funksjoner as current; the inventory listed 48 features (21 Verifisert, 22 Implementert, 5 Demodata) and the Demodata chip left 5 visible. Key 3 showed the diagram; clicking the line from Verifisert kjøper to Kontrolltårnet highlighted both boxes and filled the side panel with «Kjøperbidrag» and the mechanism. At 390×844 the navigation folded into a top bar with a sideways list of sections and the page had no horizontal scroll. Without a session the route answered 303 to `/logg-inn?back=/presentasjon`.

Every file named in the inventory was checked to exist, and every table it names was queried. The journey's Byggelab step was checked against the stored check (analytics_events 255: lys builder, products 291385, 291482, 291483, 291485, verdict mangler, four unknown links, the charging cable message). The Søren response time in the roadmap comes from specs/015 verification.

Rejected in testing: the first diagram was too small to read on a projector and lines crossed boxes; it was laid out again with larger boxes and the buyer below Kontrolltårnet. The criteria heading repeated the three column titles and the longest title overflowed its column; the heading was reworded and the column titles made smaller.

## Known weaknesses

The test gate figures (tests, evals, PHPStan level) are a constant with a date, not run on the request. Statuses are set by hand in `Presentation::features()`; a new verification does not update them. The diagram positions are fixed for these twelve parts. The page was not tested on a real projector.

## Section 6 Søren (added 2026-11-22)

A section showing Søren's product retrieval before and after feature 034, read from `specs/034-product-retrieval/after.json` (both implementations in one run of `evals/retrieval.php`), with the held-out questions counted from the cases, and a short list of what the server enforces in the answer (features 035 and 036). The sections and keys are now 1 to 8. Checked in headless Chromium at 1366 px in light and dark and at 390 px: the table fits, the comparison bars are hidden on phones, and a missing file shows how to make it instead of figures.
