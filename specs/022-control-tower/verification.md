# 022 Verification

Run in the app: `php -S 127.0.0.1:8091 -t public public/index.php` against the local MariaDB, headless Chromium with SwiftShader (WebGL), logged in as the seeded `ansatt`, `support` and `admin` users.

Tested:

1. Access: without login `/ansatt/tarn` redirects to `/logg-inn?back=/ansatt/tarn` and `/api/tarn/events`, `/kpi`, `/throughput` and `/produkt/{id}` return 403. As `ansatt` and `support` all return 200. `/api/tarn/kpi` leaves out `via`, `viaDemo` and `users` for non-admins and includes them for `admin`.
2. Queries: `Tower::events` for hour, today, 7 days and 30 days returned 0, 7, 179 and 179 rows, matching `COUNT(*)` on `analytics_events`. Route counts for 30 days: guide 128, soren 22, sok 19, kvalitet 5, handel 3, sok>avvik 1, soren>kundeservice 1.
3. Floor at 1440x900: nine station labels plus Varer, counts match the window (Inntak 7, Guide 7, Varer 3). Kvalitet and Kundeservice show their queue.
4. Picking: clicking an event parcel opens the event in the inspector; clicking a product parcel shows name, ID, price, the open revision with Flett and Avvis, missing keys and specifications with source and version. The selected parcel turns solid.
5. Live: two real searches made from the open page (`/sok?q=hodelykt`, `/sok?q=zzqxv`) appeared within one poll. The count went from 7 to 9, and the search without results landed in Avvik.
6. Actions: the inspector's POST path passes CSRF for `ansatt` (redirect, treated as success), gets 403 for `support` and 400 with a wrong token. Tested against a report ID that does not exist, so no data changed.
7. WebGL disabled (`--disable-webgl`): the floor shows a table per station with the same counts; inspector, log and KPIs work.
8. Narrow window (rendered 485 px wide): one column, floor first, no horizontal scroll.
9. `/admin` still renders after its queries moved to `Tower::kpis()`.
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

19. Checked against DELIVERY.md 5.11 and found that the KPI column ignored the window and the stations. `Tower::kpis()` now takes both; `/admin` still passes nothing and shows all time. API: 30 days with every station gave 19 searches and 2 without results, the last hour gave 2 and 1, Avvik alone gave 2 and 2, Guide alone gave 0 and no terms. The catalog blocks below are labelled as current state, not filtered. In the browser the first KPI went from 19 to 2 when only Avvik was left on.
20. Replay writes nothing: highest `analytics_events` ID was 182 before and after a replay.
21. Reduced motion (emulated): the media query matched, the floor drew with parcels in place and replay ran.

Remaining DELIVERY 5 items (second pass):

22. Stations: product parcels now rest at Kvalitet, Kundeservice or Mottak. API for 30 days with every station and type: 182 events, products mottak 10, kvalitet 3, kundeservice 1, 2 open tickets. `st=mottak` returned 0 events and only the 10 Mottak products. Floor labels and the text list beside the canvas showed the same counts (Mottak 10).
23. Type filter: `ty=search` returned 17 rows, all `search`. In the browser, unchecking `manual_view` showed 11 / 12 types, Guide dropped to 0 and the first KPI followed.
24. Tickets: the list showed #2 (with product) and #1 (without product, an older test ticket). The ticket view showed alias Gjest, product, the chat log and 2 messages. A reply posted from another staff session 16 s after opening arrived over SSE (2 to 3 messages without reload). Close from the panel set #2 to `lukket` and removed it from the list.
25. Report assessment: one report was filed through the app's own route (report #6 on product 265988, brightness). "Vurdering fra Søren" returned a text from `deepseek-r1:8b` with the note that nothing changed; the value stayed 80 `verifisert` and the report stayed open. The assessment itself was weak (it called the report valid only because the numbers differ).
26. Multi-select: `/api/tarn/mangler` for three products returned combined counts (Farge 3, Vekt 3, Materiale 2, Merke 2). Without login 403, as for `/api/tarn/sak/{id}`.
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

Not verified: a real merge or rejection from the inspector (would change catalogue data), performance with 400 parcels (the local database has 196), a real 390 px device, the 1 hour window expiring rows between full reloads, the old staff pages retired (they still work).

Side effects of testing: the two searches above are now real rows in the local `analytics_events` table. The local database also got the transport fixtures (seeded from the import block, without a full reimport), ticket #2 with one test reply, and its escalate event. The second pass added: two test messages and the closing of ticket #2, error report #6 (open), one extra demo position for SR-IN-0001, and the demo transport rows re-created by `scripts/transport-demo.php`.
