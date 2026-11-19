# 016 Design overhaul: Nordic industrial visual system

## Goal

One visual system for the whole store: pine-charcoal and birch paper surfaces, editorial display headings, technical monospace labels, thin borders and a fixed spacing scale. Sør-Tre red is kept for primary actions and status. The store stays easy to scan and navigate on phone, tablet and desktop.

## Scope

Shared tokens and components in `public/assets/app.css`, the header and category navigation in `templates/layout.php`, and the home page in `templates/home.php`. Other pages take the system through the shared tokens and component rules, without template changes.

Not in scope: Byggelab, theme pages per category, the chat panel redesign and the assembly manual redesign. Those are separate features.

## Acceptance criteria

1. Colors, fonts, radius and spacing come from CSS variables on `:root`. Dark mode uses a pine-charcoal base and follows the device unless the user has chosen light or dark in Settings.
2. Spacing uses the scale `--space-1` to `--space-10` (4 to 80 px). Page gutter is 16 px on phones, 24 px on tablets and 48 px on desktop.
3. No serif type. Archivo Black for h1 and section headings, Archivo for everything else: body text, interface headings, navigation, buttons, labels and figures (tabular numbers). No monospace face, and labels are sentence case, never all caps. Font files are served from `public/assets/fonts/`. No font is loaded from an external host.
4. The logo wordmark is readable on both light and dark backgrounds. The leaf keeps its red.
5. Header: logo, search, store picker, inbox, account and cart on one row on desktop and on a 390 px phone when signed in. The current category is marked with an underline, not a filled pill. The category row signals that it scrolls on small screens.
6. Home: split hero with text and calls to action on the left and one store photo on the right, with Søren overlapping the bottom left corner of the photo. No background photo collage. The product guide is a red button in the hero and in the header (red in every state, no leaf); only "Spør Søren" carries the leaf. The hero figures (products, categories, stores) are counted from the database or code, never typed in. Categories are a ruled two column list without numbering, each row showing what its products are compared on (from `Catalog::compareKeys`). Headings and banners carry no eyebrow labels, breadcrumbs, slash separators or numbered prefixes.
7. Form fields, select boxes, checkboxes and radio buttons are drawn on the theme tokens (native elements kept, `appearance: none`), with a custom select arrow, no number spinners and a custom disclosure chevron. Every control shows a visible focus ring.
8. Product page sections are separated by rules and space, not boxes. Category pages open with one shared header (name, description, attributes to compare on) tinted by the category accent; categories change colour accents only, never type or layout.
9. Every page listed under verification renders without overflow at 390, 820 and 1366 px in light and dark mode.
10. Motion respects `prefers-reduced-motion`. Søren animations are unchanged.
