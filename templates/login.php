<?php /** @var array $demo @var string $back */ ?>
<div class="narrow">
  <h1><?= t('Logg inn') ?></h1>
  <form method="post" action="/logg-inn" class="stack">
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>">
    <label><?= t('E-post') ?> <input name="email" type="email" required autofocus autocomplete="email"></label>
    <label><?= t('Passord') ?> <input name="password" type="password" required autocomplete="current-password"></label>
    <button class="btn"><?= t('Logg inn') ?></button>
  </form>
  <p class="small"><a href="/glemt-passord"><?= t('Glemt passordet?') ?></a></p>
  <p class="small"><?= t('Ingen konto?') ?> <a href="/registrer"><?= t('Registrer deg') ?></a></p>
  <h2><?= t('Testbrukere') ?></h2>
  <p class="muted"><?= t('Alle har passordet <code>admin</code>. Klikk for å fylle inn.') ?></p>
  <table class="mini demo-users">
    <?php foreach ($demo as $d): ?>
      <tr data-email="<?= h($d['email']) ?>"><td><?= person($d['name']) ?></td><td><?= h(t($d['role'])) ?></td><td><code><?= h($d['email']) ?></code></td></tr>
    <?php endforeach ?>
  </table>
  <p class="muted small"><?= t('Ingrid (kunde@) er verifisert kjøper og eier en H7R Core. Nora (ny@) har ikke bekreftet e-post. Erik (epost@) har bekreftet e-post, men har ikke handlet ennå.') ?></p>
</div>
