# 022 Verification

Run in the app: `php -S 127.0.0.1:8091 -t public public/index.php` against the local MariaDB, headless Chromium with SwiftShader (WebGL), logged in as the seeded `ansatt`, `support` and `admin` users.

Tested:

1. Access: without login `/ansatt/tarn` redirects to `/logg-inn?back=/ansatt/tarn` and `/api/tarn/events`, `/api/tarn/kpi`, `/api/tarn/throughput` and `/api/tarn/produkt/{id}` return 403. As `ansatt` and `support` all return 200. `/api/tarn/kpi` leaves out `via`, `viaDemo` and `users` for non-admins and includes them for `admin`.
2. Queries: `Tower::events` for hour, today, 7 days and 30 days returned 0, 7, 179 and 179 rows, matching `COUNT(*)` on `analytics_events`. Route counts for 30 days: guide 128, soren 22, sok 19, kvalitet 5, handel 3, sok>avvik 1, soren>kundeservice 1.
3. Floor at 1440x900: nine station labels plus Varer, counts match the window (Inntak 7, Guide 7, Varer 3). Kvalitet and Kundeservice show their queue.
4. Picking: clicking an event parcel opens the event in the inspector; clicking a product parcel shows name, ID, price, the open revision with Flett and Avvis, missing keys and specifications with source and version. The selected parcel turns solid.
5. Live: two real searches made from the open page (`/sok?q=hodelykt`, `/sok?q=zzqxv`) appeared within one poll. The count went from 7 to 9, and the search without results landed in Avvik.
6. Actions: the inspector's POST path passes CSRF for `ansatt` (redirect, treated as success), gets 403 for `support` and 400 with a wrong token. Tested against a report ID that does not exist, so no data changed.
7. WebGL disabled (`--disable-webgl`): the floor shows a table per station with the same counts; inspector, log and KPIs work.
8. Narrow window (rendered 485 px wide): one column, floor first, no horizontal scroll.
9. The old metrics page still rendered after its queries moved to `Tower::kpis()` (that page is now removed).
10. `php -l` on changed PHP files, `node --check public/assets/tower.js`, PHPStan level 3 on `src/Tower.php`, `public/index.php` and `templates/tower.php`: no errors.

Phase 5, transport and tickets:

11. Station filter: `st=avvik` for 30 days returned 2 rows, both `search_zero`; `st=ukjent` returned 0; no `st` returned 0 (no hidden default). Replay over 30 days counted up from 1 to 182 and stopped with Pause.
12. Transport API: 4 demo shipments, the Bergen one (45 minutes old) and the delivered one flagged stale. As `ansatt` the shipment has no `registration` or `phone`; as `support` both are present (phone NULL). Without login 403.
13. Transport view at 1440x900: four markers, dashed estimated routes, selected route highlighted, inspector shows DEMO DATA, Drammen to Kristiansand, progress 0 % marked estimated, timeline, vehicle and driver. Found that two shipments shared vehicle V-03; the delivered demo shipment now has its own vehicle V-04 and driver.
14. Escalation as a guest on a product page: Søren asked to confirm the product on the page, "Et annet produkt" opened the picker, typing "hodelykt" gave 6 suggestions, the chosen product was confirmed and ticket #2 was created with `product_id` 265988. `POST /api/support` with no product or an unknown ID returned 400.
15. The ticket appeared on product 265988 in the panel (Saker 1) next to the inbound shipment SR-IN-0001. Reply from the inspector as `support` stored a `support_messages` row.
16. Draft: with Ollama down the endpoint returned 503 "Søren svarte ikke. Skriv svaret selv." and the box stayed empty. With Ollama running (`deepseek-r1:8b`) the first try returned a reply cut mid-word: the model reasons even with `think: false`, and 300 tokens ran out. The budget now matches the chat (1500 tokens, 180 s) and a draft cut by the budget or deadline is dropped. After the fix the endpoint returned a full draft in 35 s; product names and specs in it came from `Chat::facts`. In the browser the draft filled the reply box with the note to read and correct it, and `support_messages` for the ticket stayed at 1 row (nothing sent). Draft quality is weak (an invented word, emoji, stock wording applied to the wrong product), which is why staff must edit before sending.
17. WebGL disabled: the table shows on the floor, switching to Transport hides it and shows the map with four markers, switching back shows the table again.
18. `php evals/run.php`: 131 cases, 0 failed. PHPStan level 3 on `src/Tower.php`, `public/index.php`, `templates/tower.php`, `src/I18nEn.php`: no errors. `node --check` on `tower.js` and `app.js`.

