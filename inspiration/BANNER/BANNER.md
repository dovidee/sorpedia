# Claude Implementation Brief --- Integrate the Section Images

## Objective

Integrate the supplied section images into the existing Sørpedia
category pages/sections. This is a **straight integration task**, not a
redesign.

Use the existing page structure, components, typography, spacing,
navigation, product data, and responsive behavior. Do not rebuild the
pages or introduce a new visual system. The supplied screenshots are
visual references for the category hero sections.

**Preserve the existing section background colors exactly.** The
reference screenshots contain the following sampled solid background
colors; retain these hex values where they are already used in the
implementation:

  Section               Background hex
  ------------------- ----------------
  Lys og lykter              `#17130E`
  Bygg og feste              `#55341C`
  Maling og sparkel          `#F3B7CD`
  Balkong og levegg          `#17559B`
  Kjetting og løft           `#C0C5C9`
  Skog og ved                `#101C12`
  Tur og friluft             `#0B293C`

Do not approximate, replace, tint, gradient, or globally normalize these
colors. Do not change text colors or contrast treatments unless strictly
necessary to preserve the current design. The **Tur og friluft** hero
background must be exactly `#0B293C`.

## Supplied section images

The seven section images are named after their corresponding section.
Use each image only in its matching section:

  -----------------------------------------------------------------------
  Section                 Supplied image file     Intended image content
  ----------------------- ----------------------- -----------------------
  Lys og lykter           `lysoglykter(1).png`    Rugged LED work light /
                                                  portable work lamp

  Bygg og feste           `byggogfeste.png`       Screws and fasteners on
                                                  timber

  Maling og sparkel       `malingogsparkel.png`   Paintbrush with pink
                                                  paint

  Balkong og levegg       `balkongoglevegg.png`   Dark timber balcony
                                                  screen / fence

  Kjetting og løft        `kjettingogløft.png`    Heavy chain and lifting
                                                  hook

  Skog og ved             `skogogved(1).png`      Axe splitting wood

  Tur og friluft          `turogfriluft.png`      Outdoor backpack /
                                                  campfire / mountain
                                                  landscape
  -----------------------------------------------------------------------

All seven images are landscape images at approximately **1672 × 941 px**
(16:9). Preserve their aspect ratio. Do not stretch, squash, or crop
away the main subject.

### Reference screenshots

The following files are screenshots of the existing category-page
design. Use them as visual references for the layout, not as replacement
assets:

-   `image(1).png` --- Lys og lykter
-   `image(2).png` --- Bygg og feste
-   `image(3).png` --- Maling og sparkel
-   `image(4).png` --- Balkong og levegg
-   `image(5).png` --- Kjetting og løft
-   `image(6).png` --- Skog og ved

The screenshots show a consistent category hero pattern: the category
heading and description/statistics occupy the left side, while product
tiles occupy the right side. Keep that pattern intact. The section image
is the supplied image for that section; do not use a screenshot as the
section image.

## Integration requirements

1.  **Find the existing category/section implementation first.** Reuse
    its current components, data model, routes, and styling conventions.
    Do not create duplicate category pages or duplicate section
    components.
2.  **Connect each image to the matching category by its existing
    section identity/slug.** Do not rely on array order if the project
    already has explicit category IDs or slugs.
3.  **Use the supplied image files directly.** Place/copy them into the
    project's existing public/static asset location, following the
    repository's current asset conventions. Update only the relevant
    image references.
4.  **Keep the current hero composition.** Preserve the existing
    heading, supporting copy, product highlights, counts, filters,
    navigation, and alignment. Add/integrate the image without removing
    or replacing this content.
5.  **Do not alter the section background hex colors listed above.**
    Keep each color attached to its own section. Avoid introducing a
    shared background style that accidentally makes all category heroes
    the same color.
6.  **Keep the images responsive.** They should scale within their
    intended container, retain their natural proportions, and behave
    correctly on desktop, tablet, and mobile. Follow the project's
    existing responsive breakpoints.
7.  **Use appropriate image fitting.** Prefer `object-fit: cover` only
    if the current design uses a fixed image frame and the subject
    remains clearly visible. Otherwise use `contain` or the project's
    existing image treatment. Set a sensible `object-position` if needed
    to keep the main subject in view.
8.  **Avoid layout shifts.** Preserve or define the image aspect
    ratio/dimensions so the page does not jump while images load. Use
    the project's existing image optimization/lazy-loading approach
    where available.
9.  **Accessibility:** provide concise, meaningful alt text for each
    image, based on the subject and category. If an image is purely
    decorative because the adjacent heading already communicates the
    same information, use the project's established decorative-image
    accessibility pattern.
10. **Do not add new visual effects.** No new overlays, gradients,
    borders, shadows, animations, filters, color grading, or decorative
    UI unless already present in the current component.
11. **Do not change product imagery.** These seven supplied images
    belong to the category/section presentation. Leave individual
    product thumbnails and product data untouched.
12. **Do not modify unrelated pages, categories, copy, functionality, or
    styling.**

## Category-to-asset mapping

