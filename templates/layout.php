<?php
/** @var string $content @var string $title */
$u = current_user();
$wh = warehouses();
$msg = flash();
$here = (string) ($_SERVER['REQUEST_URI'] ?? '/');
if (str_starts_with($here, '/sprak')) { $here = '/'; } // error page on /sprak: don't nest the link inside itself
// Which category the customer is in: /tema/x, or the category of the product on the page
$path = parse_url($here, PHP_URL_PATH) ?: '/';
$activeTheme = null;
if (preg_match('#^/tema/([a-z0-9_-]+)#', $path, $mm)) {
    $activeTheme = $mm[1];
} elseif (preg_match('#^/produkt/([a-z0-9-]+)#', $path, $mm)) {
    $st = db()->prepare('SELECT theme FROM products WHERE slug = ?');
    $st->execute([$mm[1]]);
    $activeTheme = $st->fetchColumn() ?: null;
}
// Light or dark: a personal setting. Guests and "Som enheten" (match device) follow the operating system.
$themeAttr = match ($u['theme'] ?? 'system') { 'lys' => ' data-theme="light"', 'mork' => ' data-theme="dark"', default => '' };
$en = lang() === 'en';
// The flag shows the language you switch TO
$flag = $en
    ? '<svg viewBox="0 0 22 16" aria-hidden="true"><rect width="22" height="16" fill="oklch(50.3% .193 27)"/><rect x="6" width="4" height="16" fill="oklch(100% 0 0)"/><rect y="6" width="22" height="4" fill="oklch(100% 0 0)"/><rect x="7" width="2" height="16" fill="oklch(32% .12 264)"/><rect y="7" width="22" height="2" fill="oklch(32% .12 264)"/></svg>'
    : '<svg viewBox="0 0 60 30" aria-hidden="true"><clipPath id="uk-c"><rect width="60" height="30"/></clipPath><g clip-path="url(#uk-c)"><rect width="60" height="30" fill="oklch(30% .11 264)"/><path d="M0,0 60,30M60,0 0,30" stroke="oklch(100% 0 0)" stroke-width="6"/><path d="M0,0 60,30M60,0 0,30" stroke="oklch(50.3% .193 27)" stroke-width="2.5"/><path d="M30,0v30M0,15h60" stroke="oklch(100% 0 0)" stroke-width="10"/><path d="M30,0v30M0,15h60" stroke="oklch(50.3% .193 27)" stroke-width="6"/></g></svg>';
