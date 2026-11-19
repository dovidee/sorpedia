# Design overhaul plan (workstream 1)

Scope: the shared design foundation and the surfaces needed to apply and verify it (layout, home, category template, product template, form controls). Theme pages per category (workstream 2), the chat panel (3) and assembly manuals (4) are not started. Byggelab is not redesigned here.

## A. Audit

Stack: PHP templates (`templates/*.php`), one stylesheet (`public/assets/app.css`, 876 lines), one script (`public/assets/app.js`), no build step, no animation library. Fonts are served locally from `public/assets/fonts/`.

Routes in scope: `/`, `/tema/{lys,bygg,maling,balkong,kjetting,skog,tur}`, `/produkt/{slug}`, `/sok`, `/guide` (rendering only), `/handlekurv`, `/innstillinger`.

Theme architecture: tokens on `:root`, dark mode through `prefers-color-scheme` unless `data-theme` is set from the user setting. Spacing scale `--space-1..10` already exists.

Must not change: the deterministic guide (`Guide::QUESTIONS`, `/guide` logic), Søren sprites and their animation code (`public/assets/soren.js`, `public/assets/soren/`), search and suggest behaviour, filter form names and auto submit, cart and checkout forms, `t()` translation keys that are still used, provenance labels (`sortre`, `utledet`, `bruker`, `ansatt`) and relation levels.

Data available per category (verified version, facet keys, products with a value, all from Sør-Tre):

| Category | Comparable attributes |
|---|---|
| lys | Lysstyrke 40, Batteri 34, Vanntetthet (IP) 21, Effekt 19, Sokkel 18, Vekt 12 |
| bygg | Lengde 79, Materiale 74, Diameter 48, Dimensjon 47 |
| maling | Bredde 48, Kornstørrelse 6 |
| balkong | Høyde 24, Bredde 12, Glasstykkelse 12 |
| kjetting | Dimensjon 65, Lengde 61, Arbeidslast (WLL) 38, Bredde 37, Materiale 21 |
| skog | Størrelse 29, Lengde 12, Drivlenker 8, Sverdlengde 8 |
| tur | Vekt 13, Forstørrelse 8, Materiale 5, Effekt 5 |

Relations: 14 documented and 2785 rule based "passer sammen", 32 documented "passer ikke", 236 assumed "kjøpt sammen".

## B. Diagnosis (screenshots taken at 1366 px, light and dark)

1. Two unrelated design languages. Home and product pages use Cormorant Garamond; the category hero switches per category between a heavy sans (lys, skog), a centered italic serif (kjetting, tur) and an uppercase tracked sans with a top bar (bygg, maling, balkong). Header and filters stay the same, so each category page looks like a different site under one header.
2. Light birch paper is the default look. The master direction asks for deep pine-charcoal as the base; dark mode exists but is secondary and the category heroes force their own dark panels into light mode, which reads as a copy-paste accident.
3. Generic copy repeated on every category: "Filtrer på mål og egenskaper, se hva som passer sammen, og sjekk om varen er inne i ...", plus "Start produktguiden" and "Spør Søren" CTAs that duplicate the home page and the floating Søren button.
4. Decorative labels with no information: breadcrumb "Forsiden / Lys og lykter" on a top level page, eyebrow "01 / SE BEDRE I MØRKET", numbered home category list 01 to 07, "Slik fungerer det" 01 to 03.
5. Category pages do not say which attributes matter. The facts exist (table above) but are only visible as collapsed filters.
6. Product cards: compare checkbox in browser default style, a filled red button per card (seven competing primary buttons per row), white image plates in dark mode.
7. Browser defaults still visible: checkboxes (`accent-color` only), select arrow, number spinners on the quantity field, `<details>` triangle, list markers in the product description.
8. Motion: logo hover uses an overshoot curve, the logo ink drifts forever, reveals are not systematic, no shared easing tokens.
9. Selection colour is the browser default; the scrollbar is 16 px wide and red on every page, which is louder than the "red is for actions and status" rule.

## C. Proposal

### Visual direction

Nordic industrial intelligence: a dark pine-charcoal workspace with birch and amber light, Swiss grid discipline and thin rules. Structure carries the brutalism (visible grid, hard edges, flush data rows, oversized product imagery), not decoration. Reference image: a dark architectural hero split with a large photo, thin outlined secondary buttons, one solid action colour, numbers in a quiet row. Red stays the only action and status colour.

### Tokens

Semantic colour tokens, OKLCH, separate dark and light mappings (dark is the designed first mode):

