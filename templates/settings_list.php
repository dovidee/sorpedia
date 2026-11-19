<?php /** @var string $kind 'ordrer' or 'meldinger' @var array $rows */
$current = $kind;
$back = '/innstillinger/meldinger';
?>
<div class="settings">
  <h1><?= t('Innstillinger') ?></h1>
  <?php require __DIR__ . '/_settings_nav.php' ?>
  <p><a class="btn ghost small" href="/innstillinger#<?= $kind ?>"><?= icon('arrow-left') ?><?= t('Tilbake til Innstillinger') ?></a></p>
  <section class="block">
    <h2><?= t($kind === 'ordrer' ? 'Alle ordrene' : 'Alle feilmeldingene') ?> <small class="muted"><?= count($rows) ?></small></h2>
    <?php if (!$rows): ?><p class="muted"><?= t($kind === 'ordrer' ? 'Ingen ordrer ennå.' : 'Du har ikke meldt fra om noen feil.') ?></p><?php endif ?>
    <ul class="acct-list">
      <?php foreach ($rows as $row) {
          if ($kind === 'ordrer') { $o = $row; require __DIR__ . '/_order_row.php'; } else { $r = $row; require __DIR__ . '/_report_row.php'; }
      } ?>
    </ul>
  </section>
</div>
