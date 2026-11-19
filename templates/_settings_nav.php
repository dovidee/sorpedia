<?php /** @var string $current section shown as active, or '' */ ?>
<nav class="settings-nav" aria-label="<?= t('Innstillinger') ?>">
  <?php foreach (['profil' => ['user-round', 'Profil'], 'ordrer' => ['receipt', 'Ordrer'], 'meldinger' => ['triangle-alert', 'Feilmeldinger'],
                  'utseende' => ['sun-moon', 'Utseende'], 'epost' => ['mail', 'E-post'], 'passord' => ['key-round', 'Passord'], 'data' => ['shield-check', 'Personvern']] as $id => [$ic, $label]): ?>
    <a href="/innstillinger#<?= $id ?>"<?= ($current ?? '') === $id ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><?= t($label) ?></a>
  <?php endforeach ?>
</nav>
