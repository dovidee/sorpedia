<?php
declare(strict_types=1);

// Checks the app against the golden dataset in evals/golden.json. Run: php evals/run.php

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/helpers.php';
$_SESSION = [];

$g = json_decode((string) file_get_contents(__DIR__ . '/golden.json'), true, 512, JSON_THROW_ON_ERROR);
$n = 0;
$failed = 0;
$case = function (string $feature, string $name, bool $ok, string $why = '') use (&$n, &$failed): void {
    $n++;
    if ($ok) {
        echo "ok   $feature: $name\n";
    } else {
        $failed++;
        echo "FAIL $feature: $name" . ($why !== '' ? ": $why" : '') . "\n";
    }
};
$one = fn(string $sql) => (int) db()->query($sql)->fetchColumn();

// 001, 002: the catalog
$c = $g['catalog'];
$case($c['feature'], "{$c['products']} products", $one('SELECT COUNT(*) FROM products') === $c['products'], 'database has ' . $one('SELECT COUNT(*) FROM products'));
$case($c['feature'], "{$c['themes']} themes", $one('SELECT COUNT(DISTINCT theme) FROM products') === $c['themes'], 'database has ' . $one('SELECT COUNT(DISTINCT theme) FROM products'));
$case($c['feature'], "{$c['stores']} stores", $one('SELECT COUNT(*) FROM warehouses') === $c['stores'], 'database has ' . $one('SELECT COUNT(*) FROM warehouses'));
if ($c['every_sortre_image_is_local']) {
    // Every image Sør-Tre has for a product is stored locally. Products Sør-Tre has no image for show "Ingen bilder fra Sør-Tre".
    $raw = json_decode((string) file_get_contents(__DIR__ . '/../data/raw/products.json'), true, 512, JSON_THROW_ON_ERROR);
    $hasRaw = [];
    foreach ($raw as $p) {
        $hasRaw[$p['slug']] = !empty($p['images']);
    }
    $missing = [];
    $local = db()->query("SELECT p.slug, MAX(i.path_m) path_m FROM products p LEFT JOIN product_images i ON i.product_id = p.id AND i.kind = 'sortre' GROUP BY p.id")->fetchAll();
    foreach ($local as $r) {
        if (($hasRaw[$r['slug']] ?? false) && ($r['path_m'] === null || !is_file(__DIR__ . '/../public/' . $r['path_m']))) {
            $missing[] = $r['slug'];
        }
    }
    $case($c['feature'], 'every image from Sør-Tre is stored locally', !$missing, 'missing: ' . implode(', ', $missing));
}

// 004: search
foreach ($g['search'] as $s) {
    $ids = Search::run($s['query'], 5)['ids'];
    $first = $ids ? (Catalog::cards([$ids[0]])[0]['name'] ?? '') : '';
    $case($s['feature'], "\"{$s['query']}\" finds {$s['first_hit_contains']}", str_contains(mb_strtolower($first), $s['first_hit_contains']),
        $first === '' ? 'no hits' : "first hit is \"$first\"");
}

// 003: the product guide gives at most three suggestions
$gd = $g['guide'];
foreach ($gd['tasks'] as $task) {
    $picks = count(Guide::recommend(['oppgave' => $task])['picks']);
    $case($gd['feature'], "guide \"$task\" gives at most {$gd['max_suggestions']} suggestions", $picks <= $gd['max_suggestions'], "gave $picks");
}

// 011: documented "goes well with" and "does not fit" pairs keep their kind and level
$rel = db()->prepare('SELECT r.kind, r.level FROM relations r JOIN products a ON a.id = r.product_a JOIN products b ON b.id = r.product_b WHERE a.slug = ? AND b.slug = ?');
foreach ($g['documented_relations']['pairs'] as $p) {
    $rel->execute([$p['a'], $p['b']]);
    $got = $rel->fetch() ?: null;
    $case($g['documented_relations']['feature'], "{$p['a']} {$p['kind']} {$p['b']}", $got !== null && $got['kind'] === $p['kind'] && $got['level'] === $p['level'],
        $got ? "is {$got['kind']} {$got['level']}" : 'relation missing');
}

// 015: every chat message goes through the fixed safety checks first; casual messages must reach the model
$soren = json_decode((string) file_get_contents(__DIR__ . '/soren.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($soren['cases'] as $sc) {
    $got = Chat::blocked($sc['message'])[0] ?? null;
    $case($soren['feature'], "{$sc['scenario']} gets " . ($sc['fixed'] ?? 'the model'), $got === $sc['fixed'], 'got ' . ($got ?? 'the model'));
}

// 015: the violence check against a labeled set. Precision: of the blocked messages, how many were threats.
// Recall: of the threats, how many were blocked. Each case also fails on its own when it is wrong.
$v = json_decode((string) file_get_contents(__DIR__ . '/violence.json'), true, 512, JSON_THROW_ON_ERROR);
$tp = $fp = $fn = 0;
foreach ($v['cases'] as $vc) {
    $got = Violence::threat($vc['message']);
    $tp += $got && $vc['threat'] ? 1 : 0;
    $fp += $got && !$vc['threat'] ? 1 : 0;
    $fn += !$got && $vc['threat'] ? 1 : 0;
    $case($v['feature'], "violence ({$vc['group']}, {$vc['lang']}): \"{$vc['message']}\" " . ($vc['threat'] ? 'is blocked' : 'reaches the model'), $got === $vc['threat'],
        $got ? 'false positive' : 'missed threat');
}
printf("violence check: precision %.2f, recall %.2f (%d threats, %d false positives, %d missed)\n",
    $tp + $fp ? $tp / ($tp + $fp) : 1, $tp + $fn ? $tp / ($tp + $fn) : 1, $tp + $fn, $fp, $fn);

echo "$n cases, $failed failed\n";
exit($failed ? 1 : 0);
