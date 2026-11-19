<?php
/**
 * Control Tower (feature 022). Own full-screen shell: no shop header, navigation, banners or chat.
 * All data is loaded from /api/tarn/* by public/assets/tower.js.
 */
$u = current_user();
$en = lang() === 'en';
$v = fn(string $f) => filemtime(APP_ROOT . "/public/assets/$f");
$labels = [
    'stations' => array_map(fn($s) => t($s), Floor::STATIONS) + ['inntak' => t('Inntak')],
    'pick' => t('Velg en pakke på gulvet'),
    'event' => t('Hendelse'), 'type' => t('Type'), 'station' => t('Stasjon'), 'time' => t('Tidspunkt'), 'id' => 'ID',
    'product' => t('Produkt'), 'price' => t('Pris'), 'stock' => t('Lager'), 'demo' => 'DEMO',
    'specs' => t('Spesifikasjoner'), 'missing' => t('Mangler'), 'unknown' => t('ukjent'),
    'revisions' => t('Endringer'), 'reports' => t('Feilmeldinger'), 'merge' => t('Flett'), 'reject' => t('Avvis'),
    'remove' => t('Fjern verdi'), 'correct' => t('Riktig'), 'open' => t('Åpne'), 'selected' => t('valgt'),
    'empty' => t('Ingen hendelser i perioden'), 'sampled' => t('viser'), 'updated' => t('Oppdatert'),
    'queue' => t('Kø'), 'failed' => t('Handlingen feilet'), 'noaccess' => t('Ingen tilgang'), 'nowebgl' => t('3D er ikke tilgjengelig. Viser tabell.'),
    'sources' => ['sortre' => 'Sør-Tre', 'utledet' => t('Utledet'), 'bruker' => t('Kjøper'), 'ansatt' => t('Ansatt')],
    'versions' => ['verifisert' => t('Verifisert'), 'apen' => t('Åpen')],
    'kpi' => [
        'title' => t('Måltall'), 'searches' => t('Søk'), 'zero' => t('Søk uten treff'), 'guide' => t('Fullførte guider'), 'chat' => t('Spørsmål til Søren'),
        'escalate' => t('Sendt til menneske'), 'compare' => t('Sammenligninger'), 'purchases' => t('Kjøp'),
        'zeroTerms' => t('Uten treff'), 'coverage' => t('Spesifikasjoner'), 'rel' => t('Koblinger'), 'contrib' => t('Bidrag'),
        'via' => t('Handel per kilde'), 'users' => t('Brukere'), 'viaDemo' => t('demolinjer inkludert'), 'state' => t('Under: katalogen nå, uavhengig av periode og stasjoner'),
    ],
    'throughput' => t('Gjennomstrømning'), 'log' => t('Logg'),
    'play' => t('Spill av'), 'pause' => t('Pause'), 'replay' => t('Avspilling'),
    'arrow' => icon('arrow-right'),
    'answer' => t('Svar'), 'send' => t('Send'), 'close' => t('Lukk sak'), 'sent' => t('Sendt'), 'draft' => t('Utkast fra Søren'), 'drafting' => t('Søren skriver …'), 'drafted' => t('Utkast satt inn. Les og rett før du sender.'), 'messages' => t('meldinger'), 'tstatus' => ['apen' => t('Åpen'), 'pagar' => t('Pågår'), 'lukket' => t('Lukket')],
    'tk' => [
        'title' => t('Saker'), 'none' => t('Ingen åpne saker'), 'noProduct' => t('Uten produkt'), 'guest' => t('Gjest'),
        'chat' => t('Samtalen med Søren'), 'customer' => t('Kunde'), 'staff' => t('Kundeservice'), 'thread' => t('Meldinger'),
    ],
    'assess' => t('Vurdering fra Søren'), 'assessNote' => t('Bare en vurdering. Ingenting er endret.'),
    'combined' => t('Mangler samlet'),
    'q' => [
        'title' => t('Datakvalitet'), 'missingProducts' => t('Produkter med hull'), 'missingValues' => t('Manglende verdier'),
        'unverified' => t('Ukontrollerte verdier'), 'reports' => t('Åpne feilmeldinger'), 'noPrice' => t('Uten pris'),
        'unknownEvents' => t('Ukjente hendelser'), 'stalePositions' => t('Gamle posisjoner'), 'of' => t('av'),
    ],
    'tr' => [
        'demo' => 'DEMO DATA', 'shipments' => t('Sendinger'), 'stale' => t('Gammel posisjon'), 'fresh' => t('Fersk'), 'noPos' => t('Ingen posisjon'),
        'estimate' => t('Estimert rute, ikke veien'), 'progress' => t('Fremdrift'), 'timeline' => t('Tider'), 'tz' => 'Europe/Oslo',
        'plannedDep' => t('Planlagt avgang'), 'actualDep' => t('Faktisk avgang'), 'lastPos' => t('Siste posisjon'), 'eta' => t('Beregnet ankomst'),
        'plannedArr' => t('Planlagt ankomst'), 'actualArr' => t('Faktisk ankomst'), 'variance' => t('Avvik'), 'min' => 'min', 'source' => t('Kilde'),
        'calculated' => t('beregnet'), 'delay' => t('Årsak'), 'vehicle' => t('Kjøretøy'), 'driver' => t('Sjåfør'), 'reg' => t('Reg.nr.'), 'phone' => t('Telefon'),
        'payload' => t('Nyttelast'), 'capacity' => t('Totalvekt'), 'cargo' => t('Last'), 'container' => t('Container'), 'qty' => t('Antall'),
        'weight' => t('Vekt'), 'declared' => t('Deklarert'), 'measured' => t('Målt'), 'itemsSum' => t('Sum varer'), 'remaining' => t('Ledig nyttelast'),
        'mismatch' => t('Vekter stemmer ikke'), 'history' => t('Historikk'), 'fitAll' => t('Alle'), 'fitRoute' => t('Rute'), 'fitNo' => t('Norge'),
        'none' => t('Ingen sendinger registrert'), 'inbound' => t('På vei inn'), 'tickets' => t('Saker'),
        'status' => ['ikke_startet' => t('Ikke startet'), 'lastes' => t('Lastes'), 'underveis' => t('Underveis'), 'stoppet' => t('Stoppet'), 'losses' => t('Losses'),
            'levert' => t('Levert'), 'forsinket' => t('Forsinket'), 'kansellert' => t('Kansellert'), 'ukjent' => t('Ukjent')],
        'types' => ['lastebil' => t('Lastebil'), 'trekkvogn' => t('Trekkvogn'), 'varebil' => t('Varebil'), 'tilhenger' => t('Tilhenger'), 'annet' => t('Annet')],
    ],
];
$types = tower_floor()->knownTypes();
$cfg = ['csrf' => csrf(), 'lang' => $en ? 'en' : 'no', 'role' => $u['role'] ?? '', 'routes' => Floor::ROUTES, 'stations' => array_keys(Floor::STATIONS),
    'types' => $types, 'L' => $labels];