19. Checked against DELIVERY.md 5.11 and found that the KPI column ignored the window and the stations. `Tower::kpis()` now takes both; The old metrics page passed nothing and showed all time. API: 30 days with every station gave 19 searches and 2 without results, the last hour gave 2 and 1, Avvik alone gave 2 and 2, Guide alone gave 0 and no terms. The catalog blocks below are labelled as current state, not filtered. In the browser the first KPI went from 19 to 2 when only Avvik was left on.
20. Replay writes nothing: highest `analytics_events` ID was 182 before and after a replay.
21. Reduced motion (emulated): the media query matched, the floor drew with parcels in place and replay ran.

Remaining DELIVERY 5 items (second pass):

22. Stations: product parcels now rest at Kvalitet, Kundeservice or Mottak. API for 30 days with every station and type: 182 events, products mottak 10, kvalitet 3, kundeservice 1, 2 open tickets. `st=mottak` returned 0 events and only the 10 Mottak products. Floor labels and the text list beside the canvas showed the same counts (Mottak 10).
23. Type filter: `ty=search` returned 17 rows, all `search`. In the browser, unchecking `manual_view` showed 11 / 12 types, Guide dropped to 0 and the first KPI followed.
24. Tickets: the list showed #2 (with product) and #1 (without product, an older test ticket). The ticket view showed alias Gjest, product, the chat log and 2 messages. A reply posted from another staff session 16 s after opening arrived over SSE (2 to 3 messages without reload). Close from the panel set #2 to `lukket` and removed it from the list.
25. Report assessment: one report was filed through the app's own route (report #6 on product 265988, brightness). "Vurdering fra Søren" returned a text from `deepseek-r1:8b` with the note that nothing changed; the value stayed 80 `verifisert` and the report stayed open. The assessment itself was weak (it called the report valid only because the numbers differ).
26. Multi-select: `/api/tarn/mangler` for three selected items returned combined counts (Farge 3, Vekt 3, Materiale 2, Merke 2). Without login 403, as for `/api/tarn/sak/{id}`.
27. Data quality (30 days): 496 products, 496 with at least one missing facet key, 2413 missing values, 1004 unchecked values, 0 open reports at the time, 0 without price, 0 unknown events, 1 stale position (demo).
28. Map: wheel zoom changed the viewBox from width 374.7 to 312.2 around the pointer, drag moved it by the pointer distance. With the map open, a new position row for SR-IN-0001 moved the V-01 marker from Porsgrunn to Arendal at the next 30 second poll, with no movement in between.
29. English: 136 Control Tower strings were missing from `I18nEn::T` and showed in Norwegian; all are added (Søren stays a name). After the fix only "Søren" is untranslated.
30. 390 px: the headless build ignores viewport overrides below 500 px, so the body was constrained to 390 px instead. Header, floor, inspector, KPIs, chart and log all ended at 390 px with no horizontal overflow. Station labels overlap a little on the floor at that width.
31. `scripts/transport-demo.php` run twice on the local database: 4 demo shipments each time, vehicles and drivers matched by their references, analytics (182) and tickets unchanged.
32. No WebGL after these changes: the table lists all ten stations including Mottak.
33. `php evals/run.php`: 131 cases, 0 failed. PHPStan level 3 on `src/Tower.php`, `src/I18nEn.php`, `public/index.php`, `templates/tower.php`, `scripts/transport-demo.php`, `scripts/import.php`: no errors.

Business rules separated from data access:

