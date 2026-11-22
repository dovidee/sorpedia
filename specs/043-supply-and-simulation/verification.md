# 043 Supply, simulation and Control Tower redesign: verification

## Tested

1. Unit tests: `php tests/run.php` 230 passed (after removing the unused simulated GPS and its test). `tests/DemoSimTest.php` was rewritten for the road network and the plan API: both E39 ferries from Bergen and none from Oslo, Trondheim or Stavanger, a local road leg off the network, continuous phases, the storm delaying only in the storm scenario, the ETA changing only when news arrives, the van on the water during a crossing and still at the quay while waiting, twenty minutes loading at a pickup, notices in time order ending with the delivery, departures on the timetable interval, the clock rate and pause, road messages matched to their road, and the batch assignment (nearest store, busy vehicle, deselected vehicle). The Control Tower test for stale positions now expects a delivered shipment never to be stale.
2. API with curl as the seeded admin: `/api/tarn/sim/tick` returns the clock and trips with the MET source and fetch time on the weather; six order lines added with `POST /api/tarn/bestilling`; `GET /api/tarn/bestilling` assigned them to V-02 Bergen, V-03 Stavanger, V-04 Arendal and V-05 Trondheim with nothing uncovered.
3. Run in the app with headless Chromium (Playwright) at 1440 x 900: Transport in light and dark, Bestill alt opens the batch panel with per vehicle counts and have/want per line; ordering made four shipments; V-02 then showed the Halhjem to Sandvikvåg crossing with «Kilde: Entur rutetabell, avgang hvert 25 min», the weather with «Kilde: MET Norway, varsel hentet 21.11 17:14», and a Vegvesen road closure notice on V-01 with its source. V-04 arrived, Lagerflyt showed 4 units in, Gulv listed SR-V04-0034 under «Levert, venter på mottak» with Kvitter mottak, and the order list showed Bestilt and Mottatt lines. Innsikt rendered the three levels with Mangler i butikken; Oversikt rendered the urgent card first and Søren as a drawer. No page errors and no horizontal scroll at 1440 and 390 px. Byggelab rendered at 1440 light and 390 dark. Clicked through: Kvitter mottak took the received shipments from 4 to 3; Ignorer on «zzqxv» in the search drill-down set it to Ignorert without a task.
4. `python3 .claude/hooks/check_code.py` and `python3 .claude/hooks/check_docs.py`: 0 findings.

## Design review

An independent design review found five token values in the dark theme that referred to themselves (violet, overlay, shadow, scrim, plate), ink text on red fills, link-buttons without a text colour, a missing denominator on the accessory tile, deltas coloured green when a bad rate rose, pressed toggles styled like the primary action, the same number repeated in order lines, Ignorert shown in green, control borders under 3:1, hard-coded weather and chip colours, and the clock panel covering most of the map at 390 px. All of these were fixed: real dark values, `--on-red` and `--red-fill`, `color` on `.tw-btn`, `accessoryRate` parts in `src/Insight.php`, inverted deltas for the no-result and hand-over rates, an underlined pressed state, one line format («{store} har {have} · henter {take} av {want} stk») with a screen-reader text for the shortage triangle, a muted Ignorert, `--line-strong` at oklch 60 % in light and 56 % in dark, tokens for chips and weather, and a collapsed clock panel on phones that opens when the clock is tapped. Rechecked: Transport at 390 px with the panel collapsed, Innsikt in dark, no page errors.

Fixed afterwards: the Lagerflyt chart was sized from `clientWidth` once, so a fetch while its view was hidden drew at the 400 px fallback; it now keeps the data and redraws from a `ResizeObserver` on the chart box, and skips drawing at width 0. Task cards and drill rows mark the selection with the accent bar used by other selected states instead of an amber outline. On wide screens the Byggelab sidebar keeps room at its end so its last item scrolls clear of the Søren launcher. Syntax checked (`node --check`) and `check_code`/`check_docs` 0 findings; not run in the browser (no Playwright on this machine).

## Rejected

1. A separate theme setting for the tower: the account setting already exists (`users.theme`), so the tower reads it.
2. Moving a free vehicle to where it delivered: it returns to its base, so the base list in `scripts/transport-demo.php` stays the single truth.
3. A frequency threshold before a search can get a task: the triage brief forbids an invented threshold; the task button appears after a person marks the search reviewed.

## Known weaknesses

1. The weather is the forecast stored at the last run of `scripts/conditions.php`; the server had not run it since 21.11, so the fetch time shown is from then.
2. The floor's 3D ground reads the theme at load; switching theme needs a reload, as the account setting does anyway.

## Fleet across the country, stores on the map, scenario switch (2026-11-22)

Implemented and tested at PHP level against the development database; not yet run in the browser.