| Token | Dark | Light |
|---|---|---|
| `--color-background` | pine-charcoal 17% .012 165 | birch paper 96.5% .008 85 |
| `--color-surface` | 20.5% .013 165 | 99% .004 85 |
| `--color-surface-raised` | 24% .014 165 | 100% .002 85 |
| `--color-text-primary` | 94% .012 85 | 20% .012 165 |
| `--color-text-secondary` | 72% .018 80 | 46% .014 80 |
| `--color-border` | 31% .014 165 | 87% .012 85 |
| `--color-accent` (Sør-Tre red) | 66% .19 25 | 52% .2 27 |
| `--color-accent-hover` | 72% .17 25 | 45% .18 27 |
| `--color-focus` | 80% .14 75 (amber) | 52% .2 27 |
| `--color-selection` | red 40% alpha | red 22% alpha |
| `--color-amber` | 78% .13 75 | 55% .12 65 |
| `--color-birch` | 84% .05 80 | 52% .075 68 |

Existing names (`--bg`, `--surface`, `--ink`, `--muted`, `--line`, `--brand`, ...) become aliases of the semantic tokens, so templates outside scope keep working without edits. Provenance colours (`--sortre`, `--utledet`, `--bruker`, `--ansatt`) and stock colours stay as they are, only re-tuned for contrast.

Category tokens, one block per `[data-tema]`, feeding shared components only: `--category-accent`, `--category-muted`, `--category-surface-tint`, `--category-ambient`, each with a dark and a light value.

| Category | Accent idea |
|---|---|
| lys | warm amber, graphite |
| bygg | steel blue grey, concrete |
| maling | muted rose pigment |
| balkong | timber brown |
| kjetting | brushed steel, silver |
| skog | forest green, earth |
| tur | moss and stone |

They may tint: the category header ambient, the active category underline, selected filter chips, the attribute strip, small graphic accents. They never change header, type, button geometry or spacing.

Motion tokens: `--ease-standard: cubic-bezier(.4,0,.2,1)`, `--ease-out: cubic-bezier(.22,1,.36,1)`, `--dur-tooltip: 100ms`, `--dur-hover: 150ms`, `--dur-press: 150ms`, `--dur-component: 250ms`, `--dur-dialog: 320ms`, `--dur-page: 500ms`, `--dur-hero: 800ms`.

Radius: `--radius: 2px` everywhere (inputs, buttons, plates), `999px` only for counters.

Z index scale: `--z-sticky: 20`, `--z-popover: 30`, `--z-fab: 40`, `--z-overlay: 50`.

### Typography

No serif typefaces anywhere. Cormorant Garamond is removed from the project: its three woff2 files are deleted from `public/assets/fonts/`, its `@font-face` rules and every `--font-display` serif fallback are removed. No decorative or editorial type. All files are local woff2, no external host at runtime.

| Role | Typeface | Weights | Used for |
|---|---|---|---|
| Display | Archivo Black | 400 (single weight, black design) | h1, home headline, major section headings (h2) |
| Interface | Archivo (variable) | 500 to 700 | h3 and h4, navigation, category nav, buttons, tabs, filter headings, product names |
| Body | Hanken Grotesk (variable) | 400 to 600 | body text, descriptions, form text, help text |
| Technical | IBM Plex Mono | 500 | labels, specifications, measurements, prices in tables, stock counts, product IDs, figures |

Font stacks fall back to sans only: `"Archivo Black", "Archivo", system-ui, sans-serif`, `"Archivo", system-ui, sans-serif`, `"Hanken Grotesk", system-ui, sans-serif`, `"IBM Plex Mono", ui-monospace, monospace`.

Scale (rem): 0.75 label, 0.875 small, 1 body, 1.25 h4, 1.563 h3, 2 h2, clamp(2.4, 4vw, 3.6) h1, clamp(3, 6vw, 5) home display. Line height 1 to 1.05 for Archivo Black display, 1.2 for Archivo headings, 1.6 for body. Archivo Black is set with tight tracking (-0.02em) and never below 1.5rem. Uppercase only for mono labels and short Archivo button labels, letter spacing .08em. No italics.

Applied to the current templates: the Søren FAB name, the hero figcaption, card titles and the product title stop using the serif and move to Archivo; category hero variants that switched typeface per category are removed so every page uses the same four roles.

### Layout rules

Container 1320 px, gutter 16 px on phone, 24 px on tablet and 48 px on desktop, section spacing 40 px, 48 px and 64 px for the same breakpoints. Twelve column grid on desktop exposed as faint 1 px grid lines only in the home hero. Cards only where they contain a purchasable product; everything else uses rules (`border-top`) and space.

Three approved layout patterns:

1. Split hero: text left on 5 columns, media right on 7 columns, bleeds to the container edge. Home only.
2. Category header: name and one category specific sentence left, attribute strip right (the comparable attributes from the audit table, with product counts). No hero image beside the title; the popular products stay in the product grid below.
3. Product page: gallery 7 columns, purchase panel 5 columns sticky, then full width data sections separated by rules.

