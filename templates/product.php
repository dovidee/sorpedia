<?php
/** @var array $p @var array $images @var array $specs @var array $stock @var array $rel @var array $reviews
 *  @var bool $verified @var bool $bought @var array $revisions @var array $allKeys */
$whId = warehouse_id();
$mine = $stock[$whId] ?? null;
$avg = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : null;
$version = ($_GET['versjon'] ?? 'verifisert') === 'apen' ? 'apen' : 'verifisert';
$missing = array_diff_key(
    array_filter($allKeys, fn($k) => true),
    $specs
);
// Unknown rows only for what matters for this type of product (a gas stove has no magnification). Types no rule knows
// fall back to the keys of the theme.
$relevant = Usage::keys($p['name']);
if (Usage::rule($p['name']) === null) {
    $themeKeys = db()->prepare("SELECT `key` FROM spec_keys WHERE themes IS NULL OR FIND_IN_SET(?, themes)");
    $themeKeys->execute([$p['theme']]);
    $relevant = $themeKeys->fetchAll(PDO::FETCH_COLUMN);
}
$missing = array_intersect_key($missing, array_flip($relevant));
?>
<div class="pdp" data-tema="<?= h($p['theme']) ?>">
  <section class="gallery" data-gallery>
    <div class="stage">
      <?php if ($images): ?>
        <img id="main-img" src="/<?= h($images[0]['path_l']) ?>" alt="<?= h($p['name']) ?>" data-zoom>
        <button type="button" class="zoom-btn" id="zoom-btn" aria-pressed="false"><?= t('Zoom inn') ?></button>
        <span class="img-kind" id="img-kind"><?= $images[0]['kind'] === 'sortre' ? t('Bilde fra Sør-Tre') : h($images[0]['caption']) ?></span>
      <?php else: ?><div class="noimg big"><?= t('Ingen bilder fra Sør-Tre') ?></div><?php endif ?>
    </div>
    <?php if (count($images) > 1): ?>
      <div class="thumbs">
        <?php foreach ($images as $i => $im): ?>
          <button data-src="/<?= h($im['path_l']) ?>" data-kind="<?= h($im['kind'] === 'sortre' ? t('Bilde fra Sør-Tre') : ($im['caption'] ?? '')) ?>" class="<?= $i ? '' : 'on' ?>"><img src="/<?= h($im['path_m']) ?>" alt="<?= t('Bilde {n}', ['n' => $i + 1]) ?>"></button>
        <?php endforeach ?>
      </div>
    <?php endif ?>
    <p class="muted small"><?= t('Klikk på bildet for å zoome inn, og klikk igjen for å zoome ut.') ?> <?= t(count($images) === 1 ? '{n} bilde' : '{n} bilder', ['n' => count($images)]) ?>. <?= t('Mangler: ferdig montert og i bruk.') ?></p>
  </section>

  <section class="buy">
    <h1><?= h($p['name']) ?></h1>
    <?php if ($p['brand']): ?><p class="muted"><?= t('Merke') ?>: <?= h($p['brand']) ?></p><?php endif ?>
    <p class="rating"><?php if ($avg): ?><?= stars($avg) ?> <a href="#anmeldelser"><?= t(count($reviews) === 1 ? '{n} anmeldelse fra kjøpere' : '{n} anmeldelser fra kjøpere', ['n' => count($reviews)]) ?></a><?php else: ?><span class="muted"><?= t('Ingen anmeldelser ennå') ?></span><?php endif ?></p>
    <p class="price big"><?= kr((int) $p['price_ore']) ?><?php if ($p['regular_ore']): ?> <s><?= kr((int) $p['regular_ore']) ?></s><?php endif ?></p>

    <div class="stockbox">
      <b><?= h(warehouses()[$whId]['name']) ?></b>
      <ul class="stock3">
        <li class="<?= ($mine['in_store'] ?? 0) > 0 ? 'ok' : 'no' ?>"><?= t('I butikk') ?>: <?= ($mine['in_store'] ?? 0) > 0 ? t('{n} stk', ['n' => (int) $mine['in_store']]) : t('ikke inne') ?></li>
        <li class="<?= ($mine['online'] ?? 0) > 0 ? 'ok' : 'no' ?>"><?= t('Nettlager') ?>: <?= ($mine['online'] ?? 0) > 0 ? t('{n} stk, sendes i dag', ['n' => (int) $mine['online']]) : t('tomt') ?></li>
        <?php if ($mine['preorder_eta'] ?? null): ?><li class="mid"><?= t('Bestillingsvare: ventes ca. {d}', ['d' => date('d.m.Y', strtotime($mine['preorder_eta']))]) ?></li><?php endif ?>
      </ul>
      <details><summary><?= t('Se alle varehus') ?></summary>
        <table class="mini"><tr><th><?= t('Varehus') ?></th><th><?= t('Butikk') ?></th><th><?= t('Nett') ?></th></tr>
        <?php foreach ($stock as $wid => $s): ?>
          <tr class="<?= $wid === $whId ? 'here' : '' ?>"><td><?= h($s['name']) ?></td><td><?= (int) $s['in_store'] ?></td><td><?= (int) $s['online'] ?></td></tr>
        <?php endforeach ?></table>
      </details>
    </div>

    <form method="post" action="/handlekurv/legg-til" class="add-row">
      <?= csrf_field() ?><input type="hidden" name="product" value="<?= (int) $p['id'] ?>"><input type="hidden" name="via" value="produkt">
      <input type="hidden" name="back" value="/produkt/<?= h($p['slug']) ?>">
      <input type="number" name="qty" value="1" min="1" max="99" aria-label="<?= t('Antall') ?>">
      <button class="btn"><?= t('Legg i handlekurv') ?></button>
    </form>
    <label class="cmp"><input type="checkbox" data-compare="<?= (int) $p['id'] ?>" data-name="<?= h($p['name']) ?>"> <?= t('Legg til sammenligning') ?></label>

    <?php if ($rel['ikke']): ?>
      <div class="warn-box"><b><?= t('Passer ikke') ?></b>
        <?php foreach ($rel['ikke'] as $c): ?><p><a href="/produkt/<?= h($c['slug']) ?>"><?= h($c['name']) ?></a><br><small><?= h($c['rel']['reason']) ?></small></p><?php endforeach ?>
      </div>
    <?php endif ?>
    <p class="ask-soren"><a href="#" data-open-soren data-ask="<?= h(t('Fortell meg kort om {name}. Hva passer den til, og hva bør jeg tenke på?', ['name' => $p['name']])) ?>"><span data-soren="leaf"></span> <?= t('Spør Søren om dette produktet') ?></a></p>
  </section>
