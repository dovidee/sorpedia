<?php
/** @var array $man @var array $steps @var array $products */
// Editorial manual (spec 020). Each block renders only what the manual row holds: no tools, intro or images are filled in.
$tools = array_values(array_filter(array_map('trim', explode(',', (string) $man['tools'])), 'strlen'));
$tools = array_map(fn(string $x) => mb_strtoupper(mb_substr($x, 0, 1)) . mb_substr($x, 1), $tools); // list items start with a capital, text otherwise as given
// The PDF follows the language toggle: {slug}-en.pdf in English when that file exists, otherwise the Norwegian one.
$pdf = $man['pdf_path'];
if ($pdf && lang() === 'en') {
    $en = preg_replace('/\.pdf$/', '-en.pdf', $pdf);
    if (is_file(APP_ROOT . '/public/' . $en)) {
        $pdf = $en;
    }
}
$cover = '';
foreach ($steps as $s) {
    if ($s['svg']) { $cover = $s['svg']; break; }
}
?>
<article class="man" data-tema="<?= h($man['theme']) ?>">
  <header class="man-cover">
    <div class="man-rail">
      <p class="man-rail-label"><?= icon('book-open') ?> <?= t('Veiledning') ?></p>
      <dl class="man-facts">
        <div><dt><?= t('Steg') ?></dt><dd><?= count($steps) ?></dd></div>
        <?php if ($tools): ?><div><dt><?= t('Utstyr') ?></dt><dd><?= count($tools) ?></dd></div><?php endif ?>
      </dl>
    </div>
    <div class="man-head">
      <h1><?= h($man['title']) ?></h1>
      <?php if ($man['is_demo']): ?><p class="man-source"><?= t('Demoveiledning, ikke fra produsenten. Følg alltid produsentens anvisning.') ?></p><?php endif ?>
      <?php if (lang() === 'en'): ?><p class="man-source"><?= t('Veiledningen finnes bare på norsk.') ?></p><?php endif ?>
      <?php if ($man['intro']): ?><p class="man-intro"><?= h($man['intro']) ?></p><?php endif ?>
      <p class="man-actions">
        <?php if ($steps): ?><a class="btn" href="#steg"><?= t('Til stegene') ?></a><?php endif ?>
        <?php if ($pdf): ?><a class="btn ghost" href="/<?= h($pdf) ?>" download><?= icon('download') ?> <?= t('Last ned PDF') ?></a><?php endif ?>
        <button class="btn ghost" type="button" onclick="print()"><?= t('Skriv ut') ?></button>
      </p>
    </div>
    <?php if ($cover): ?><div class="man-art" aria-hidden="true"><?= $cover ?></div><?php endif ?>
  </header>

  <?php if ($tools): ?>
  <section class="man-kit" aria-labelledby="man-kit-h">
    <h2 id="man-kit-h"><?= t('Du trenger') ?></h2>
    <ul>
      <?php foreach ($tools as $i => $tool): ?><li><span aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span><?= h($tool) ?></li><?php endforeach ?>
    </ul>
  </section>
  <?php endif ?>

  <?php if ($steps): ?>
  <section class="man-steps" id="steg" aria-labelledby="man-steps-h">
    <nav class="man-index" aria-labelledby="man-steps-h">
      <h2 id="man-steps-h"><?= t('{n} steg', ['n' => count($steps)]) ?></h2>
      <ol>
        <?php foreach ($steps as $s): ?>
          <li><a href="#steg-<?= (int) $s['step_no'] ?>"><span aria-hidden="true"><?= sprintf('%02d', (int) $s['step_no']) ?></span><?= h($s['title']) ?></a></li>
        <?php endforeach ?>
      </ol>
    </nav>
    <ol class="man-list">
      <?php foreach ($steps as $s): ?>
        <li id="steg-<?= (int) $s['step_no'] ?>"<?= $s['svg'] ? '' : ' class="no-media"' ?>>
          <div class="man-text">
            <span class="man-no"><?= sprintf('%02d', (int) $s['step_no']) ?></span>
            <h3><?= h($s['title']) ?></h3>
            <p><?= h($s['body']) ?></p>
          </div>
          <?php if ($s['svg']): ?><figure class="man-media" aria-hidden="true"><?= $s['svg'] ?></figure><?php endif ?>
        </li>
      <?php endforeach ?>
    </ol>
  </section>
  <?php endif ?>
</article>
<?php if ($products): ?>
  <h2><?= t('Produkter denne veiledningen gjelder') ?></h2>
  <div class="grid"><?php foreach ($products as $c) { $via = 'veiledning'; require __DIR__ . '/_card.php'; } ?></div>
<?php endif ?>
