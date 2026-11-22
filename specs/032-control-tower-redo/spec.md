# 032 Control Tower redo

## Goal

The Control Tower shows real products, real records and an honest simulation. Gulv is about products and shipments, not analytics events. Every key figure opens the records behind it. The transport demo is a journey the presenter can watch and steer, on one clock.

## Gulv

1. The floor shows products and shipments only. Customer questions, searches and support cases are never drawn as parcels.
2. Stations (`src/Gulv.php`): Varemottak (`inntak`), Sendinger inn (`mottak`), Produktkontroll (`kontroll`), Datakvalitet (`kvalitet`), Varekunnskap (`kunnskap`), Kompatibilitet (`kobling`) and Klargjøring (`klar`). A product rests at the first station its state puts it in: an open error report, then an active buyer change or active task, then an inbound shipment, then a documented relationship, then handled in the last 30 days. Varekunnskap is drawn as shelves from the counts of verified values.
3. Each station label shows its count and up to three product photos; a product without a photo gets a marked placeholder.
4. A panel beside the floor is always visible: Varemottak (inbound shipments and the products waiting in them, buyer contributions awaiting a check) and Forbedringsoppgaver (task cards with photo, title, product, status chip, source and time, with Aktive and Ferdige tabs and the next step as a button).
5. Selecting a product opens a closable inspector over the floor: photo, name, ID, price, station and why, specifications with source and version, buyer changes, error reports, tasks, documented relationships, inbound shipments, missing keys and demo stock. A pallet opens its shipment.
6. Improvement tasks have explicit states: Åpen, Under behandling, Klar for kontroll, Fullført, Avvist and Arkivert. They are raised from a search, an error report, a product, a relationship, an opportunity or a buyer change. `origin` names what raised a task, and a second active task for the same origin is refused with the existing task's number.
7. Flett inn and Avvis ask first, showing the current and the proposed value, what will happen, and how many linked tasks will be closed. Merging completes the active tasks for the same product and key or the same revision; rejecting rejects them. The result says which tasks were closed and which stay open.
8. Polls refresh the panel and the floor but never redraw an open inspector, so a form or a confirm step is never lost.

## KPI drill-downs

1. Clicking Søk med treff or Søren løste selv on Oversikt opens a sheet over the page with the records behind the number. Innsikt opens the same sheet from Søk uten treff and Byggelab. Closing returns to the page and to the element that opened it. The period is 7 or 30 days.
2. Søk: the exact terms, the ones without a hit first, with times searched, customers (sessions), first and last time, whether the term was seen before the period, the current product mapping, review state and tasks. Actions: search the catalogue, mark as reviewed, make a task.
3. Spørsmål til Søren: the stored conversations, newest first, with the customer's first question, Søren's last answer, the number of questions and whether it was passed to a person. Opening one shows the whole stored chat, the case and its product. An escalation now records the conversation it came from (`support_tickets.conversation_id`); older cases show the copy of the chat they kept. A conversation can be marked as read (`chat_conversations.reviewed_at`). Nothing is filled in when a message is missing.
4. Byggelab: one card per session and builder with the product photos, count, price of one of each at today's price, the verdict and whether it was saved, put on a shopping list or in the cart, else Forlatt. A session is never shown as a purchase. Opening one shows the products, missing items and warnings, conflicts, unknown links and its timeline.
5. Muligheter in Innsikt is a photo feed: the product the issue names first (its photo and name), then the issue, customers, occurrences and last date. A search opportunity shows the mapped product or a marked placeholder. Lag oppgave links the named product and the event, so the task shows in Forbedringsoppgaver with the source Mulighet or Søk.

## Transport

1. The camera belongs to the presenter. Selecting a shipment or a tick never moves it. Rute fits the selected route, Tilbakestill visning fits all shipments, and Følg kjøretøy keeps the demo van in the middle until the presenter drags or presses another camera button. Zooming works at all times.
2. The land is drawn once; only the layer on top of it is rebuilt, at most once per frame.
3. Demolevering starts a simulated truck in Bergen. It drives E39 with two ferry crossings (Halhjem to Sandvikvåg, Arsvågen to Mortavika) through Stord, Stavanger, Egersund, Flekkefjord and Mandal to Kristiansand and then to the presenter's point. Booked, the journey to Kristiansand takes 424 simulated minutes when it leaves at 08:00; the ferry waits depend on the time of departure.
4. One clock (`demo_clock`, `src/DemoSim.php`): simulated seconds at an anchor, the anchor's real time, the rate (x60, x240 or x600) and a pause flag. Position, ferry waits and crossings, weather, the delay, the ETA, the status and the notifications are all read from the same plan at the same simulated second. The control strip shows the simulated time with play or pause, speed and restart.
5. Weather follows the van's leg: cloud, rain, storm and clear, with wind in m/s. The map's rain and storm effects and the weather badge show it while the demo van is selected or followed, labelled as simulated.
6. The storm at Boknafjorden cancels the first departure and slows the crossing. At Aksdal the delay becomes known: the stored shipment gets the status forsinket and a new ETA, and the strip shows the original ETA, the new ETA, the delay in minutes and the reason.
7. Notifications arrive one by one as the clock passes them (departure, approaching a ferry, on board, ashore, rain, storm, cancelled departure, delay, clearing, approaching, delivered), as a short toast and in a history list. Selecting one centres the map on where it happened without changing the zoom.
8. Ferry crossings are drawn as dashed lines over the water and the van is drawn as on board while crossing. A wait at the quay keeps it still at the quay.
9. Every row is demo (`is_demo = 1`, positions with source `demo-simulering`), one stored position per tick.

## Acceptance

1. No support case, search or chat appears as a parcel on Gulv.
2. A task made from a product appears at once in Forbedringsoppgaver; merging the change it concerns marks it Fullført.
3. Søk shows the exact failed terms; Søren shows the real conversations; Byggelab shows the products with photos; opportunities show product photos where available.
4. During a running demo, a zoom or a pan is kept across ticks.
5. The demo shows two ferry crossings, rain and storm, a delay with original and new ETA and the reason, and notifications that arrive over time; pause stops the clock.
