<?php
/** @var string $theme @var array $f @var string $sort @var array $facets @var array $products @var array $band */
$fmt = fn(float $n) => rtrim(rtrim(number_format($n, 1, ',', ' '), '0'), ',');
?>
<section class="banner tema-band" data-tema="<?= h($theme) ?>" aria-labelledby="tema-title">
  <?php // Category photo, keyed by the category slug. Decorative: the heading already names the category
  $bandImg = ['lys' => 'lysoglykter.png', 'bygg' => 'byggogfeste.png', 'maling' => 'malingogsparkel.png', 'balkong' => 'balkongoglevegg.png',
    'kjetting' => 'kjettingogløft.png', 'skog' => 'skogogved.png', 'tur' => 'turogfriluft.png'][$theme] ?? null; ?>
  <?php if ($bandImg): ?>
  <figure class="banner-photo"><img src="/assets/tema/<?= rawurlencode($bandImg) ?>" alt="" width="1672" height="941" decoding="async"></figure>
  <?php endif ?>
  <div class="banner-text band-text">
    <?php // Every category name is "X og Y" ("X and Y"): the joining word ends line one, so all names break the same way in both languages
    $nameParts = preg_split('/ (og|and) /u', theme_name($theme), 2, PREG_SPLIT_DELIM_CAPTURE); ?>
    <h1 id="tema-title"><?= count($nameParts) === 3 ? h($nameParts[0]) . ' ' . h($nameParts[1]) . '<br>' . h($nameParts[2]) : h(theme_name($theme)) ?></h1>
    <p class="band-desc"><?= h(theme_desc($theme)) ?></p>
    <dl class="band-figs">
      <div><dt><?= t('Produkter') ?></dt><dd><?= (int) $band['products'] ?></dd></div>
      <div><dt><?= t('Filtre') ?></dt><dd><?= (int) $band['filters'] ?></dd></div>
      <div><dt><?= t('Med monteringsveiledning') ?></dt><dd><?= (int) $band['guides'] ?></dd></div>
    </dl>
  </div>
</section>

