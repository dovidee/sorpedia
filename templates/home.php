<section class="hero">
  <div class="hero-text">
    <h1><?= t('Finn riktig utstyr, og alt som hører til') ?></h1>
    <p><?= t('Svar på fire spørsmål, så foreslår Søren produkter og forklarer hvorfor. Du ser også hva som passer sammen, hva andre kjøpere har lagt til av info, og om varen er inne i ditt varehus.') ?></p>
    <p class="hero-cta"><a class="btn" href="/guide"><?= t('Start produktguiden') ?></a> <a class="btn ghost" href="#" data-open-soren><span data-soren="leaf"></span> <?= t('Spør Søren') ?></a></p>
  </div>
  <div class="hero-mascot" data-soren data-greet role="img" aria-label="<?= t('Søren, maskoten til Sørpedia') ?>"></div>
</section>

<h2><?= t('Hva skal du gjøre?') ?></h2>
<div class="theme-grid">
  <?php foreach (Guide::QUESTIONS['oppgave'][1] as $k => $label): ?>
    <a class="theme-tile" href="/tema/<?= $k ?>"><span class="tile-icon"><?= icon(Icons::THEMES[$k]) ?></span><b><?= h(t($label)) ?></b><span><?= h(theme_desc($k)) ?></span></a>
  <?php endforeach ?>
</div>

<h2><?= t('Populært nå') ?></h2>
<div class="grid"><?php foreach ($popular as $c) { $via = 'forside'; require __DIR__ . '/_card.php'; } ?></div>

<section class="explain">
  <div><h3><?= t('To versjoner av produktinfo') ?></h3><p><?= t('<b>Verifisert</b> er det Sør-Tre selv skriver. <b>Åpen</b> kan utvides av kjøpere som har handlet hos oss, slik som Wikipedia. Hver verdi viser hvor den kommer fra.') ?></p></div>
  <div><h3><?= t('Passer sammen') ?></h3><p><?= t('Vi viser bare at noe <i>passer</i> når Sør-Tre har skrevet det, eller når en tydelig regel sier det, som at kjetting og sjakkel har samme dimensjon.') ?></p></div>
  <div><h3><?= t('Ditt varehus') ?></h3><p><?= t('Velg varehus øverst for å se hva som står på hylla, hva som ligger på nettlager og hva som kan bestilles.') ?></p></div>
</section>
