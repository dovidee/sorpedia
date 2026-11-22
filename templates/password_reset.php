<?php /** @var string $token */ ?>
<div class="narrow">
  <h1><?= t('Velg nytt passord') ?></h1>
  <form method="post" action="/nytt-passord/<?= h($token) ?>" class="stack">
    <?= csrf_field() ?>
    <label><?= t('Nytt passord') ?> <input name="password" type="password" required minlength="8" autofocus autocomplete="new-password"></label>
    <label><?= t('Gjenta passordet') ?> <input name="repeat" type="password" required minlength="8" autocomplete="new-password"></label>
    <button class="btn"><?= t('Lagre passordet') ?></button>
  </form>
</div>
