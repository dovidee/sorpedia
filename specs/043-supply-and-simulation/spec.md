# 043 Supply, simulation and Control Tower redesign

Briefs: `inspiration/CONTROLTOWER/CONTROLTOWER.md`, `inspiration/CONTROLTOWER/CONTROLTOWER-REDESIGN.md`, `inspiration/CONTROLTOWER/CONTROLTOWER-TRIAGE.md`. Design pre-flight: `specs/043-supply-and-simulation/design-preflight.md`.

## Supply: from a missing product to goods on the shelf

1. Innsikt, Mangler i butikken: products with no units on the shelf of the receiving store (Kristiansand, warehouse 1), best sellers of the last 60 days first, with the units and stores that have it elsewhere. Legg i bestilling puts the product with a quantity on the order list (`supply_list`, status `valgt`). Stock per store is demo data and marked so.
2. Gulv, Bestillingsliste: every line with its state (Venter på bil, Bestilt with shipment and source store, Mottatt). A waiting line can be removed. Varemottak lists delivered shipments that are not checked in, with their items and Kvitter mottak; the Varemottak station on the floor holds their products until then.
3. Transport: every demo vehicle is on the map, at its simulated position on a trip, or when free where its last trip ended (at its base if it has never driven). After a delivery with goods the vehicle drives back without cargo to where the job started, and stays busy until it is there; a demo trip leaves the vehicle at the chosen point, and a free vehicle away from its base can be sent back to it. Kristiansand is the real store and the receiving store, marked with the red leaf; the other stores are simulated branches for a national expansion, marked with a yellow leaf and as demo. Vehicles at the same spot are fanned out so each can be clicked. Clicking a free vehicle shows the stores within 60 km and the waiting lines one of them has, with have/want per line and a red triangle when the store has fewer than asked; tick lines and order them with this vehicle only. Bestill alt assigns each waiting line to the free vehicle with the nearest store that has it; the vehicles that take part are marked on the map with a count, the lines no free vehicle reaches are listed with the stores that have them, and those stores get a red triangle. Unticking a line or a vehicle leaves it out of the order; it stays on the list.
4. An order takes the units off the source store's shelf in one transaction, plans the route from the vehicle through its stores to the receiving store, and starts the clock. The arrival adds the units to the receiving store once (`shipment_plans.stocked`), sets the lines to `mottatt` and the shipment to `levert`.

## Simulation

1. Routes follow a road network (`src/Roads.php`, Dijkstra over towns, quays and roads). The west coast takes the E39 ferries Halhjem to Sandvikvåg and Mortavika to Arsvågen only where the geography requires them; Oslo, Trondheim and Stavanger drive to Kristiansand without a ferry. A road closure on a leg between two network nodes makes the trip go round it when the network has another way that takes at most 1.25 times as long (`DemoDelivery::DETOUR_MAX`); the closed stretch is drawn red on the map with the reason and its source.
2. One clock (`sim_clock`) drives every trip: play, pause, x60, x240, x600, x1800, and the scenario Ekte forhold or Simulert storm. It pauses itself when no trip is on the road. Switching the scenario also switches the trips already on the road.
3. Ekte forhold uses the rows `scripts/conditions.php` stored: weather from MET Norway near each leg, road messages from Statens vegvesen on the road a leg names, ferry intervals and cancellations from Entur. Each weather badge, delay reason and notice names its source and fetch time. Demo fallback rows say so. Simulert storm replaces the coast weather with a storm labelled as simulated; road messages and ferry timetables stay real.
4. An event (road works, storm, cancellation) becomes known shortly before the vehicle reaches it. The ETA is the plan with the known events; the booked ETA is the plan with none. A delay changes the shipment's status, ETA and reason.
5. Selecting a vehicle on a trip shows where it is, the weather, the ETA and why it changed, and the notices; Spill av fra start replays that trip with the current scenario without receiving the goods twice. A free vehicle can take a demo trip without cargo to a point clicked on the map.

## Redesign

1. Light and dark: the tower follows the account's theme setting (Settings). `mork` renders dark; `lys`, `system` and no account render light. Every colour is a token in `tower.css`, including the map, the charts and the floor ground.
2. Oversikt: the most urgent card first, then customer cases, the route, deliveries, orders, searches, Søren and product data. Solid panels, no glass field.
3. Innsikt: six outcome tiles with numerator and denominator for each rate, a quieter row for the rest, then Trenger oppmerksomhet (missing in the store, searches without results, days of stock), Utvikling (searches, funnel, orders, order values), and folded sections for observations, Byggelab and guide use, and data quality. Neutral series use the info hue; amber and red mean warning and urgent only.
4. Spør Søren is a drawer opened on purpose; no view docks it.
5. Byggelab: the secondary actions in the bottom bar are quiet until pointed at, so Testkjør and the cart lead.

## Triage (CONTROLTOWER-TRIAGE.md)

1. Observations (searches without results, opportunities) have a review state apart from task status: Ikke vurdert, Vurdert, Ignorert, Oppgave opprettet (`event_reviews.outcome`). A linked task reads «Oppgave #N · status» and opens in Gulv, Forbedringsoppgaver.
2. Actions follow the record: Marker vurdert and Ignorer on a raw observation; Opprett forbedringsoppgave only after it is reviewed, with a line on when a task is right; Åpne oppgave #N when one exists. Nothing creates a task automatically.
3. Anonymous sessions are called økter, never customers.
4. A Byggelab session opens with Se oppsett at its exact configuration (`/byggelab/{builder}?c=id:qty`); a session without stored products says so. The dimension warning names each selected part with its value and source and says that the parts differ, not that a catalogue value is wrong.

## Acceptance

1. A product added in Innsikt appears in Gulv, Bestillingsliste, and in Transport under Bestill alt.
2. Bestill alt shows the vehicles that take part with counts, the uncovered lines with the stores that have them, and orders only the ticked lines.
3. A Bergen pickup crosses at Halhjem and Mortavika on the map, with the Entur source on the ferry notices and the MET source on the weather.
4. A delivered order shows in Varemottak; Kvitter mottak removes it, and the lines read Mottatt.
5. With theme `mork` the tower is dark; otherwise it is light, on every view.
6. A search without results can be marked Vurdert or Ignorert without a task.
