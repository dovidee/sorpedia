# 021 Banners (locked design)

## Status

Approved and locked. Every page banner (home and all `/tema/*` category pages) uses this design. Do not change the layout, sizes, colors, photos or motion below unless this spec is changed first.

## Layout

1. The banner is full width, edge to edge out of the centered page column (`.banner`, `margin-inline: calc(50% - 50vw)`). The body clips sideways overflow.
2. Two equal halves on screens wider than 767 px: the photo first on the left half, the text on the right half. Text never sits on the photo.
3. The photo fills its half with `object-fit: cover` and fades into the banner background with a horizontal mask (solid to 55 %, transparent at the inner edge). Cropping the photo is fine as long as the subject stays recognizable.
4. The right edge of the text half lines up with the right edge of the centered page column.
5. Height: `min-height: clamp(300px, 28vw, 400px)`, 382 px at 1366 px (28vw). It stays low so the content below (filters and products on category pages) is visible without scrolling.
6. At 767 px and below the banner stacks: the photo first (aspect 16 / 8, fading downward into the background), then the text.
7. No mascot, no product slots, no overlays, no extra decoration in any banner. The mascot appears only in the chat.

## Home banner

1. Background `oklch(14% .01 60)`, text `oklch(99% .004 85)`.
2. Photo: `public/assets/saw/lowsaw.png`, crossfading to `public/assets/saw/highsaw.png` over 4 seconds, starting 5 seconds after both photos have loaded. With reduced motion `highsaw.png` shows directly.
3. Text: the headline "Finn riktig utstyr, og alt som hører til" in Archivo Black, `clamp(2.2rem, 1.2rem + 2.6vw, 3.8rem)`, then the two actions "Start produktguiden" and "Spør Søren". No intro paragraph and no figures.

## Category banners

1. Photo per category from `public/assets/tema/`, chosen by the category slug: `lys` lysoglykter.png, `bygg` byggogfeste.png, `maling` malingogsparkel.png, `balkong` balkongoglevegg.png, `kjetting` kjettingogløft.png, `skog` skogogved.png, `tur` turogfriluft.png. The photo is decorative (`alt=""`).
2. Text: the category name on two lines ("X og" / "Y"), the one sentence overview, and the three figures counted from the database (products, filters, products with an assembly guide).
3. Background colors (fixed, same in light and dark mode):

| Category | Background |
|---|---|
| Lys og lykter | `#CB020F` |
| Bygg og feste | `#55341C` |
| Maling og sparkel | `#F3B7CD` |
| Balkong og levegg | `#17559B` |
| Kjetting og løft | `#C0C5C9` |
| Skog og ved | `#101C12` |
| Tur og friluft | `#0B293C` |

## Acceptance criteria

1. Every banner matches the layout above at 390, 820 and 1366 px, with no sideways scroll.
2. Text is never placed over the photo.
3. No banner contains the mascot, product slots or a sawdust layer.
4. The home photo crossfades from `lowsaw.png` to `highsaw.png` as described, and respects `prefers-reduced-motion`.
5. Category background colors match the table exactly.
