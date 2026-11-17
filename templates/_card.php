<?php
/** @var array $c  card from Catalog::cards, optional $c['rel'] and $via */
$via ??= 'liste';
?>
<article class="card">
  <a href="/produkt/<?= h($c['slug']) ?>" class="card-img">
    <?php if ($c['img']): ?><img src="/<?= h($c['img']) ?>" alt="" loading="lazy"><?php else: ?><span class="noimg"><?= t('Ingen bilde') ?></span><?php endif ?>
    <?php if (Catalog::hasManual((int) $c['id'])): ?><span class="manual-badge" title="<?= t('Har monteringsveiledning') ?>"><?= icon('book-open') ?><?= t('Veiledning') ?></span><?php endif ?>
  </a>
  <div class="card-body">
    <?php if (!empty($c['rel'])): ?>
      <span class="lvl lvl-<?= h($c['rel']['level']) ?>" title="<?= h($c['rel']['reason']) ?>"><?= h(t(LEVEL_LABEL[$c['rel']['level']])) ?></span>
    <?php endif ?>
    <a href="/produkt/<?= h($c['slug']) ?>" class="card-name"><?= h($c['name']) ?></a>
    <?php if (!empty($c['rel'])): ?><p class="why"><?= h($c['rel']['reason']) ?></p><?php endif ?>
    <?php if ($c['rating'] !== null): ?><p class="rating"><?= stars((float) $c['rating']) ?> <small>(<?= (int) $c['n_reviews'] ?>)</small></p><?php endif ?>
    <p class="price"><?= kr((int) $c['price_ore']) ?><?php if ($c['regular_ore']): ?> <s><?= kr((int) $c['regular_ore']) ?></s><?php endif ?></p>
    <p class="stock-line">
      <?php if ($c['in_store'] > 0): ?><span class="ok">● <?= t('{n} i butikk', ['n' => (int) $c['in_store']]) ?></span>
      <?php elseif ($c['online'] > 0): ?><span class="mid">● <?= t('Nettlager') ?></span>
      <?php elseif ($c['preorder_eta']): ?><span class="low">● <?= t('Bestilling, ca. {d}', ['d' => date('d.m', strtotime($c['preorder_eta']))]) ?></span>
      <?php else: ?><span class="low">● <?= t('Utsolgt') ?></span><?php endif ?>
    </p>
    <div class="card-actions">
      <form method="post" action="/handlekurv/legg-til" class="add">
        <?= csrf_field() ?><input type="hidden" name="product" value="<?= (int) $c['id'] ?>"><input type="hidden" name="via" value="<?= h($via) ?>">
        <input type="hidden" name="back" value="<?= h($_SERVER['REQUEST_URI'] ?? '/') ?>">
        <button class="btn small"><?= t('Legg i kurv') ?></button>
      </form>
      <label class="cmp"><input type="checkbox" data-compare="<?= (int) $c['id'] ?>" data-name="<?= h($c['name']) ?>"> <?= t('Sammenlign') ?></label>
    </div>
  </div>
</article>
