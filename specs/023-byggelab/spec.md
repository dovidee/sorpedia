# 023 Byggelab

Product configurator at `/byggelab/{theme}`, reached from the category bar under "Hjelp" (Produktguide and Byggelab). The customer combines products from one category, sees them on an isometric workbench with the connections between them, and gets a check with totals, compatibility, gaps and a test run report. Design brief: `inspiration/BYGGELAB/BYGGELAB.md`.

## Scope

1. Seven builders, one per category (`Byggelab::BUILDERS`): Lysbygger, Byggbenk, Overflateplan, Utebygger, Løfteoppsett, Skogsett, Tursett. Each page has the same three panels and bottom bar.
2. Left: every product in the category with image, price and two key specifications, search, Sør-Tre categories as filter chips, add button and drag and drop onto the workbench.
3. Centre: Three.js workbench (the vendored `three.module.min.js`, as in the Control Tower). Products stand on plinths in lanes derived from stored specifications (light: light sources, bulbs, power and charging, accessories). Connections are tubes coloured by status; unknown is dashed. Click selects a product or a connection. Isometric, rotate, zoom and fit controls. Without WebGL the same content is a list.
4. Right: total price, total weight with "known for k of n", quantity, verdict, compatibility pairs with reason and basis, gaps and notes, category calculations, data basis (values from Sør-Tre or staff, derived or buyer, missing), and the selected product with its specifications and source.
5. Bottom: undo and redo (also Ctrl+Z and Ctrl+Shift+Z), history, save to this browser, saved setups, export shopping list (CSV), ask Søren, test run, add the whole setup to the cart.
6. The configuration lives in the address (`?c=id:qty,...`), so a link can be shared and Søren gets the setup as page context (`PageContext`, kind `byggelab`). The page opens with the first example, never empty.

## Compatibility engine (`src/Byggelab.php`)

Four statuses. Each pair shows its reason and its basis.

| Status | From |
|---|---|
| Dokumentert kompatibel | `relations` passer_sammen, level dokumentert |
| Regelutledet kompatibel | `relations` passer_sammen, level regelutledet, or a category rule over stored specifications |
| Ukjent | assumed relations, pairs without relation or rule, pairs where a rule cannot decide |
| Dokumentert ikke kompatibel | `relations` passer_ikke, level dokumentert |

When two claims cover the same pair the strongest wins in this order: not compatible, documented, rule, unknown.

Category rules (only stored values, never names or guesses):

1. Light: battery type and count from `batteri` against battery products and `pakning_stk`; missing or too few cells; runtime only as stated `brenntid_t` times whole fills; lumen bars and sum with how many are known; charging methods from `lading`; whether a cable is included only from `folger_med`; sockets and voltage.
2. Lifting: weakest link from `arbeidslast_kg` (called "lowest known" while any part lacks it), lowest breaking strength, dimensions, same dimension is a rule, different dimension stays unknown. Angle, sling and jerk are stated as not included.
3. Forestry: chain against file from `x_anbefalt_rundfil` and the file's `diameter_mm`.
4. Outdoor, surface, construction, adventure: totals over stated width, thickness, grit order, pieces per pack, volume.

## Test run

`POST /api/byggelab/{theme}/sjekk` with `test: true` returns the same report plus six steps: inspect products, validate specifications, check compatibility, look for missing information, the category step, result. The workbench animates each step (light: beam cones sized from stated lumen and labelled as an illustration; lifting: the weakest link ring). The report says in text that it reads catalogue data only and is not a physical test. It can be downloaded as text.

## Control Tower

`lab_test` (test run) ends at a new station "Byggelab"; `lab_cart` (whole setup to the cart) ends at Handel. Payload fields `builder` and `verdict` are shown to staff.

## Rules

1. Price, name, stock and specifications come from the database through the API. The browser only sends product IDs and quantities.
2. Lines are cleaned on the server: only products in the category, at most 24 lines, quantity 1 to 99.
3. Unknown values are reported as unknown. No weight, capacity, lumen or rating is filled in.
4. Saved setups use browser storage only and are a convenience.

## Acceptance

1. "Hjelp" in the category bar opens a menu with Produktguide and Byggelab on every page.
2. Every builder page loads with an example and no horizontal scroll at 390, 820 and 1440 px.
3. H5R Core with the MH cable shows "Dokumentert ikke kompatibel" and the verdict Konflikt.
4. Fokus 500 and Fokus 700 without D batteries list the missing D batteries; adding AAA shows the rule pair.
5. A test run writes one `lab_test` row that appears at the Byggelab station in the Control Tower; adding the setup to the cart writes `lab_cart` and updates the cart counter.
6. Without WebGL the setup and the connections are listed as text.
