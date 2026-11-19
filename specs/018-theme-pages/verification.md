# Verification: 018 category pages with their own look

Levels used below: run in the app (`php -S localhost:8099 -t public`, requests with curl, headless Chromium through the DevTools protocol for layout checks and captures), syntax checked, inspected, not verified.

## Implementation

`public/index.php` passes a `band` array to `templates/theme.php`: products in the category (`Catalog::themeIds`), filter groups (spec facets plus price), products with an assembly guide (`Catalog::manualIds` within the category) and the three most popular products (`Catalog::popular(3, $theme)` through `Catalog::cards`). The template maps each category to its world and renders the band. The guide question and the category's answer come from `Guide::QUESTIONS['oppgave']`, and the primary action links to `/guide?oppgave=<category>`. The band CSS in `public/assets/app.css` replaces the spec 016 category header. Band colours are scoped `--band-*` tokens with no dark mode override.

## Run in the app

All seven `/tema/*` pages were loaded at 390, 820 and 1366 px. On every page and width `scrollWidth` equalled `clientWidth`. A first run found 20 px of sideways scroll on the showroom pages (Lys og lykter, Skog og ved) at 390 and 820 px, caused by the glow behind the products reaching past the grid. The glow now stays inside the grid width horizontally, and the rerun showed no overflow.

The band products had opacity 1 after 1.8 s at every width. With `prefers-reduced-motion: reduce` emulated, the band products had no animation (`animation-name: none`) and opacity 1. With `prefers-color-scheme: dark` emulated, Maling og sparkel kept its paper band.

Figures read from the page, for example: Lys og lykter 65 products, 11 filters, 14 with a guide; Maling og sparkel 102, 7, 102; Bygg og feste 118, 8, 0. The product count matched the result count under the band with no filters on all seven pages. With `?v[merke][]=Ledlenser` on `/tema/lys` the result count was 16 products. `/guide?oppgave=kjetting` returned 200.

Captures checked by eye: Lys og lykter, Kjetting og løft and Maling og sparkel at 1366 px, Tur og friluft at 820 px, Bygg og feste at 390 px in dark mode. Each showed its world, the red primary action and three linked products with image and name, no price or stock.

English: `/tema/lys` with the cookie from `/sprak/en` showed name, description, guide question and answer, both actions and all figure labels in English. New strings were added to `scripts/i18n_en.py` and `src/I18nEn.php` regenerated.

PHP lint passed on the changed PHP files. PHPStan with the project configuration reported no errors.

## Not verified

The live filter update in the browser (the JavaScript that swaps the result count) was not clicked through; that code is unchanged and the server side count was checked with curl. The Søren link in the band was not clicked.

## Known weaknesses

The catalog bands stay light in dark mode, as the spec asks, so a bright band sits between the dark header and the dark filter area. A category with no assembly guides shows the figure as 0 (Bygg og feste, Tur og friluft). On phones the three figures wrap onto two lines. Some filter values that come from the product text (for example "Arbeid og verksted") are not translated in English.

## Filter counts follow the active filters

Filter values and counts are computed from the products that match every other active filter, so an option that would give zero hits is no longer offered. Groups with no remaining values are hidden. A checked value stays visible (with its current count) so it can be unchecked. Within one group, the other values keep counting as alternatives (OR).

Known weakness: one extra filter query per group on every theme page load. Fine for a catalog of this size.

## One composition, new palettes, recently viewed slots

Run in the app (`php -S 127.0.0.1:8099 -t public`, curl and headless Chromium captures). All seven `/tema/*` pages returned 200 and had no sideways scroll at 1366 px. Captures checked by eye: Kjetting og løft (silver), Maling og sparkel (pink), Skog og ved (green), Tur og friluft (ocean blue), Lys og lykter in dark mode. With a fresh session every slot showed the zig-zag placeholder. After opening one product in Lys og lykter, `/tema/lys` showed that product in the first slot and two placeholders, and `/tema/skog` still showed three placeholders. The guide question, the band actions and the middle dot separators are gone from the page source. English overviews were added and `src/I18nEn.php` regenerated.

Not verified: Bygg og feste and Balkong og levegg were not captured, only loaded. 390 and 820 px were not rerun for the bands.

## Best rated products and two line names

Run in the app: `/tema/lys`, `/tema/maling` and `/tema/bygg` render the name as "X og" / "Y" at one size and show three best rated products with image and name, no placeholder slots. Checked at 1366 and 390 px with headless Chromium screenshots, no sideways scroll. PHP lint passed on `public/index.php` and `templates/theme.php`; `src/I18nEn.php` regenerated and linted. English names now all read "X and Y".

Not verified: the English band in the browser, and a category where fewer than three products exist.

## Category photos in the band

Each band shows a 16:9 category photo above the three product slots, keyed by the category slug in `templates/theme.php` and served from `public/assets/tema/`. The image is decorative (`alt=""`) because the heading names the category. Width and height attributes plus `aspect-ratio` reserve the space before it loads. Band background colors are unchanged; the seven OKLCH values convert to #17130E, #55341C, #F3B7CD, #17559B, #C0C5C9, #101C12 and #0B293C.

Run in the app (`php -S 127.0.0.1:8099 -t public`, curl and headless Chromium captures). All seven `/tema/*` pages returned 200, each with its own photo, and every photo URL returned 200 (including the encoded `ø` in the chain photo). Captures checked by eye: Tur og friluft at 1366 px, Maling og sparkel at 820 px, Kjetting og løft at 390 px, no sideways scroll.

Known weakness: the photos are 2 to 3 MB PNG files. Not verified: English band, dark mode, the other four categories by eye.

## Low band: photo half, text half, no product slots

The three product slots and their query are removed. The band is now about 380 px high at 1366 px (was about 780), with the category photo on the left half fading into the band color and the text on the right half. On phones the photo comes first. Lys og lykter uses #CB020F with lighter secondary text for contrast.

Run in the app (`php -S 127.0.0.1:8099 -t public`, curl and headless Chromium captures). All seven `/tema/*` pages returned 200. Captures checked by eye: Lys og lykter at 1366 px (filters, sort bar and first product row visible without scrolling), Tur og friluft at 390 px. PHP lint passed on `public/index.php` and `templates/theme.php`.

Not verified: 820 px, English band, the other five categories by eye.
