<?php /** @var array $u @var bool $verified @var array $orders @var array $reports */
$alias = Privacy::alias($u, 'anonym');
$first = Privacy::alias($u, 'fornavn');
$theme = $u['theme'] ?? 'system';
$current = '';
$back = '/innstillinger#meldinger';
?>
<div class="settings">
  <h1><?= t('Innstillinger') ?></h1>
  <?php require __DIR__ . '/_settings_nav.php' ?>

  <section class="block" id="profil">
    <h2><?= t('Profil') ?></h2>
    <p class="small muted"><?= t('Rolle') ?>: <?= h(t($u['role'])) ?><?= $verified ? ', ' . t('verifisert kjøper') : ($u['email_verified_at'] ? ', ' . t('blir verifisert kjøper etter første kjøp') : ', ' . t('bekreft e-posten for å bli verifisert kjøper')) ?>.</p>
    <div class="avatar-row">
      <?php if ($u['avatar']): ?><img class="avatar big" src="/profilbilde?v=<?= h(substr($u['avatar'], 0, 8)) ?>" alt="<?= t('Profilbildet ditt') ?>">
      <?php else: ?><span class="avatar big empty"><?= icon('user-round') ?></span><?php endif ?>
      <div>
        <form method="post" action="/innstillinger/bilde" enctype="multipart/form-data" class="inline-form">
          <?= csrf_field() ?>
          <label class="btn ghost small file-btn"><?= icon('image-up') ?><?= t('Velg bilde') ?><input type="file" name="avatar" accept="image/jpeg,image/png" required onchange="this.form.submit()"></label>
          <?php if ($u['avatar']): ?><button class="btn ghost small" name="action" value="fjern" formnovalidate><?= icon('trash-2') ?><?= t('Fjern') ?></button><?php endif ?>
        </form>
        <p class="small muted"><?= t('JPEG eller PNG, maks 3 MB. Bare du ser bildet: i menyen og i chatten med Søren. Posisjon og kameradata fjernes automatisk.') ?></p>
      </div>
    </div>
    <form method="post" action="/innstillinger/profil" class="stack">
      <?= csrf_field() ?>
      <label><?= t('Fullt navn') ?> <input name="name" value="<?= h($u['name']) ?>" required maxlength="120" autocomplete="name"><small class="muted"><?= t('Privat. Brukes bare i e-post til deg.') ?></small></label>
      <label><?= t('Telefon') ?> <input name="phone" value="<?= h($u['phone']) ?>" inputmode="tel" autocomplete="tel" placeholder="<?= t('Valgfritt') ?>"><small class="muted"><?= t('Privat. Bare kundeservice kan bruke det hvis du ber dem ringe deg.') ?></small></label>
      <fieldset class="choice">
        <legend><?= t('Slik vises du for andre') ?></legend>
        <p class="small muted"><?= t('Gjelder anmeldelser og bidrag i Sørpedia, også de du allerede har skrevet.') ?></p>
        <label><input type="radio" name="display_name" value="anonym" <?= $u['display_name'] !== 'fornavn' ? 'checked' : '' ?>>
          <span><b><?= h($alias) ?></b> <span class="tag"><?= t('Anbefalt') ?></span><small><?= t('Anonymt. Koden er laget av et tilfeldig tall og kan ikke spores tilbake til deg.') ?></small></span></label>
        <label><input type="radio" name="display_name" value="fornavn" <?= $u['display_name'] === 'fornavn' ? 'checked' : '' ?>>
          <span><b><?= person($first) ?></b><small><?= t('Bare fornavnet. Etternavn, e-post og telefon vises aldri.') ?></small></span></label>
      </fieldset>
      <button class="btn"><?= icon('check') ?><?= t('Lagre profil') ?></button>
    </form>
  </section>

  <section class="block" id="ordrer">
    <h2><?= t('Ordrer') ?></h2>
    <?php if ($orders): ?>
      <ul class="acct-list"><?php foreach (array_slice($orders, 0, 3) as $o) { require __DIR__ . '/_order_row.php'; } ?></ul>
      <?php if (count($orders) > 3): ?><p><a class="btn ghost small" href="/innstillinger/ordrer"><?= t('Se alle ordrene ({n})', ['n' => count($orders)]) ?><?= icon('arrow-right') ?></a></p><?php endif ?>
    <?php else: ?><p class="muted"><?= t('Ingen ordrer ennå.') ?></p><?php endif ?>
  </section>

  <section class="block" id="meldinger">
    <h2><?= t('Feilmeldinger') ?></h2>
    <p class="small muted"><?= t('Det du har meldt fra om i den åpne versjonen. Du kan trekke en melding tilbake så lenge en ansatt ikke har behandlet den.') ?></p>
    <?php if ($reports): ?>
      <ul class="acct-list"><?php foreach (array_slice($reports, 0, 3) as $r) { require __DIR__ . '/_report_row.php'; } ?></ul>
      <?php if (count($reports) > 3): ?><p><a class="btn ghost small" href="/innstillinger/meldinger"><?= t('Se alle feilmeldingene ({n})', ['n' => count($reports)]) ?><?= icon('arrow-right') ?></a></p><?php endif ?>
    <?php else: ?><p class="muted"><?= t('Du har ikke meldt fra om noen feil.') ?></p><?php endif ?>
  </section>

  <section class="block" id="utseende">
    <h2><?= t('Utseende') ?></h2>
    <form method="post" action="/innstillinger/utseende" class="stack">
      <?= csrf_field() ?>
      <fieldset class="choice theme-choice">
        <legend><?= t('Lys eller mørk') ?></legend>
        <?php foreach (['system' => ['monitor', 'Som enheten', 'Følger innstillingen på telefonen eller datamaskinen din.'],
                        'lys' => ['sun', 'Lys', 'Alltid lys bakgrunn.'],
                        'mork' => ['moon', 'Mørk', 'Alltid mørk bakgrunn. Skåner øynene om kvelden.']] as $v => [$ic, $label, $help]): ?>
          <label><input type="radio" name="theme" value="<?= $v ?>" <?= $theme === $v ? 'checked' : '' ?> onchange="this.form.submit()">
            <span><b><?= icon($ic) ?> <?= t($label) ?></b><small><?= t($help) ?></small></span></label>
        <?php endforeach ?>
      </fieldset>
      <noscript><button class="btn"><?= icon('check') ?><?= t('Lagre') ?></button></noscript>
    </form>
  </section>

  <section class="block" id="epost">
    <h2><?= t('E-post') ?></h2>
    <p class="small muted"><?= t('Nå') ?>: <b><?= h($u['email']) ?></b>, <?= t($u['email_verified_at'] ? 'bekreftet' : 'ikke bekreftet') ?>. <?= t('Endrer du den, må den nye adressen bekreftes før du er verifisert kjøper igjen.') ?></p>
    <form method="post" action="/innstillinger/epost" class="stack">
      <?= csrf_field() ?>
      <label><?= t('Ny e-post') ?> <input name="email" type="email" required autocomplete="email"></label>
      <label><?= t('Passord') ?> <input name="password" type="password" required autocomplete="current-password"></label>
      <button class="btn"><?= icon('mail') ?><?= t('Endre e-post') ?></button>
    </form>
  </section>

  <section class="block" id="passord">
    <h2><?= t('Passord') ?></h2>
    <form method="post" action="/innstillinger/passord" class="stack">
      <?= csrf_field() ?>
      <label><?= t('Nåværende passord') ?> <input name="current" type="password" required autocomplete="current-password"></label>
      <label><?= t('Nytt passord') ?> <input name="new" type="password" required minlength="8" autocomplete="new-password"><small class="muted"><?= t('Minst 8 tegn.') ?></small></label>
      <label><?= t('Gjenta nytt passord') ?> <input name="repeat" type="password" required minlength="8" autocomplete="new-password"></label>
      <button class="btn"><?= icon('key-round') ?><?= t('Bytt passord') ?></button>
    </form>
  </section>

  <section class="block" id="data">
    <h2><?= t('Personvern og dine data') ?></h2>
    <table class="mini privacy-table">
      <tr><th><?= t('Opplysning') ?></th><th><?= t('Hvem ser den') ?></th></tr>
      <tr><td><?= t('Navn, e-post, telefon') ?></td><td><?= t('Bare du. Ansatte og kundeservice ser aliaset ditt.') ?></td></tr>
      <tr><td><?= t('Profilbilde') ?></td><td><?= t('Bare du') ?></td></tr>
      <tr><td><?= t('Anmeldelser og bidrag') ?></td><td><?= t('Alle, som «{alias}»', ['alias' => $u['display_name'] === 'fornavn' ? person($first) : h($alias)]) ?></td></tr>
      <tr><td><?= t('Ordrer') ?></td><td><?= t('Du og regnskapet. Lagres i fem år etter bokføringsloven.') ?></td></tr>
      <tr><td><?= t('Søk og klikk') ?></td><td><?= t('Telles uten å knyttes til kontoen din') ?></td></tr>
    </table>
    <div class="data-actions">
      <a class="btn ghost" href="/innstillinger/eksport"><?= icon('download') ?><?= t('Last ned dataene mine') ?></a>
    </div>
    <details class="danger" id="slett">
      <summary><?= icon('trash-2') ?><?= t('Slett kontoen') ?></summary>
      <p class="small"><?= t('Navn, e-post, telefon, profilbilde og anmeldelser slettes. Bidrag i den åpne versjonen blir stående som «Slettet bruker». Ordrer beholdes uten navn fordi loven krever det. Dette kan ikke angres.') ?></p>
      <form method="post" action="/innstillinger/slett" class="stack">
        <?= csrf_field() ?>
        <label><?= t('Skriv passordet for å bekrefte') ?> <input name="password" type="password" required autocomplete="current-password"></label>
        <button class="btn danger"><?= icon('trash-2') ?><?= t('Slett kontoen for godt') ?></button>
      </form>
    </details>
  </section>
</div>
