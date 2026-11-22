<?php
/**
 * Demo Hub (feature 033): an overview of the running demo, beside the live store in a second tab.
 * Norwegian only: it is a staff page, not customer text. Every figure is counted on this request.
 * @var array $s Presentation::stats()
 * @var array $m measured activity
 */
$v = fn(string $f) => (string) @filemtime(APP_ROOT . "/public/assets/$f");
$areas = Presentation::features($s);
$all = array_merge(...array_map(fn($a) => $a[1], array_values($areas)));
$count = array_count_values(array_column($all, 3));
$live = count(array_filter($all, fn($f) => $f[4]));
$status = ['verifisert' => 'Verifisert', 'implementert' => 'Implementert', 'demodata' => 'Demodata', 'gjenstar' => 'Gjenstår'];
$n = fn(int $x) => number_format($x, 0, ',', "\u{202F}");
$sections = ['oversikt' => 'Sørpedia', 'funksjoner' => 'Funksjoner', 'arkitektur' => 'Arkitektur', 'teknikk' => 'Teknikk', 'soren' => 'Søren', 'veikart' => 'Veikart', 'sporsmal' => 'Spørsmål'];
// Diagram nodes: key => [label, sub, x, y], placed so no line crosses a box
$nodes = [
    'kunde' => ['Kunde', 'nettleser', 20, 30], 'butikk' => ['Butikken', 'katalog, søk, kasse', 280, 30], 'woo' => ['WooCommerce', 'webhook', 800, 30],
    'ollama' => ['Ollama', 'Qwen2.5 3B lokalt', 20, 250], 'soren' => ['Søren', 'chat med KI', 280, 250], 'db' => ['MariaDB', $s['tables'] . ' tabeller', 540, 170],
    'api' => ['REST API', '/api/v1', 800, 170], 'cron' => ['Planlagt jobb', 'MET, vegvesen, Entur', 800, 310],
    'lab' => ['Byggelab', $s['builders'] . ' byggere', 280, 450], 'tarn' => ['Kontrolltårnet', 'ansatte', 540, 450], 'backup' => ['Sikkerhetskopi', 'hver natt', 800, 450],
    'kjoper' => ['Verifisert kjøper', 'åpen versjon', 540, 560],
];
$W = 180; $H = 60;
$links = Presentation::links();
?><!doctype html>
<html lang="nb" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Demo Hub | Sørpedia</title>
<link rel="icon" type="image/png" href="/assets/brand/sorpedia-badge.png">
<link rel="stylesheet" href="/assets/app.css?v=<?= $v('app.css') ?>">
<link rel="stylesheet" href="/assets/present.css?v=<?= $v('present.css') ?>">
<script type="module" src="/assets/present.js?v=<?= $v('present.js') ?>"></script>
<script src="/assets/autodemo.js?v=<?= $v('autodemo.js') ?>" defer></script>
</head>
<body class="ph">
<nav class="ph-nav" aria-label="Seksjoner">
  <a class="ph-brand" href="#oversikt">Sørpedia <small>Demo Hub</small></a>
  <ol>
    <?php $i = 1; foreach ($sections as $id => $label): ?>
      <li><a href="#<?= $id ?>" data-key="<?= $i ?>"><kbd><?= $i++ ?></kbd><?= h($label) ?></a></li>
    <?php endforeach ?>
  </ol>
  <div class="ph-out">
    <a class="ph-btn go" href="/" target="sorpedia-live">Åpne Sørpedia</a>
    <a class="ph-btn" href="/ansatt/tarn" target="sorpedia-live">Kontrolltårnet</a>
    <button type="button" class="ph-btn" id="ph-theme" aria-pressed="false">Mørk visning</button>
    <button type="button" class="ph-btn" id="ph-auto"<?= $demoProduct === '' ? ' disabled' : '' ?> data-product="/produkt/<?= h($demoProduct) ?>" title="Kjører gjennom alle funksjonene på rundt fem minutter">Autodemo</button>
    <p class="ph-keys">Taster: 1 til 7, piltast opp og ned, F for fullskjerm</p>
  </div>
</nav>

