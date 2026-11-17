<?php /** @var array $items @var array $cards @var array $suggest @var array $warn */
$total = 0; ?>
<h1><?= t('Handlekurv') ?></h1>
<?php foreach ($warn as [$a, $b]): ?>
  <div class="warn-box"><b><?= t('Obs') ?>:</b> <?= t('{b} passer ikke til {a}.', ['b' => h($b['name']), 'a' => h($a['name'])]) ?> <small><?= h($b['rel']['reason']) ?></small></div>
<?php endforeach ?>
<?php if (!$cards): ?>
  <div class="empty"><div class="empty-mascot" data-soren="basket"></div><p><?= t('Handlekurven er tom.') ?> <a href="/guide"><?= t('Start produktguiden') ?></a>.</p></div>
<?php else: ?>
  <table class="cart">
    <?php foreach ($cards as $c): $qty = (int) $items[$c['id']]['qty']; $total += $qty * $c['price_ore']; ?>
      <tr>
        <td class="ci"><?php if ($c['img']): ?><img src="/<?= h($c['img']) ?>" alt=""><?php endif ?></td>
        <td><a href="/produkt/<?= h($c['slug']) ?>"><?= h($c['name']) ?></a><br><small class="muted"><?= t($c['in_store'] > 0 ? 'På lager i butikk' : ($c['online'] > 0 ? 'Sendes fra nettlager' : 'Bestillingsvare')) ?></small></td>
        <td><form method="post" action="/handlekurv/endre" class="qty"><?= csrf_field() ?><input type="hidden" name="product" value="<?= $c['id'] ?>">
          <input type="number" name="qty" value="<?= $qty ?>" min="0" max="99" onchange="this.form.submit()" aria-label="<?= t('Antall') ?>"></form></td>
        <td class="num"><?= kr($qty * (int) $c['price_ore']) ?></td>
      </tr>
    <?php endforeach ?>
    <tr class="sum"><td></td><td><?= t('Sum') ?></td><td></td><td class="num"><?= kr($total) ?></td></tr>
  </table>
  <p class="right"><a class="btn" href="/kasse"><?= t('Til kassen') ?></a></p>
<?php endif ?>

<?php if ($suggest): ?>
  <h2><?= t('Har du husket?') ?></h2>
  <p class="muted"><?= t('Ting som passer sammen med det du har i kurven. Hvert forslag sier hvorfor.') ?></p>
  <div class="grid"><?php foreach ($suggest as $c) { $c['rel']['reason'] = $c['rel']['reason'] . ' (' . t('til') . ' ' . $c['for'] . ')'; $via = 'kurv_forslag'; require __DIR__ . '/_card.php'; } ?></div>
<?php endif ?>
