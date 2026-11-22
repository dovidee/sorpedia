# 045 Verification

## Implemented

`public/assets/autodemo.js` (runner, captions, steps), `templates/presentation.php` (button, script), `templates/layout.php` and `templates/tower.php` (script), `public/index.php` (the product for the tour, from the database).

## Tested

Run in headless Chromium at 1366 x 768, logged in as admin on `/presentasjon`, pressing Autodemo: every step ran in order across the shop and the Control Tower and the tour ended back on the hub after 4:40 (elapsed time read from the caption every 10 s). The database afterwards held a new order for the demo customer with status `refundert` and a new customer service case from the same customer. Screenshots of Innsikt and Transport were checked with the caption in place.

Found and fixed during the runs: the checkout POST was cut off because the next step navigated away before the response, so the order was rolled back; steps that submit now wait for the next page. The simulation clock could be paused when the tour reached Transport; the tour now presses play.

## Known weaknesses

Byggelab's 3D view could not be checked headless (no WebGL there); the steps on that page ran. Whether the order list is fetched depends on a free vehicle within reach at that moment; if none is free, the step only shows the list. Pause and Escape were not tried in the headless run. The captions cover part of the lower left corner of each page.