</div>

<?php $usage = Usage::needs((int) $p['id'], $p['name']); if ($usage): ?>
<section class="block usage">
  <h2><?= t('Bruk og tilbehør') ?> <?= source_badge('utledet', t('Regel ut fra produkttypen. Sjekk produktteksten før du kjøper.')) ?></h2>
  <p><?= h($usage['use']) ?></p>
  <?php if ($usage['has'] || $usage['not']): ?>
    <h3><?= t('Du trenger også') ?></h3>
    <?php foreach ($usage['has'] as $need): ?>
      <p class="need"><?= h(ucfirst($need['text'])) ?></p>
      <div class="grid"><?php foreach ($need['cards'] as $c) { require __DIR__ . '/_card.php'; } ?></div>
    <?php endforeach ?>
    <?php if ($usage['not']): ?>
      <p class="need muted"><?= t('Selges ikke her') ?>: <?= h(implode(', ', $usage['not'])) ?></p>
    <?php endif ?>
  <?php endif ?>
</section>
<?php endif ?>

<?php if ($rel['passer']): ?>
<section class="block">
  <h2><?= t('Passer sammen med') ?></h2>
  <p class="muted"><?= t('Merket <span class="lvl lvl-dokumentert">Dokumentert</span> når Sør-Tre skriver det selv, og <span class="lvl lvl-regelutledet">Regel</span> når vi har en tydelig regel. Hold over merket for begrunnelsen.') ?></p>
  <div class="grid"><?php foreach ($rel['passer'] as $c) { $via = 'passer_sammen'; require __DIR__ . '/_card.php'; } ?></div>
</section>
<?php endif ?>

<?php if ($manuals): ?>
<section class="block">
  <h2><?= icon('book-open') ?> <?= t('Monteringsveiledning') ?></h2>
  <?php foreach ($manuals as $mn): ?>
    <p><a href="/veiledning/<?= h($mn['slug']) ?>"><b><?= h($mn['title']) ?></b></a><br><small class="muted"><?= h($mn['intro']) ?></small>
    <?php if ($mn['pdf_path']): ?><br><a href="/<?= h($mn['pdf_path']) ?>" download class="small"><?= t('Last ned PDF') ?></a><?php endif ?></p>
  <?php endforeach ?>
</section>
<?php endif ?>