<main class="ph-main">
<section class="ph-sec ph-hero" id="oversikt" aria-labelledby="h-oversikt">
  <p class="ph-kicker">Demo</p>
  <h1 id="h-oversikt">Sørpedia</h1>
  <p class="ph-lead">En plattform for netthandel, produktkunnskap og logistikk, bygget på Sør-Tres egne produkter. Kunden får samme veiledning og trygghet på nett som i butikken.</p>
  <div class="ph-cols">
    <div><h2>Problemet</h2><p>Produktsidene mangler ofte målene og egenskapene som avgjør om noe passer. Kunden må gjette, ringe eller returnere, og ansatte svarer på de samme spørsmålene igjen og igjen.</p></div>
    <div><h2>For hvem</h2><p>Gjør-det-selv-kunder og fagfolk som handler hos Sør-Tre, og de ansatte som holder produktdata, kundeservice og leveranser i gang.</p></div>
    <div><h2>Det som er annerledes</h2><p>Hver verdi har en kilde. Kjøpere fyller inn det som mangler, ansatte godkjenner, og Søren, Byggelab og Kontrolltårnet bruker de samme dataene.</p></div>
  </div>
  <dl class="ph-stats">
    <div><dt>Produkter</dt><dd><?= $n($s['products']) ?></dd></div>
    <div><dt>Kategorier</dt><dd><?= $n($s['categories']) ?></dd></div>
    <div><dt>Verifiserte verdier</dt><dd><?= $n($s['specValues']) ?></dd></div>
    <div><dt>Produktkoblinger</dt><dd><?= $n($s['relations']) ?></dd></div>
    <div><dt>Byggere i Byggelab</dt><dd><?= $n($s['builders']) ?></dd></div>
    <div><dt>Ruter i appen</dt><dd><?= $n($s['routes']) ?></dd></div>
    <div><dt>Funksjoner med verifisering</dt><dd><?= $n($s['verified']) ?></dd></div>
    <div><dt>Enhetstester</dt><dd><?= $n(Presentation::GATES['tests']) ?></dd></div>
  </dl>
  <p class="ph-foot">Talt fra databasen <?= h(date('d.m.Y \k\l. H:i')) ?>. Testtallene er fra siste kjøring <?= h(date('d.m.Y', strtotime(Presentation::GATES['date']))) ?>.</p>
</section>

<section class="ph-sec" id="funksjoner" aria-labelledby="h-funksjoner">
  <p class="ph-kicker">2 Funksjoner</p>
  <h2 id="h-funksjoner"><?= count($all) ?> funksjoner, <?= $live ?> kan vises live</h2>
  <div class="ph-filter" role="group" aria-label="Filtrer">
    <button type="button" class="ph-chip" data-f="alle" aria-pressed="true">Alle <b><?= count($all) ?></b></button>
    <?php foreach ($status as $k => $label): if (!isset($count[$k])) { continue; } ?>
      <button type="button" class="ph-chip st-<?= $k ?>" data-f="<?= $k ?>" aria-pressed="false"><?= h($label) ?> <b><?= $count[$k] ?></b></button>
    <?php endforeach ?>
  </div>
  <div class="ph-areas">
    <?php foreach ($areas as $key => [$title, $items]): ?>
      <section class="ph-area" aria-labelledby="a-<?= $key ?>">
        <h3 id="a-<?= $key ?>"><?= h($title) ?> <small><?= count($items) ?></small></h3>
        <ul>
          <?php foreach ($items as [$name, $what, $where, $st, $isLive]): ?>
            <li data-st="<?= $st ?>"><details>
              <summary><span class="ph-fname"><?= h($name) ?></span>
                <span class="ph-tags"><span class="ph-st st-<?= $st ?>"><?= h($status[$st]) ?></span><?= $isLive ? '<span class="ph-live" title="Kan vises live">Live</span>' : '' ?></span></summary>
              <p><?= h($what) ?></p><p class="ph-where"><?= h($where) ?></p>
            </details></li>
          <?php endforeach ?>
        </ul>
      </section>
    <?php endforeach ?>
  </div>
</section>