1. `scripts/transport-demo.php` seeds two vehicles at every store (24) and puts 18 of them on a trip carrying seeded order lines (`supply_list`, status `bestilt`, `created_by = 0`, replaced on the next seed). Each trip goes base, a store with the goods, the receiving store, and starts part way through. Ran the seed: 18 trips, 54 lines; one tick placed vehicles from Mandal to Trondheim.
2. `DemoDelivery::tick` reads up to forty trips (was twelve, so the rest stood still).
3. Switching the scenario re-reads the legs of trips on the way. Tested: storm gives 12/12 trips in storm, back to real weather gives 0 with the MET Norway source.
4. A free vehicle stands where its last trip ended (`Supply::vehicles`, `at`), so a demo trip to a chosen point stays there; the next trip starts from that point.
5. Map: every store is drawn with the leaf, ringed when in the selected vehicle's reach; vehicles closer than 30 screen pixels fan out; the weather mood is a tinted layer instead of an SVG filter, and markers are rebuilt when the wheel rests. Choosing a destination can be cancelled with Esc, a right click or the cancel button in the hint. Syntax checked (`node --check`), not run in the browser.

## Return trip and real versus simulated stores (2026-11-22)

Implemented: `DemoDelivery::arrive` starts a return trip without cargo (`Retur uten last`) from the delivery point to where the job started, at the arrival time, for shipments with goods only. Demo trips and return trips end where they end. Kristiansand (`warehouses.is_real = 1`) has the red leaf, the other stores a yellow leaf with "simulated branch, demo" in the tooltip. A free vehicle away from its base gets a button to drive back. Tested at PHP level: V-06 (base Trondheim) delivered in Kristiansand, got return trip SR-V06-0093 and was busy; after it ended the vehicle was free at Trondheim (63.43, 10.40). The seed was run again after the test. Not run in the browser.

Known weakness: the seeded trips fetch from a store chosen by a fixed rule, not within the 60 km reach that ordering in the app uses. Supplier depots and other external pickup or delivery places are not modelled; the only stock is the store stock.

## Map motion, labels and Innsikt tabs (2026-11-22)

Implemented per `specs/043-supply-and-simulation/design-preflight-transport-insight.md`. Run in the app with headless Chromium at 1366 x 768 (admin login, local dev server):

1. Transport: vehicles are kept as SVG nodes and moved with a one second CSS transform, so they glide between ticks. Labels go through one placement per draw (selected trip, stores, vehicles, towns); 53 text labels were drawn at country zoom with 24 vehicles, none overlapping in the screenshot. The arrival box is shown only for the selected trip and was as wide as "Ankomst tir. kl. 03:20". A vehicle on a ferry gets a hull and a wake, a crossing in use a moving wake line, a vehicle waiting at a quay a ring. A missing wind value hides the wind part instead of printing null, and a trip without weather falls back to the map's own weather badge.
2. Detours: `DemoDelivery::planRoute` avoids closed legs. Over all node pairs with the stored conditions, 32 routes took a detour within 1.25 times the time; Oslo to Kristiansand did not (the only way round is through Bergen).
3. Innsikt: one tile row and five tabs (remembered per browser, arrow keys move between tabs). Content heights: Trenger oppmerksomhet 720 px, Utvikling 883 px (measured before the last tile change), Muligheter 1036 px (a feed), Byggelab 803 px, Datakvalitet 720 px. Charts show the week summed against the week before and the last value at the end of the line.

Not verified: the smooth motion and the ferry animation as motion (screenshots are still frames), dark theme screenshots, 390 px width, a trip actually crossing a ferry in a screenshot.

## Design review of map motion and Innsikt (2026-11-22)

An independent design review returned twelve findings. Fixed: each Innsikt tab now fits 1366 x 768 without scrolling (measured `#tw-ins` scroll height 720 px on all five tabs: charts drawn at 640 x 108 and 640 x 110 with the legend beside the week summary, three opportunity cards and the missing list behind «Vis n til», Byggelab and guide lists capped at five); every chart viewBox now matches its CSS aspect ratio; the selected tab uses the project's pressed style (raise and accent underline) and the tab is named «Muligheter» without the period; the tile labels share a baseline and «Ingen data» is shown at label size; the week summary no longer repeats the number; the detour text has a variant without a road name; tab panels are focusable and «Vis n til» moves focus to the first revealed item.

On the map: vehicle, condition, ferry, weather and stop markers are registered before any label, row, stop, route end and temperature labels go through the same placement check, town names are placed last, and the HTML overlays (weather badge, clock panel, messages) are kept free of labels. The arrival box is measured in its own font, sits inside the selected vehicle's node so it moves with it, and flips below the vehicle when the space above is taken. Vehicle nodes keep their inner markup between ticks, so the wait ring and the wake no longer restart; vehicles glide only on a draw that follows a simulation tick and a move under 80 screen pixels, and jump on zoom, fit, replay and the return to base. Keyboard focus stays on the selected vehicle when it is moved to the top.

Verified in headless Chromium at 1366 x 768 (Innsikt tab heights, the trends tab and a selected vehicle on the map); tests, evals and PHPStan pass. Not verified: dark theme, English, phone width and reduced motion in a live browser. Known weakness: at country zoom the clock panel covers vehicles on the south coast, and their arrival box with them.