<section class="block" id="spesifikasjoner">
  <div class="spec-head">
    <h2><?= t('Spesifikasjoner') ?></h2>
    <div class="tabs" role="tablist">
      <a role="tab" href="?versjon=verifisert#spesifikasjoner" class="<?= $version === 'verifisert' ? 'on' : '' ?>"><?= t('Verifisert') ?></a>
      <a role="tab" href="?versjon=apen#spesifikasjoner" class="<?= $version === 'apen' ? 'on' : '' ?>"><?= t('Åpen (Sørpedia)') ?></a>
    </div>
  </div>
  <?php if ($version === 'verifisert'): ?>
    <p class="muted"><?= t('Bare det Sør-Tre selv oppgir, eller som en ansatt har godkjent.') ?></p>
    <table class="specs">
      <?php $any = false; foreach ($specs as $k => $s): if (!$s['verifisert']) continue; $any = true; $v = $s['verifisert']; ?>
        <tr><th><?= hc(I18n::spec($k, $s['label'])) ?></th><td><?= hc(I18n::value($v['value_text'])) ?> <?= hc($s['unit']) ?></td><td><?= source_badge($v['source'], $v['evidence']) ?></td></tr>
      <?php endforeach ?>
      <?php if (!$any): ?><tr><td colspan="3" class="muted"><?= t('Sør-Tre oppgir ingen tekniske data for dette produktet. Se den åpne versjonen.') ?></td></tr><?php endif ?>
    </table>
  <?php else: ?>
    <p class="muted"><?= t('Alt vi vet, inkludert det som er tolket fra teksten og lagt inn av kjøpere. Hold over merket for å se kilden. Ser du en feil, trykk på {icon} eller høyreklikk på raden.', ['icon' => icon('triangle-alert', 'inline-warn')]) ?></p>
    <form method="post" action="/produkt/<?= h($p['slug']) ?>/rediger">
      <?= csrf_field() ?>
      <table class="specs">
        <?php foreach ($specs as $k => $s): $v = $s['apen'] ?? $s['verifisert']; ?>
          <tr><th><?= h(I18n::spec($k, $s['label'])) ?></th>
            <td><?php if ($verified): ?><input name="spec[<?= h($k) ?>]" value="<?= h($v['value_text']) ?>"> <?= h($s['unit']) ?>
                <?php else: ?><?= hc(I18n::value($v['value_text'])) ?> <?= hc($s['unit']) ?><?php endif ?></td>
            <td class="who-cell" data-report-cell="<?= h($k) ?>"><?= source_badge($v['source'], $v['evidence']) ?><?php if ($v['author']): ?> <small class="by" title="<?= h($v['author']) ?>. <?= t('Høyreklikk for å melde fra om feil.') ?>"><?= person(preg_replace('/^Kjøper (?=#)/u', '', $v['author'])) ?></small><?php endif ?>
              <button type="button" class="report-btn" data-report="<?= h($k) ?>" data-label="<?= h(I18n::spec($k, $s['label'])) ?>" data-value="<?= h(trim($v['value_text'] . ' ' . $s['unit'])) ?>" title="<?= t('Meld fra om feil') ?>" aria-label="<?= h(t('Meld fra om feil i {label}', ['label' => I18n::spec($k, $s['label'])])) ?>"><?= icon('triangle-alert') ?></button></td></tr>
        <?php endforeach ?>
        <?php foreach ($missing as $k => $meta): ?>
          <tr class="unknown"><th><?= h(I18n::spec($k, $meta['label'])) ?></th>
            <td><?php if ($verified): ?><input name="spec[<?= h($k) ?>]" placeholder="<?= t('Ukjent') ?>"> <?= h($meta['unit']) ?><?php else: ?><span class="muted"><?= t('Ukjent') ?></span><?php endif ?></td>
            <td><span class="src src-ukjent" title="<?= t('Ingen kilde oppgir dette ennå') ?>"><?= t('Ukjent') ?></span></td></tr>
        <?php endforeach ?>
        <?php if ($verified): ?>
          <tr class="new-key"><th><input name="new_label" placeholder="<?= t('Ny egenskap, f.eks. «Ladetid»') ?>"></th><td><input name="new_value" placeholder="<?= t('Verdi') ?>"> <input name="new_unit" placeholder="<?= t('Enhet') ?>" class="unit"></td><td><small class="muted"><?= t('Blir en ny rad, ingen migrering') ?></small></td></tr>
        <?php endif ?>
      </table>
      <?php if ($verified): ?>
        <label class="comment"><?= t('Kilde eller kommentar') ?> <input name="comment" placeholder="<?= t('Hvor har du dette fra? F.eks. «målte selv» eller lenke til datablad') ?>"></label>
        <button class="btn"><?= t('Lagre i åpen versjon') ?></button>
      <?php else: ?>
        <p class="note"><?= t('Har du kjøpt dette produktet? Som <b>verifisert kjøper</b> (bekreftet e-post og minst ett kjøp) kan du fylle inn det som mangler.') ?> <?= current_user() ? '' : '<a href="/logg-inn?back=' . urlencode('/produkt/' . $p['slug'] . '?versjon=apen#spesifikasjoner') . '">' . t('Logg inn') . '</a>.' ?></p>
      <?php endif ?>
    </form>
    <?php if ($revisions): ?>
      <details class="history"><summary><?= t('Endringshistorikk') ?> (<?= count($revisions) ?>)</summary>
        <ul><?php foreach ($revisions as $r): ?>
          <li><b><?= person($r['name'] ?? Privacy::DELETED) ?></b> <?= date('d.m.Y', strtotime($r['created_at'])) ?>, <?= t('status') ?> <?= h(t($r['status'])) ?>:
            <?php foreach (json_decode($r['diff'], true) as $d): ?><?= h(I18n::spec($d['key'], $allKeys[$d['key']]['label'] ?? $d['key'])) ?>: <?= h($d['from'] ?? t('ukjent')) ?> → <?= h($d['to']) ?>. <?php endforeach ?>
            <?php if ($r['comment']): ?><br><small><?= hc($r['comment']) ?></small><?php endif ?></li>
        <?php endforeach ?></ul>
      </details>
    <?php endif ?>
    <dialog class="report" id="report-dialog" aria-labelledby="report-title">
      <form method="post" action="/produkt/<?= h($p['slug']) ?>/meld-feil">
        <?= csrf_field() ?><input type="hidden" name="key">
        <h2 id="report-title"><?= icon('triangle-alert') ?><?= t('Meld fra om feil') ?></h2>
        <div class="val"><b data-r-label></b>: <span data-r-value></span></div>
        <div class="quick" aria-label="<?= t('Vanlige grunner') ?>">
          <button type="button"><?= t('Feil tall eller enhet') ?></button><button type="button"><?= t('Gjelder et annet produkt') ?></button><button type="button"><?= t('Står annerledes på pakken') ?></button>
        </div>
        <label class="sr" for="report-reason"><?= t('Hva er feil?') ?></label>
        <textarea id="report-reason" name="reason" maxlength="300" required placeholder="<?= t('Hva er feil? For eksempel «pakken sier 220 lumen»') ?>"></textarea>
        <p class="small muted"><?= t('Du trenger ikke logge inn. Meldingen går til en ansatt, og ingen andre ser hvem som sendte den.') ?></p>
        <p class="small" data-r-msg role="status"></p>
        <div class="row"><button type="button" class="btn ghost" data-r-cancel><?= t('Avbryt') ?></button><button class="btn"><?= icon('send') ?><?= t('Send') ?></button></div>
      </form>
    </dialog>
  <?php endif ?>
