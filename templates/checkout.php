<?php /** @var array $lines */ $total = array_sum(array_map(fn($l) => $l['qty'] * $l['price_ore'], $lines)); ?>
<h1><?= t('Kasse') ?></h1>
<p class="note"><?= t('Kjøpet gjør deg til verifisert kjøper hvis e-posten din er bekreftet.') ?></p>
<table class="cart">
  <?php foreach ($lines as $l): ?><tr><td><?= h($l['name']) ?></td><td><?= t('{n} stk', ['n' => (int) $l['qty']]) ?></td><td class="num"><?= kr($l['qty'] * (int) $l['price_ore']) ?></td></tr><?php endforeach ?>
  <tr class="sum"><td><?= t('Sum') ?></td><td></td><td class="num"><?= kr($total) ?></td></tr>
</table>
<form method="post" action="/kasse" class="pay">
  <?= csrf_field() ?>
  <p><?= t('Hentes i eller sendes fra') ?>: <b><?= h(warehouses()[warehouse_id()]['name']) ?></b></p>
  <fieldset><legend><?= t('Betaling') ?></legend>
    <label class="opt"><input type="radio" name="payment" value="vipps" checked><span>Vipps</span></label>
    <label class="opt"><input type="radio" name="payment" value="klarna"><span>Klarna</span></label>
    <label class="opt"><input type="radio" name="payment" value="kort"><span><?= t('Kort') ?></span></label>
  </fieldset>
  <button class="btn"><?= t('Bekreft kjøp') ?></button>
</form>
