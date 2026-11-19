<?php /** @var string $q @var array $r @var array $products @var array $fallback */ ?>
<h1><?= t('Søk') ?>: «<?= h($q) ?>» <small class="muted"><?= t('{n} treff', ['n' => count($products)]) ?></small></h1>
<?php if ($r['corrected']): ?><p class="note"><?= t('Viser treff for <b>{fixed}</b>. Søkte du på «{q}»?', ['fixed' => h($r['corrected']), 'q' => h($q)]) ?></p><?php endif ?>
<?php if (!empty($r['translated'])): ?><p class="note"><?= t('Søkte på norsk: <b>{no}</b>. Produktene har norske navn fra Sør-Tre.', ['no' => h($r['translated'])]) ?></p><?php endif ?>
<?php $uses = array_filter($r['matched'], fn($s) => $s['kind'] === 'bruksomrade'); if ($uses): ?>
  <p class="note"><?= t('Søren tolket søket som en oppgave') ?>: <b><?= h(implode(', ', array_column($uses, 'term'))) ?></b>.
  <?php foreach ($r['themes'] as $th): ?> <a href="/tema/<?= h($th) ?>"><?= t('Se alt i {theme}', ['theme' => h(theme_name($th))]) ?></a><?php endforeach ?></p>
<?php endif ?>
<?php if (!$products): ?>
  <div class="empty">
    <div class="empty-head av" data-soren="head" data-still></div>
    <p><b><?= t('Ingen produkter matcher «{q}».', ['q' => h($q)]) ?></b> <?= t('Sør-Tre selger ikke alt på nett.') ?></p>
    <p><?= t('<a href="/guide">Prøv produktguiden</a> eller <a href="#" data-open-soren>spør Søren</a>. Søkeord uten treff lagres slik at butikken ser hva kundene leter etter.') ?></p>
  </div>
  <?php if ($fallback): ?><h2><?= t('Kanskje dette?') ?></h2><div class="grid"><?php foreach ($fallback as $c) { $via = 'sok'; require __DIR__ . '/_card.php'; } ?></div><?php endif ?>
<?php endif ?>
<div class="grid"><?php foreach ($products as $c) { $via = 'sok'; require __DIR__ . '/_card.php'; } ?></div>
