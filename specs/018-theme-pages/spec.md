# 018 Category pages with their own look

## Problem

The seven category pages (`/tema/*`) looked the same: a breadcrumb, a heading and the filter grid. A customer moving from lights to chains to paint got no sense of entering a different part of the store.

## Approach

Each category page opens with a low full width band: the category photo on one half, fading into the band color, and on the other half the category name (every name is "X og Y" and breaks after "og", or after "and" in English, so all bands share the same two line heading at the same size), a short overview of what the category holds and figures counted from the database. Text never sits on the photo. On narrow screens the photo comes first, then the text. The band is kept low so the filters and the product grid are visible without scrolling. Every category uses the same composition and changes only the palette: lights red, building wood brown, paint pink, balcony blue, chain silver, forest green and outdoors ocean blue. The band palette is fixed and does not follow the light or dark setting.

The band has no product slots and no call to action: the product guide is reached from the home page and Søren from the launcher.

The filters and the product grid below the band keep the shared design system.

## Acceptance criteria

1. Every category page has a band with the category name on two lines ("X og" / "Y", in English "X and" / "Y") at one shared size, and a one sentence overview that is not a repeat of the name.
2. The figures in the band (products in the category, filters, products with an assembly guide) are counted from the database or code, never typed in.
3. The band shows the category photo beside the text, never under it, and no products. At 1366 px the filters and the first product row are visible without scrolling.
4. The seven palettes are as listed above, and the band palette does not change with the light or dark setting.
5. The result count under the band updates with the filters as before.
6. The band works without overflow at 390, 820 and 1366 px. Motion respects `prefers-reduced-motion`.
7. All new text has an English translation through the language toggle.
