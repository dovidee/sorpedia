# Verification: 020 assembly guides as editorial manuals

Levels used below: run in the app (`php -S` on a separate port, headless Chromium through the DevTools protocol and screenshots), syntax checked, inspected, not verified.

## Run in the app

All five guides were loaded at 390, 820 and 1366 px, in Norwegian and English. On every page and width `scrollWidth` equalled `clientWidth`. The step count in the list matched the data (four, and three for the headlamp guide), the heading read «4 steg» / «4 steps», the equipment list had 6, 4, 2, 6 and 2 items, matching the comma lists in `manuals.tools`, and the demo line was present (all seeded guides have `is_demo = 1`).

Screenshots: «Male tak» (catalog palette, terracotta) and «Koble sjakkel» (industrial palette, black and red) at 1366 px, «Balkongglass» (fjord blue) at 820 px, «Hodelykt» (amber on charcoal) at 390 px. The rail sits across the top on tablet and phone.

## Rejected

A pseudo element for the full bleed rail on tablet added sideways scroll (scrollWidth 1290 at 390 px). It was replaced with a clipped box shadow, which does not add to the scroll width.

## Syntax checked

`php -l templates/manual.php`. `scripts/i18n_en.py` regenerates `src/I18nEn.php` with only the six new entries added.

## Not verified

Print output was inspected in the CSS but not printed. A guide without tools, intro or step illustrations does not exist in the seed data, so those branches were inspected only. `prefers-reduced-motion` needs nothing here: the page adds no motion.

## Known weaknesses

The cover repeats the first step illustration. Items in «Du trenger» keep the casing of the source text, so most start with a lower case letter. On the dark palettes the cover and the dark equipment band meet without a strong edge.

## Second pass: step index and layout (run in the app)

Changes: a sticky index of the steps next to the steps (links to `#steg-N`), illustrations beside the step text from 768 px and below it on phones, illustrations on the category tint with a drafting grid instead of dark panels, a drafting grid on the equipment band, the equipment band and steps share the same column split so their edges line up, and the band is one shade lighter in dark mode so it stays separate from the page. Equipment items now start with a capital letter in the template; the rest of the text is as given in `manuals.tools`.

All five guides were loaded at 390, 820 and 1366 px, with and without `?lang=en`, through the DevTools protocol. On every page `scrollWidth` equalled `clientWidth`. Index entries matched the step count (4, and 3 for the headlamp guide) and the equipment items matched the comma lists (6, 2, 6, 2, 4). Screenshots checked: «Male tak» at 1366 and 820 px light, «Hodelykt» at 390 px, «Balkongglass» at 820 px, «Koble sjakkel» and «File sagkjede» at 1366 px dark.

Syntax checked: `php -l templates/manual.php`, CSS braces balanced. No new UI strings.

Not verified: print output (inspected only), the `?lang=en` parameter did not switch the headings in the headless run, so the English text was not seen on screen. A step without an illustration does not exist in the seed data, so that branch was inspected only.

Known weaknesses: the cover still repeats the first step illustration.

## PDF per language (run in the app)

Each guide has an English text in `scripts/manuals.php` and the import writes two PDFs: `{slug}.pdf` (Norwegian) and `{slug}-en.pdf` (English). The page links the English file when the language toggle is set to English and the file exists, otherwise the Norwegian one. The ten PDFs were generated with LibreOffice from a copy of the script without the database inserts. With the `lang` cookie set to `no` and `en`, `/veiledning/male-tak` and `/veiledning/hodelykt-lading` linked `male-tak.pdf` / `male-tak-en.pdf` and `hodelykt-lading.pdf` / `hodelykt-lading-en.pdf`. `pdftotext` showed English text in the `-en` files and Norwegian in the others, and the English file is served as `application/pdf`. The page text itself stays Norwegian.

## Printed PDFs in the page design

The PDFs are now printed with headless Chrome from `scripts/manual_pdf.php` instead of LibreOffice: a front cover with the category rail, title, source line, intro and the first illustration on a grid; the dark equipment band and the numbered steps with their illustrations; a back cover with the Sørpedia logo on a plate over the dark grid. `php scripts/manual_pdfs.php` prints them again without rebuilding the database (set `CHROME_BIN` when the browser is not on the PATH). All ten PDFs were printed, each four pages. Pages of `male-tak.pdf` and the cover of `file-sagkjede-en.pdf` were rendered with `pdftoppm` and checked by eye. The manual page on the site was not changed.

Known weaknesses: without a browser the import keeps the PDFs already in `public/media/manuals`. The page footer and page numbers rely on Chrome's support for page margin boxes.

## Exact category colors

The guide pages and the PDFs now use the exact category banner colors (spec 021) instead of the lifted accent. On the site `.man` reads `--band-bg` and `--band-ink` from the shared palette rules; in `scripts/manual_pdf.php` the same hex and ink values are listed per theme. The rail and the back cover foot use the exact hex; the numbers on the dark equipment band use a lifted version so they stay readable.

Run in the app: all five `/veiledning/*` pages returned 200; File sagkjedet (#101C12) and Male tak (#F3B7CD) captured at 1366 px. All ten PDFs printed again with `php scripts/manual_pdfs.php`; page 1 of `hodelykt-lading.pdf` (#CB020F) and `file-sagkjede-en.pdf` (#101C12) checked by eye.

Page 1 of all ten PDFs checked by eye afterwards: Lys og lykter #CB020F, Skog og ved #101C12, Maling og sparkel #F3B7CD, Balkong og levegg #17559B, Kjetting og løft #C0C5C9, in both languages. Not verified: dark mode on the site, inner pages of the PDFs.