34. Rules moved out of `Tower` and out of `tower.js` into pure classes: `Floor` (stations, routes, windows, type selection, payload fields, product station, congestion), `ShipmentRules` (staleness, age, weights and remaining payload, variance, progress along the route, redaction) and `DraftPrompt` (prompts, accepting a model answer). Data access sits behind interfaces with a PDO and an in-memory implementation (`FloorStore`, `ShipmentStore`), and time behind `Clock` (`SystemClock`, `FixedClock`). `ControlFloor`, `Transport` and `Drafts` combine them; routes call `tower_floor()` and `tower_transport()`. The client now shows age, staleness, variance, progress, remaining payload and congestion as sent by the server.
35. `tests/ControlTowerTest.php`: 20 checks over in-memory stores and a fixed clock. Run through a harness with `DB_HOST=db.invalid`: 20 passed and no connection was opened. `php tests/run.php` on the local database: 125 tests, 0 failed. `php evals/run.php`: 131 cases, 0 failed. PHPStan level 3 on every changed file: no errors. `node --check` on `tower.js`: no errors.
36. Local dev server, read only: events, throughput, KPI, transport and `/ansatt/tarn` returned 200. Events gave 183 rows, backlog Kvalitet 4 and Kundeservice 1, neither congested. Shipment 9 gave progress 0.81, variance +10 min, remaining 14 200 kg and stale; delivered shipment 12 gave progress 1. Registration was hidden for the staff role and shown for support.
37. Drafts separated the same way: `DraftStore` (`PdoDraftStore`) and `DraftModel` (`OllamaDraftModel`) are injected into `Drafts`; routes call `tower_drafts()`. Two more checks use an in-memory store and a fixed model answer: a closed ticket never reaches the model, and a cut-off answer is dropped. Without a database: 22 passed. `php tests/run.php`: 127 tests, 0 failed. Evals 131, 0 failed. PHPStan: no errors.
38. Headless Chromium on `/ansatt/tarn` (staff role): 10 station labels, none congested, no console errors. Transport view, shipment SR-IN-0001: progress 81 % with the estimated route note, "Gammel posisjon, 23 min", variance +10 min, remaining payload 14 200 kg marked as calculated, no registration shown.

Not verified after the separation: age rounding at exactly the 15 minute boundary in the client view, and a live draft from the model through the new wiring.

Found and fixed during testing: station counts showed the last index instead of the count; picking missed because the instanced mesh cached an empty bounding sphere.

Not verified: a real merge or rejection from the inspector (would change catalogue data), performance with 400 parcels (the local database has 196), a real 390 px device, the 1 hour window expiring rows between full reloads. The old staff pages were removed later (see "Old staff pages removed").

Side effects of testing: the two searches above are now real rows in the local `analytics_events` table. The local database also got the transport fixtures (seeded from the import block, without a full reimport), ticket #2 with one test reply, and its escalate event. The second pass added: two test messages and the closing of ticket #2, error report #6 (open), one extra demo position for SR-IN-0001, and the demo transport rows re-created by `scripts/transport-demo.php`.

## Old staff pages removed

The old merge queue, customer service and metrics pages and their templates are removed; their three GET addresses return 404. The account menu has one link, Kontrolltårn. As the seeded admin with curl: the tower returns 200, its back arrow goes to the front page, and `POST /support/{id}/lukk` and `POST /ansatt/revisjon/{id}` (on IDs that do not exist, so nothing changed) redirect to `/ansatt/tarn`. `php tests/run.php`: 127 tests, 0 failed. Not run in a browser: a real merge, rejection or ticket close from the inspector.

## Browser pass after the separation

Headless Chromium with WebGL (SwiftShader), logged in as the seeded admin, server started as in the README (four workers):

1. Floor at 1440 x 900: nine stations, no Byggelab station, no console errors. The KPI column, inspector and throughput render.
2. Transport view: the arrow between origin and destination in the shipment list was drawn at full container size. Inline icons in `.tw-t` tables are now 14 px. Rechecked: the list reads "Drammen → Kristiansand" on one line.
3. Floor at 390 x 844: the Mottak label was cut at the left edge. The portrait camera now shows a wider area (`span * 1.2`). Rechecked: every label is inside the canvas, and the page has no horizontal scroll.

Found while testing: with a single `php -S` worker, the live stream holds the only worker, so the KPI and throughput requests wait and those columns stay empty. The README command already starts four workers, and Docker runs Apache.

## Event actions

Built from the Control Tower brief: related searches, mark as reviewed, product mapping and improvement task on one event (`src/EventDesk.php`, routes under `/api/tarn/hendelse/{id}` and `/api/tarn/oppgave/{id}/lukk`). Tested over HTTP on event 181 (a search for «zzqxv» without results): detail before was empty; mark as reviewed twice gave one row; mapping to product 291482 twice stored `produkt:291482` once, and `/api/suggest?q=zzqxv` then returned that product (it returned nothing before); a product ID that does not exist gave 400; an empty title gave 400; a task with a title gave id 1 and appeared in `/api/tarn/events` under `tasks`. Without login the detail gave 403, a post without CSRF gave 400, and the `support` user got 403 on mapping and 200 on the detail. In headless Chromium (1440 and 390 px) the inspector showed all four sections; finding «fokus 700», pressing Koble and submitting the task form updated the inspector without console errors. Closing: a task created over HTTP was closed with Lukk in the browser; the list then read «Oppgaver 0», the row had status `lukket` with `closed_by` set, and closing it again gave 404. The rows made by these checks (one mapping, three tasks, one review) were removed afterwards.

