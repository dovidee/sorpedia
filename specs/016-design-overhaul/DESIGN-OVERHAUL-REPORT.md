# Design overhaul report (workstream 1)

Implements `specs/016-design-overhaul/DESIGN-OVERHAUL-PLAN.md`. Verification details are in `specs/016-design-overhaul/verification.md`.

## Changed

| Area | Change |
|---|---|
| Tokens | Semantic OKLCH colour tokens with separate light and dark mappings, category accents (`--category-accent` and derived muted, surface tint and ambient), motion, radius and z index tokens. Older short names are aliases. |
| Type | Archivo Black, Archivo, Hanken Grotesk and IBM Plex Mono as local woff2 files. Cormorant Garamond and DM Sans files removed. `font-synthesis: none`. |
| Controls | Checkbox, radio, select, number input, search input and disclosure chevron drawn on tokens; native elements kept. `::selection`, thin token scrollbar and a shared focus ring. |
| Header | Search button and product guide link in ink instead of red; the current category is underlined in its category accent. Logo hover lifts without overshoot; the drifting logo ink is static. |
| Home | Split hero on a twelve column grid, no eyebrow and no photo caption, one red action and a text link to Søren, figures in mono. Category list without numbers, each row lists its top attributes from the database. "Slik fungerer det" as a ruled strip without numbers. |
| Category | One shared header: name, description and "Sammenlign på" with attribute counts from `Catalog::compareKeys`. Breadcrumb, numbered eyebrow, generic lead, duplicate calls to action and showcase removed. |
| Product | Breadcrumb removed. Gallery and sticky purchase panel on seven and five columns, price and specification values in mono, sections separated by rules instead of boxes. |
| Cards | Light image plate, outlined "Legg i kurv" so a grid does not repeat red buttons. |
| Motion | Hero and category header enter once (under 600 ms); home sections reveal once through one IntersectionObserver. Reduced motion removes transforms and animation. |

## Unchanged

Routes, form field names, IDs and data attributes used by `public/assets/app.js`, the deterministic guide, Søren's figure and animations, and the chat panel.

## Dependencies for later workstreams

Byggelab replaces the compare checkbox and the compare bar. Category specific pages, the chat panel and the assembly manuals are separate workstreams; the manual cover now reads the shared category accent so it keeps rendering until its redesign.
