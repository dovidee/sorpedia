# 022 Control Tower

Staff operations floor at `/ansatt/tarn`. One full-screen panel that brings together the merge queue (`/ansatt`), the metrics (`/admin`) and customer service (`/support`). The full delivery contract is `inspiration/CONTROLTOWER/DELIVERY.md`, section 5. Build order follows `inspiration/CONTROLTOWER/PRIORITY.md`.

## Scope

In scope (PRIORITY phases 1 to 5, then DELIVERY 5.9 and 5.10):

1. Isometric floor drawn with plain Three.js (vendored in `public/assets/vendor/`, no CDN, no build step). Stations: Inntak, Søk, Avvik, Guide, Søren, Kundeservice, Kvalitet, Handel, Ukjent and Mottak (the goods belt).
2. Event parcels: one per `analytics_events` row in the chosen window (hour, today, 7 days, 30 days). The type decides the station (`Tower::ROUTES`). Unknown types go to Ukjent.
3. Product parcels: products with an active revision, an open error report, an open ticket, or a place on an inbound shipment that is still on the way. They rest at Kvalitet (revisions or reports), else Kundeservice (ticket), else Mottak.
4. Live: new rows are polled every 5 seconds and travel along the belt to their station. Nothing moves without a new row. A full reload runs every 5 minutes.
5. Inspector beside the floor (never a modal). Event: ID, type, stations, time (Europe/Oslo), permitted payload fields. Product: name, ID and price from the database, specifications with source and version, missing facet keys, revisions with Flett and Avvis, error reports with Fjern verdi and Riktig (existing routes), stock marked DEMO. Shift-click selects several parcels and shows counts per station and type.
6. KPI column with the definitions from `/admin`, moved to `Tower::kpis()` so both pages share them. Turnover by source and users by role stay admin-only. Turnover is marked DEMO with the number of seeded order lines.
7. Congestion: Kvalitet and Kundeservice turn red when their open items pass `Tower::CONGESTED`.
8. Fallbacks: without WebGL the floor becomes a table per station. With reduced motion, parcels are placed without travel.

9. Station filter (chips) and type filter (dropdown), both always sent as explicit lists, applied to floor, inspector, KPIs and throughput. Station counts are also written as text outside the canvas. Replay of the window in time order with play, pause and speed (x60, x600, x3600). Polling pauses during replay. Throughput follows the filter.
10. Transport view (DELIVERY 5.9): SVG map of mainland Norway from a local Natural Earth outline (`public/assets/tower/norway.json`), no tile service. Markers sit at the latest stored position and never move between rows. Routes are dashed and labelled estimated. Positions older than 15 minutes are shown as stale. Shipment inspector: origin to destination, progress estimated from the route, timeline in Europe/Oslo with variance, vehicle, driver, cargo with links to the product inspector, weights (declared, measured, item sum, remaining payload as calculated, mismatch flagged), position history. Registration and phone only for `support` and `admin`. All fixtures come from `scripts/transport-demo.php` (also a step of `scripts/import.php`) and are marked DEMO DATA.
11. Tickets (DELIVERY 5.10): a new ticket needs a product. Søren asks the customer to confirm the product on the page, or to pick one with the search suggestions; Søren never searches on its own. The server checks the product exists. With nothing selected the inspector lists open tickets (also those without a product); a ticket opens with customer alias, product, the chat log, messages (live over SSE), reply and close (the existing customer service routes). Error reports get "Vurdering fra Søren", an assessment for staff only; nothing is sent or changed. "Utkast fra Søren" puts a draft from Ollama in the reply box; nothing is sent until staff press Send. If Ollama does not answer, no draft is made up.

## Rules

1. Only persisted rows are drawn. An empty window shows an idle floor.
2. Session ID and user ID are never sent to the browser.
3. Every `/api/tarn/*` endpoint and the page check the role on the server (`ansatt`, `support`, `admin`).
4. No shop navigation, banners or chat on this page. One back arrow.

## Acceptance

1. A user without a staff role gets a redirect to login on the page and 403 on every endpoint.
2. Station counts on the floor match the rows in the window.
3. A real search done while the panel is open appears within about 5 seconds; a search without results lands in Avvik.
4. Clicking a product parcel shows its specifications with source, and Flett or Avvis works from the inspector.
5. With WebGL disabled the panel still shows counts, the log, the product list and the KPIs.
6. No horizontal scroll at phone width.
7. The transport view shows one marker per shipment with a position, and DEMO DATA while any shipment is demo.
8. Escalating from the chat without a product is rejected (400); the created ticket stores `product_id` and shows on that product in the panel.
9. KPIs include a data quality block; catalogue counts are labelled as not filtered.
10. `php scripts/transport-demo.php` refreshes the demo shipments without touching other data.
