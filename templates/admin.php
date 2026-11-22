<?php
/**
 * Admin page: signs of password guessing, the security log, staff actions, backups and the error log.
 * Read from audit_log (src/Audit.php), data/backups and data/logs/errors.log. Nobody's name or IP address is shown:
 * accounts appear as "konto #ID", places as a hash that changes every day.
 * @var array $brute @var array $security @var array $actions @var array $ops @var array $backups @var array $errors
 */
$when = fn(int $t) => date('d.m.Y H:i:s', $t);
$mb = fn(int $b) => number_format($b / 1048576, 1, ',', ' ') . ' MB';
$labels = [
    'login_ok' => t('Innlogging'), 'login_fail' => t('Feil passord eller ukjent e-post'), 'login_blocked' => t('Forsøk på låst konto'),
    'lockout' => t('Konto låst i {n} min', ['n' => Auth::LOCK_MINUTES]), 'reset_request' => t('Bedt om nytt passord'), 'reset_done' => t('Passord byttet med lenke'),
    'password_change' => t('Passord endret i innstillinger'), 'refund' => t('Refusjon'), 'search_mapping' => t('Søkeord koblet til produkt'),
    'revision_merge' => t('Endring flettet inn'), 'revision_reject' => t('Endring avvist'), 'report_remove' => t('Verdi fjernet etter feilmelding'),
    'report_close' => t('Feilmelding lukket'), 'case_close' => t('Sak lukket'), 'backup_request' => t('Sikkerhetskopi bestilt'),
    'backup_ok' => t('Sikkerhetskopi tatt'), 'backup_fail' => t('Sikkerhetskopi feilet'),
];
// Targets are stored in Norwegian ("konto #7", "ukjent e-post"); shown in the page language
$target = function (?string $s): string {
    if ($s === null) {
        return '';
    }
    if (preg_match('/^(konto|sak|ordre|endring|feilmelding|hendelse) #(\d+)$/', $s, $m)) {
        return t($m[1] . ' #{id}', ['id' => $m[2]]);
    }
    return $s === 'ukjent e-post' ? t('ukjent e-post') : $s;
};
$rows = function (array $list) use ($when, $labels, $target): string {
    if (!$list) {
        return '<p class="muted">' . t('Ingenting registrert ennå.') . '</p>';
    }
    $h = '<table class="mini admin-log"><tr><th>' . t('Tid') . '</th><th>' . t('Hendelse') . '</th><th>' . t('Objekt') . '</th><th>' . t('Utført av') . '</th><th>' . t('Sted') . '</th></tr>';
    foreach ($list as $r) {
        $bad = in_array($r['action'], ['login_fail', 'login_blocked', 'lockout', 'backup_fail'], true);
        $h .= '<tr' . ($bad ? ' class="bad"' : '') . '><td><code>' . h($when((int) $r['at'])) . '</code></td><td>' . h($labels[$r['action']] ?? $r['action'])
            . '</td><td>' . h($target($r['target'])) . '</td><td>' . h((string) ($r['who'] ?? '')) . '</td><td><code>' . h((string) $r['ip_hash']) . '</code></td></tr>';
    }
    return $h . '</table>';
};
?>
<div class="admin">
  <h1><?= t('Admin') ?></h1>
  <p class="muted"><?= t('Sikkerhet, handlinger fra ansatte, sikkerhetskopier og feil. Kontoer vises som nummer, og stedet er en kode som byttes hver dag, så ingen IP-adresse lagres.') ?></p>

  <section aria-labelledby="sik-h">
    <h2 id="sik-h"><?= t('Sikkerhet siste døgn') ?></h2>
    <div class="kpis">
      <div><b><?= (int) $brute['fails'] ?></b><span><?= t('Mislykkede innlogginger') ?></span></div>
      <div><b><?= (int) $brute['lockouts'] ?></b><span><?= t('Kontoer låst') ?></span></div>
      <div><b><?= count($brute['locked']) ?></b><span><?= t('Låst nå') ?></span></div>
      <div><b><?= (int) $brute['resets'] ?></b><span><?= t('Bedt om nytt passord') ?></span></div>
    </div>
    <h3><?= t('Mulig passordgjetting') ?></h3>
    <p class="muted small"><?= t('Steder med minst {n} mislykkede innlogginger siste døgn. Kontoer låses automatisk etter {n} feil på rad i {m} minutter.', ['n' => Auth::MAX_FAILS, 'm' => Auth::LOCK_MINUTES]) ?></p>
    <?php if ($brute['places']): ?>
      <table class="mini admin-log"><tr><th><?= t('Sted') ?></th><th><?= t('Feil') ?></th><th><?= t('Kontoer prøvd') ?></th><th><?= t('Sist') ?></th></tr>
        <?php foreach ($brute['places'] as $p): ?><tr class="bad"><td><code><?= h($p['ip_hash']) ?></code></td><td><?= (int) $p['n'] ?></td><td><?= (int) $p['accounts'] ?></td><td><code><?= h($when((int) $p['last'])) ?></code></td></tr><?php endforeach ?>
      </table>
    <?php else: ?><p class="muted"><?= t('Ingen tegn til passordgjetting.') ?></p><?php endif ?>
    <?php if ($brute['locked']): ?>
      <p class="small"><?= t('Låst nå:') ?> <?php foreach ($brute['locked'] as $l): ?><code><?= h($target('konto #' . (int) $l['id'])) ?></code> <?= t('til') ?> <?= h(date('H:i', (int) $l['until'])) ?>. <?php endforeach ?></p>
    <?php endif ?>
    <h3><?= t('Sikkerhetslogg') ?></h3>
    <?= $rows($security) ?>
  </section>

  <section aria-labelledby="adm-h">
    <h2 id="adm-h"><?= t('Handlinger fra ansatte') ?></h2>
    <?= $rows($actions) ?>
  </section>

  <section id="kopier" aria-labelledby="kop-h">
    <h2 id="kop-h"><?= t('Sikkerhetskopier') ?></h2>
    <p class="muted small"><?= t('Hele databasen tas automatisk ved oppstart og hvert døgn. De {n} nyeste beholdes i data/backups, utenfor nettsiden, siden de inneholder kundedata.', ['n' => 14]) ?></p>
    <form method="post" action="/admin/sikkerhetskopi"><?= csrf_field() ?><button class="btn"><?= icon('download') ?> <?= t('Ta sikkerhetskopi nå') ?></button></form>
    <?php if ($backups): ?>
      <table class="mini admin-log"><tr><th><?= t('Fil') ?></th><th><?= t('Tatt') ?></th><th><?= t('Størrelse') ?></th></tr>
        <?php foreach ($backups as $b): ?><tr><td><code><?= h($b['name']) ?></code></td><td><code><?= h($when($b['at'])) ?></code></td><td><?= h($mb($b['bytes'])) ?></td></tr><?php endforeach ?>
      </table>
    <?php else: ?><p class="muted"><?= t('Ingen sikkerhetskopi ennå.') ?></p><?php endif ?>
    <h3><?= t('Driftslogg') ?></h3>
    <?= $rows($ops) ?>
  </section>

  <section aria-labelledby="feil-h">
    <h2 id="feil-h"><?= t('Feillogg') ?></h2>
    <p class="muted small"><?= t('De siste feilene. Referansen er den samme som kunden fikk se.') ?></p>
    <?php if ($errors): ?><pre class="admin-errors"><?= h(implode("\n", $errors)) ?></pre><?php else: ?><p class="muted"><?= t('Ingen feil logget.') ?></p><?php endif ?>
  </section>
</div>
