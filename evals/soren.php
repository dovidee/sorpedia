<?php
declare(strict_types=1);

// Runs the model cases in evals/soren.json against the configured Ollama model (feature 015).
// Checks the deterministic criteria, measures prompt size and latency, and writes every answer with its review note
// to a Markdown file for a person to read. Friendliness and humor are judged by that person, not by another model.
// Exit code: 0 all checks passed, 1 a check failed, 2 Ollama was not reachable (nothing was tested).
// Run: php evals/soren.php [scenario ...]

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/helpers.php';
$_SESSION = [];

$g = json_decode((string) file_get_contents(__DIR__ . '/soren.json'), true, 512, JSON_THROW_ON_ERROR);
$only = array_slice($argv, 1);
$cases = array_values(array_filter($g['cases'], fn($c) => $c['fixed'] === null && (!$only || in_array($c['scenario'], $only, true))));

$c = config();
$ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
if (!is_array(json_decode((string) @file_get_contents($c['ollama_url'] . '/api/tags', false, $ctx), true))) {
    echo "NOT RUN: Ollama is not reachable at {$c['ollama_url']}. " . count($cases) . " model cases were not tested.\n";
    exit(2);
}

$refusal = '/\b(?:utenfor (?:mitt|ditt) felt|kan (?:dessverre )?ikke hjelpe|kan bare hjelpe|I can only help|outside my)\b/iu';
$aiApology = '/\b(?:som en (?:ai|kunstig intelligens|språkmodell)|jeg er (?:bare )?en (?:ai|språkmodell|chatbot)|as an ai|i am (?:just )?an ai)\b/iu';
$money = '/\d[\d  ]*(?:,\d+)?\s?kr(?!\p{L})/u';
$digits = fn(string $s) => preg_replace('/\D/', '', $s);

$failed = 0;
$md = ["# Søren conversation eval", '', 'Model: ' . $c['ollama_model'] . '. Date: ' . date('Y-m-d H:i') . '.', ''];
foreach ($cases as $sc) {
    $page = PageContext::resolve(($sc['page'] ?? '') === 'product' ? $g['product_page'] : null);
    $messages = [['role' => 'user', 'content' => $sc['message']]];
    Chat::$lang = I18n::$force = Lang::question($sc['message'], 'no');
    $found = ProductRetriever::forChat($messages, $page);
    $intent = $found['intent'];
    $ids = $found['ids'];
    $facts = ($intent['facts'] ?? '') . "\n" . Chat::facts($ids);
    $promptChars = mb_strlen(json_encode(Chat::prompt($messages, $page, $facts), JSON_UNESCAPED_UNICODE));

    $t0 = microtime(true);
    $first = null;
    $answer = '';
    [$ok, $stored] = Chat::answer($messages, $page, $ids, function (string $ev, mixed $data) use (&$first, &$answer, $t0) {
        if (in_array($ev, ['think', 'token'], true) && $first === null) {
            $first = microtime(true) - $t0;
        }
        if ($ev === 'token') {
            $answer .= $data;
        }
    }, 'rask', $intent['facts'] ?? '');
    $total = microtime(true) - $t0;

    $prices = array_map(fn($id) => $digits(ProductRef::resolve(ProductRef::price($id))), ProductRef::allowed($facts . "\n" . $page['text']));
    preg_match_all($money, $answer, $amounts);
    $wrong = array_values(array_filter($amounts[0], fn($a) => !in_array($digits($a), $prices, true)));
    $checks = [
        'not empty' => $ok && trim($answer) !== '',
        'no profanity' => Moderation::clean($answer),
        'no repetition' => !Chat::looping($answer),
        'amounts from the database' => !$wrong,
        'no AI apology' => !preg_match($aiApology, $answer),
    ];
    if (!empty($sc['no_refusal'])) {
        $checks['does not decline'] = !preg_match($refusal, $answer);
    }
    $bad = array_keys(array_filter($checks, fn($v) => !$v));
    $failed += $bad ? 1 : 0;
    $timing = sprintf('first event %.1f s, total %.1f s, prompt %d characters', $first ?? -1, $total, $promptChars);
    echo ($bad ? 'FAIL' : 'ok  ') . " {$sc['scenario']}: $timing" . ($bad ? ' (' . implode(', ', $bad) . ($wrong ? ': ' . implode(', ', $wrong) : '') . ')' : '') . "\n";
    $md[] = "## {$sc['scenario']}";
    $md[] = '';
    $md[] = "Customer: {$sc['message']}" . (($sc['page'] ?? '') === 'product' ? " (on {$g['product_page']})" : '');
    $md[] = '';
    $md[] = 'Søren: ' . ($answer !== '' ? $answer : '(no answer: ' . (Chat::$lastError ?? 'unknown') . ')');
    $md[] = '';
    $md[] = "Checks: " . ($bad ? 'failed ' . implode(', ', $bad) : 'all passed') . ". $timing.";
    $md[] = '';
    $md[] = "Review: {$sc['review']}";
    $md[] = '';
}
$out = sys_get_temp_dir() . '/sorpedia-soren-eval-' . date('Ymd-His') . '.md';
file_put_contents($out, implode("\n", $md));
echo count($cases) . " model cases, $failed failed. Answers for review: $out\n";
exit($failed ? 1 : 0);