?><!doctype html>
<html lang="<?= $en ? 'en' : 'nb' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<title><?= h(t('Kontrolltårn')) ?> | Sørpedia</title>
<link rel="icon" type="image/png" href="/assets/brand/sorpedia-badge.png">
<link rel="stylesheet" href="/assets/tower.css?v=<?= $v('tower.css') ?>">
<script type="importmap">{"imports":{"three":"/assets/vendor/three.module.min.js"}}</script>
<script type="module" src="/assets/tower.js?v=<?= $v('tower.js') ?>"></script>
</head>
<body class="tw">
<script type="application/json" id="tw-cfg"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<header class="tw-bar">
  <a class="tw-back" href="<?= ($u['role'] ?? '') === 'support' ? '/support' : '/ansatt' ?>" aria-label="<?= h(t('Tilbake')) ?>"><?= icon('arrow-left') ?></a>
  <h1><?= h(t('Kontrolltårn')) ?></h1>
  <fieldset class="tw-win" aria-label="<?= h(t('Visning')) ?>">
    <label><input type="radio" name="view" value="gulv" checked><span><?= h(t('Gulv')) ?></span></label>
    <label><input type="radio" name="view" value="transport"><span><?= h(t('Transport')) ?></span></label>
  </fieldset>
  <fieldset class="tw-win" aria-label="<?= h(t('Periode')) ?>">
    <?php foreach (['time' => t('Time'), 'dag' => t('I dag'), '7d' => '7 d', '30d' => '30 d'] as $k => $l): ?>
      <label><input type="radio" name="w" value="<?= $k ?>"<?= $k === 'dag' ? ' checked' : '' ?>><span><?= h($l) ?></span></label>
    <?php endforeach ?>
  </fieldset>
  <fieldset class="tw-st" id="tw-st" aria-label="<?= h(t('Stasjoner')) ?>">
    <?php foreach (Floor::STATIONS as $k => $l): ?>
      <label><input type="checkbox" name="st" value="<?= $k ?>" checked><span class="<?= $k === 'avvik' ? 'avvik' : '' ?>"><?= h(t($l)) ?></span></label>
    <?php endforeach ?>
  </fieldset>
  <details class="tw-ty" id="tw-ty">
    <summary><?= h(t('Typer')) ?> <b id="tw-ty-n"><?= count($types) ?></b></summary>
    <fieldset aria-label="<?= h(t('Hendelsestyper')) ?>">
      <?php foreach ($types as $ty): ?>
        <label><input type="checkbox" name="ty" value="<?= h($ty) ?>" checked><span><?= h(str_replace('_', ' ', $ty)) ?></span></label>
      <?php endforeach ?>
    </fieldset>
  </details>
  <div class="tw-play">
    <button type="button" class="tw-btn" id="tw-play" aria-pressed="false"><?= h(t('Spill av')) ?></button>
    <select id="tw-speed" aria-label="<?= h(t('Fart')) ?>">
      <?php foreach ([60, 600, 3600] as $s): ?><option value="<?= $s ?>"<?= $s === 600 ? ' selected' : '' ?>>x<?= $s ?></option><?php endforeach ?>
    </select>
  </div>
  <output class="tw-count" id="tw-count" aria-live="polite"></output>
  <span class="tw-live" id="tw-live"><i aria-hidden="true"></i><span></span></span>