<section class="ph-sec" id="arkitektur" aria-labelledby="h-arkitektur">
  <p class="ph-kicker">3 Arkitektur</p>
  <h2 id="h-arkitektur">En plattform, de samme dataene</h2>
  <p class="ph-lead">PHP 8 uten rammeverk, MariaDB, vanlig JavaScript og Three.js, Ollama lokalt. Velg en linje for å se hva som flyter og hvor i koden.</p>
  <div class="ph-arch">
    <svg viewBox="0 0 1000 630" role="group" aria-label="Arkitekturdiagram" class="ph-svg">
      <?php $pairs = []; foreach ($links as $i => [$a, $b, $label]):
        [, , $x1, $y1] = $nodes[$a]; [, , $x2, $y2] = $nodes[$b];
        $x1 += $W / 2; $y1 += $H / 2; $x2 += $W / 2; $y2 += $H / 2;
        // A second link between the same two parts runs beside the first
        $key = min($a, $b) . '|' . max($a, $b); $off = ($pairs[$key] ?? 0) * 10; $pairs[$key] = ($pairs[$key] ?? 0) + 1;
        $len = max(1, hypot($x2 - $x1, $y2 - $y1)); $ox = -($y2 - $y1) / $len * $off; $oy = ($x2 - $x1) / $len * $off;
        $pts = sprintf('x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"', $x1 + $ox, $y1 + $oy, $x2 + $ox, $y2 + $oy); ?>
        <g class="ph-edge" data-link="<?= $i ?>" tabindex="0" role="button" aria-label="<?= h($nodes[$a][0] . ' til ' . $nodes[$b][0] . ': ' . $label) ?>">
          <line <?= $pts ?>/><line class="hit" <?= $pts ?>/>
        </g>
      <?php endforeach ?>
      <?php foreach ($nodes as $k => [$label, $sub, $x, $y]): ?>
        <g class="ph-node n-<?= $k ?>" data-node="<?= $k ?>" transform="translate(<?= $x ?>,<?= $y ?>)">
          <rect width="<?= $W ?>" height="<?= $H ?>" rx="3"/><text x="<?= $W / 2 ?>" y="27" text-anchor="middle" class="t"><?= h($label) ?></text>
          <text x="<?= $W / 2 ?>" y="47" text-anchor="middle" class="s"><?= h($sub) ?></text>
        </g>
      <?php endforeach ?>
    </svg>
    <div class="ph-why" id="ph-why" aria-live="polite">
      <p class="ph-muted">Velg en linje i diagrammet eller i listen.</p>
    </div>
  </div>
  <ol class="ph-links">
    <?php foreach ($links as $i => [$a, $b, $label, $how]): ?>
      <li><button type="button" data-link="<?= $i ?>" data-from="<?= h($nodes[$a][0]) ?>" data-to="<?= h($nodes[$b][0]) ?>" data-label="<?= h($label) ?>" data-how="<?= h($how) ?>">
        <b><?= h($nodes[$a][0]) ?> til <?= h($nodes[$b][0]) ?></b><span><?= h($label) ?></span></button></li>
    <?php endforeach ?>
  </ol>

  <h3 class="ph-h3">En kundereise gjennom hele plattformen</h3>
  <ol class="ph-journey">
    <li><b>Butikken</b><span>Kunden søker «hodelykt» og åpner Ledlenser H7R Core (#291483). Hver spesifikasjon viser kilden.</span></li>
    <li><b>Byggelab</b><span>I Lysbyggeren legges lykten inn med H5R Core (#291482), ladekabel (#291385) og batteri (#291485). Sjekken svarer Mangler noe: om den magnetiske ladekabelen følger med er ikke oppgitt, og fire koblinger er ukjent.</span></li>
    <li><b>Søren</b><span>«Spør Søren» sender oppsettet som spørsmål. Svaret bruker katalogdataene, og pris og lager fylles inn fra databasen.</span></li>
    <li><b>Kjøp og bidrag</b><span>Etter kjøpet er kunden verifisert og kan fylle inn det som mangler i den åpne versjonen.</span></li>
    <li><b>Kontrolltårnet</b><span>Bidraget står i Produktkontroll på Gulv. Byggelab-sjekken (hendelse 255) står som mulighet med bildet av H7R Core i Innsikt, og Lag oppgave gjør den til en forbedringsoppgave.</span></li>
    <li><b>Fletting</b><span>Flett inn gjør verdien verifisert med kilden ansatt, og oppgaven for samme verdi blir Fullført. Neste kunde ser svaret.</span></li>
    <li><b>Levering</b><span>Demoleveringen bærer linjene fra siste kundeordre og legger dem på lageret ved ankomst.</span></li>
  </ol>
</section>

<section class="ph-sec" id="teknikk" aria-labelledby="h-teknikk">
  <p class="ph-kicker">4 Teknikk</p>
  <h2 id="h-teknikk">Under panseret</h2>
  <div class="ph-tech">
    <details open><summary>Database <small><?= $s['tables'] ?> tabeller</small></summary>
      <p>Spesifikasjoner lagres som rader (spec_keys og spec_values) med kilde, versjon og bevis, så en ny spesifikasjonstype er en ny rad og ingen migrering. Endringer fra kjøpere er endringssett i spec_revisions som kan flettes, avvises og rulles tilbake.</p></details>
    <details><summary>API <small><?= $s['apiRoutes'] ?> API-ruter</small></summary>
      <p>Den offentlige /api/v1 er read-only med ETag og 304, Link-paginering, feil etter RFC 9457 og en OpenAPI 3.1-beskrivelse. Kontrolltårnet har egne ruter bak rollesjekk.</p></details>
    <details><summary>Innlogging og tilgang</summary>
      <p>Argon2id, låsing i 15 minutter etter fem feil, CSRF-token på alle skjemaer, fire roller, og en revisjonslogg med daglig hash i stedet for IP-adresse.</p></details>
    <details><summary>KI og henting fra katalogen</summary>
      <p>Qwen2.5 3B i Ollama leser spørsmålet inn i faste felt (hensikt, produkttype, krav, budsjett), og tolkningen sjekkes mot kundens egne ord. Serveren finner varene med nøkkelord, synonymer og bruksområder og skriver svaret med pris og lager fra databasen. Hilsener og hjelp får et fast svar uten modellkall. Hver melding sjekkes for instruksjonsangrep og trusler før modellen.</p></details>
    <details><summary>Synkronisering og hendelser</summary>
      <p>Endringer skriver en rad i events i samme transaksjon. Sider holder en Server-Sent Events-forbindelse eller spør jevnlig, og ser nye rader uten å laste siden på nytt. Vær, vei og ferjer hentes av en planlagt jobb, aldri under en sideforespørsel.</p></details>
    <details><summary>Sikkerhetskopi og gjenoppretting</summary>
      <p>scripts/backup.php lager en konsistent gzip-dump hver natt i Docker og beholder de 14 nyeste. Den nyeste vises i Innsikt og på admin-siden, med knapp for ny kopi.</p></details>
    <details><summary>Testing og kvalitet</summary>
      <p><?= Presentation::GATES['tests'] ?> enhetstester, <?= Presentation::GATES['evals'] ?> eval-tilfeller for Sørens faste sikkerhetssvar, PHPStan nivå <?= Presentation::GATES['phpstan'] ?>, og kroker som avviser udokumenterte påstander. <?= $s['verified'] ?> funksjoner har en skriftlig verifisering av hva som ble testet og hva som er svakt.</p></details>
    <details><summary>Responsiv og tilgjengelig</summary>
      <p>Sjekket ved 390, 820 og 1366 px. Redusert bevegelse respekteres i 3D og animasjoner. Lys og mørk visning, og norsk og engelsk med <?= $n($s['english']) ?> oversatte tekster.</p></details>
    <details><summary>Drift</summary>
      <p>Docker Compose med app og MariaDB, nattlig sikkerhetskopi, feillogg med referanse kunden får se, og en admin-side for drift.</p></details>
  </div>
</section>

<?php $so = Presentation::soren();
// One value as text: a share in per cent, a count of the total, or milliseconds
$show = fn(array $r, float $x) => match ($r['fmt']) {
    'pct' => number_format($x * 100, $x > 0 && $x < 0.1 ? 1 : 0, ',', '') . "\u{202F}%",
    'of' => (int) $x . ' av ' . (int) $r['max'],
    default => number_format($x, 1, ',', '') . "\u{202F}ms",
}; ?>
<section class="ph-sec" id="soren" aria-labelledby="h-soren">
  <p class="ph-kicker">5 Søren</p>
  <h2 id="h-soren">Søren før og etter</h2>
  <?php if ($so): ?>
  <p class="ph-lead">Samme <?= $so['cases'] ?> spørsmål, med fasit satt for hånd fra katalogen, kjørt mot den gamle og den nye varehentingen på samme database.</p>
  <table class="ph-cmp">
    <caption class="sr">Varehenting før og etter, målt <?= h($so['date']) ?></caption>
    <thead><tr><th scope="col">Måling</th><th scope="col" class="num">Før</th><th scope="col" class="num">Etter</th><th scope="col" class="ph-cmp-bar"><span class="sr">Sammenligning</span></th></tr></thead>
    <tbody>
      <?php foreach ($so['rows'] as $r): $better = $r['lower'] ? $r['after'] < $r['before'] : $r['after'] > $r['before']; ?>
        <tr>
          <th scope="row"><?= h($r['label']) ?><small><?= h($r['hint']) ?><?= $r['lower'] ? ', lavere er bedre' : '' ?></small></th>
          <td class="num ph-cmp-was"><?= $show($r, $r['before']) ?></td>
          <td class="num ph-cmp-now<?= $better ? ' up' : '' ?>"><?= $show($r, $r['after']) ?></td>
          <td class="ph-cmp-bar" aria-hidden="true">
            <span class="was" style="--w: <?= round(100 * $r['before'] / $r['max'], 1) ?>%"></span>
            <span class="now" style="--w: <?= round(100 * $r['after'] / $r['max'], 1) ?>%"></span>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
  <p class="ph-foot">Målt <?= h($so['date']) ?> med evals/retrieval.php. <?= $so['cases'] - $so['held'] ?> av spørsmålene ble brukt mens hentingen ble laget, så de viser den i beste lys; de <?= $so['held'] ?> nye er den ærlige prøven. Målingen gjelder hvilke varer Søren får, ikke hvordan språkmodellen formulerer svaret.</p>
  <?php else: ?>
  <p class="ph-lead">Målingen mangler. Kjør php evals/retrieval.php --json &gt; specs/034-product-retrieval/after.json.</p>
  <?php endif ?>
  <h3 class="ph-h3">Sikret av serveren, ikke av modellen</h3>
  <div class="ph-cols">
    <div><h3>Pris og sum</h3><p>Priser fylles inn fra databasen. Summer regnes i øre av serveren, og en feil pris i svaret byttes med varens egen.</p></div>
    <div><h3>Passer ikke</h3><p>Sier modellen at noe passer når Sør-Tre har dokumentert at det ikke gjør det, byttes setningen med fakta.</p></div>
    <div><h3>Ukjente modeller</h3><p>Spør kunden etter en modell vi ikke har, svarer serveren selv at den ikke er i sortimentet.</p></div>
  </div>
</section>

<section class="ph-sec" id="veikart" aria-labelledby="h-veikart">
  <p class="ph-kicker">6 Veikart</p>
  <h2 id="h-veikart">Prototype, ikke produksjon ennå</h2>
  <dl class="ph-legend">
    <div><dt><span class="ph-st st-verifisert">Verifisert</span></dt><dd>Testet, med skriftlig verifisering: <?= $count['verifisert'] ?? 0 ?></dd></div>
    <div><dt><span class="ph-st st-implementert">Implementert</span></dt><dd>Finnes og kjører i appen: <?= $count['implementert'] ?? 0 ?></dd></div>
    <div><dt><span class="ph-st st-demodata">Demodata</span></dt><dd>Virker, på data laget for demoen: <?= $count['demodata'] ?? 0 ?></dd></div>
    <div><dt><span class="ph-st st-gjenstar">Gjenstår</span></dt><dd>Må på plass før produksjon: <?= count(Presentation::REMAINING) ?></dd></div>
  </dl>
  <ol class="ph-left">
    <?php foreach (Presentation::REMAINING as [$what, $why]): ?><li><b><?= h($what) ?></b><span><?= h($why) ?></span></li><?php endforeach ?>
  </ol>
</section>

<section class="ph-sec" id="sporsmal" aria-labelledby="h-sporsmal">
  <p class="ph-kicker">7 Spørsmål</p>
  <h2 id="h-sporsmal">Svar vi har klare</h2>
  <dl class="ph-qa">
    <?php foreach (Presentation::QA as [$q, $a, $where]): ?>
      <div><dt><?= h($q) ?></dt><dd><?= h($a) ?> <a href="#<?= $where ?>">Se <?= h(mb_strtolower($sections[$where])) ?></a></dd></div>
    <?php endforeach ?>
  </dl>
</section>
</main>
</body>
</html>
