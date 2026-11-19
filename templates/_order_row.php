<?php /** @var array $o one row from Account::orders */ ?>
<li class="acct-row">
  <a href="/ordre/<?= (int) $o['id'] ?>">
    <span><b><?= t('Ordre {id}', ['id' => (int) $o['id']]) ?></b>
      <small class="muted"><?= date('d.m.Y', strtotime($o['created_at'])) ?>, <?= h($o['first_item'] ?? '') ?><?= $o['items'] > 1 ? ' ' . t('og {n} til', ['n' => (int) $o['items'] - 1]) : '' ?></small></span>
    <span class="acct-end"><span class="tag"><?= t($o['status'] === 'betalt' ? 'Betalt' : 'Kansellert') ?></span><b><?= kr((int) $o['total_ore']) ?></b></span>
  </a>
</li>