### Home

Split hero with the store photo, display headline, the existing lead, one primary CTA "Start produktguiden" and one text link to Søren. The eyebrow above the headline and the caption on the photo are removed. Numbers row (products, categories, stores) counted from data, in mono. Category index as a two column ruled list without 01 to 07 numbers, each row showing the category name, its real description and its top comparable attributes. Popular products grid. "Slik fungerer det" keeps its three facts (provenance, passer sammen, stock) as a ruled three part strip without numbers, since it is the only place that explains the provenance levels.

Signature interaction (proposal only, not built in this workstream): a relationship explorer that takes one product and draws its documented and rule based "passer sammen" relations from the `relations` table, with the level shown on each link. Needs no backend change because the data exists; it is left for a later feature so this workstream stays inside its boundary.

### Category page

Remove the breadcrumb, the numbered eyebrow, the generic lead, both CTAs and the showcase plates. Keep the h1 (category name) and the category description from `Catalog::THEMES`. Add the attribute strip: "Sammenlign på" with the facet labels that have values from Sør-Tre (`Catalog::compareKeys`, minimum 4) in that category, each with the count of products that state it (from Sør-Tre). Filters and grid as today, restyled.

### Product page

Keep all content and order. Restyle: gallery plate on surface with no white box in dark mode (contained image on a neutral plate), mono price and stock, purchase panel with one primary button, compare checkbox restyled (functionality unchanged until Byggelab replaces it), data sections as ruled blocks instead of boxed cards.

### Shared components

Buttons (primary solid red, secondary outline, text link with arrow, small), inputs and textareas, select with custom arrow, custom checkbox and radio (native input kept, styled with `appearance: none`, visible focus), number input without spinners on the quantity field, `<details>` with a custom chevron, product card, attribute strip, ruled list, provenance badge, stock indicator, flash message, header, category nav, footer.

### Titles and banners

Headings, the home banner, category headers and section titles are plain text only. No slash separators, no bullet characters, no em or en dashes, no numbered prefixes, no eyebrow labels and no caption overlays. Every `.eyebrow` and `.crumbs` element is removed from the templates in scope, and the `.eyebrow` and `.num` styles are deleted. Product names are printed exactly as stored in the database, because the name must come from Sør-Tre unchanged; this is the only exception.

### Interface details

`::selection` in Sør-Tre red tint in both modes. Thin token based scrollbar (10 px, border colour thumb, accent on hover). Focus ring 2 px `--color-focus` with 2 px offset on every interactive element. One icon family: the existing Lucide set in `src/Icons.php` at 1.75 stroke.

### Motion

Premium and engineered: page frame first, then headline, then media, then details, staggered by 60 ms, total under 600 ms. Scroll reveals only on section level through one IntersectionObserver. Hover 150 ms, press `scale(.98)`. No infinite motion except Søren's own sprites. Under `prefers-reduced-motion`: opacity only, no transforms, logo ink stopped.

### Responsive

Phone: one column, header becomes logo, search, cart on one row with categories in a horizontal scroll row, filters collapse into a `<details>` above the grid, purchase panel stacks under the gallery. Tablet: two column product grid, filters as a side column from 900 px. Desktop: as described above. Checked at 390, 820, 1366 and 1920 px.

### Functionality preservation

No change to routes, form field names, IDs used by `public/assets/app.js` (`q`, `suggest`, `filters`, `results`, `result-count`, `sort-select`, `cart-count`, `soren-open`, `compare-bar`) or data attributes (`data-soren`, `data-open-soren`, `data-compare`, `data-auto`). Copy removed from templates keeps its translation key in `I18nEn` only if still used elsewhere.

### Testing plan

PHP lint on changed templates, `php tests/run.php`, `php evals/run.php`, PHPStan level 3. Headless Chromium captures at 390, 820, 1366 px in light and dark for home, lys, kjetting, maling, skog, one product page, guide step 1, cart, settings. Checks: no horizontal overflow (scrollWidth equals clientWidth), keyboard tab through header and filters, reduced motion capture, filter auto submit, search suggest, add to cart.

## How this follows the references

MASTER-DESIGN.md: pine-charcoal base, birch and amber accents, red reserved for actions and status, heavy sans display with functional and mono roles and no serif, thin borders, asymmetric product-first composition, guide and Søren untouched. LAYOUT.md: the spacing scale is kept and enforced, 12 px, 24 px and 40 to 64 px rhythm, section separation by space before borders, one primary action per component, separate phone, tablet and desktop values. UI-brutalism.jpg: dark split hero with a large photo, thin outlined secondary action, quiet number row.
