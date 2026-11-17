<?php /** @var array $kpi @var array $zeroTerms @var array $via @var array $coverage @var array $rel @var array $users @var array $contrib */
$pct = fn($a, $b) => $b ? round(100 * $a / $b) . ' %' : 'Ingen data'; ?>
<h1>Måltall</h1>
<p class="muted">Målt i prototypen fra analytics_events og ordrer. Ordrer og anmeldelser fra startdataene er oppdiktet. Søk, guide og chat telles bare når noen bruker siden.</p>
<div class="kpis">
  <div><b><?= $kpi['searches'] ?></b><span>Søk</span></div>
  <div><b><?= $pct($kpi['zero'], $kpi['searches']) ?></b><span>Søk uten treff</span></div>
  <div><b><?= $kpi['guide'] ?></b><span>Fullførte produktguider</span></div>
  <div><b><?= $kpi['chat'] ?></b><span>Spørsmål til Søren</span></div>
  <div><b><?= $pct($kpi['escalate'], $kpi['chat']) ?></b><span>Sendt videre til menneske</span></div>
  <div><b><?= $kpi['compare'] ?></b><span>Sammenligninger</span></div>
</div>
<div class="admin-grid">
  <section><h2>Søkeord uten treff</h2><p class="muted small">Det kundene leter etter som ikke finnes på nett.</p>
    <table class="mini"><?php foreach ($zeroTerms as $z): ?><tr><td><?= h($z['q']) ?></td><td><?= (int) $z['n'] ?></td></tr><?php endforeach ?></table>
    <?php if (!$zeroTerms): ?><p class="muted">Ingen ennå.</p><?php endif ?></section>
  <section><h2>Hvor varer i kurven kom fra</h2><p class="muted small">Tilbehør via «passer sammen» og guiden er målet for P5.</p>
    <table class="mini"><tr><th>Kilde</th><th>Linjer</th><th>Omsetning</th></tr>
      <?php foreach ($via as $v): ?><tr><td><?= h($v['via']) ?></td><td><?= (int) $v['n'] ?></td><td><?= kr((int) $v['ore']) ?></td></tr><?php endforeach ?></table></section>
  <section><h2>Spesifikasjoner per kilde</h2>
    <table class="mini"><tr><th>Versjon</th><th>Kilde</th><th>Verdier</th></tr>
      <?php foreach ($coverage as $c): ?><tr><td><?= h($c['version']) ?></td><td><?= source_badge($c['source']) ?></td><td><?= (int) $c['n'] ?></td></tr><?php endforeach ?></table>
    <p class="small">Bidrag: <?php foreach ($contrib as $s => $n): ?><?= h($s) ?> <?= (int) $n ?>. <?php endforeach ?></p></section>
  <section><h2>Passer sammen</h2>
    <table class="mini"><tr><th>Nivå</th><th>Type</th><th>Antall</th></tr>
      <?php foreach ($rel as $r): ?><tr><td><?= h($r['level']) ?></td><td><?= h($r['kind']) ?></td><td><?= (int) $r['n'] ?></td></tr><?php endforeach ?></table></section>
  <section><h2>Brukere</h2>
    <table class="mini"><tr><th>Rolle</th><th>Antall</th><th>Bekreftet e-post</th></tr>
      <?php foreach ($users as $u): ?><tr><td><?= h($u['role']) ?></td><td><?= (int) $u['n'] ?></td><td><?= (int) $u['verified'] ?></td></tr><?php endforeach ?></table></section>
</div>
