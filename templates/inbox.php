<?php /** @var array $mails */ ?>
<div class="narrow">
  <h1><?= t('Innboks') ?></h1>
  <p class="muted"><?= t('Prototypen sender ikke ekte e-post. Alt som ville blitt sendt til deg havner her.') ?><?= lang() === 'en' ? ' ' . t('E-postene er på norsk.') : '' ?></p>
  <?php if (!current_user()): ?><p><a href="/logg-inn?back=/innboks"><?= t('Logg inn') ?></a> <?= t('for å se e-posten din.') ?></p><?php endif ?>
  <?php foreach ($mails as $m): ?>
    <article class="mail"><header><b><?= h($m['subject']) ?></b><small class="muted"><?= t('Til') ?> <?= h($m['to_email']) ?>, <?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></small></header>
      <div><?= $m['body_html'] ?></div></article>
  <?php endforeach ?>
  <?php if (current_user() && !$mails): ?><p class="muted"><?= t('Ingen e-post ennå.') ?></p><?php endif ?>
</div>
