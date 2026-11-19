SØRPEDIA BUILDLAB — PRODUCT CONFIGURATION ENGINE

Reference: https://dribbble.com/shots/27796492-Fieldwork-Robot-Cell-Setup-Service-UI

Build a PCPartPicker-inspired product configuration system for Sørpedia.

The concept is an interactive industrial product configurator inspired by robot-cell setup software.

Customers must be able to combine products, inspect their relationships, calculate totals, check compatibility and simulate configurations.

Seven specialized builders
Lys og lykter — Light Builder
Bygg og feste — Construction Builder
Maling og sparkel — Surface Planner
Balkong og levegg — Outdoor Builder
Kjetting og løft — Lifting Configurator
Skog og ved — Forestry Kit Builder
Tur og friluft — Adventure Kit Builder
Interface

Create a consistent three-panel interface:

LEFT:

Searchable product inventory.
Product categories.
Filters.
Product cards.
Drag and drop.

CENTER:

Interactive configuration workspace.
Isometric or 3D product visualization where appropriate.
Connections between products.
Visual assembly.
Component selection.

RIGHT:

Configuration statistics.
Total price.
Total weight when known.
Technical specifications.
Compatibility warnings.
Missing components.
Verification status.

BOTTOM:

Configuration history.
Undo/redo.
Save configuration.
Add configuration to cart.
Export shopping list.
Ask Søren.
Compatibility engine

Integrate the existing Sørpedia compatibility relationships.

Support four statuses:

Documented compatible.
Rule-derived compatible.
Unknown.
Documented incompatible.

Show the reason and evidence behind every compatibility claim.

Never present inferred compatibility as verified.

Test-run feature

Add a simulation button.

The simulation should:

Inspect all selected products.
Validate available specifications.
Check documented compatibility.
Identify missing information.
Calculate category-specific statistics.
Visualize the assembled configuration.
Produce a readable report.

Different categories must have different simulation logic.

Do not pretend that a visual animation is a physical engineering test.

Do not invent missing specifications, product weights, capacities or safety ratings.

Lighting builder initial demonstration

Use existing catalogue products:

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

Support:

Lumen comparison.
Battery compatibility.
Voltage and socket compatibility.
Charging compatibility.
Runtime estimates when sufficient data exists.
Combined price.
Total weight when known.
Visual direction

Use the Fieldwork robot-cell interface as the interaction inspiration.

Premium industrial software aesthetic.
Dark pine-charcoal surfaces.
Restrained orange/red highlights.
Precise typography.
Consistent control sizes.
Clear technical data.
Smooth interaction animations.
Persistent central visualization.

Preserve Sørpedia branding and Søren's mascot identity.

Technical implementation

Before implementation, inspect the existing repository, framework, product schema, compatibility schema and cart integration.

Reuse existing product data and relationships.

Build a reusable configuration engine with category-specific calculation modules.

Keep all calculations grounded in actual product specifications.

Preserve existing shopping functionality and routes.

Start with a fully functional Lighting Builder before extending the architecture to all seven categories.