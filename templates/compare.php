<?php /** @var array $cards @var array $specs @var array $keys */ ?>
<h1><?= t('Sammenlign') ?></h1>
<?php if (count($cards) < 2): ?>
  <p><?= t('Velg minst to produkter med «Sammenlign» på produktkortene.') ?></p>
<?php else: ?>
<div class="compare-wrap">
<table class="specs compare">
  <tr><th></th><?php foreach ($cards as $c): ?><td><a href="/produkt/<?= h($c['slug']) ?>"><?php if ($c['img']): ?><img src="/<?= h($c['img']) ?>" alt="" class="cmp-img"><?php endif ?><br><b><?= h($c['name']) ?></b></a></td><?php endforeach ?></tr>
  <tr><th><?= t('Pris') ?></th><?php foreach ($cards as $c): ?><td><b><?= kr((int) $c['price_ore']) ?></b></td><?php endforeach ?></tr>
  <tr><th><?= t('Kjøpere') ?></th><?php foreach ($cards as $c): ?><td><?= $c['rating'] !== null ? stars((float) $c['rating']) . " ({$c['n_reviews']})" : '<span class="muted">' . t('Ingen') . '</span>' ?></td><?php endforeach ?></tr>
  <tr><th><?= t('Lager her') ?></th><?php foreach ($cards as $c): ?><td><?= $c['in_store'] > 0 ? t('{n} i butikk', ['n' => $c['in_store']]) : t($c['online'] > 0 ? 'Nettlager' : 'Ikke inne') ?></td><?php endforeach ?></tr>
  <?php foreach ($keys as $k => $label): ?>
    <tr><th><?= hc(I18n::spec($k, $label)) ?></th>
      <?php foreach ($cards as $c): $s = $specs[$c['id']][$k] ?? null; $v = $s ? Catalog::effective($s) : null; ?>
        <td><?= $v ? hc(I18n::value($v['value_text'])) . ' ' . source_badge($v['source'], $v['evidence']) : '<span class="muted">' . t('Ukjent') . '</span>' ?></td>
      <?php endforeach ?></tr>
  <?php endforeach ?>
</table>
</div>
<p class="muted small"><?= t('«Ukjent» betyr at verken Sør-Tre eller kjøpere har oppgitt verdien. Vi gjetter ikke.') ?></p>
<?php endif ?>
