# 030 Control Tower overview

## Goal

Staff open the Control Tower on one calm page that answers "is anything wrong right now?" before they go into the floor, the map or the cases. The KPI column leaves the floor view, so the 3D floor and the map get the full width.

## Page

1. "Oversikt" is the first view in the tower navigation and the one that opens. The views are now Oversikt, Gulv, Transport, Kundeservice and Innsikt.
2. A status line under the header lists only what needs attention, each item with a dot and its state in text: when the figures were fetched, cases waiting, late shipments, shipments without a fresh position, ferries that are not running normally, closed roads, searches without results, and the age of the last backup.
3. Eight cards, each leading to the view with the details when clicked or opened with Enter:
   1. Produktdata: filter values present of all filter values the products' themes have, as a gauge with a grade (God, Middels, Svak), plus unchecked values and open error reports. Opens Innsikt.
   2. Levert i tide: deliveries in the last 30 days that arrived on or before the planned time, as a gauge, plus shipments on the road and late. Marked demo when the shipments are demo rows. Opens Transport.
   3. Kundesaker: cases waiting for a reply, cases in progress and how long the oldest has waited. Opens Kundeservice.
   4. Omsetning (admin) or Ordre (other roles): paid orders in the last 7 days, average per order and refunds; money only for the admin role. Demo orders are counted and labelled. Opens Innsikt.
   5. Søk med treff: share of searches in the last 7 days with a result, and the number without. Opens Innsikt.
   6. Søren løste selv: share of questions to Søren in the last 7 days not passed on to a person. Opens Kundeservice.
   7. Viktigst nå: the oldest waiting case (with "Åpne saken", which opens that case), else late shipments, else the most common search without results, else "Ingenting haster nå".
   8. Ruten i dag: shipments on the road, the status of each ferry crossing, closed roads and weather warnings on the routes. Opens Transport.
4. Siste aktivitet: the latest orders, new cases, suggested changes, delivered shipments and logged staff actions and backups, newest first, with relative time. Order amounts only for the admin role.
5. Varehus: every warehouse with products on the shelf, units, orders in the last 30 days, shipments on the road and stock coverage (products on the shelf of all products) as a bar and a percentage. Labelled as demo data, since stock per store is demo (rule 7).
6. Søren is docked on the right of the page on screens 1280 px and wider, with an opening line built from the same counted figures (cases waiting, late shipments), not from the model. Leaving the overview puts the panel back to how it was. Below 1280 px Søren stays behind the "Spør Søren" button.
7. Glass cards (translucent surface, blur, light border) over a soft light field, on the tower's own colour tokens. The Søren panel uses the same glass with chat bubbles.
8. First load only: the cards rise in one after another (55 ms apart), each gauge sweeps up from 0 to its value, the figures count up and the bars fill, all on the same ease-out curve so number and arc arrive together (1300 ms per gauge and figure, 1100 ms per bar). The real value is in the markup from the start; refreshes change numbers in place without motion; with reduced motion nothing animates.

## Data

`GET /api/tarn/oversikt` (`src/Overview.php`) counts everything from stored rows in one request. A figure without data is shown as "Ukjent", never as 0 %.

## Acceptance

1. The tower opens on the overview; the floor view has no KPI column.
2. Every card opens its view; "Åpne saken" opens the oldest waiting case.
3. A non-admin gets no money figures from the endpoint.
4. The page works in English and at 390 px without sideways scrolling.
