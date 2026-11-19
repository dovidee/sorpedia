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
