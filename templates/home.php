<?php /** @var array $popular @var int $products @var array $compare */ ?>
<?php // Full width banner: the saw photo, then the text. The photo crossfades slowly to the cut ?>
<section class="banner hero" data-saw>
  <figure class="banner-photo" aria-hidden="true">
    <img class="saw-low" src="/assets/saw/lowsaw.png" alt="" width="1672" height="941" fetchpriority="high">
    <img class="saw-high" src="/assets/saw/highsaw.png" alt="" width="1672" height="941">
  </figure>
  <div class="banner-text hero-text">
    <h1><?= t('Finn riktig utstyr, og alt som hører til') ?></h1>
    <p class="hero-cta"><a class="btn" href="/guide"><?= t('Start produktguiden') ?></a> <a class="text-link" href="#" data-open-soren><span data-soren="leaf"></span><span><?= t('Spør Søren') ?></span><?= icon('arrow-right', 'go') ?></a></p>
  </div>
</section>

<section class="home-sec reveal">
  <h2><?= t('Hva skal du gjøre?') ?></h2>
  <ul class="theme-index">
    <?php foreach (Guide::QUESTIONS['oppgave'][1] as $k => $label): ?>
      <li data-tema="<?= h($k) ?>"><a href="/tema/<?= $k ?>"><span class="tile-icon"><?= icon(Icons::THEMES[$k]) ?></span><span class="txt"><b><?= h(t($label)) ?></b><span><?= h(theme_desc($k)) ?></span>
        <?php if ($compare[$k]): ?><small class="keys"><?= h(implode(', ', array_map(fn($r) => I18n::spec($r['key'], $r['label']), $compare[$k]))) ?></small><?php endif ?></span><?= icon('arrow-right', 'go') ?></a></li>
    <?php endforeach ?>
  </ul>
</section>

<section class="home-sec reveal">
  <h2><?= t('Populært nå') ?></h2>
  <div class="grid"><?php foreach ($popular as $c) { $via = 'forside'; require __DIR__ . '/_card.php'; } ?></div>
</section>

<section class="home-sec reveal">
  <h2><?= t('Slik fungerer det') ?></h2>
  <div class="explain">
    <div><h3><?= t('To versjoner av produktinfo') ?></h3><p><?= t('<b>Verifisert</b> er det Sør-Tre selv skriver. <b>Åpen</b> kan utvides av kjøpere som har handlet hos oss, slik som Wikipedia. Hver verdi viser hvor den kommer fra.') ?></p></div>
    <div><h3><?= t('Passer sammen') ?></h3><p><?= t('Vi viser bare at noe <i>passer</i> når Sør-Tre har skrevet det, eller når en tydelig regel sier det, som at kjetting og sjakkel har samme dimensjon.') ?></p></div>
    <div><h3><?= t('Ditt varehus') ?></h3><p><?= t('Velg varehus øverst for å se hva som står på hylla, hva som ligger på nettlager og hva som kan bestilles.') ?></p></div>
  </div>
</section>