Not verified: a mapping on a real search term.


## Vehicle pictures in the transport view

Implemented: each shipment shows a side-profile picture of its vehicle type, on the map marker, on its card in the shipment list (riding the progress line), and large in the shipment inspector next to container type and available cargo (remaining payload over payload). Pictures are local files in `public/assets/tower/vehicles/` (`varebil`, `lastebil`, `trekkvogn`, `tilhenger`); `annet` falls back to the lorry. Source and license (OpenMoji, CC BY-SA 4.0) are in `LICENSE.txt` in that folder. The shipment list gained a search on reference, fleet number and city, and an estimated progress per card.

Run in the app (headless Chrome, admin session, 1440 px): the list shows a van for V-03 and V-04, a lorry for V-02 and a tractor with semitrailer for V-01; selecting V-03 and V-01 shows the matching large picture; no horizontal scroll at 1440 or 390 px. Tests, evals, PHPStan and both checkers pass.

Known weaknesses: no demo shipment uses `tilhenger`, so that picture was only checked as a standalone render. Available cargo shows unknown when no cargo lines have weights (V-03 while loading).

## Demo delivery and stock refill

Implemented: `src/DemoDelivery.php`, routes `POST /api/tarn/demolevering`, `POST /api/tarn/demolevering/tick` and `POST /api/tarn/demolevering/stopp` (staff only, CSRF), the button, recipient pin and arrival countdown in `public/assets/tower.js`. `scripts/transport-demo.php` reads its town list from `DemoDelivery::TOWNS`.

Tested against the dev database with HTTP requests as admin: buying 2 of product 265987 at store 1 took online stock from 35 to 33. A demo delivery to a point in Kristiansand started from Mandal (x12), carried order 54's line (qty 2) as `ORDRE-54`, `inn`, warehouse 1. With the departure moved back 181 s, two ticks gave `done` both times; stock went back to 35 once and one `restock` event was logged. A second start carried nothing (`Tom bil`). Without a CSRF token the tick is rejected (400).

Run in the app (headless Chrome with a fixed browser position in Oslo, 1440 and 390 px): the van left Drammen, the marker moved each second with a fresh position, the countdown and recipient pin showed, and the shipment inspector updated. No console errors, no horizontal scroll.

Known weaknesses: the route is a straight line, labelled estimated. The browser position needs a secure context (localhost or HTTPS); elsewhere the map click is used. A page reload during a run loses the pin and countdown; the shipment keeps moving only while a page sends ticks.

## 3D conveyor floor

Implemented in `public/assets/tower.js` (scene block) and `public/assets/tower.css` (label stems). Belts are swept along the filleted floor plan: raised frame with side plates, slatted deck, side rails, legs in pairs with cross beams and base plates, end rollers. Diverts leave the trunk at 45 degrees and the trunk rail opens where each one leaves. The exception lane is red. Stations are raised decks at belt height on legs, with low walls on every edge no belt passes through and the station colour along the rim. Intake is a building the trunk leaves through a lit opening. The sun casts soft shadows. Parcels are cartons with a darker tape band; a new event's parcel follows the belts (trunk, divert, station) and glides onto its place in the pile. The slats scroll only while a parcel is travelling, so the floor stays still when no rows arrive. The camera fits the whole floor for any canvas shape; labels have leader stems and are kept inside the canvas.

Found while testing: the previous floor-plan change had left the scene code calling a removed helper (`pt`), so the module threw and the floor was empty. Diverts first rendered as short stubs because straight runs had only two samples; belt samples are now at most 0.3 units apart.

Run in the app (headless Chromium with SwiftShader WebGL, admin session): 1440 x 900 shows all ten stations and every belt with no console errors; replay on the 30 day window moved parcels along the trunk and into the Søk divert; clicking a Mottak carton selected it and the inspector showed the product from the database; at 390 x 844 every label is inside the canvas.

Not verified: frame rate on a real mid-range device, and how the floor looks with hundreds of parcels in one pile.

## Floor camera, selection turn and drag to station

