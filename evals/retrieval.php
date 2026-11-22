<?php
declare(strict_types=1);

// Product retrieval eval for the chat (feature 034). Read only: runs the cases in evals/retrieval.json against the
// catalogue and reports Recall@5, Precision@5, exact match, follow-ups, compatibility, irrelevant hits, card consistency,
// time and database statements per question.
// Two implementations run on the same questions and the same database: "legacy" is the orchestration from before 034
// (page products, then Search::run, and a separate search for the cards), kept below for comparison; "retriever" is
// ProductRetriever. The expected products are in the JSON file and were set by hand, not by either implementation.
// Run: php evals/retrieval.php [legacy|retriever] [--json]. Exit code 0 always; this is a measurement, not a gate.

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/helpers.php';
$_SESSION = [];

$g = json_decode((string) file_get_contents(__DIR__ . '/retrieval.json'), true, 512, JSON_THROW_ON_ERROR);
$modes = array_values(array_intersect(array_slice($argv, 1), ['legacy', 'retriever'])) ?: (class_exists('ProductRetriever') ? ['legacy', 'retriever'] : ['legacy']);
$asJson = in_array('--json', $argv, true);

/** The orchestration in public/index.php and src/PublicApi.php before feature 034, unchanged. */
$legacy = function (array $messages, array $page, array $history): array {
    $q = Chat::lastUser($messages);
    $intent = ($kind = CatalogIntent::detect($q)) ? CatalogIntent::build($kind, $q, $page) : null;
    if ($intent) {
        return ['ids' => array_slice($intent['ids'], 0, 3), 'cards' => $intent['ids']];
    }
    $search = Search::run($q, 6, false, true)['ids'];
    $ids = array_slice(array_values(array_unique(array_merge($page['ids'], $search))), 0, 5);
    $cardSearch = Search::run($q, 4, false, true)['ids'];
    $cards = array_slice(array_values(array_unique($cardSearch ?: $page['ids'])), 0, 4);
    return ['ids' => $ids, 'cards' => $cards];
};

$retriever = function (array $messages, array $page, array $history): array {
    $r = ProductRetriever::forChat($messages, $page, $history);
    return ['ids' => $r['ids'], 'cards' => $r['cards']];
};

$names = [];
foreach (db()->query('SELECT id, name FROM products') as $p) {
    $names[(int) $p['id']] = (string) $p['name'];
}
$statements = fn() => (int) db()->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch()['Value'];

