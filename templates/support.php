<?php /** @var array $tickets @var array $msgs */ ?>
<h1>Kundeservice <small class="muted"><?= count($tickets) ?> åpne saker</small></h1>
<p class="muted">Saker der kunden ba Søren om et menneske. Du ser hele samtalen med Søren først, så slipper kunden å forklare på nytt. Nye saker og meldinger kommer inn automatisk.</p>
<?php if (!$tickets): ?><div class="empty"><div class="empty-mascot" data-soren="lumber"></div><p class="muted">Ingen åpne saker.</p></div><?php endif ?>
<div class="tickets" id="tickets">
<?php foreach ($tickets as $t): ?>
  <article class="ticket" data-ticket="<?= (int) $t['id'] ?>">
    <header><b>Sak #<?= (int) $t['id'] ?></b> <span class="muted"><?= h($t['customer'] ?? 'Gjest') ?>, <?= h($t['status']) ?></span>
      <form method="post" action="/support/<?= (int) $t['id'] ?>/lukk"><?= csrf_field() ?><button class="link">Lukk sak</button></form></header>
    <p class="small"><?= h($t['summary']) ?></p>
    <details><summary>Samtalen med Søren</summary>
      <?php foreach (json_decode((string) $t['chat_log'], true) ?: [] as $m): ?>
        <p class="small"><b><?= ($m['role'] ?? '') === 'user' ? 'Kunde' : 'Søren' ?>:</b> <?= h($m['content'] ?? '') ?></p>
      <?php endforeach ?>
    </details>
    <div class="thread">
      <?php foreach ($msgs[$t['id']] ?? [] as $m): ?><p class="msg <?= $m['sender'] === 'kunde' ? 'bot' : 'me' ?>"><?= h($m['body']) ?></p><?php endforeach ?>
    </div>
    <form class="reply" data-reply="<?= (int) $t['id'] ?>"><input placeholder="Svar kunden" required><button class="btn small">Send</button></form>
  </article>
<?php endforeach ?>
</div>
<script>
(() => {
  const csrf = document.querySelector('meta[name=csrf]').content;
  const ids = [...document.querySelectorAll('[data-ticket]')].map((t) => 'ticket:' + t.dataset.ticket);
  const es = new EventSource('/api/stream?ch=' + ['support', ...ids].join(','));
  es.addEventListener('message', (e) => {
    const d = JSON.parse(e.data);
    if (d.channel === 'support') { location.reload(); return; }
    const t = document.querySelector(`[data-ticket="${d.channel.split(':')[1]}"] .thread`);
    if (t) { const p = document.createElement('p'); p.className = 'msg ' + (d.from === 'kunde' ? 'bot' : 'me'); p.textContent = d.body; t.append(p); }
  });
  document.querySelectorAll('[data-reply]').forEach((f) => f.addEventListener('submit', async (e) => {
    e.preventDefault(); const i = f.querySelector('input');
    await fetch('/api/support/' + f.dataset.reply, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf }, body: JSON.stringify({ body: i.value }) });
    i.value = '';
  }));
})();
</script>
