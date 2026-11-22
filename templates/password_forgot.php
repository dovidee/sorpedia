<div class="narrow">
  <h1><?= t('Glemt passord') ?></h1>
  <p class="muted"><?= t('Skriv e-posten du bruker hos oss. Vi sender en lenke som virker i {n} minutter.', ['n' => Auth::RESET_MINUTES]) ?></p>
  <form method="post" action="/glemt-passord" class="stack">
    <?= csrf_field() ?>
    <label><?= t('E-post') ?> <input name="email" type="email" required autofocus autocomplete="email"></label>
    <button class="btn"><?= t('Send lenke') ?></button>
  </form>
  <p class="small"><a href="/logg-inn"><?= t('Tilbake til innlogging') ?></a></p>
</div>
