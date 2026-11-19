<?php /** @var array $o order with lines (Account::order) @var bool $fresh just bought @var bool $verified */
$until = Account::withdrawUntil($o);
$open = Account::canWithdraw($o);
$pay = t(ucfirst($o['payment']));
$imgs = [];
foreach (Catalog::cards(array_map(fn($l) => (int) $l['product_id'], $o['lines'])) as $c) {
    $imgs[(int) $c['id']] = $c['img'];
}
?>
<div class="narrow order-page">
  <p><a class="btn ghost small" href="/innstillinger#ordrer"><?= icon('arrow-left') ?><?= t('Alle ordrene') ?></a></p>
  <h1><?= $fresh ? t('Takk for handelen!') : t('Ordre {id}', ['id' => (int) $o['id']]) ?></h1>

  <dl class="order-facts">
    <div><dt><?= t('Ordrenummer') ?></dt><dd><?= (int) $o['id'] ?></dd></div>
    <div><dt><?= t('Dato') ?></dt><dd><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></dd></div>
    <div><dt><?= t('Betaling') ?></dt><dd><?= h($pay) ?></dd></div>
    <div><dt><?= t('Varehus') ?></dt><dd><?= h($o['warehouse'] ?? t('Ukjent')) ?></dd></div>
    <div><dt><?= t('Status') ?></dt><dd><span class="tag"><?= t($o['status'] === 'betalt' ? 'Betalt' : 'Kansellert') ?></span></dd></div>
  </dl>

  <table class="cart">
    <?php foreach ($o['lines'] as $l): ?>
      <tr><td class="order-item"><?php if ($imgs[(int) $l['product_id']] ?? null): ?><img src="/<?= h($imgs[(int) $l['product_id']]) ?>" alt="" width="48" height="48" loading="lazy"><?php endif ?>
        <a href="/produkt/<?= h($l['slug']) ?>"><?= h($l['name']) ?></a></td>
        <td><?= t('{n} stk', ['n' => (int) $l['qty']]) ?></td><td class="num"><?= kr($l['qty'] * (int) $l['price_ore']) ?></td></tr>
    <?php endforeach ?>
    <tr class="sum"><td><?= t('Sum') ?></td><td></td><td class="num"><?= kr((int) $o['total_ore']) ?></td></tr>
    <tr><td class="small muted"><?= t('Herav mva. (25 %)') ?></td><td></td><td class="num small muted"><?= kr(intdiv((int) $o['total_ore'], 5)) ?></td></tr>
  </table>
  <p class="small muted"><?= t('Prisene er det du betalte. Dagens pris kan være en annen.') ?></p>

  <section class="block">
    <h2><?= t('Angrerett og retur') ?></h2>
    <?php if ($o['status'] !== 'betalt'): ?>
      <p><?= t('Ordren er kansellert.') ?></p>
    <?php elseif ($open): ?>
      <p><b><?= t('Du kan angre kjøpet til og med {date}.', ['date' => $until->format('d.m.Y')]) ?></b></p>
    <?php else: ?>
      <p><b><?= t('Angrefristen gikk ut {date}.', ['date' => $until->format('d.m.Y')]) ?></b></p>
    <?php endif ?>
    <p class="small"><?= t('Etter angrerettloven har du 14 dager fra du mottok varene. I demoen regnes fristen fra ordredatoen. Varene må sendes tilbake i omtrent samme stand. Pengene betales tilbake med samme betalingsmåte ({pay}) senest 14 dager etter at vi fikk beskjed.', ['pay' => h($pay)]) ?></p>
    <p class="small"><?= t('Er noe feil med en vare, har du reklamasjonsrett i 2 år, eller 5 år for ting som skal vare mye lenger (forbrukerkjøpsloven).') ?></p>
    <p><a class="btn small" href="#" data-open-soren data-ask="<?= h(t('Kan du hjelpe meg med ordre {id}?', ['id' => (int) $o['id']])) ?>"><span data-soren="leaf"></span> <?= t('Spør Søren om ordren') ?></a></p>
    <p class="small muted"><?= t('Vil du angre eller reklamere, ber du Søren om å snakke med et menneske i kundeservice.') ?></p>
  </section>

  <?php if ($verified): ?>
    <p class="note"><?= t('Du er verifisert kjøper. Gå til et produkt du har kjøpt og velg <b>Åpen (Sørpedia)</b> under spesifikasjoner for å fylle inn det som mangler, eller skriv en anmeldelse.') ?></p>
  <?php endif ?>
</div>
