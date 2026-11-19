Sørpedia --- Connected Product Intelligence Platform
System-wide delivery specification
File: `DELIVERY.md`  
Product: Southern Product Intelligence (SPI)  
Scope: Existing Sørpedia application, integrated Control Tower,
BuildLab, Søren Intelligence, and Produktguiden. This delivery covers the
Control Tower only (see 5.0).
Delivery principle: Extend the existing system. Do not build a
disconnected prototype or a parallel product platform.
---
1. Mission
Transform Sørpedia from a product catalogue with search,
recommendations, product guides, and assistant functionality into one
connected product-intelligence platform.
Customers should be able to discover products, understand how they work
together, assemble complete solutions, validate available compatibility
evidence, and get contextual assistance throughout the journey.
Employees should be able to understand customer needs, identify gaps in
product knowledge, investigate unsuccessful searches and configuration
problems, and improve the catalogue through controlled human review.
The four experiences are one system:
Control Tower --- customer and product intelligence, represented
by an event-backed isometric warehouse.
BuildLab --- reusable product configuration engine, beginning
with the fully functional Lys og lykter builder.
Søren Intelligence --- the existing assistant, extended with
shared customer and product context.
Produktguiden --- the existing deterministic, adaptive product
discovery flow, connected to BuildLab and Søren.
All four must use the existing Sørpedia catalogue, stable product IDs,
specifications, compatibility relationships, analytics infrastructure,
recommendation services, cart, authentication, authorization, and
application navigation.
2. Non-negotiable delivery rules
Inspect the repository and database before implementation. Never
assume the framework, schema, routes, libraries, or API design.
Preserve all existing working functionality, authentication,
authorization, routes, APIs, catalogue behaviour, product guide,
Søren assistant, comparison, and cart integration.
Extend existing services and components before creating new ones. Do
not create a second chatbot, a second product catalogue, a second
recommendation engine, or separate databases for each feature.
Use stable existing product IDs across search, guide
recommendations, BuildLab, Søren, comparison, and cart.
Preserve deterministic product recommendation ranking. An LLM must
not invent products, fabricate specifications, or silently override
the recommendation engine.
Preserve the distinction between verified, derived/inferred,
buyer-contributed, unknown, and documented incompatible information.
Every compatibility claim must have a status, reason, and available
evidence. Never describe inferred compatibility as verified.
Do not invent events, products, specifications, weights, capacities,
safety ratings, stock, prices, or compatibility.
Synthetic, seed, fixture, demo, and imported records must be
identified and excluded from real-user activity metrics unless
explicitly shown in a separately labelled dataset.
Do not present visual configuration simulations as physical tests,
engineering certification, or proof of safety.
Customer information must remain private. Aggregate analytics must
not expose personal or sensitive customer data.
Catalogue changes and new documented product relationships require
an authorized human decision and an auditable workflow.
All visible controls must work. Do not ship decorative buttons that
imply an action has happened when it has not.
Reuse the current stack. The Control Tower uses plain Three.js, vendored
locally with no CDN and no build step (5.2). No other 3D engine, UI framework or large dependency.
Build reusable components and services with category-specific
modules rather than seven independent applications.
---
3. Mandatory discovery and system audit
Complete this phase before making architectural changes. Record findings
in the pull request or a project implementation note.
3.1 Repository and application
Inspect and document:
Framework, language, package manager, build tooling, and runtime.
Route tree, layouts, navigation, page guards, and existing
application shell.
Design system, shared components, forms, dialogs, tables, and
responsive patterns.
Existing analytics dashboard and metric definitions.
Existing search, comparison, product detail, cart, and checkout
flows.
Existing animation, chart, visualization, and drag-and-drop
libraries.
API conventions, server actions, background jobs, subscriptions, and
event ingestion.
Authentication, session management, roles, permissions, and audit
logging.
Tests, linting, formatting, type checks, CI, deployment, and
feature-flag conventions.
3.2 Database and domain model
Inspect actual schemas, migrations, indexes, constraints, and
relationships for:
Products and categories.
Product images, prices, variants, availability, and suppliers where
present.
Product specifications and their source/verification status.
Documented `passer_sammen` and `passer_ikke` relationships.
Rule-derived compatibility and the rules/specifications that support
it.
Buyer-contributed specifications and product feedback.
Product guide sessions, questions, answers, candidate sets,
recommendations, and feedback.
Søren conversations and context handling.
Analytics events, event producers, sessions, timestamps, and source
metadata.
BuildLab or existing saved-configuration functionality, if any.
Cart and order integration.
Product opportunities, moderation, tasks, and audit records, if any.
Seed, fixture, demo, synthetic, and imported data markers.
3.3 Required discovery outputs
Produce a verified inventory that identifies:
---
Area                  Existing          Source of truth   Reuse or change
implementation                      required
---
Products and          Inspect           Existing product  Reuse
categories                              model
Specifications        Inspect           Existing          Preserve
specification     provenance
model
Compatibility         Inspect           Existing          Centralize access
relationships and
rules
Product guide         Inspect           Existing          Extend, do not
deterministic     replace
guide services
Søren                 Inspect           Existing          Add context
assistant and  
tools
Search and comparison Inspect           Existing services Preserve
and routes
Cart                  Inspect           Existing cart     Reuse
API/service
Analytics             Inspect           Existing event    Extend event
pipeline          coverage
Authentication/RBAC   Inspect           Existing auth     Preserve and
system            enforce
BuildLab              Inspect           Existing          Extend or add
functionality, if integrated module
any
Control Tower         Inspect           Existing          Add event-backed
analytics sources visualization
Also document any missing event types, product attributes, evidence
links, permissions, or APIs needed by the feature. Propose the smallest
safe change for each gap. Never silently substitute guessed data.
---
4. Target system architecture
4.1 Shared domain services
Implement or extend shared services around the existing source of truth:
Product catalogue service: product lookup, categories, images,
price, and availability where supported.
Specification service: values plus provenance, verification
status, units, and source references.
Compatibility service: documented relationships, rule-derived
evaluations, unknown results, and documented incompatibilities.
Recommendation service: existing deterministic product guide
ranking and candidate filtering.
Configuration service: saved configurations, items, quantities,
validation snapshots, and history.
Søren context service: permission-filtered context from the
current page, guide, comparison, and configuration.
Analytics service: existing event ingestion and authorized
aggregation/read APIs.
Opportunity service: evidence-backed product/data improvement
opportunities and human review.
Cart adapter: the existing cart integration, invoked through
existing validated application actions.
Do not duplicate business rules between the UI, assistant, guide, and
API. The server-side domain services are authoritative. The UI may
present results but must not be the authority for price, compatibility,
permissions, or cart operations.
4.2 Logical data flow
``` text
Existing Sørpedia catalogue and domain services
       |              |                |
       v              v                v
 Search / Guide    BuildLab         Søren
       |              |                |
       +--------------+----------------+
                      |
              Existing event pipeline
                      |
                      v
               Control Tower
                      |
                      v
             Product Opportunities
                      |
             Authorized human review
                      |
             Existing catalogue tools
```
Use the existing database and event infrastructure. Extend existing
models where appropriate; do not create isolated feature databases.
4.3 API and security boundaries
Follow current server/client and API conventions.
Enforce authentication and role-based permissions on every read and
write endpoint.
Never rely on hidden UI controls as authorization.
Validate all IDs, quantities, payloads, and state transitions
server-side.
Use transactions for multi-record configuration and cart operations
where appropriate.
Prevent duplicate event ingestion and duplicate cart additions
according to existing application semantics.
Avoid exposing private conversation content, raw event payloads, or
unnecessary user identifiers to analytics views.
Keep secrets and privileged database access server-side.
Log sensitive administrative actions using the existing audit
system.
---
5. Control Tower --- database-driven operations floor
5.0 Scope of this delivery
This delivery covers the Control Tower only. Sections 6 to 9 (BuildLab,
Produktguiden, Søren extensions outside the Control Tower, Product
Opportunities) remain planned and are out of scope until separately
started. Build order follows `PRIORITY.md` (phases 1 to 5), followed by
the transport map (5.9) and product-linked tickets (5.10).
The Control Tower replaces three existing staff pages with one panel:
`Flettekø` (`/ansatt`, merge queue and error reports), `Måltall`
(`/admin`, KPIs) and `Kundeservice` (`/support`, tickets). Existing POST
actions for those pages are reused, not duplicated. The old routes stay
working until the Control Tower covers all of their actions. The Control
Tower now covers them all (merge and reject, remove value and correct,
ticket list with the chat log, reply and close); retiring the old pages is
a separate decision.
5.1 Shell, route and access
Route: `GET /ansatt/tarn` (exact path may follow existing route naming).
Access: `has_role('ansatt', 'support', 'admin')`, checked server-side on
the page and on every Control Tower API endpoint. Ticket actions keep the
existing `support`/`ansatt`/`admin` check. Aggregate KPI counts are shown
to all staff; turnover by source and users by role stay restricted to
`admin`.
The Control Tower has its own full-screen shell. It does not use the shop
`layout.php` navigation, header, footer, banners or the Søren chat
launcher. A single back arrow at the top left returns to the previous
staff page. Headers are one or two words. No explanatory paragraphs.
5.2 Visual direction and rendering
Dark pine-charcoal background, muted industrial-green belts, birch and
amber parcels, Sør-Tre red only for exceptions. Monospace for IDs,
counts and timestamps. Square corners. No decorative glow beyond the
selected parcel and active belt.
Rendering uses plain Three.js (ES module) with an orthographic isometric
camera. Rules:
No React, React Three Fiber, Framer Motion, Recharts or other framework.
No build step. Three.js is vendored once as a single file under
`public/assets/vendor/` and loaded only on the Control Tower route. No CDN
and no runtime network calls (project rule 8).
All parcels of one kind share one `InstancedMesh`. Per-parcel state is
written to instance matrices and instance colours, never one mesh per
parcel.
Parcels are transparent boxes with solid edges. The selected parcel
becomes opaque.
Rendering pauses when the tab is hidden.
Fallbacks: if WebGL is unavailable, the panel renders the same data as an
accessible table per station (no canvas). With `prefers-reduced-motion`,
parcels are placed at their station without travel animation. Every
station count has a text equivalent outside the canvas.
5.3 Layout
Three zones on desktop:
Left: conveyor floor (the 3D scene) with stations and belts.
Right of the floor, beside it (never a modal): the inspector for the
selected parcel or selection.
Far right column: KPIs from the existing `/admin` definitions.
On narrow screens: floor first, inspector as a scrollable sheet below,
KPIs last.
5.4 What travels on the conveyor
Two parcel kinds, each from a database table. Nothing is generated by the
client.
Event parcels: one parcel per `analytics_events` row in the selected time
window. Position is derived from its type (5.5). The row ID and
`created_at` are kept on the parcel.
Product parcels: one parcel per `shipment_items` row whose shipment is
inbound and not yet delivered (5.9), plus products with open work
(`spec_revisions.status = 'aktiv'`, `spec_reports.status = 'apen'`, or an
open support ticket linked to the product). Product name, ID, price and
stock come from the database only (project rule 1).
If a window holds more parcels than the scene limit (initially 400), the
floor shows the newest ones and labels the count as sampled, for example
`400 / 2 318`. Totals in KPIs always use full counts.
An empty window shows an idle floor with no parcels. Nothing is invented
to fill it.
5.5 Stations and event mapping
One central mapping in a PHP class (`src/Tower.php`) maps actual event
types to stations. Unknown types are shown in `UKJENT` and never dropped.
Event types verified in the code (producer in `public/index.php`):
`search` -> `SØK` (result found)
`search_zero` -> `AVVIK` (exception lane)
`compare` -> `SØK`
`guide_done`, `guide_feedback` -> `GUIDE`
`chat` -> `SØREN`
`escalate` -> `SØREN`, then `KUNDESERVICE` (escalated parcels leave
Søren on the branch to the human bay)
`spec_report` -> `KVALITET`
`injection_blocked` -> `AVVIK`
`add_to_cart`, `purchase` -> `HANDEL`
`manual_view` -> `GUIDE`
any other type -> `UKJENT`
Product parcels enter at `MOTTAK` (inbound shipments) and stop at
`KVALITET` when the product has open revisions or reports. A product whose
only open work is a linked ticket rests at `KUNDESERVICE`. `MOTTAK` is a
station like the others and can be filtered.
A parcel only follows the path its type allows. Unrelated events are
never chained into one customer journey.
5.6 Inspector
Event parcel: event ID, type, station, actual timestamp (`Europe/Oslo`),
permitted payload fields (search term, hit count, product ID, ticket ID).
`session_id` and `user_id` are never shown (`track()` does not store a
user ID).
Product parcel: product name, ID, price and stock from the database;
specifications per key with source and level (`sortre`, `utledet`,
`bruker`, `ansatt`), missing facet keys, open revisions with the existing
merge/reject actions, open error reports with the existing close/remove
actions, linked open tickets, and inbound shipment if any.
Multi-select (shift-click or drag box): count per type, per station,
time span, and for products the combined missing specification keys.
Actions are only shown when they call an existing authorized route.
5.7 Live and history
Live: the client polls an authorized JSON endpoint
(`GET /api/tarn/events?after=<id>`) every 5 seconds for new
`analytics_events` rows. Ticket and revision changes reuse the existing
SSE stream (`/api/stream`, channels `support` and `staff` for the lists,
`ticket:<id>` for the open ticket).
Freshness ("sist oppdatert") is shown. Polling stops while the tab is
hidden.
History: time filters last hour, today, 7 days, 30 days. Replay plays
stored rows in timestamp order with play, pause and speed. Replay never
writes events.
Filters by station and type apply to the floor, inspector selection,
KPIs and throughput chart alike. The client always sends both lists
explicitly; a request without them selects nothing.
Throughput: events per bucket for the active window, drawn from the same
query. Bottleneck: a station is flagged when its open items (tickets,
reports, revisions) exceed a fixed threshold documented in the code.
5.8 Metrics
Keep the existing `/admin` KPI definitions unchanged: searches
(`search` + `search_zero`), searches without results, completed guides,
chat questions, escalations, comparisons, purchases, top zero-result
terms, cart lines and turnover by source, specifications by version and
source, relations by level and kind, users by role, contributions by
status. Counts that include seed data are labelled `DEMO` (orders,
order lines and reviews with `is_demo_seed = 1`, stock, shipments).
`analytics_events` is not seeded by `scripts/import.php`; its rows are
real application activity and are shown without a demo label.
Event counts follow the window and filters. Catalogue counts
(specifications, relations, contributions, turnover, users) show the
current state and are labelled as not filtered.
Data quality (PRIORITY phase 5): products with missing facet keys,
missing values, unchecked open values, open error reports, products
without price, unknown event types in the window, and stale shipment
positions (labelled `DEMO`).
5.9 Transport and shipments
Data model (new tables in `db/schema.sql`, all with `is_demo`):
`vehicles`: id, fleet_no, type (lastebil, trekkvogn, varebil, tilhenger,
annet), make, model, model_year, registration (restricted),
capacity_kg, payload_kg.
`drivers`: id, alias, driver_ref, phone (nullable, restricted). Demo rows
use an alias and no phone number.
`shipments`: id, ref, direction (inn, ut), status (ikke_startet,
lastes, underveis, stoppet, losses, levert, forsinket, kansellert,
ukjent), origin_city, origin_point, dest_city, dest_point, warehouse_id
(FK `warehouses`), vehicle_id, driver_id, container_ref, container_type,
planned_departure, actual_departure, eta, eta_source,
eta_calculated_at, planned_arrival, actual_arrival,
weight_declared_kg, weight_measured_kg, delay_reason, is_demo.
`shipment_items`: shipment_id, product_id (FK `products`), qty,
weight_kg, weight_basis (malt, deklarert, estimert), handling_unit.
`shipment_positions`: id, shipment_id, lat, lng, heading, observed_at,
received_at, source.
`shipment_route_points`: shipment_id, seq, lat, lng, source (kilde,
estimert).
Rules:
No real fleet, GPS or telematics source exists in the repository. All
rows are demo fixtures with `is_demo = 1`, seeded by
`scripts/transport-demo.php` (also run as a step of `scripts/import.php`).
Running it alone replaces only the demo transport rows and keeps all
other data. The map and every shipment panel show a persistent
`DEMO DATA` label. Demo rows never count in operational KPIs.
Markers sit at the latest stored position. They move only when a new
`shipment_positions` row exists. Nothing is interpolated or animated
between positions as if it were live.
A position older than 15 minutes is shown as stale with its timestamp.
Planned, estimated and actual timestamps are labelled separately.
Routes from `shipment_route_points` with source `estimert` are labelled
as estimates, never as the exact road route.
Weight: declared, measured and estimated values are labelled. Item
weights are summed and compared with the shipment total; a mismatch is
flagged, not adjusted.
The Norway map is drawn from a local, simplified outline file under
`public/assets/` (public-domain or openly licensed source, license noted
next to the file). No tile service.
Registration number and driver phone are returned by the API only to
`admin` and `support`. Other staff get the alias and fleet number.
Inbound shipment items become product parcels at `MOTTAK` (5.4).
5.10 Tickets linked to products
`support_tickets` gets a nullable `product_id` (FK `products`). When a
customer asks for a human, Søren asks which product it concerns if no
product is in context. The customer picks it with the same autocomplete
as the site search; Søren does not search on its own. The ticket is then
shown on that product's parcel.
Søren may draft replies to tickets and to error reports. A draft is only
sent after an employee approves it. Søren never writes to the catalogue
(`spec_values`, `relations`, `products`).
A ticket draft is placed in the staff reply box and sent only with Send.
Error reports have no reply channel (reporters are anonymous), so Søren
gives a short assessment to the staff member instead; the decision stays
with the existing remove value and correct actions. A draft cut off by
the model's budget or deadline is discarded, never shown in part.
5.11 Acceptance
Only persisted rows are shown. An empty window shows an idle floor.
Unknown event types appear in `UKJENT`.
Filters affect floor, inspector, KPIs and chart consistently.
Replay writes nothing.
Product name, ID, price and stock come from the database.
Specification source and level are always shown.
Demo data is labelled wherever it appears.
Unauthorized requests to every Control Tower endpoint get 403.
WebGL off and reduced motion both give a usable panel.
---
---
6. BuildLab --- reusable product configuration engine
6.1 Purpose and initial scope
BuildLab is a PCPartPicker-inspired configuration workspace with the
interaction language of industrial robot-cell setup software.
Implement a shared configuration engine with category-specific modules.
Begin with a fully functional Lys og lykter --- Light Builder.
Design the architecture for all seven builders, but do not claim the
other six are complete until their own rules, data, UI, and tests are
implemented.
Seven planned builders:
Lys og lykter --- Light Builder.
Bygg og feste --- Construction Builder.
Maling og sparkel --- Surface Planner.
Balkong og levegg --- Outdoor Builder.
Kjetting og løft --- Lifting Configurator.
Skog og ved --- Forestry Kit Builder.
Tur og friluft --- Adventure Kit Builder.
6.2 Workspace layout
Use a consistent responsive three-panel layout:
Left --- Product inventory - Searchable catalogue inventory. -
Product categories and filters. - Product cards with existing product
image, name, price, and relevant known attributes. -
Add-to-configuration action. - Drag and drop where it is accessible and
reliable; always provide an equivalent keyboard/touch action.
Center --- Configuration workspace - Selected products and
quantities. - Persistent visual assembly/configuration view. - Product
relationships and connection lines where meaningful. - Component
selection and removal. - Visual status for compatible, inferred,
unknown, and incompatible relationships. - Use SVG/2D diagrams or
existing visualization tools where sufficient. A 3D view is optional and
must not block delivery.
Right --- Configuration inspector - Combined price using current
authoritative product prices. - Total weight only when all required
weights are known. - Relevant technical specifications. - Compatibility
findings and evidence. - Missing components or information, where
supported by rules. - Verification/provenance status. - Clear
incomplete-calculation indicators.
Bottom --- Configuration actions - Configuration history. -
Undo/redo. - Save configuration. - Add configuration to cart. - Export
shopping list. - Ask Søren.
On small screens, convert the panels into accessible tabs, drawers, or
stacked sections without losing functionality.
6.3 Configuration data and behaviour
Use stable catalogue product IDs. A configuration item should reference
the real product record and store only configuration-specific values
such as quantity and user selection. Do not copy product data into a
competing catalogue.
Support:
Add, remove, and change quantity.
Duplicate or reorder where meaningful.
Undo/redo for user edits.
Save and reopen configurations for the authorized owner.
Configuration history/version snapshots where supported.
Cart integration using the existing cart API.
Shopping list export based on the current configuration.
Transfer of guide recommendations into a new or existing
configuration.
Server-side validation is authoritative. Recalculate price and
compatibility when saving or adding to cart; do not trust client totals.
6.4 Compatibility engine
Use existing Sørpedia relationships and rules. Support exactly four
user-facing outcomes:
---
Status                              Meaning
---
DOCUMENTED COMPATIBLE           An explicit documented compatible
relationship exists
RULE-DERIVED COMPATIBLE         Existing deterministic rules infer
compatibility from known
specifications
UNKNOWN                         Evidence is insufficient, missing,
ambiguous, or unavailable
DOCUMENTED INCOMPATIBLE         An explicit documented
incompatibility exists
For every result, provide:
Status.
Plain-language explanation.
Evidence or rule reference.
Relevant product/specification records.
Provenance and verification level.
Any limitations or missing information.
Do not turn "no known incompatibility" into "compatible". Do not upgrade
inferred compatibility to documented compatibility. If evidence
conflicts, show an unresolved/unknown result with the conflict explained
rather than choosing the most optimistic result.
6.5 Test-run
Add a TEST-RUN action that:
Inspects all selected products and quantities.
Loads current authoritative specifications.
Checks documented compatibility.
Evaluates applicable deterministic rules.
Identifies missing or conflicting information.
Calculates category-specific statistics only where inputs are
sufficient.
Produces a visual configuration summary.
Generates a readable report with findings and evidence.
The report must distinguish passed checks, inferred checks, unknowns,
incompatibilities, and incomplete calculations.
A test-run is a data-based configuration review. It is not a physical
test, engineering certification, installation approval, or safety
guarantee. For lifting, construction, electrical, and other
safety-critical categories, clearly state when professional assessment
or authoritative manufacturer documentation is required.
6.6 Lighting Builder --- first complete implementation
Use existing catalogue records where available, including these
requested product families:
Ledlenser H5R Core.
Ledlenser H7R Core.
Ledlenser P7R Core.
Ledlenser 21700 battery.
Ledlenser H/HF/P Core magnetic charging cable.
Osean LED work lights.
Osean camping lanterns.
1852 solar-powered dock lights.
Energetic LED bulbs.
GP batteries.
These names are catalogue lookup targets, not permission to create
missing products. If a product is absent, show no fabricated catalogue
card; record the gap as a product opportunity if appropriate.
Implement lighting-specific checks and summaries where actual data
supports them:
Lumen comparison.
Battery type and model compatibility.
Voltage compatibility.
Socket/base compatibility.
Charging connector and charging compatibility.
Runtime estimates only when the required manufacturer/product data
and calculation method exist.
Combined price.
Total weight only when known for every included item.
Never infer runtime from battery capacity alone if required
load/consumption data is missing. Never infer voltage, socket, charging,
or battery compatibility from product names or appearance.
6.7 Category-specific calculation modules
Define a shared interface for category modules, for example:
Validate selected items.
Evaluate relevant relationships.
Identify missing components/data.
Calculate category statistics.
Produce report sections.
Provide visualization metadata.
Each category module must implement only calculations supported by its
domain data. Do not use one generic "compatible" rule for all seven
categories. The six later builders remain planned until their category
rules are defined, implemented, and tested.
---
7. Produktguiden --- preserve deterministic discovery
7.1 Preserve the existing guide
The current Akinator-style product guide is an existing system. Preserve
its dynamic question flow, candidate filtering, budget filtering,
candidate counts, deterministic ranking, product suggestions,
explanations, feedback, and Søren follow-up.
Do not replace it with a conventional chatbot or LLM-generated
recommendation flow.
Continue to use existing guide rules and ranking based on available user
answers, category, budget, specifications, customer ratings,
availability, and documented relationships where those inputs are
already supported.
7.2 Connect discovery to configuration intent
Support two clear intents:
FIND A PRODUCT --- discover and select one or more products.
BUILD A COMPLETE SOLUTION --- transfer selected recommendations
into BuildLab.
After recommendations, offer actions such as:
Compare recommendations.
Add selected products to BuildLab.
Build a complete solution.
Ask Søren about the recommendations.
When the customer transfers recommendations, pass stable product IDs and
relevant guide context. Do not create duplicate product records.
7.3 Structured feedback and unmet needs
Preserve existing "Did you find what you were looking for?" feedback.
Extend negative feedback with an optional structured description of the
missing need.
Store feedback through existing conventions with suitable privacy
controls. Feedback may inform opportunities, but must not automatically
create products, specifications, or documented compatibility
relationships.
---
8. Søren Intelligence --- extend the existing assistant
8.1 No second chatbot
Keep the existing Søren implementation, identity, mascot, model/tooling,
and product knowledge integrations. Extend its context and permitted
actions rather than building a separate assistant.
8.2 Shared context
Where authorized and relevant, provide Søren with structured context
for:
Current page and product.
Current product category.
Current guide session and previous answers.
Deterministic recommendations and explanations.
Current comparison selection.
Current BuildLab configuration and quantities.
Compatibility findings and evidence.
Missing components and active warnings.
Previously recommended products.
Do not send unnecessary personal information or unrestricted raw records
to the model.
8.3 Contextual actions
Søren may offer actions through existing application APIs/tools:
Add or remove a product in BuildLab.
Compare selected products.
Open product specifications.
Start Produktguiden.
Explain a compatibility result.
Suggest missing accessories that exist in the catalogue.
Explain configuration warnings.
Save a configuration.
Help prepare a shopping list.
All actions must be validated by the same domain services used by the
UI. The model must not bypass permissions, write arbitrary catalogue
data, alter documented relationships, or claim an action succeeded
before the application confirms it.
8.4 Evidence-grounded answers
For configuration questions, Søren must distinguish documented facts,
rule-derived conclusions, unknowns, and documented incompatibilities. If
evidence is missing, say so. Do not invent a confident answer.
If Søren cannot confidently narrow a product choice, he may offer to
start Produktguiden and pass relevant context so the customer does not
need to repeat answers.
---
9. Product Opportunities --- the controlled intelligence loop
Create a shared opportunity workflow using evidence across:
Searches without results.
Repeated search terms.
Product guide abandonment.
Negative guide feedback and unmet-need descriptions.
Frequently missing BuildLab components.
Repeated compatibility failures or unknowns.
Unresolved questions to Søren.
Frequently configured product combinations.
An opportunity should include, where available:
Clear title and customer need.
Aggregated event count and observation period.
Related search terms and guide categories.
Related existing products.
Relevant configuration and compatibility evidence.
Missing or conflicting specifications.
Suggested investigation/action.
Status, assignee, notes, and audit history.
Employee actions may include:
Mark investigated.
Link an existing product.
Request missing specifications.
Propose a new catalogue entry.
Propose a product relationship.
Dismiss with a reason.
All catalogue and relationship changes must go through existing
authorized workflows and human approval. An opportunity is a signal for
investigation, not an automatic catalogue mutation.
Aggregate data and apply minimum grouping/privacy safeguards where
appropriate. Do not expose identifiable customer journeys in general
opportunity views.
---
10. Shared analytics event contract
Reuse the current event infrastructure:
`analytics_events` written by `track()` in `src/helpers.php`. No user ID
is stored (data minimization). The Control Tower reads this table; it
adds no new producer.
`events` written by `emit()` and streamed by `/api/stream` (ADR-004).
Used for live ticket and revision updates.
Existing event types and producers are listed in 5.5. New event types
for BuildLab or guide steps belong to those features and are out of scope
for this delivery.
Do not emit duplicate events from both client and server for one action.
One authoritative producer per action.
---
11. Navigation and connected customer journey
Integrate into the existing Sørpedia navigation and route structure. Exception: the Control Tower is a staff panel with its own full-screen shell and no shop navigation (5.1).
Suggested destinations, adapted to current naming and routes:
Forsiden.
Varehus / product categories.
Produktguide.
BuildLab.
Sammenligninger.
Spør Søren.
The customer must be able to move between systems without restarting
their journey.
Target journey
Customer searches for a suitable work light.
Search displays existing catalogue products.
Customer opens Produktguiden.
The guide asks adaptive questions about use, environment, product
type, and budget.
Deterministic ranking returns actual catalogue products with
explanations.
Customer selects recommendations and opens BuildLab.
BuildLab loads the same product IDs.
Customer adds accessories and checks compatibility.
BuildLab calculates supported totals and identifies unknowns.
Customer asks Søren about the actual configuration.
Søren explains evidence and limitations and may suggest existing
catalogue products.
Customer saves the configuration and adds selected items to the
existing cart.
Actual actions are recorded in the shared analytics pipeline.
Control Tower aggregates activity and surfaces patterns.
Employees review evidence-backed opportunities and improve product
data through authorized workflows.
Preserve state across navigation where appropriate and permitted. Do not
leak one customer's saved configuration or conversation into another
user's context.
---
12. UX, accessibility, and performance
Use Sørpedia branding and preserve Søren's existing mascot identity.
Keep Control Tower visually operational and BuildLab technically
precise, while sharing the same design tokens and component
language.
Provide responsive desktop, tablet, and mobile layouts.
All interactive functions must be keyboard accessible, have visible
focus states, and use semantic labels.
Do not rely on color alone for compatibility or provenance status;
include text and icons/labels.
Respect reduced-motion preferences. Animation must not prevent
inspection or operation.
Provide loading, empty, error, stale-data, and permission-denied
states.
Use pagination, virtualization, aggregation, or bounded queries for
large event/product lists.
Avoid rendering every historical event as a live animated object.
Keep scene animation efficient and pause or reduce it when off-screen
or when reduced motion is requested.
Ensure charts and warehouse states have accessible textual
equivalents.
Do not sacrifice product image quality, catalogue usability, or cart
responsiveness for visual effects.
---
13. Testing and acceptance criteria
Use the project's existing checks: `php evals/run.php`, PHPStan (`tools/phpstan.phar`, level 3), and verification in the running app recorded in `specs/NNN-*/verification.md`. This project adds no new test files.
13.1 Regression
Existing routes, authentication, permissions, search, product pages,
guide, Søren, comparison, cart, and checkout continue to work.
Existing deterministic recommendation ordering remains stable for
unchanged inputs.
Existing product IDs and relationships remain authoritative.
13.2 Compatibility and calculations
Each of the four compatibility statuses is tested.
Missing evidence returns unknown, not compatible.
Inferred compatibility is never displayed as documented.
Conflicting evidence is surfaced safely.
Price totals use authoritative current prices and quantities.
Weight totals are incomplete if any required weight is unknown.
Runtime and category calculations are omitted or marked incomplete
when inputs are insufficient.
Server-side validation is tested independently of the UI.
13.3 Control Tower
Only persisted events are represented.
No-event periods show an idle state.
Synthetic/seed data is excluded from live activity.
Time and event filters affect all relevant views consistently.
Historical replay does not create new events.
Parcel inspection resolves to the correct source record and enforces
permissions.
Unknown event types remain visible as unclassified.
Aggregated views do not expose private customer information.
13.4 BuildLab
Add/remove/change quantity and undo/redo work.
Saved configurations can be reopened by authorized users.
Guide recommendations transfer using existing product IDs.
Test-run produces a report with evidence and limitations.
Cart actions use the existing cart service and validate server-side.
Exported shopping lists reflect the current configuration.
Mobile and keyboard interactions work.
13.5 Produktguiden and Søren
Guide question flow and deterministic ranking are preserved.
Recommendation explanations remain available.
Guide-to-BuildLab transfer works.
Søren receives the correct current context.
Søren cannot invent products or bypass validation.
Assistant actions are permission-checked and report success only
after confirmation.
Feedback is stored as structured data and does not mutate catalogue
facts automatically.
13.6 Product opportunities
Opportunity counts are derived from real qualifying evidence.
Duplicate or noisy signals are handled consistently.
Employee actions are permission-checked and audited.
Dismissal requires/retains a reason where specified.
No opportunity automatically creates a product or documented
relationship.
13.7 Quality gates
Before delivery, run and report:
Type checks.
Lint and formatting checks.
Unit tests.
Integration/API tests.
Relevant end-to-end tests.
Production build.
Database migration validation and rollback review.
Accessibility checks on key flows.
Responsive checks at representative screen sizes.
Do not claim a check passed unless it was actually run.
---
14. Phased implementation plan
Deliver in integrated, testable increments. Do not attempt all seven
builders simultaneously.
Phase 0 --- Audit and design
Inspect repository, schema, event producers, permissions, and
integrations.
Produce the discovery inventory and source-of-truth mapping.
Identify missing fields/events and propose minimal changes.
Agree shared data contracts and route integration.
Record risks and migration plan.
Exit condition: Implementation can proceed without guessing about
existing architecture or data.
Phase 1 --- Shared foundation
Establish shared product/specification/compatibility access.
Establish typed configuration and assistant context contracts.
Confirm event provenance and synthetic-data handling.
Preserve existing guide, Søren, cart, and recommendation behaviour.
Add only necessary migrations and APIs.
Exit condition: Shared services are reusable and existing flows pass
regression tests.
Phase 2 --- BuildLab Lighting Builder
Build the three-panel workspace.
Implement product search and selection from the real catalogue.
Implement four-state compatibility display with evidence.
Implement lighting-specific calculations where data supports them.
Implement test-run report.
Integrate save, shopping list, existing cart, and contextual Søren.
Instrument actual BuildLab actions.
Exit condition: A customer can assemble, inspect, validate, save,
and add a supported lighting configuration to the existing cart.
Phase 3 --- Produktguiden connection
Add find-versus-build intent.
Transfer selected recommendations to BuildLab using stable IDs.
Share guide context with Søren.
Preserve deterministic ranking and feedback.
Instrument missing guide events.
Exit condition: Guide-to-BuildLab-to-Søren journey works without
duplicated products or repeated customer input.
Phase 4 --- Control Tower
Build order and scope for this phase: section 5.0 and `PRIORITY.md`.
Integrate existing analytics metrics.
Add warehouse stations, event mapping, parcel inspection, and event
history.
Add live and historical modes, filters, and throughput.
Add BuildLab and guide activity.
Enforce real-versus-synthetic separation.
Exit condition: Warehouse reflects persisted event data and
accurately shows idle periods.
Phase 5 --- Product Opportunities
Aggregate unmet needs and product-data gaps.
Add employee review workflow and audit trail.
Link opportunities to existing products and evidence.
Require human approval for catalogue changes.
Exit condition: Employees can investigate and act on evidence
without automatic catalogue mutation.
Phase 6 --- Expand the six remaining builders
Implement one category at a time using the shared engine:
Bygg og feste.
Maling og sparkel.
Balkong og levegg.
Kjetting og løft.
Skog og ved.
Tur og friluft.
For each category, define domain-specific inputs, compatibility rules,
calculation methods, missing-data behaviour, safety language,
visualization, and tests before marking it complete.
Exit condition: Each builder has validated domain logic and does not
reuse inappropriate calculations from another category.
---
15. Definition of done
The delivery is complete only when the integrated platform meets the
following outcomes.
Customer outcomes
A customer can:
Find products through existing search.
Discover products through the existing deterministic guide.
Understand why a product was recommended.
Compare products.
Transfer recommendations into BuildLab.
Combine actual catalogue products into a configuration.
See documented, rule-derived, unknown, and incompatible
relationships accurately.
Calculate totals only from available authoritative data.
Understand missing information and warnings.
Ask Søren about the current guide or configuration.
Save a configuration and add items through the existing cart.
Continue their journey across the connected features.
Employee outcomes
An authorized employee can:
Understand real customer activity in Control Tower.
Inspect underlying events.
Distinguish live activity from synthetic/demo data.
Identify unsuccessful searches and repeated unmet needs.
Understand common configurations and compatibility gaps.
Review product opportunities with supporting evidence.
Request missing information and propose catalogue improvements
through controlled workflows.
System outcomes
One shared product catalogue and stable product identity.
One authoritative compatibility service.
Existing deterministic recommendation logic preserved.
One existing Søren assistant with contextual capabilities.
One shared analytics/event infrastructure.
Existing authentication, authorization, routes, and cart preserved.
No fabricated products, events, specifications, or safety claims.
Reusable BuildLab engine with independently tested category modules.
Automated regression coverage for critical journeys.
---
16. Final implementation instruction
Treat this document as the system-wide delivery contract. Start by
auditing the existing Sørpedia repository and schema. Then implement the
phases in order, integrating every feature into the current application
and reusing its existing services and data.
Do not deliver a standalone mockup, disconnected dashboard, duplicate
chatbot, replacement recommendation engine, or parallel catalogue. The
final result must be one coherent Sørpedia platform in which Control
Tower, BuildLab, Søren, and Produktguiden share the same real products,
evidence, customer context, and event infrastructure.
