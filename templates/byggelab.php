<?php
/**
 * Byggelab (feature 023). Three panels: products on the left, the configuration in 3D in the middle, the check on the
 * right, and the actions along the bottom. Everything in the panels is drawn by public/assets/byggelab.js from
 * /api/byggelab/{theme}/sjekk, so price, stock and specifications always come from the database.
 * @var string $theme @var array $inventory @var array $categories @var array $presets @var array $lanes
 */
[$builderName, $builderDesc] = Byggelab::BUILDERS[$theme];
$v = fn(string $f) => filemtime(APP_ROOT . '/public/assets/' . $f);
$L = [
    'tour' => [
        'ui' => ['of' => t('{i} av {n}'), 'next' => t('Neste'), 'back' => t('Tilbake'), 'close' => t('Lukk omvisningen'), 'finish' => t('Ferdig'),
            'doIt' => t('Gjør det, så går omvisningen videre.')],
        'steps' => [
            ['title' => t('Velg hva du skal bygge'), 'text' => t('Sju byggere, en per kategori. Hver sjekker sine egne regler, for eksempel batterier mot lykter eller kjetting mot sjakler.')],
            ['title' => t('Legg til et produkt'), 'text' => t('Finn et produkt i listen og trykk pluss, eller dra det inn på benken. Søket og kategoriene snevrer inn listen.')],
            ['title' => t('Arbeidsbenken'), 'text' => t('Oppsettet i 3D, en rad per type. Dra for å snu, klikk et produkt for å velge det.')],
            ['title' => t('Sjekken'), 'text' => t('Totalpris, vekt når den er kjent, hva som mangler, og hvert par med status: dokumentert, regelutledet, ukjent eller passer ikke, alltid med grunnen.')],
            ['title' => t('Fjern et produkt'), 'text' => t('Velg et produkt på benken eller i panelet og trykk Fjern. Delete-tasten virker også.')],
            ['title' => t('Angre og historikk'), 'text' => t('Alt kan angres og gjøres om. Historikken viser hvert steg.')],
            ['title' => t('Testkjør'), 'text' => t('Går gjennom produktene, spesifikasjonene og koblingene steg for steg og lager en rapport. Det er en sjekk av dataene, ikke en garanti for at oppsettet er trygt.')],
            ['title' => t('Lagre, eksporter og spør Søren'), 'text' => t('Lagre oppsettet, last ned handlelisten, eller spør Søren om akkurat dette oppsettet.')],
            ['title' => t('Legg oppsettet i kurven'), 'text' => t('Hele oppsettet går i kurven i ett trykk, med antallene.')],
            ['title' => t('Videre til kassen'), 'text' => t('Oppsettet ligger i kurven. Bestill i kassen, så dukker bestillingen opp i Kontrolltårnet.')],
        ],
    ],
    'lanes' => array_map(fn($l) => t($l), $lanes),
    'status' => ['dokumentert' => t('Dokumentert'), 'regel' => t('Regelutledet'), 'ukjent' => t('Ukjent'), 'ikke' => t('Passer ikke')],
    'statusLong' => ['dokumentert' => t('Dokumentert kompatibel'), 'regel' => t('Regelutledet kompatibel'), 'ukjent' => t('Ukjent'), 'ikke' => t('Dokumentert ikke kompatibel')],
    'basis' => ['relasjon' => t('Fra relasjonene i katalogen'), 'regel' => t('Regel over lagrede spesifikasjoner'), 'ingen' => t('Ingen dokumentasjon')],
    'source' => array_map(fn($s) => t($s[0]), SOURCE_LABEL),
    'verdict' => ['klar' => t('Klar'), 'mangler' => t('Mangler noe'), 'konflikt' => t('Konflikt'), 'tom' => t('Tomt')],
    'verdictText' => array_map(fn($s) => t($s), Byggelab::VERDICT),
    'add' => t('Legg til'), 'added' => t('I oppsettet'), 'remove' => t('Fjern'), 'qty' => t('Antall'),
    'total' => t('Totalpris'), 'weight' => t('Vekt'), 'weightOf' => t('kjent for {k} av {n}'), 'weightNone' => t('Ikke oppgitt for noen'),
    'lines' => t('{l} linjer, {u} enheter'), 'compat' => t('Kompatibilitet'), 'compatNone' => t('Ingen par å vurdere ennå. Legg til minst to produkter.'),
    'missing' => t('Mangler og merknader'), 'missingNone' => t('Ingen mangler funnet i dataene.'), 'stats' => t('Beregninger'),
    'verification' => t('Datagrunnlag'), 'verOk' => t('Sør-Tre eller ansatt'), 'verDerived' => t('Utledet eller kjøper'), 'verMissing' => t('Mangler'),
    'selected' => t('Valgt produkt'), 'specsNone' => t('Ingen spesifikasjoner lagret.'),
    'run' => t('Testkjøring'), 'runNote' => t('Testkjøringen leser bare data fra katalogen. Den er ingen fysisk test, og den gjetter ikke ukjente verdier.'),
    'download' => t('Last ned rapport'),
    'empty' => t('Dra produkter hit, eller velg et eksempel over.'), 'search' => t('Søk i {n} produkter'), 'all' => t('Alle'),
    'noHits' => t('Ingen treff i denne kategorien.'), 'history' => t('Historikk'), 'historyNone' => t('Ingen endringer ennå.'),
    'hAdd' => t('La til {name}'), 'hRemove' => t('Fjernet {name}'), 'hQty' => t('{name}: antall {n}'), 'hPreset' => t('Eksempel: {name}'), 'hLoad' => t('Åpnet {name}'), 'hClear' => t('Tømte oppsettet'), 'hMove' => t('Flyttet {name}'),
    'menuRemove' => t('Fjern fra oppsettet'), 'menuMore' => t('Ett til'), 'menuLess' => t('Ett mindre'), 'menu' => t('Valg for {name}'),
    'dragHint' => t('Dra et produkt for å flytte det, eller ut av benken for å fjerne det. Høyreklikk for flere valg.'),
    'saved' => t('Lagrede oppsett'), 'savedNone' => t('Ingen lagrede oppsett i denne nettleseren.'), 'saveDone' => t('Lagret som «{name}».'), 'open' => t('Åpne'),
    'cartDone' => t('{n} varer lagt i handlekurven.'), 'cartFail' => t('Kunne ikke legge i handlekurven.'), 'checkFail' => t('Sjekken feilet. Prøv igjen.'),
    'ask' => t('Jeg setter sammen dette i Byggelab: {names}. Passer delene sammen, og mangler det noe?'),
    'csvHead' => t('Varenr;Navn;Antall;Pris;Sum'), 'listTitle' => t('Handleliste fra Byggelab'), 'unknown' => t('Ukjent'),
    'inStore' => t('{n} i butikk'), 'online' => t('Nettlager'), 'out' => t('Ikke inne'), 'illustration' => t('Illustrasjon av oppgitt lysstyrke, ikke en måling'),
    'pair' => t('Par'), 'evidence' => t('Grunnlag'), 'step' => t('Steg {i} av {n}'),
    'noGl' => t('3D er ikke tilgjengelig i denne nettleseren. Oppsettet vises som liste.'),
];
$cfg = ['theme' => $theme, 'builder' => t($builderName), 'csrf' => csrf(), 'inventory' => $inventory, 'presets' => $presets,
    'lanes' => array_keys($lanes), 'L' => $L, 'max' => Byggelab::MAX_LINES];