</section>

<section class="block">
  <h2><?= t('Beskrivelse fra Sør-Tre') ?></h2>
  <?php if (lang() === 'en'): ?><p class="muted small"><?= t('Produktbeskrivelsen kommer fra Sør-Tre og finnes bare på norsk.') ?></p><?php endif ?>
  <div class="desc"><?= strip_tags((string) $p['long_html'] ?: (string) $p['short_html'], '<p><br><ul><ol><li><b><strong><i><em><h2><h3><h4><table><tr><td><th>') ?></div>
  <p class="muted small"><a href="<?= h($p['source_url']) ?>" rel="noopener" target="_blank"><?= t('Se produktet på sor-tre.no') ?></a></p>
</section>

<section class="block" id="anmeldelser">
  <h2><?= t('Anmeldelser') ?></h2>
  <p class="muted"><?= t('Bare kunder som har kjøpt produktet kan anmelde det.') ?></p>
  <?php foreach ($reviews as $r): ?>
    <article class="review"><p><?= stars((float) $r['rating']) ?> <b><?= hc($r['title']) ?></b></p><p><?= hc($r['body']) ?></p>
      <small class="muted"><?= person($r['name']) ?>, <?= t('verifisert kjøp') ?>, <?= date('d.m.Y', strtotime($r['created_at'])) ?></small></article>
  <?php endforeach ?>
  <?php if ($verified && $bought): ?>
    <form method="post" action="/produkt/<?= h($p['slug']) ?>/anmeldelse" class="review-form">
      <?= csrf_field() ?>
      <label><?= t('Karakter') ?> <select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option><?= $i ?></option><?php endfor ?></select></label>
      <input name="title" placeholder="<?= t('Tittel') ?>" required maxlength="120">
      <textarea name="body" placeholder="<?= t('Hva bør andre vite?') ?>" required maxlength="2000"></textarea>
      <button class="btn"><?= t('Publiser anmeldelse') ?></button>
    </form>
  <?php endif ?>
</section>

<?php if ($rel['kjopt']): ?>
<section class="block">
  <h2><?= t('Andre kjøpte også') ?></h2>
  <p class="muted"><?= t('Basert på hva som er kjøpt sammen. Det betyr ikke at produktene passer sammen.') ?></p>
  <div class="grid"><?php foreach (array_slice($rel['kjopt'], 0, 4) as $c) { unset($c['rel']); $via = 'kjopt_sammen'; require __DIR__ . '/_card.php'; } ?></div>
</section>
<?php endif ?>
