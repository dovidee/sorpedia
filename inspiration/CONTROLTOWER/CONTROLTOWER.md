SØRPEDIA: CONTROL TOWER
Product, analytics and logistics operations specification (revised)
Document type: Implementation specification
Interface: Control Tower (`/ansatt/tarn`, staff only)
Companion briefs: CONTROLTOWER-REDESIGN.md (visual hierarchy, theme, charts), CONTROLTOWER-TRIAGE.md (observations, review states and tasks)
Core principle: make operational data understandable through visuals and direct actions, and never show a value the database does not hold.

---

1. PRODUCT VISION

The Control Tower is the staff command centre of Sørpedia. It has its own full-screen shell: no shop header or navigation, one exit arrow at the top left, short headings.

It covers three domains that share data but are never mixed:

Product operations: products, specifications, quality control, improvement tasks, stock and supply.
Customer intelligence: searches, questions to Søren, Byggelab sessions, guide use, purchases.
Physical logistics: vehicles, shipments, routes, weather, road messages, ferries, delays and deliveries.

The warehouse floor shows products and shipments only. A search, a chat, a Byggelab session or any other analytics event never becomes a parcel. An observation can lead a person to create a task; the product of that task can then appear on the floor.

---

2. NAVIGATION

Section       Purpose
Oversikt      What needs attention now, operational status, the week's figures
Gulv          Products and shipments on an isometric floor, goods in, the order list, improvement tasks
Transport     The map of Norway with every demo vehicle, the simulation clock, ordering and trips
Kundeservice  Customer cases handed over by Søren, with the conversation, orders and refunds
Innsikt       Customer behaviour and trends, missing stock, observations for review
Omvisning     A guided tour through every section
Spør Søren    A drawer with the staff assistant, opened on purpose

A feature lives in one section and is linked from the others. Improvement tasks live only in Gulv, Forbedringsoppgaver.

---

3. THEME AND DESIGN

The tower follows the account's theme setting from Settings. Dark when the account chose dark; light for light, system or no choice. Every colour is a token with a light and a dark value, including the map, the charts and the floor ground. Neutral series use an informational hue; amber means warning or pending; red means error or urgent; green means done or healthy. Status is never colour alone. Details are in CONTROLTOWER-REDESIGN.md.

---

4. OVERSIKT

Order of importance:

1. Viktigst nå: the single most urgent thing (the oldest waiting case, else a late shipment, else the most common search without a result) with one action.
2. Customer cases waiting, the route today (shipments on the road, ferries, closed roads, warnings).
3. Deliveries on time with numerator and denominator, orders and revenue.
4. Searches with a result, Søren answered without a person, product data coverage.
5. Recent activity, warehouses with stock (stock per store is demo data and marked).

A status line above the cards lists only what needs attention. Every card leads to the view or the records behind its number.

---

5. GULV

5.1 Floor
The isometric floor with conveyors and stations stays. Each crate is a real product and each pallet a real shipment. Stations: Varemottak, Sendinger inn, Produktkontroll, Datakvalitet, Varekunnskap, Kompatibilitet, Klargjøring. Clicking a crate shows the product, its specifications with source and level, revisions and reports. Without WebGL the same content is a table.

5.2 Varemottak
Shipments on the way in with their ETA. Delivered shipments that are not yet checked in, with their items and Kvitter mottak. Until checked in, their products stand at the Varemottak station.

5.3 Bestillingsliste
The order list from Innsikt: each line with its quantity and state (Venter på bil, Bestilt with shipment and source store, Mottatt). A waiting line can be removed. A link leads to Transport to choose vehicles.

5.4 Forbedringsoppgaver
The only place for improvement tasks and their lifecycle (Åpen, Under behandling, Klar for kontroll when a check is genuinely needed, Fullført, Avvist, Arkivert).

5.5 Lagerflyt
Units in from arrived shipments, units sold, the net change and units on the way, per day.

---

6. TRANSPORT

6.1 Map
An SVG map of Norway with roads, counties and towns. The presenter owns the camera: zoom and pan freely; Følg kjøretøy and Tilbakestill visning are explicit choices. Weather, road messages and ferries from the stored conditions are drawn on the map with their source.

6.2 Vehicles
Every demo vehicle is on the map: on a trip at its simulated position, otherwise at its base. Clicking a vehicle opens its panel.

A vehicle on a trip shows where it is (phase, road, from and to), the weather with its source and fetch time, the booked and the new arrival, the delay with each reason and its source, and the notices in time order. Spill av and Pause control the clock; Spill av fra start replays that trip with the current scenario without receiving the goods twice.