?>
<section class="lab" data-tema="<?= h($theme) ?>" aria-labelledby="lab-title">
  <header class="lab-head">
    <div class="lab-title">
      <h1 id="lab-title"><?= t('Byggelab') ?> <span><?= h(t($builderName)) ?></span></h1>
      <p><?= h(t($builderDesc)) ?></p>
    </div>
    <nav class="lab-builders" aria-label="<?= t('Byggere') ?>">
      <?php foreach (Byggelab::BUILDERS as $k => [$n]): ?>
        <a href="/byggelab/<?= $k ?>" data-tema="<?= $k ?>"<?= $k === $theme ? ' aria-current="page"' : '' ?> title="<?= h(t(Catalog::THEMES[$k][0])) ?>"><?= icon(Icons::THEMES[$k]) ?><span><?= h(t($n)) ?></span></a>
      <?php endforeach ?>
    </nav>
  </header>

  <div class="lab-grid">
    <aside class="lab-inv" aria-labelledby="lab-inv-h">
      <h2 id="lab-inv-h"><?= t('Produkter') ?></h2>
      <input type="search" id="lab-q" placeholder="<?= h(t('Søk i {n} produkter', ['n' => count($inventory)])) ?>" aria-label="<?= t('Søk i produktene') ?>" autocomplete="off">
      <div class="lab-chips" role="group" aria-label="<?= t('Kategorier fra Sør-Tre') ?>">
        <button type="button" data-cat="alle" aria-pressed="true"><?= t('Alle') ?></button>
        <?php foreach ($categories as $id => $name): ?><button type="button" data-cat="<?= (int) $id ?>" aria-pressed="false"><?= h(t($name)) ?></button><?php endforeach ?>
      </div>
      <ul class="lab-list" id="lab-list" aria-live="off"></ul>
    </aside>

    <div class="lab-stage">
      <div class="lab-stage-top">
        <?php if ($presets): ?>
          <div class="lab-presets" role="group" aria-label="<?= t('Eksempler') ?>"><span><?= t('Eksempler') ?></span>
            <?php foreach ($presets as $i => $p): ?><button type="button" data-preset="<?= $i ?>"><?= h(t($p['name'])) ?></button><?php endforeach ?>
          </div>
        <?php endif ?>
        <ul class="lab-legend" aria-label="<?= t('Forklaring') ?>">
          <?php foreach (['dokumentert', 'regel', 'ukjent', 'ikke'] as $s): ?><li class="st-<?= $s ?>"><i></i><?= h($L['status'][$s]) ?></li><?php endforeach ?>
        </ul>
      </div>
      <div class="lab-view" id="lab-view" aria-describedby="lab-view-desc">
        <p class="sr" id="lab-view-desc"><?= t('Tredimensjonal visning av oppsettet. Det samme innholdet står i panelet til høyre.') ?> <?= h($L['dragHint']) ?></p>
        <div class="lab-labels" id="lab-labels" aria-hidden="true"></div>
        <div class="lab-menu" id="lab-menu" role="menu" hidden></div>
        <p class="lab-empty" id="lab-empty" hidden><?= icon('box') ?><span><?= h($L['empty']) ?></span></p>
        <p class="lab-runbar" id="lab-runbar" hidden></p>
      </div>
      <div class="lab-tools" role="toolbar" aria-label="<?= t('Visning') ?>">
        <button type="button" data-view="iso"><?= icon('box') ?><span><?= t('Isometrisk') ?></span></button>
        <button type="button" data-view="rotate" aria-pressed="false"><?= icon('rotate-cw') ?><span><?= t('Roter') ?></span></button>
        <span class="lab-zoom"><button type="button" data-view="out" aria-label="<?= t('Zoom ut') ?>"><?= icon('minus') ?></button><span><?= t('Zoom') ?></span><button type="button" data-view="in" aria-label="<?= t('Zoom inn') ?>"><?= icon('plus') ?></button></span>
        <button type="button" data-view="fit"><?= icon('scan') ?><span><?= t('Tilpass') ?></span></button>
      </div>
    </div>

    <aside class="lab-side" id="lab-side" aria-label="<?= t('Sjekk av oppsettet') ?>" aria-busy="true"></aside>
  </div>

  <footer class="lab-bar">
    <div class="lab-bar-group">
      <button type="button" class="lab-btn" data-act="undo" disabled title="<?= t('Angre') ?> (Ctrl+Z)"><?= icon('undo-2') ?><span class="sr"><?= t('Angre') ?></span></button>
      <button type="button" class="lab-btn" data-act="redo" disabled title="<?= t('Gjør om') ?> (Ctrl+Shift+Z)"><?= icon('redo-2') ?><span class="sr"><?= t('Gjør om') ?></span></button>
      <details class="lab-pop" data-menu>
        <summary class="lab-btn"><?= t('Historikk') ?> <b id="lab-hcount">0</b></summary>
        <ol class="lab-pop-body" id="lab-history"></ol>
      </details>
    </div>
    <div class="lab-bar-group">
      <button type="button" class="lab-btn" data-act="save"><?= icon('save') ?><span><?= t('Lagre') ?></span></button>
      <details class="lab-pop" data-menu>
        <summary class="lab-btn"><?= t('Lagrede') ?></summary>
        <ul class="lab-pop-body" id="lab-saved"></ul>
      </details>
      <button type="button" class="lab-btn" data-act="export"><?= icon('download') ?><span><?= t('Eksporter handleliste') ?></span></button>
      <button type="button" class="lab-btn" id="lab-tour"><?= icon('circle-help') ?><span><?= t('Omvisning') ?></span></button>
      <a href="#" class="lab-btn" data-open-soren id="lab-ask"><span data-soren="leaf"></span><span><?= t('Spør Søren') ?></span></a>
    </div>
    <div class="lab-bar-group lab-bar-main">
      <button type="button" class="lab-btn lab-run" data-act="run"><?= icon('play') ?><span><?= t('Testkjør') ?></span></button>
      <button type="button" class="btn" data-act="cart"><?= icon('shopping-basket') ?><span><?= t('Legg oppsettet i kurven') ?></span></button>
    </div>
  </footer>
  <p class="lab-live sr" id="lab-live" aria-live="polite"></p>
</section>
<script type="importmap">{"imports":{"three":"/assets/vendor/three.module.min.js"}}</script>
<script type="application/json" id="lab-cfg"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<link rel="stylesheet" href="/assets/tour.css?v=<?= $v('tour.css') ?>">
<script type="module" src="/assets/byggelab.js?v=<?= $v('byggelab.js') ?>"></script>
