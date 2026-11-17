<?php /** @var array $answers */ $answers ??= [];
$asked = Guide::asked($answers);
$next = Guide::next($answers);
$left = isset($answers['oppgave']) ? count(Guide::candidates($answers)) : null;
?>
<div class="guide">
  <div class="guide-head">
    <div><h1><?= t('Produktguiden') ?></h1><p><?= t('Noen raske spørsmål, fra bredt til smalt. Hvert svar bestemmer neste spørsmål.') ?></p></div>
  </div>
  <?php if ($asked): ?>
    <ol class="quiz-done">
      <?php $prev = []; foreach ($asked as [$key, $question, $label]): ?>
        <li><span><?= h(t($question)) ?></span> <b><?= h(t($label)) ?></b>
          <a href="/guide<?= $prev ? '?' . h(http_build_query($prev)) : '' ?>"><?= t('Endre') ?></a></li>
      <?php $prev[$key] = $answers[$key]; endforeach ?>
    </ol>
  <?php endif ?>
  <?php if ($next): [$key, $question, $options] = $next; ?>
    <form method="get" action="/guide" class="quiz" id="quiz">
      <?php foreach ($asked as [$k]): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($answers[$k]) ?>"><?php endforeach ?>
      <fieldset class="q">
        <legend><span class="step"><?= t('Spørsmål {n}', ['n' => count($asked) + 1]) ?><?= $left !== null ? ' · ' . t('{n} produkter passer', ['n' => $left]) : '' ?></span><?= h(t($question)) ?></legend>
        <div class="opts">
          <?php foreach ($options as $v => $label): ?>
            <label class="opt"><input type="radio" name="<?= h($key) ?>" value="<?= h((string) $v) ?>" required><span><?= h(t($label)) ?></span></label>
          <?php endforeach ?>
        </div>
      </fieldset>
      <noscript><button class="btn"><?= t('Neste') ?></button></noscript>
    </form>
  <?php endif ?>
</div>
