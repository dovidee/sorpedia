<?php
// Print version of an assembly guide (spec 020): the same editorial design as the manual page, laid out on A4.
// Front cover in the category palette, the equipment band and the numbered steps, and a back cover with the Sørpedia logo.
// Rendered to PDF with headless Chrome or Chromium. Set CHROME_BIN to the browser binary if it is not on the PATH.

declare(strict_types=1);

/** The headless browser used for printing, or '' when none is installed. */
function manual_pdf_browser(): string
{
    $env = (string) getenv('CHROME_BIN');
    if ($env !== '' && is_executable($env)) {
        return $env;
    }
    foreach (['chromium', 'chromium-browser', 'google-chrome', 'chrome-headless-shell'] as $bin) {
        $path = trim((string) shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null'));
        if ($path !== '') {
            return $path;
        }
    }
    return '';
}

/**
 * The print HTML for one guide. $m holds title, theme, intro, tools (comma separated) and steps as [title, body, svg].
 * $l holds the interface words in the guide's language.
 */
function manual_pdf_html(array $m, array $l): string
{
    $root = APP_ROOT . '/public/assets';
    $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $tools = array_values(array_filter(array_map('trim', explode(',', (string) $m['tools'])), 'strlen'));
    $tools = array_map(fn(string $x) => mb_strtoupper(mb_substr($x, 0, 1)) . mb_substr($x, 1), $tools);
    $steps = $m['steps'];
    $cover = '';
    foreach ($steps as $s) {
        if ($s[2]) { $cover = $s[2]; break; }
    }
    // Category color and the text color on it, exactly as the category banners on the site (--band-bg, --band-ink)
    [$accent, $onAccent] = [
        'lys' => ['#CB020F', 'oklch(98% .008 85)'], 'bygg' => ['#55341C', 'oklch(96% .014 80)'], 'maling' => ['#F3B7CD', 'oklch(24% .05 355)'],
        'balkong' => ['#17559B', 'oklch(97% .01 240)'], 'kjetting' => ['#C0C5C9', 'oklch(22% .01 250)'], 'skog' => ['#101C12', 'oklch(95% .012 85)'],
        'tur' => ['#0B293C', 'oklch(96% .01 85)'],
    ][$m['theme']] ?? ['#CB020F', 'oklch(98% .008 85)'];
    $n = count($steps);

    ob_start(); ?>
<!doctype html>
<html lang="<?= $e($l['lang']) ?>"><head><meta charset="utf-8"><title><?= $e($m['title']) ?></title>
<style>
@font-face { font-family: "Archivo Black"; src: url("file://<?= $root ?>/fonts/archivo-black-latin-400-normal.woff2") format("woff2"); }
@font-face { font-family: "Archivo"; src: url("file://<?= $root ?>/fonts/archivo-latin-wght-normal.woff2") format("woff2"); font-weight: 100 900; }
:root {
  --accent: <?= $accent ?>; --on-accent: <?= $onAccent ?>; --kit-accent: oklch(from var(--accent) max(l, .76) c h);
  --paper: color-mix(in oklch, var(--accent) 7%, oklch(99% .004 85)); --page: oklch(96.5% .008 85);
  --ink: oklch(20% .012 165); --muted: oklch(46% .014 80); --label: color-mix(in oklch, var(--accent) 55%, var(--ink)); --line: oklch(87% .012 85);
  --dark: oklch(16% .01 160); --dark-ink: oklch(93% .012 80); --dark-muted: oklch(72% .014 80); --dark-line: oklch(100% 0 0 / .1); --grid: oklch(20% .012 165 / .05);
}
@page { size: A4; margin: 16mm 0 18mm; background: oklch(96.5% .008 85);
  @bottom-left { content: "<?= addcslashes($m['title'], '"\\') ?>"; margin-left: 16mm; font: 600 8pt "Archivo", sans-serif; color: oklch(46% .014 80); }
  @bottom-right { content: counter(page); margin-right: 16mm; font: 600 8pt "Archivo", sans-serif; color: oklch(46% .014 80); } }
@page open { margin: 0 0 18mm; }
@page bleed { margin: 0; @bottom-left { content: none; } @bottom-right { content: none; } }
* { box-sizing: border-box; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { margin: 0; background: var(--page); color: var(--ink); font: 400 10.5pt/1.6 "Archivo", sans-serif; }
h1, h2 { font-family: "Archivo Black", sans-serif; font-weight: 400; letter-spacing: -.015em; margin: 0; }
svg { display: block; width: 100%; height: auto; }

/* Front cover: rail in the category accent, title block, first illustration on a drafting grid */
.cover { page: bleed; height: 297mm; display: grid; grid-template-columns: 46mm 1fr; grid-template-rows: auto 1fr; background: var(--paper); }
.rail { grid-row: 1 / -1; display: flex; flex-direction: column; justify-content: space-between; padding: 14mm 8mm; background: var(--accent); color: var(--on-accent); }
.rail p { margin: 0; font: 650 10pt/1.3 "Archivo", sans-serif; }
.rail dl { margin: 0; display: grid; gap: 8mm; }
.rail dl div { display: flex; flex-direction: column-reverse; gap: 1mm; }
.rail dd { margin: 0; font: 500 30pt/1 "Archivo", sans-serif; font-variant-numeric: lining-nums tabular-nums; }
.rail dt { font: 650 9pt/1.3 "Archivo", sans-serif; }
.head { padding: 26mm 16mm 14mm 14mm; }
.head h1 { font-size: 40pt; line-height: 1.02; max-width: 13ch; margin-bottom: 8mm; }
.source { display: flex; align-items: baseline; gap: 2.5mm; margin: 0 0 2mm; font: 650 9.5pt/1.4 "Archivo", sans-serif; color: var(--label); }
.source::before { content: ""; flex: none; width: 2.2mm; height: 2.2mm; background: var(--accent); }
.intro { margin: 7mm 0 0; max-width: 44ch; font-size: 12.5pt; line-height: 1.6; color: var(--muted); }
.art { display: grid; place-items: center; padding: 16mm; border-top: 1px solid var(--line); color: var(--ink);
  background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px); background-size: 8mm 8mm; }
.art svg { max-width: 120mm; }

/* Equipment: dark band on a faint grid */
.kit { break-inside: avoid; display: grid; grid-template-columns: 1fr 2fr; gap: 8mm; padding: 14mm 16mm; background-color: var(--dark); color: var(--dark-ink);
  background-image: linear-gradient(var(--dark-line) 1px, transparent 1px), linear-gradient(90deg, var(--dark-line) 1px, transparent 1px); background-size: 16mm 16mm; }
.kit h2 { font-size: 24pt; line-height: 1.05; }
.kit ul { display: grid; grid-template-columns: 1fr 1fr; column-gap: 7mm; margin: 0; padding: 0; list-style: none; }
.kit li { display: flex; gap: 4mm; padding: 3.5mm 0; border-top: 1px solid var(--dark-line); font-size: 10.5pt; line-height: 1.4; }
.kit li span { flex: none; padding-top: .25em; font: 600 7.5pt/1.3 "Archivo", sans-serif; color: var(--kit-accent); }

/* Steps */
.inside { page: open; }
/* Every page of the steps keeps the same top space */
.steps { padding: 14mm 16mm 0; -webkit-box-decoration-break: clone; box-decoration-break: clone; }
.steps > h2 { font-size: 24pt; margin-bottom: 7mm; }
.step { break-inside: avoid; display: grid; grid-template-columns: 18mm 1fr 1.15fr; column-gap: 7mm; padding: 7mm 0 9mm; border-top: 1px solid var(--ink); }
.step.no-media { grid-template-columns: 18mm 1fr; }
.no { font: 500 24pt/1 "Archivo", sans-serif; font-variant-numeric: lining-nums tabular-nums; }
.no::after { content: ""; display: block; width: 8mm; height: .6mm; margin-top: 3mm; background: var(--accent); }
.step h3 { margin: .5mm 0 3mm; font: 700 15pt/1.2 "Archivo", sans-serif; letter-spacing: -.01em; }
.step p { margin: 0; font-size: 11pt; line-height: 1.65; }
.media { position: relative; margin: 0; padding: 6mm; border: 1px solid var(--line); background-color: var(--paper);
  background-image: linear-gradient(var(--grid) 1px, transparent 1px), linear-gradient(90deg, var(--grid) 1px, transparent 1px); background-size: 8mm 8mm; }
.media::after { content: ""; position: absolute; right: -1px; bottom: -1px; width: 6mm; height: 6mm; background: var(--accent); }

/* Back cover: the logo on a birch plate, framed by the dark grid, with the category accent along the foot */
.back { page: bleed; break-before: page; height: 297mm; display: grid; grid-template-rows: 1fr auto; background-color: var(--dark); color: var(--dark-ink);
  background-image: linear-gradient(var(--dark-line) 1px, transparent 1px), linear-gradient(90deg, var(--dark-line) 1px, transparent 1px); background-size: 16mm 16mm; }
.plate { align-self: center; justify-self: center; position: relative; width: 150mm; padding: 18mm 16mm; background: oklch(97% .008 85); }
.plate::after { content: ""; position: absolute; right: 0; bottom: 0; width: 10mm; height: 10mm; background: var(--accent); }
.plate img { display: block; width: 100%; height: auto; }
.foot { display: flex; justify-content: space-between; align-items: end; gap: 10mm; padding: 10mm 16mm 12mm; border-top: 3mm solid var(--accent); }
.foot p { margin: 0; max-width: 60ch; font-size: 9pt; line-height: 1.5; color: var(--dark-muted); }
.foot b { font: 650 10pt/1.3 "Archivo", sans-serif; color: var(--dark-ink); white-space: nowrap; }
</style></head>
<body>
<section class="cover">
  <aside class="rail">
    <p><?= $e($l['guide']) ?></p>
    <dl><div><dt><?= $e($l['steps']) ?></dt><dd><?= $n ?></dd></div><?php if ($tools): ?><div><dt><?= $e($l['kit']) ?></dt><dd><?= count($tools) ?></dd></div><?php endif ?></dl>
  </aside>
  <div class="head">
    <h1><?= $e($m['title']) ?></h1>
    <p class="source"><?= $e($l['demo']) ?></p>
    <?php if ($m['intro']): ?><p class="intro"><?= $e($m['intro']) ?></p><?php endif ?>
  </div>
  <?php if ($cover): ?><div class="art"><?= $cover ?></div><?php endif ?>
</section>
<div class="inside">
<?php if ($tools): ?>
<section class="kit">
  <h2><?= $e($l['need']) ?></h2>
  <ul><?php foreach ($tools as $i => $t): ?><li><span><?= sprintf('%02d', $i + 1) ?></span><?= $e($t) ?></li><?php endforeach ?></ul>
</section>
<?php endif ?>
<section class="steps">
  <h2><?= $e(str_replace('{n}', (string) $n, $l['count'])) ?></h2>
  <?php foreach ($steps as $i => [$t, $b, $s]): ?>
  <div class="step<?= $s ? '' : ' no-media' ?>">
    <span class="no"><?= sprintf('%02d', $i + 1) ?></span>
    <div><h3><?= $e($t) ?></h3><p><?= $e($b) ?></p></div>
    <?php if ($s): ?><figure class="media"><?= $s ?></figure><?php endif ?>
  </div>
  <?php endforeach ?>
</section>
</div>
<section class="back">
  <div class="plate"><img src="file://<?= $root ?>/brand/sorpedia-full.png" alt="Sørpedia"></div>
  <div class="foot"><p><?= $e($l['back']) ?></p><b>Sørpedia</b></div>
</section>
</body></html>
<?php
    return (string) ob_get_clean();
}

/** Writes $out from the print HTML. Returns false when no browser is installed or printing failed. */
function manual_pdf(array $m, array $l, string $tmpDir, string $out): bool
{
    $bin = manual_pdf_browser();
    if ($bin === '') {
        return false;
    }
    $html = $tmpDir . '/' . basename($out, '.pdf') . '.html';
    file_put_contents($html, manual_pdf_html($m, $l));
    shell_exec('timeout 60 ' . escapeshellarg($bin) . ' --headless --no-sandbox --disable-gpu --allow-file-access-from-files --no-pdf-header-footer'
        . ' --print-to-pdf=' . escapeshellarg($out) . ' ' . escapeshellarg('file://' . $html) . ' >/dev/null 2>&1');
    return is_file($out) && filemtime($out) >= filemtime($html);
}
