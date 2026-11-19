<?php /** @var array $revs @var array $keys @var array $gaps @var array $reports */ ?>
<h1>Flettekø <small class="muted"><?= count($revs) ?> endringer venter</small></h1>
<p><a class="btn small" href="/ansatt/tarn"><?= icon('warehouse') ?>Kontrolltårn</a></p>
<p class="muted">Endringer fra verifiserte kjøpere i den åpne versjonen. Flett for å flytte verdien til verifisert versjon med kilde «Ansatt», eller avvis for å fjerne den.</p>
<div id="live-note" class="note" hidden></div>
<?php if (!$revs): ?><div class="empty"><div class="empty-mascot" data-soren="lumber"></div><p class="muted">Ingen endringer venter.</p></div><?php endif ?>
<?php foreach ($revs as $r): ?>
  <article class="rev">
    <header><a href="/produkt/<?= h($r['slug']) ?>?versjon=apen#spesifikasjoner"><b><?= h($r['product']) ?></b></a>
      <small class="muted"><?= person($r['author'] ?? 'Ukjent') ?>, <?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></small></header>
    <table class="specs">
      <?php foreach (json_decode($r['diff'], true) as $d): ?>
        <tr><th><?= h($keys[$d['key']]['label'] ?? $d['key']) ?></th><td><s class="muted"><?= h($d['from'] ?? 'ukjent') ?></s> → <b><?= h($d['to']) ?></b> <?= h($keys[$d['key']]['unit'] ?? '') ?></td></tr>
      <?php endforeach ?>
    </table>
    <?php if ($r['comment']): ?><p class="small">Kilde fra kjøper: <?= h($r['comment']) ?></p><?php endif ?>
    <form method="post" action="/ansatt/revisjon/<?= (int) $r['id'] ?>" class="rev-actions">
      <?= csrf_field() ?>
      <button class="btn small" name="action" value="flett">Flett inn i verifisert</button>
      <button class="btn small ghost" name="action" value="avvis">Avvis</button>
    </form>
  </article>
<?php endforeach ?>

<h2 id="meldinger">Meldinger om feil <small class="muted"><?= count($reports) ?> åpne</small></h2>
<p class="muted">Kunder har meldt fra om at en verdi i den åpne versjonen er feil. Avsenderen vises ikke.</p>
<?php if (!$reports): ?><p>Ingen meldinger.</p><?php endif ?>
<div class="reports">
<?php foreach ($reports as $r): ?>
  <article class="rep">
    <header><a href="/produkt/<?= h($r['slug']) ?>?versjon=apen#spesifikasjoner"><b><?= h($r['product']) ?></b></a>
      <small class="muted"><?= (int) $r['n'] ?> <?= $r['n'] > 1 ? 'meldinger' : 'melding' ?>, først <?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></small></header>
    <p><?= h($r['label']) ?>: <b><?= $r['value_text'] !== null ? hc($r['value_text'] . ' ' . $r['unit']) : '<span class="muted">allerede fjernet</span>' ?></b>
      <?= $r['source'] ? source_badge($r['source']) : '' ?></p>
    <blockquote><?= hc($r['reasons']) ?></blockquote>
    <form method="post" action="/ansatt/rapport/<?= (int) $r['id'] ?>" class="rev-actions">
      <?= csrf_field() ?>
      <?php if ($r['value_text'] !== null): ?><button class="btn small" name="action" value="fjern"><?= icon('trash-2') ?>Fjern verdien</button><?php endif ?>
      <button class="btn small ghost" name="action" value="lukk"><?= icon('check') ?>Verdien er riktig</button>
    </form>
  </article>
<?php endforeach ?>
</div>

<h2>Største hull i produktdataene</h2>
<p class="muted">Filtre der flest produkter mangler verdi. Her har kjøpere og ansatte mest å bidra med.</p>
<table class="mini">
  <tr><th>Egenskap</th><th>Mangler</th><th>Av</th></tr>
  <?php foreach ($gaps as $g): ?><tr><td><?= h($g['label']) ?></td><td><?= (int) $g['missing'] ?></td><td><?= (int) $g['total'] ?></td></tr><?php endforeach ?>
</table>
<script>
{
  // Sanntid: nye bidrag dukker opp uten å laste siden på nytt (SSE over hendelsestabellen)
  const escStaff = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  new EventSource('/api/stream?ch=staff').addEventListener('message', (e) => {
    const d = JSON.parse(e.data), n = document.getElementById('live-note');
    n.hidden = false; n.textContent = ''; n.insertAdjacentHTML('beforeend', d.label ? `Ny feilmelding på ${escStaff(d.name)} (${escStaff(d.label)}). ` : `Nytt bidrag fra ${escStaff(d.by)} på ${escStaff(d.name)}. `); n.insertAdjacentHTML('beforeend', '<a href="/ansatt">Last inn på nytt</a>');
  });
}
</script>