</header>

<main class="tw-grid">
  <section class="tw-floor" aria-label="<?= h(t('Gulv')) ?>">
    <div class="tw-canvas" id="tw-canvas"></div>
    <div class="tw-labels" id="tw-labels" aria-hidden="true"></div>
    <p class="tw-idle" id="tw-idle" hidden></p>
    <div class="tw-table" id="tw-table" hidden></div>
    <div class="tw-sr" id="tw-counts" aria-live="polite"></div>
    <div class="tw-map" id="tw-map" hidden>
      <div class="tw-map-bar"><span class="tw-demo-big">DEMO DATA</span>
        <span class="tw-map-fit"><button type="button" class="tw-btn" data-fit="no"><?= h(t('Norge')) ?></button><button type="button" class="tw-btn" data-fit="all"><?= h(t('Alle')) ?></button><button type="button" class="tw-btn" data-fit="route"><?= h(t('Rute')) ?></button></span>
      </div>
      <svg id="tw-svg" role="img" aria-label="<?= h(t('Kart over Norge med sendinger')) ?>"></svg>
    </div>
  </section>

  <aside class="tw-inspect" id="tw-inspect" aria-live="polite" aria-label="<?= h(t('Inspektør')) ?>">
    <p class="tw-muted"><?= h($labels['pick']) ?></p>
  </aside>

  <aside class="tw-kpi" id="tw-kpi" aria-label="<?= h(t('Måltall')) ?>"></aside>

  <section class="tw-flow" aria-label="<?= h($labels['throughput']) ?>">
    <h2><?= h($labels['throughput']) ?></h2>
    <div id="tw-chart" class="tw-chart"></div>
  </section>

  <section class="tw-log" aria-label="<?= h($labels['log']) ?>">
    <h2><?= h($labels['log']) ?></h2>
    <ol id="tw-log"></ol>
  </section>
</main>
</body>
</html>
