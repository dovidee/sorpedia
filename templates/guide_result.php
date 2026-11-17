<?php /** @var array $res @var array $answers */ ?>
<div class="guide-head">
  <div><h1><?= t('Søren foreslår') ?></h1><p><?= h(theme_name($res['theme'])) ?>. <a href="/guide"><?= t('Ta guiden på nytt') ?></a></p>
    <p><a class="btn small ghost" href="#" data-open-soren data-ask="<?= h(t('Hvorfor passer disse forslagene for meg, og hva er forskjellen på dem?')) ?>"><span data-soren="leaf"></span> <?= t('Spør Søren om forslagene') ?></a></p></div>
</div>

<div class="picks">
  <?php foreach ($res['picks'] as $i => $c): ?>
    <article class="pick<?= $i === 0 ? ' best' : '' ?>">
      <?php if ($i === 0): ?><span class="badge"><?= t('Beste treff') ?></span><?php endif ?>
      <a href="/produkt/<?= h($c['slug']) ?>" class="card-img"><?php if ($c['img']): ?><img src="/<?= h($c['img']) ?>" alt=""><?php endif ?></a>
      <a href="/produkt/<?= h($c['slug']) ?>" class="card-name"><?= h($c['name']) ?></a>
      <p class="price"><?= kr((int) $c['price_ore']) ?></p>
      <ul class="reasons"><?php foreach ($c['why'] ?: [t('Populær i denne kategorien')] as $w): ?><li><?= h($w) ?></li><?php endforeach ?></ul>
      <form method="post" action="/handlekurv/legg-til"><?= csrf_field() ?><input type="hidden" name="product" value="<?= (int) $c['id'] ?>"><input type="hidden" name="via" value="guide"><input type="hidden" name="back" value="<?= h($_SERVER['REQUEST_URI']) ?>"><button class="btn small"><?= t('Legg i kurv') ?></button></form>
    </article>
  <?php endforeach ?>
</div>

<?php
$hidden = implode('', array_map(fn($k, $v) => '<input type="hidden" name="a[' . h($k) . ']" value="' . h($v) . '">', array_keys($answers), $answers));
?>
<section class="guide-ask" aria-labelledby="ga-h">
  <h2 id="ga-h"><?= t('Fant du det du lette etter?') ?></h2>
  <p class="muted small"><?= t('Svaret ditt gjør guiden bedre for neste kunde med lignende svar.') ?></p>
  <div class="ga-row">
    <?php foreach ($res['picks'] as $c): ?>
      <form method="post" action="/guide/svar"><?= csrf_field() . $hidden ?><input type="hidden" name="product" value="<?= (int) $c['id'] ?>">
        <button class="btn small ghost"><?= icon('check') ?><?= t('Ja: {name}', ['name' => h(mb_strimwidth($c['name'], 0, 38, '…'))]) ?></button></form>
    <?php endforeach ?>
  </div>
  <form method="post" action="/guide/svar" class="ga-no"><?= csrf_field() . $hidden ?>
    <label><?= t('Nei, jeg lette etter:') ?>
      <input name="wanted" required maxlength="120" autocomplete="off"></label>
    <button class="btn small"><?= t('Send') ?></button>
  </form>
</section>

<?php if ($res['bundle']): ?>
  <h2><?= t('Dette passer sammen med {name}', ['name' => h($res['picks'][0]['name'])]) ?></h2>
  <div class="grid"><?php foreach ($res['bundle'] as $c) { $via = 'guide'; require __DIR__ . '/_card.php'; } ?></div>
<?php endif ?>

<details class="trace" open>
  <summary><?= t('Slik ble forslagene valgt') ?></summary>
  <p class="muted small"><?= t('Rangeringen bruker ikke språkmodellen. Da er den rask, lik hver gang og kan ikke finne på produkter. Vil du ha det forklart med ord, trykk «Spør Søren om forslagene».') ?></p>
  <ol><?php foreach ($res['trace'] as $t): ?><li><?= h($t) ?></li><?php endforeach ?></ol>
</details>
