<?php /** @var array $mails */ ?>
<div class="narrow">
  <h1><?= t('Innboks') ?></h1>
  <p class="muted"><?= t('Prototypen sender ikke ekte e-post. Alt som ville blitt sendt til deg havner her.') ?><?= lang() === 'en' ? ' ' . t('E-postene er på norsk.') : '' ?></p>
  <?php if (!current_user() && !$mails): ?><p><a href="/logg-inn?back=/innboks"><?= t('Logg inn') ?></a> <?= t('for å se e-posten din.') ?></p><?php endif ?>
  <?php foreach ($mails as $m): ?>
    <article class="mail"><header><b><?= h($m['subject']) ?><?= $m['read_at'] === null ? ' <span class="tag">' . t('Ny') . '</span>' : '' ?></b><small class="muted"><?= t('Til') ?> <?= h($m['to_email']) ?>, <?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></small></header>
      <div><?= $m['body_html'] ?></div></article>
  <?php endforeach ?>
  <?php if (current_user() && !$mails): ?><div class="empty"><div class="empty-mascot" data-soren="lumber"></div><p class="muted"><?= t('Ingen e-post ennå.') ?></p></div><?php endif ?>
</div>
