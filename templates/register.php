<div class="narrow">
  <h1><?= t('Registrer deg') ?></h1>
  <p class="muted"><?= t('For å bli <b>verifisert kjøper</b> må du bekrefte e-posten og handle minst en gang. Da kan du fylle inn produktinfo og skrive anmeldelser. E-posten finner du under Innboks øverst på siden.') ?></p>
  <form method="post" action="/registrer" class="stack">
    <?= csrf_field() ?>
    <label><?= t('Navn') ?> <input name="name" required maxlength="120" autocomplete="name"><small class="muted"><?= t('Vises aldri for andre. Bidragene dine vises som «Kjøper #a1b2c3» til du eventuelt velger noe annet under Innstillinger.') ?></small></label>
    <label><?= t('E-post') ?> <input name="email" type="email" required autocomplete="email"></label>
    <label><?= t('Passord') ?> <input name="password" type="password" required minlength="8" autocomplete="new-password"></label>
    <label class="check"><input type="checkbox" name="terms" value="1" required> <span><?= t('Jeg godtar') ?> <a href="/vilkar" target="_blank"><?= t('vilkårene og personvernerklæringen') ?></a></span></label>
    <button class="btn"><?= t('Opprett konto') ?></button>
  </form>
  <p class="small"><?= t('Har du konto?') ?> <a href="/logg-inn"><?= t('Logg inn') ?></a></p>
</div>
