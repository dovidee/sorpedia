<?php /** @var array $r one row from Account::reports @var string $back where to return after taking it back */ ?>
<li class="acct-row">
  <div>
    <span><a href="/produkt/<?= h($r['slug']) ?>?versjon=apen#spesifikasjoner"><b><?= h($r['product']) ?></b></a>
      <small class="muted"><?= h(I18n::spec($r['key'], (string) ($r['label'] ?? $r['key']))) ?>: <?= h(trim($r['value_text'] . ' ' . $r['unit'])) ?>, <?= date('d.m.Y', strtotime($r['created_at'])) ?></small>
      <small>«<?= h($r['reason']) ?>»</small></span>
    <span class="acct-end">
      <span class="tag"><?= t($r['status'] === 'apen' ? 'Til vurdering' : 'Behandlet') ?></span>
      <?php if ($r['status'] === 'apen'): ?>
        <form method="post" action="/innstillinger/meldinger/<?= (int) $r['id'] ?>/trekk"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>">
          <button class="btn ghost small"><?= icon('undo-2') ?><?= t('Trekk tilbake') ?></button></form>
      <?php endif ?>
    </span>
  </div>
</li>