?><!doctype html>
<html lang="<?= $en ? 'en' : 'nb' ?>"<?= $themeAttr ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= csrf() ?>">
<?php $sorenCtx = PageContext::resolve($_SERVER['REQUEST_URI'] ?? '/'); ?>
<meta name="soren-page" content="<?= h(t($sorenCtx['label'])) ?>">
<meta name="soren-scope" content="<?= in_array($sorenCtx['kind'], ['ordre', 'konto'], true) ? 'konto' : 'produkt' ?>">
<title><?= h($title ? t($title) : 'Sørpedia') ?> | Sørpedia</title>
<meta name="color-scheme" content="<?= match ($u['theme'] ?? 'system') { 'lys' => 'light', 'mork' => 'dark', default => 'light dark' } ?>">
<meta name="lang" content="<?= $en ? 'en' : 'no' ?>">
<script>window.I18N = <?= json_encode($en ? I18nEn::JS : new stdClass(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<link rel="icon" type="image/png" href="/assets/brand/sorpedia-badge.png">
<link rel="stylesheet" href="/assets/app.css?v=<?= filemtime(APP_ROOT . '/public/assets/app.css') ?>">
<script src="/assets/soren.js?v=<?= filemtime(APP_ROOT . '/public/assets/soren.js') ?>" defer></script>
<script src="/assets/app.js?v=<?= filemtime(APP_ROOT . '/public/assets/app.js') ?>" defer></script>
</head>
<body<?= $activeTheme ? ' data-tema="' . h($activeTheme) . '"' : '' ?>>
<div class="demo-bar"><span><?= t('Varene og bildene er fra sor-tre.no. Lager, kunder, anmeldelser og ordrer er laget for demoen, og du betaler ingenting.') ?></span>
  <a class="lang-flag" href="/sprak/<?= $en ? 'no' : 'en' ?>?back=<?= urlencode($here) ?>" hreflang="<?= $en ? 'nb' : 'en' ?>" lang="<?= $en ? 'nb' : 'en' ?>" title="<?= $en ? 'Bytt til norsk' : 'Switch to English' ?>"><?= $flag ?><span><?= $en ? 'Norsk' : 'English' ?></span></a></div>
<header class="top">
  <div class="wrap top-row">
    <a class="logo" href="/"><img src="/assets/brand/sorpedia-full.png" alt="Sørpedia" width="3487" height="875"><span class="logo-ink" aria-hidden="true"></span><span class="logo-shine" aria-hidden="true"></span></a>
    <form class="search" action="/sok" role="search" autocomplete="off">
      <div class="q-wrap"><span class="q-ghost" id="ghost" aria-hidden="true"></span><input name="q" id="q" type="search" placeholder="<?= t('Søk etter produkt eller hva du skal gjøre, f.eks. «male tak»') ?>" value="<?= h($_GET['q'] ?? '') ?>" aria-label="<?= t('Søk') ?>" aria-describedby="q-tip" spellcheck="false"></div>
      <span id="q-tip" hidden><?= t('Trykk Tab for å fullføre det grå forslaget.') ?></span>
      <button aria-label="<?= t('Søk') ?>"><?= icon('search') ?><span><?= t('Søk') ?></span></button>
      <div class="suggest" id="suggest" hidden></div>
    </form>
    <nav class="tools" aria-label="<?= t('Varehus, konto og handlekurv') ?>">
      <form method="post" action="/varehus" class="wh" title="<?= t('Varehuset ditt. Lagerstatus vises for dette.') ?>">
        <?= csrf_field() ?><input type="hidden" name="back" value="<?= h($here) ?>">
        <label><?= icon('map-pin') ?><span class="sr"><?= t('Varehus') ?></span>
        <select name="warehouse" onchange="this.form.submit()">
          <?php foreach ($wh as $id => $w): ?>
            <option value="<?= $id ?>" <?= $id === warehouse_id() ? 'selected' : '' ?>><?= h($w['name']) ?></option>
          <?php endforeach ?>
        </select></label>
      </form>
      <?php if ($u): $unread = Mail::unread($u['email']); ?><a class="ico count-link" id="inbox-link" href="/innboks" title="<?= t('Innboks') ?>"<?= str_starts_with($path, '/innboks') ? ' aria-current="page"' : '' ?>><?= icon('inbox') ?><span class="sr"><?= t('Innboks') ?>,</span><b id="inbox-count"<?= $unread ? '' : ' hidden' ?>><?= $unread ?></b><span class="sr"><?= t('uleste') ?></span></a><?php endif ?>
      <details class="menu" data-menu>
        <summary class="ico" title="<?= t($u ? 'Kontoen din' : 'Logg inn eller registrer deg') ?>">
          <?php if ($u && $u['avatar']): ?><img class="avatar" src="/profilbilde?v=<?= h(substr($u['avatar'], 0, 8)) ?>" alt=""><?php else: ?><?= icon('user-round') ?><?php endif ?>
          <span class="sr"><?= t($u ? 'Kontoen din' : 'Logg inn') ?></span></summary>
        <div class="menu-pop">
          <?php if ($u): ?>
            <div class="menu-head">
              <span><b><?= person(explode(' ', $u['name'])[0]) ?></b>
                <small><?= t('Vises som') ?> <?= h(Privacy::alias($u)) ?><?php if (is_verified($u)): ?> <span class="verified" title="<?= t('Verifisert kjøper') ?>"><?= icon('badge-check') ?></span><?php endif ?></small></span>
            </div>
            <a href="/innstillinger"><?= icon('settings') ?><?= t('Innstillinger') ?></a>
            <?php if (has_role('ansatt', 'support', 'admin')): ?>
              <div class="menu-group" role="group" aria-label="<?= t('Arbeid') ?>" data-label="<?= t('Arbeid') ?>">
                <?php if (has_role('ansatt', 'admin')): ?><a href="/ansatt"><?= icon('git-merge') ?>Flettekø</a><?php endif ?>
                <?php if (has_role('support', 'ansatt', 'admin')): ?><a href="/support"><?= icon('headset') ?>Kundeservice</a><?php endif ?>
                <?php if (has_role('admin')): ?><a href="/admin"><?= icon('chart-column') ?>Måltall</a><?php endif ?>
              </div>
            <?php endif ?>
            <form method="post" action="/logg-ut" data-logout><?= csrf_field() ?><button class="menu-item"><?= icon('log-out') ?><?= t('Logg ut') ?></button></form>
          <?php else: ?>
            <a href="/logg-inn?back=<?= urlencode($here) ?>"><?= icon('log-in') ?><?= t('Logg inn') ?></a>
            <a href="/registrer"><?= icon('user-plus') ?><?= t('Registrer deg') ?></a>
          <?php endif ?>
        </div>
      </details>
      <a class="ico cart-link" href="/handlekurv" title="<?= t('Handlekurv') ?>"><?= icon('shopping-basket') ?><span class="sr"><?= t('Handlekurv') ?>,</span><b id="cart-count"><?= cart_count() ?></b><span class="sr"><?= t('varer') ?></span></a>
    </nav>
  </div>
  <nav class="wrap themes">
    <?php $helpHere = str_starts_with($path, '/guide') || str_starts_with($path, '/byggelab'); ?>
    <button type="button" class="guide-link help-btn<?= $helpHere ? ' is-here' : '' ?>" popovertarget="help-menu" aria-haspopup="true"><?= icon('life-buoy') ?><?= t('Hjelp') ?><?= icon('chevron-down', 'chev') ?></button>
    <div class="help-pop" id="help-menu" popover>
      <a href="/guide"<?= str_starts_with($path, '/guide') ? ' aria-current="page"' : '' ?>><?= icon('compass') ?><span><b><?= t('Produktguide') ?></b><small><?= t('Svar på noen spørsmål og få forslag med alt som hører til.') ?></small></span></a>
      <a href="/byggelab"<?= str_starts_with($path, '/byggelab') ? ' aria-current="page"' : '' ?>><?= icon('blocks') ?><span><b><?= t('Byggelab') ?></b><small><?= t('Sett sammen produkter, se hva som passer og testkjør oppsettet.') ?></small></span></a>
    </div>
    <?php foreach (Catalog::THEMES as $k => [$n]): ?><a href="/tema/<?= $k ?>"<?= $activeTheme === $k ? ' aria-current="page"' : '' ?>><?= icon(Icons::THEMES[$k]) ?><?= h(t($n)) ?></a><?php endforeach ?>
  </nav>
</header>
<?php if ($msg): ?><div class="wrap"><div class="flash"><?= h(t($msg)) ?></div></div><?php endif ?>
<main class="wrap"><?= $content ?></main>
<div class="compare-bar" id="compare-bar" hidden></div>
<footer class="wrap foot">
  <p><?= t('Sørpedia er en prototype og er ikke tilknyttet Sør-Tre AS. Spesifikasjoner merket «Utledet» eller «Kjøper» er ikke kontrollert av Sør-Tre.') ?></p>
</footer>
<button class="soren-fab" id="soren-open" aria-label="<?= t('Spør Søren') ?>"><span class="av" data-soren="head"></span></button>
</body>
</html>
