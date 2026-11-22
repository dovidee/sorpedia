# 032 Verification

All runs as admin on the development server (`php -S localhost:8080`) with Playwright (Chromium, software WebGL), and API calls with the admin session. Rows written by the tests were removed afterwards: the test buyer change, its task and its audit rows, the opportunity task, the read mark on the conversation and the demo delivery.

## Gulv

Run in the app at 1440×900: the floor showed 7 station labels with counts and product photos (Produktkontroll 3, Datakvalitet 1, Kompatibilitet 16, Varekunnskap 1749 / 1001 / 3), and the panel showed Varemottak with the three buyer contributions awaiting a check and Forbedringsoppgaver with Aktive and Ferdige tabs. No search, chat or case appeared on the floor.

Flow tested end to end on product 267356 with a buyer change of two values (Lyskilder and Lengde): a task made from the product's inspector appeared at once in the panel as Åpen; Start moved it to Under behandling; Flett inn showed the confirm step with Nå and Foreslått for both values and "Oppgaver for denne verdien som blir Fullført: 1"; Bekreft merged and reported the task as closed (Fullført); the Ferdige tab showed it with Arkiver as the next step.

Rejected in testing: the first version redrew the open product inspector on every 5 second poll, which wiped the confirm step before it could be clicked. The inspector is now only redrawn by the staff member's own actions.

## Drill-downs

`/api/tarn/innsyn/sok`, `/api/tarn/innsyn/samtaler` and `/api/tarn/innsyn/byggelab` returned 6 search terms, 12 conversations and 12 Byggelab sessions for 30 days. In the app: the Søk card opened the exact terms («zzqxv» without a hit first); the Søren card opened the stored conversations, and opening one showed the full stored chat with timestamps and "Ikke sendt videre til et menneske."; Byggelab showed cards with the four product photos, the price of one of each, Mangler noe and Forlatt or Lagt i kurven; Muligheter showed the Ledlenser H7R Core photo and name first for the charging cable issue, and placeholders for search terms without a mapped product. No console errors.

Opportunity to task: Lag oppgave on that opportunity created task 4 with source `mulighet`, product 291483 (the product the issue names) and origin `hendelse:255`; a second request returned the same task 4 instead of a duplicate, and the task was listed in the Gulv data. Marking a conversation as read returned ok, a second mark returned false.

## Transport

Pure plan checked with `tests/DemoSimTest.php` (7 tests: route and ferries, continuous phases, storm only in the stormy plan, ferry and quay positions, weather along the route, notification order, clock with pause and rate). Full suite 161 tests, 0 failed.

Run in the app with the browser position set to Kristiansand: after four wheel steps the viewBox was the same 3.5 seconds later while the demo ticked (camera kept). Følg kjøretøy moved the view with the van. At x600 the strip showed, in order, departure, approaching Halhjem to Sandvikvåg, rain, on board, ashore, storm at Aksdal, the delay, the cancelled departure at Arsvågen, and later the clearing. The ETA line changed from one arrival time to Opprinnelig ankomst 07:43 struck through, Ny ankomst 08:18, Forsinket 35 min and the storm reason; the shipment list showed the status FORSINKET. Rain and storm effects and the badge "Storm 23 m/s, Simulert vær ved kjøretøyet" were visible while the van was followed. Pause stopped the simulated clock.

Rejected in testing: the first runs returned HTTP 500 on some ticks. `delayed` is a reserved word in MariaDB (column renamed `delay_set`), and the storm reason was longer than the `eta_source` column (shortened).

## Gates

`php -l` on every PHP file, `node --check` on every script, `check_code` 0 findings, `check_docs` 0 findings, `tests/run.php` 161 tests 0 failed, `evals/run.php` 131 cases 0 failed, PHPStan level 3 no errors.

## Known weaknesses

The demo route between stops is a straight line, not the road geometry. The final leg from Kristiansand to the presenter's point is also straight, so a point far from the south coast gives a long line over land. The event drag code from the first Gulv is still in `tower.js` but unreachable, since no event items are drawn. The Byggelab drill-down was checked at 390 px (one column, cards stacked, no sideways scroll); the transport strip was not tested at 390 px, only its phone rule was written.
