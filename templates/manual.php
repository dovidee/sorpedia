<?php /** @var array $man @var array $steps @var array $products */ ?>
<p class="crumbs"><a href="/"><?= t('Forsiden') ?></a> / <a href="/tema/<?= h($man['theme']) ?>"><?= h(theme_name($man['theme'])) ?></a> / <?= t('Veiledning') ?></p>
<div class="manual">
  <h1><?= h($man['title']) ?></h1>
  <p class="demo-tag"><?= t('Demoveiledning, ikke fra produsenten. Følg alltid produsentens anvisning.') ?><?= lang() === 'en' ? ' ' . t('Veiledningen finnes bare på norsk.') : '' ?></p>
  <p><?= h($man['intro']) ?></p>
  <p><b><?= t('Du trenger') ?>:</b> <?= h($man['tools']) ?></p>
  <p><?php if ($man['pdf_path']): ?><a class="btn small" href="/<?= h($man['pdf_path']) ?>" download><?= t('Last ned PDF') ?></a><?php endif ?>
     <button class="btn small ghost" onclick="print()"><?= t('Skriv ut') ?></button></p>
  <ol class="steps">
    <?php foreach ($steps as $s): ?>
      <li><div class="step-svg"><?= $s['svg'] ?></div><div><h2><?= (int) $s['step_no'] ?>. <?= h($s['title']) ?></h2><p><?= h($s['body']) ?></p></div></li>
    <?php endforeach ?>
  </ol>
</div>
<?php if ($products): ?>
  <h2><?= t('Produkter denne veiledningen gjelder') ?></h2>
  <div class="grid"><?php foreach ($products as $c) { $via = 'veiledning'; require __DIR__ . '/_card.php'; } ?></div>
<?php endif ?>
