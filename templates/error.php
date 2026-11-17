<?php /** @var int $code @var string $text @var string $ref @var ?string $detail */ ?>
<div class="empty">
  <div class="empty-head av" data-soren="head" data-still></div>
  <h1><?= $code === 400 ? t('Ugyldig forespørsel') : t('Noe gikk galt') ?></h1>
  <p><?= h($text) ?></p>
  <p class="small muted"><?= t('Referanse: {ref}. Oppgi den hvis du kontakter oss.', ['ref' => '<code>' . h($ref) . '</code>']) ?></p>
  <?php if ($detail): ?><pre class="small"><?= h($detail) ?></pre><?php endif ?>
  <p><a class="btn" href="/"><?= icon('house') ?><?= t('Til forsiden') ?></a></p>
</div>