<div class="with-side">
<?php
$active = fn(string $k) => count(array_filter((array) ($f['v'][$k] ?? []))) + (($f['min'][$k] ?? '') !== '' ? 1 : 0) + (($f['max'][$k] ?? '') !== '' ? 1 : 0);
$anyOn = $f['pris_min'] !== '' || $f['pris_max'] !== '' || $f['lager'] || array_filter((array) $f['v']) || array_filter((array) $f['min']) || array_filter((array) $f['max']);
?>
<form class="filters" method="get" id="filters" data-auto>
  <input type="hidden" name="sort" value="<?= h($sort) ?>">
  <div class="filters-head"><b><?= t('Filtrer') ?></b><a href="/tema/<?= h($theme) ?>" class="link small" id="filters-reset" <?= $anyOn ? '' : 'hidden' ?>><?= t('Nullstill alle') ?></a></div>
  <label class="check"><input type="checkbox" name="lager" value="1" <?= $f['lager'] ? 'checked' : '' ?>> <?= t('På lager i {wh}', ['wh' => h(warehouses()[warehouse_id()]['name'])]) ?></label>
  <label class="check"><input type="checkbox" name="veiledning" value="1" <?= !empty($f['veiledning']) ? 'checked' : '' ?>> <?= icon('book-open') ?> <?= t('Har monteringsveiledning') ?></label>
  <?php $pOn = ($f['pris_min'] !== '') + ($f['pris_max'] !== ''); ?>
  <details class="facet" data-key="pris" open>
    <summary><?= t('Pris') ?> (kr)<?php if ($pOn): ?> <span class="n-on"><?= $pOn ?></span><?php endif ?></summary>
    <div class="range"><input name="pris_min" inputmode="numeric" placeholder="<?= t('Fra') ?>" value="<?= h($f['pris_min']) ?>" aria-label="<?= t('Pris fra') ?>"><input name="pris_max" inputmode="numeric" placeholder="<?= t('Til') ?>" value="<?= h($f['pris_max']) ?>" aria-label="<?= t('Pris til') ?>"></div>
  </details>
  <?php $i = 0; foreach ($facets as $k => $fc): $on = $active($k); $i++; ?>
    <details class="facet" data-key="<?= h($k) ?>" <?= ($on || $i <= 2) ? 'open' : '' ?> <?= ($fc['type'] === 'values' && !$fc['values']) ? 'hidden' : '' ?>>
      <summary><?= hc(I18n::spec($k, $fc['label'])) ?><?= $fc['unit'] ? ' (' . h($fc['unit']) . ')' : '' ?><?php if ($on): ?> <span class="n-on"><?= $on ?></span><?php endif ?></summary>
      <div>
      <?php if ($fc['type'] === 'range'): ?>
        <div class="range">
          <input name="min[<?= h($k) ?>]" inputmode="decimal" placeholder="<?= $fmt($fc['lo']) ?>" value="<?= h($f['min'][$k] ?? '') ?>" aria-label="<?= h(I18n::spec($k, $fc['label'])) ?> <?= t('fra') ?>">
          <input name="max[<?= h($k) ?>]" inputmode="decimal" placeholder="<?= $fmt($fc['hi']) ?>" value="<?= h($f['max'][$k] ?? '') ?>" aria-label="<?= h(I18n::spec($k, $fc['label'])) ?> <?= t('til') ?>">
        </div>
        <small class="muted"><?= t('Oppgitt for {n} produkter', ['n' => $fc['n']]) ?></small>
      <?php else: ?>
        <?php foreach ($fc['values'] as $vi => $v): $checked = in_array($v['value_text'], (array) ($f['v'][$k] ?? []), true); if ($vi >= 10 && !$checked) continue; ?>
          <label class="check"><input type="checkbox" name="v[<?= h($k) ?>][]" value="<?= h($v['value_text']) ?>" <?= $checked ? 'checked' : '' ?>>
            <?= hc(I18n::value($v['value_text'])) ?> <small>(<?= $v['n'] ?>)</small></label>
        <?php endforeach ?>
        <?php if ($fc['values'][0]['derived'] ?? false): ?><small class="muted" title="<?= t('Sør-Tre har ikke dette som eget felt. Verdien er tolket fra produktteksten.') ?>"><?= t('Utledet fra produkttekst') ?></small><?php endif ?>
      <?php endif ?>
      </div>
    </details>
  <?php endforeach ?>
  <noscript><button class="btn"><?= t('Bruk filter') ?></button></noscript>
</form>

<div class="results" id="results">
  <div class="sortbar">
    <p class="result-count" id="result-count"><?= t('{n} produkter', ['n' => count($products)]) ?></p>
    <label><?= t('Sorter') ?>
      <select id="sort-select" onchange="const f=document.getElementById('filters');f.sort.value=this.value;f.requestSubmit ? f.requestSubmit() : f.submit()">
        <?php foreach (['populaer' => 'Mest populære', 'vurdering' => 'Best vurdert', 'pris' => 'Laveste pris', 'pris_desc' => 'Høyeste pris', 'navn' => 'Navn'] as $k => $l): ?>
          <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= t($l) ?></option>
        <?php endforeach ?>
      </select></label>
  </div>
  <?php if (!$products): ?>
    <div class="empty"><p><?= t('Ingen produkter passer filtrene. Mange produkter mangler tall for enkelte filtre fordi Sør-Tre ikke oppgir dem.') ?> <a href="/tema/<?= h($theme) ?>"><?= t('Nullstill filtre') ?></a>.</p></div>
  <?php endif ?>
  <div class="grid"><?php foreach ($products as $c) { $via = 'tema'; require __DIR__ . '/_card.php'; } ?></div>
</div>
</div>