$report = [];
foreach ($modes as $mode) {
    $run = $mode === 'legacy' ? $legacy : $retriever;
    $rows = [];
    $sum = ['recall' => 0.0, 'precision' => 0.0, 'n' => 0, 'first' => 0, 'firstN' => 0, 'follow' => 0, 'followN' => 0, 'compat' => 0, 'compatN' => 0,
        'returned' => 0, 'irrelevant' => 0, 'consistent' => 0, 'ms' => 0.0, 'queries' => 0];
    foreach ($g['cases'] as $c) {
        $page = PageContext::resolve($c['page']);
        $messages = [];
        if (!empty($c['previous'])) {
            $messages[] = ['role' => 'user', 'content' => $c['previous']];
            $messages[] = ['role' => 'assistant', 'content' => 'Svar om ' . implode(' og ', array_map(fn($id) => $names[$id] ?? (string) $id, $c['history']))];
        }
        $messages[] = ['role' => 'user', 'content' => $c['question']];
        $q0 = $statements();
        $t0 = microtime(true);
        $out = $run($messages, $page, $c['history']);
        $ms = (microtime(true) - $t0) * 1000;
        $queries = $statements() - $q0 - 1;

        $top = array_slice(array_values(array_unique(array_map('intval', $out['ids']))), 0, 5);
        $expect = array_map('intval', $c['expect']);
        $ok = array_merge($expect, array_map('intval', $c['allowed'] ?? []));
        $like = $c['allowed_like'] ?? null;
        $fine = fn(int $id) => in_array($id, $ok, true) || ($like !== null && preg_match('/' . $like . '/iu', $names[$id] ?? ''));
        $good = array_values(array_filter($top, $fine));
        $bad = array_values(array_diff($top, $good));
        $recall = $expect ? count(array_intersect($expect, $top)) / count($expect) : ($top === [] || isset($c['min_allowed']) ? 1.0 : 0.0);
        if (isset($c['min_allowed'])) {
            $recall = min(1.0, count($good) / $c['min_allowed']);
        }
        $precision = $top ? count($good) / count($top) : ($expect || isset($c['min_allowed']) ? 0.0 : 1.0);
        $cards = array_values(array_unique(array_map('intval', $out['cards'])));
        $consistent = $cards === [] || !array_diff(array_slice($cards, 0, 4), $top) ? 1 : 0;
        $firstOk = isset($c['first']) ? (($top[0] ?? null) === (int) $c['first']) : null;
        $whole = $recall >= 1.0 && !$bad;

        $sum['recall'] += $recall;
        $sum['precision'] += $precision;
        $sum['n']++;
        if ($firstOk !== null) {
            $sum['firstN']++;
            $sum['first'] += $firstOk ? 1 : 0;
        }
        if (!empty($c['followup'])) {
            $sum['followN']++;
            $sum['follow'] += $whole ? 1 : 0;
        }
        if (!empty($c['compat'])) {
            $sum['compatN']++;
            $sum['compat'] += $recall >= 1.0 ? 1 : 0;
        }
        $sum['returned'] += count($top);
        $sum['irrelevant'] += count($bad);
        $sum['consistent'] += $consistent;
        $sum['ms'] += $ms;
        $sum['queries'] += $queries;
        $rows[] = ['id' => $c['id'], 'scenario' => $c['scenario'], 'recall' => round($recall, 2), 'precision' => round($precision, 2), 'first' => $firstOk,
            'ids' => $top, 'irrelevant' => array_map(fn($id) => "$id " . mb_substr($names[$id] ?? '?', 0, 40), $bad), 'cards' => $cards,
            'cards_consistent' => (bool) $consistent, 'ms' => round($ms, 1), 'queries' => $queries];
    }
    $n = max(1, $sum['n']);
    $report[$mode] = ['summary' => [
        'cases' => $sum['n'],
        'recall_at_5' => round($sum['recall'] / $n, 3),
        'precision_at_5' => round($sum['precision'] / $n, 3),
        'exact_match' => "{$sum['first']}/{$sum['firstN']}",
        'followup_resolution' => "{$sum['follow']}/{$sum['followN']}",
        'compatibility' => "{$sum['compat']}/{$sum['compatN']}",
        'irrelevant_hit_rate' => $sum['returned'] ? round($sum['irrelevant'] / $sum['returned'], 3) : 0.0,
        'card_consistency' => "{$sum['consistent']}/{$sum['n']}",
        'ms_per_question' => round($sum['ms'] / $n, 1),
        'queries_per_question' => round($sum['queries'] / $n, 1),
    ], 'cases' => $rows];
}

if ($asJson) {
    echo json_encode(['date' => date('Y-m-d H:i'), 'report' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}
foreach ($report as $mode => $r) {
    echo "== $mode ==\n";
    foreach ($r['cases'] as $row) {
        $mark = $row['recall'] >= 1.0 && !$row['irrelevant'] && $row['first'] !== false ? 'ok  ' : 'MISS';
        echo "$mark {$row['id']}: recall {$row['recall']}, precision {$row['precision']}" . ($row['first'] === null ? '' : ', first ' . ($row['first'] ? 'yes' : 'no'))
            . ($row['irrelevant'] ? ', irrelevant: ' . implode('; ', $row['irrelevant']) : '') . ($row['cards_consistent'] ? '' : ', cards differ') . "\n";
    }
    foreach ($r['summary'] as $k => $v) {
        echo "  $k: $v\n";
    }
}