A free vehicle shows the stores within 60 km, the waiting lines one of them has (store, units there, units it will fetch of units wanted, a red triangle when there are too few), an order button for the ticked lines, and a demo trip without cargo to a point clicked on the map.

6.3 Bestill alt
Assigns every waiting line to the free vehicle with the nearest store that has it. The map marks the vehicles that take part with a count; lines no free vehicle reaches are listed with the stores that hold them, and those stores get a red triangle on the map. Unticking a line or a whole vehicle leaves it out of this order; it stays on the list.

6.4 Orders and arrival
An order takes the units off the source store's shelf, plans the route from the vehicle through its stores to the receiving store and starts the clock. On arrival the units are added to the receiving store once, the lines become Mottatt and the shipment Levert, and it appears in Varemottak.

6.5 Routes
Routes follow a road network of towns, quays and roads. The west coast takes the E39 ferries Halhjem to Sandvikvåg and Mortavika to Arsvågen only where the geography requires them. Inland and east routes drive without a ferry.

6.6 Clock and scenarios
One simulated clock drives every trip: play, pause and the rates x60, x240, x600, x1800. It pauses itself when no trip is on the road.

Ekte forhold: the weather nearest each leg from MET Norway, road messages from Statens vegvesen on the road the leg names, ferry intervals and cancellations from Entur, all stored by the scheduled job `scripts/conditions.php`. Rows the job could not fetch are demo rows and say so.

Simulert storm: the coast weather is replaced by a storm labelled as simulated; road messages and timetables stay real.

An event (road works, storm, cancelled departure) becomes known shortly before the vehicle reaches it. The arrival time is recalculated with the known events only; the shipment's status, arrival and reason follow. Every notice and reason names its source.

6.7 Shipments list
Searchable by reference, vehicle or city, with route, status, progress and arrival. A delivered shipment is never shown with an old position.

---

7. KUNDESERVICE

Cases Søren handed to a person, with what the customer wrote to Søren before, the thread, the product, the customer's paid orders with images, a draft from Søren that is never sent without a person, refunds with confirmation, and closing.

---

8. INNSIKT

Level 1: six outcome tiles (searches, searches without a result, questions to Søren, handed to a person, purchases, orders with a matching accessory). Each rate shows its numerator and denominator; the change against the previous week is coloured by what it means. A quieter row holds guides, add to cart and time to cart.

Level 2, Trenger oppmerksomhet: Mangler i butikken (products with an empty shelf in the receiving store, best sellers first, with stock elsewhere, a quantity and Legg i bestilling), searches without a result, days of stock left.

Level 3, Utvikling: searches per day with a forecast only when there are enough days, the funnel from search to purchase, orders per day, order values with mean and median explained.

Folded: observations for review, Byggelab and guide use, data quality and sources.

---

9. OMVISNING

A guided tour of every section in order, with a step for the clock and the conditions, for choosing a vehicle, and for Mangler i butikken. Steps that need an action wait for it.

---

10. SPØR SØREN

A drawer opened from the header and closed with Escape or its close button; no view docks it. Søren answers from counted figures with the same rules as in the shop and never changes data.

---

11. DATA INTEGRITY

1. Price, stock, product name and ID come from the database.
2. Every specification shows its source and level; unknown is a valid answer.
3. Stock per store, vehicles, drivers, the clock and the speed are demo data and marked so.
4. No external call during a page request. Weather, roads and ferries come from the scheduled job only.
5. No fabricated live events: the floor and the feeds read stored rows.

---

12. TECHNICAL ARCHITECTURE

Plain PHP and JavaScript, no build step. Routes under `/api/tarn/*`; writes require the staff or admin role and a CSRF token.

Road network and routing: `src/Roads.php`. Plans, phases, events, notices: `src/DemoSim.php`. Trips, clock, conditions per leg, arrival: `src/DemoDelivery.php`. Order list, reach and assignment, orders: `src/Supply.php`. Floor data: `src/Gulv.php`. Insights: `src/Insight.php`. Tables: `sim_clock`, `shipment_plans`, `supply_list`, plus the shipment tables.

---

13. ACCEPTANCE

1. A product added in Innsikt appears in Gulv, Bestillingsliste, and in Transport under Bestill alt.
2. Bestill alt marks the vehicles with counts, lists uncovered lines with their stores, and orders only the ticked lines.
3. A pickup in Bergen crosses at Halhjem and Mortavika, with the Entur source on ferry notices and the MET source on the weather.
4. A delivered order appears in Varemottak; Kvitter mottak removes it and the lines read Mottatt.
5. The theme follows the account setting on every view.
6. A search without a result can be reviewed or ignored without a task.
7. Any vehicle can be selected and its trip played or replayed.