Implemented in `public/assets/tower.js`, `public/assets/tower.css` and `templates/tower.php`. Left drag on the floor turns the view a full circle and tilts it; right drag (or Shift and drag) pans; the wheel zooms towards the pointer (0.7x to 8x); double click or "Tilbakestill visning" returns to the start view; plus and minus buttons zoom for keyboard users. A selected carton lifts and turns slowly (still when reduced motion is on). An Avvik parcel can be dragged: while it is carried, stations that accept it are outlined amber and the rest dim. Kvalitet creates an improvement task, Søk selects the event and focuses the product mapping search (catalogue staff, needs a search term), the open floor marks the event reviewed. Every drop asks first and calls the same endpoint as the inspector button; cancel or Escape sends the parcel back along a short arc.

Run in the app (headless Chromium with real mouse events, admin, 1440 x 900): five wheel steps zoomed in around the pointer; two drags turned the floor past 180 degrees; reset restored the start view; dragging the «hammmer» parcel from Avvik to Kvalitet showed the targets and «Opprette forbedringsoppgave for «hammmer»?»; Avbryt sent it back with no row written; Bekreft created task 1 with the title «Søk uten treff: «hammmer»», selected the event and showed «Oppgave #1 opprettet». Two frames of a selected carton showed it at different angles. The task row from this check was deleted afterwards. English page shows the new strings. Tests, evals and PHPStan pass.

Not verified: the drop on Søk and on the open floor in the browser (same endpoints as the inspector buttons, which were tested before), touch devices (one finger turns the view; there is no pinch zoom), and frame rate on a real GPU.

## Header, stations, Innsikt, stock flow, customer service, refunds, Spør Søren, GPS

Implemented: `templates/tower.php`, `public/assets/tower.js`, `tower-lib.js`, `tower-insight.js`, `tower-desk.js`, `tower-soren.js`, `tower.css`; `src/Insight.php`, `src/Stats.php`, `src/Refund.php`, `src/StaffBrief.php`, and the simulated GPS (since replaced by the simulation in feature 043); routes `GET /api/tarn/innsikt`, `GET /api/tarn/lagerflyt`, `GET /api/tarn/kundeservice`, `POST /api/tarn/sak/{id}/refusjon`, `POST /api/tarn/soren`, `POST /api/tarn/gps/tick`; `order_refunds` and the order status `refundert` in `db/schema.sql` and `Refund::ensure`. Demo shipments are seeded with container, line weights and ETA.

Unit tests: `tests/StatsTest.php` (describe, Sturges histogram, least squares, forecast withheld below 14 days, never below zero, holdout error), `tests/RefundTest.php` (`Refund::plan`), `tests/StaffSorenTest.php` (prompt carries facts and rules, dashes replaced, cut answers dropped). None opens the database.

Tested with HTTP requests as admin on the dev database, then reverted: on a temporary ticket for a customer with two orders, a partial refund (1 unit) succeeded, the same unit again was refused (409), a whole order refund set `refundert`, a refund of a refunded order was refused, stock rose by the refunded units and one message per refund was added. All refund rows, messages, stock changes and the ticket were removed afterwards. The GPS tick stored one `demo-gps` row per moving demo shipment.

Run in the app (headless Chromium, admin, 1440 x 900): the header shows four views and Spør Søren; the legend lists every station; Innsikt draws tiles, both series (the search forecast is withheld with 5 days of data, the order forecast shows R² 0.10 and MAE 0.73), funnel, histogram, donut, segmented bar, meters and ranked bars; Lagerflyt shows sold units and 272 units on the way; the floor column shows tiles and bars; in Kundeservice a refund through the steppers and confirm step showed «Refundert 199,00 kr» and the message in the thread, with product photos on the order lines; the shipment inspector shows no unknown fields, a photo grid of the cargo, GPS speed and direction after a tick, and the route start and end on the map; in Byggelab right click opened Ett til, Ett mindre and Fjern, Delete removed the selected product, dragging off the bench removed one, and dragging along the bench reordered the lane (kept in the address). At 390 px the header puts the views on their own row and Gulv, Innsikt and Kundeservice have no horizontal scroll (found and fixed: the view switch was 434 px wide).

Weather and route conditions: `php scripts/conditions.php` got 8 weather points from MET Norway (clear night, -7 to 0 degrees, wind up to 4 m/s) and 0 warnings on the routes; with `DATEX_USER` unset it wrote 8 demo obstacles (ferry on E39, roadworks, closure, railway crossing, traffic). Found and fixed: the demo obstacles were first skipped because the fallback flag was inverted. `/api/tarn/transport` returns the conditions, the mood and per shipment the condition IDs and delay (50, 120 and 50 minutes). Run in the app: the live data gave the clear night mood with a badge crediting MET Norway, markers for each obstacle and a list under Forhold på ruten; the rain and storm moods were checked by setting the class (grey with rain; dark with heavy rain and flashes). Injection test: a closure row with `<img onerror>` and `<svg onload>` in title, detail and source, and a weather symbol with markup, rendered as text, no element was created and no handler ran; the table was rebuilt afterwards.