Use this explicit mapping:

``` text
lys-og-lykter       -> lysoglykter(1).png
bygg-og-feste       -> byggogfeste.png
maling-og-sparkel   -> malingogsparkel.png
balkong-og-levegg   -> balkongoglevegg.png
kjetting-og-loft    -> kjettingogløft.png
skog-og-ved         -> skogogved(1).png
tur-og-friluft      -> turogfriluft.png
```

If the application's actual slugs differ, map by the existing category
IDs/names rather than renaming routes or changing URL behavior. Preserve
the Norwegian display names and spelling.


## Saw + three-spread sequence integration

There is a second, separate visual sequence made from the supplied files
whose basenames are `lowsaw`, `highsaw`, `spread1`, `spread2`, and
`spread3`. These are not category-hero images. Integrate them into the
existing saw/spread section or feature that already owns this content.
Do not place them in the seven category hero slots above.

### Asset discovery and mapping

- Find the exact supplied files by basename (`lowsaw`, `highsaw`,
  `spread1`, `spread2`, `spread3`) in the project assets or supplied
  files. Preserve their actual extensions and filenames; do not rename
  them or invent replacement assets.
- There are exactly **three spreads**. Display them in this order:
  `spread1` → `spread2` → `spread3`.
- `lowsaw` and `highsaw` are two static visual states of the same saw,
  not two additional spreads.

### Required sequence

| Spread/state | Spread asset | Saw state |
|---|---|---|
| 1 | `spread1` | `lowsaw` |
| 2 | `spread2` | `lowsaw` |
| 3 | `spread3` | Transition from `lowsaw` to `highsaw`; show `highsaw` only on this final spread |

Keep the saw in its low state throughout spreads 1 and 2. Do not reveal,
activate, or swap to `highsaw` early. On spread 3, make the change to
`highsaw` feel intentional and visually polished, using the project's
existing transition/scroll interaction if one already exists. Do not
invent a complex interaction system or add controls just for this effect.

### High-saw composition and containment

On spread 3, let the `highsaw` visual move or extend beyond its *inner
visual frame* slightly, as if the saw is rising/exiting that container.
The effect should feel dramatic, but the image must remain part of the
page composition—not a fixed overlay covering the website.

- Give the saw visual generous, deliberate padding inside its outer
  section so the full composition has room to breathe.
- Reserve enough top, bottom, and side space for the complete saw image
  and its movement. Size and position it relative to the section, not
  the browser viewport.
- Allow visual overflow beyond the inner image frame only where needed
  for the exit/rise effect. Keep the outer section/layout bounds
  controlled; do not create page-wide horizontal scrolling.
- Keep the saw clear of headings, body copy, navigation, product cards,
  buttons, and other interactive content. It must not obscure or block
  the webpage.
- Do not use a full-screen `position: fixed` layer. Avoid an oversized
  image that sits above unrelated page content. If layering is needed,
  keep it local to the saw section and use a sensible stacking order.
- At smaller breakpoints, reduce the image size and/or movement and
  increase the reserved spacing as needed so the saw remains fully
  readable and never collides with text or controls.
- Preserve each asset's natural aspect ratio. Do not stretch or squash
  either saw image or any spread image.

Treat the three spread assets as the three sequential visual stages,
not as a carousel with extra slides. Keep the sequence order stable and
use the existing page's scroll/step behavior if present. If the project
already has a component for the spreads, extend that component rather
than creating a duplicate.

### Saw-sequence acceptance checklist

- [ ] The exact `spread1`, `spread2`, and `spread3` files appear in that
      order.
- [ ] `lowsaw` is used for spreads 1 and 2.
- [ ] `highsaw` activates only on spread 3.
- [ ] The final high-saw state has a controlled rise/exit beyond its
      inner visual frame, without becoming a page-obscuring overlay.
- [ ] The saw has enough surrounding padding and reserved space to fit
      comfortably within the section.
- [ ] No text, navigation, product content, or controls are covered.
- [ ] No horizontal page overflow is introduced at desktop or mobile
      widths.
- [ ] All five assets retain their original aspect ratios and filenames.

## Visual acceptance checklist

-   [ ] All seven categories use their matching supplied image.
-   [ ] No image is assigned to the wrong section.
-   [ ] Each image remains crisp and keeps its natural 16:9 proportions.
-   [ ] The main subject is visible at desktop and mobile sizes.
-   [ ] The existing category hero layout and product tiles remain
    intact.
-   [ ] The six supplied reference background colors remain exactly as
    listed above.
-   [ ] Tur og friluft uses the exact background color `#0B293C`.
-   [ ] Navigation, search, filters, sorting, product counts, and
    product links still work.
-   [ ] No unrelated styling or content has changed.
-   [ ] No console errors, missing assets, or broken image requests.

## Scope boundary

Make the smallest practical code change that integrates these seven
images into their corresponding existing sections. Do not redesign,
refactor unrelated components, rewrite copy, change the color palette,
or add features. After implementation, briefly report which
files/components were changed and confirm that the seven
category-to-image mappings and background colors were preserved.
