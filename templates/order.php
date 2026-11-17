<?php /** @var array $o @var array $lines @var bool $verified */ ?>
<h1><?= t('Takk for handelen!') ?></h1>
<p><?= t('Ordre {id}, betalt med {pay}.', ['id' => (int) $o['id'], 'pay' => h(t(ucfirst($o['payment'])))]) ?></p>
<table class="cart">
  <?php foreach ($lines as $l): ?><tr><td><a href="/produkt/<?= h($l['slug']) ?>"><?= h($l['name']) ?></a></td><td><?= t('{n} stk', ['n' => (int) $l['qty']]) ?></td><td class="num"><?= kr($l['qty'] * (int) $l['price_ore']) ?></td></tr><?php endforeach ?>
  <tr class="sum"><td><?= t('Sum') ?></td><td></td><td class="num"><?= kr((int) $o['total_ore']) ?></td></tr>
</table>
<?php if ($verified): ?>
  <p class="note"><?= t('Du er verifisert kjøper. Gå til et produkt du har kjøpt og velg <b>Åpen (Sørpedia)</b> under spesifikasjoner for å fylle inn det som mangler, eller skriv en anmeldelse.') ?></p>
<?php endif ?>