Success goals in Innsikt: in a transaction that was rolled back, three paid orders were added (one with a "goes well with" pair) and three sessions (search then cart after 5 and 10 minutes, and one search without cart). The share went from 0 % to 25 % (1 of 4 orders this week) and the median time to cart to 7.5 minutes; nothing was left afterwards. Run in the app: the two new tiles show "0,0 %" and "Ingen data" on the current data; no horizontal scroll at 390 px.

English: every `t()` string in the templates and code and every `tr()` string in the scripts now has an English entry, all in `scripts/i18n_en.py`. Found and fixed: 37 entries existed only in the generated `src/I18nEn.php` and were lost when it was generated again; they were restored into the script. The Control Tower in English shows no Norwegian labels (data such as vehicle types and reasons stays Norwegian).

Road source changed: DATEX II needs an approved access order, so roadworks, closures, ferry notices and road warnings now come from Statens vegvesen's open traffic messages (`traffic-info.atlas.vegvesen.no/traffic-information/messages`, GeoJSON, no registration, sent with an `X-System-ID` header). The DATEX code and its two settings were removed. Live run after the change: 8 weather points and 1 warning from MET Norway, 61 traffic messages on the routes (42 roadworks, 19 closures), 0 demo rows. Messages that are not active now or have no traffic impact are left out; ferry notices are recognised by text starting with "Ferje". Run in the app: the Transport view drew the roadwork and closure markers along the E18 and E39 routes with the live weather badge. With 61 markers the Oslo and Bergen ends were crowded and covered the route labels, so obstacles closer than 26 screen pixels now share one marker: the most serious one is drawn (a closure before roadworks) with a count, the tooltip lists up to eight, and the grouping is redone on zoom so groups split apart. Same data after the change: 21 markers, 14 of them groups, checked in a screenshot. Vehicle labels on the same stretch of road are now stacked one line apart instead of printed over each other (V-01 and V-04 near Kristiansand, checked in a screenshot); a stacked label can still cross a town name.

Not verified: a ferry notice on a route (none near the demo routes during the test); rain, snow or storm from live data. The terms of use of the traffic message service were not found in its own documentation; check them before production use.

Not verified: a real answer from Søren in Spør Søren (Ollama was not reachable from this machine; the error path and the fact list were checked); "Min posisjon" with a real browser location.

Ferries (live, from Entur): one Journey Planner request for the six quays and one vehicle position request per run, both with an `ET-Client-Name` header. Coverage found in the live run: Halhjem - Sandvikvåg (E39, Torghatten), Mortavika - Arsvågen (E39, Fjord1) and Moss - Horten (Rv. 19, Torghatten Sør) had every departure in the next three hours marked realtime by Entur, and vessel positions for each crossing (4, 3 and 3 vessels). Results: Halhjem - Sandvikvåg and Moss - Horten "running to plan (live)"; Moss had one departure 3 minutes late, below the 5 minute delay threshold; Mortavika - Arsvågen "disruption reported" with the operator's message that the 18.30 from Mortavika was cancelled, while the departure feed still had 18.30 as running with `cancellation: false`. The two feeds disagreed, so the message is shown as the operator's words and the crossing is not called normal. Positions were between 1 and 48 minutes old in the first sample; old positions are drawn faded. Only Bergen - Kristiansand passes both quays of a crossing (the E39 pair); Moss - Horten lies 20 to 30 km from the E18 routes and is drawn on the map without being tied to a shipment. The simulated E39 ferry was removed from the demo obstacles. Rule tests in `tests/FerriesTest.php`. Run in the app: the crossings are drawn green and amber on the map, and the Bergen shipment lists both E39 crossings above the road conditions with the message, live tags and vessel age.

AIS: the open Kystverket AIS stream (`153.44.253.27:5631`) gave no data in one 20 second attempt from this server and was not tried again; BarentsWatch AIS needs a registered client. No AIS is used: vessel positions come from Entur only, and no ferry status is ever read from a position. Operator pages were not needed for these crossings, since Entur carried live departures and messages for all three.

Not verified: a live cancellation flag on a departure (none during the test); a crossing with timetable data only; Entur not answering (the code stores unknown, untested against a real outage); the terms of use of the Entur APIs were not reread here.
