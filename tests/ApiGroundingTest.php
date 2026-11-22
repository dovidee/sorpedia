<?php
declare(strict_types=1);

// Public API grounding (feature 038): the product of /api/v1/products/{id}/questions is always in the facts the model gets
// and in the context the answer guard checks against, whatever the retriever finds. Read only.

$id = fn(string $like) => (int) db()->query('SELECT id FROM products WHERE name LIKE ' . db()->quote($like) . ' ORDER BY id LIMIT 1')->fetchColumn();
$h7r = fn() => $id('Ledlenser H7R Core%');
$mh = fn() => $id('%MH-Serie%');
$page = fn() => PageContext::resolve('/produkt/ledlenser-h7r-core-1000-lumen-oppladbar-hodelykt');
// What the endpoint does: the retriever on the question, then the endpoint's product first
$api = function (string $q) use ($h7r, $page): array {
    $found = ProductRetriever::forChat([['role' => 'user', 'content' => $q]], $page());
    return PublicApi::grounding($h7r(), $found);
};

return [
    'the selected product reaches the facts when the retriever finds nothing' => function () use ($h7r, $page) {
        $g = PublicApi::grounding($h7r(), ['ids' => [], 'cards' => []]);
        check($g['ids'] === [$h7r()] && $g['cards'] === [$h7r()], 'the product alone: ' . json_encode($g));
        $facts = Chat::facts($g['ids']);
        check(str_starts_with($facts, "[{$h7r()}] Ledlenser H7R Core"), 'the facts start with the selected product');
        $prompt = Chat::prompt([['role' => 'user', 'content' => 'Hva veier den?']], $page(), $facts);
        check(str_contains(end($prompt)['content'], "produktsiden til [{$h7r()}]"), 'the prompt says which product the customer is asking about');
    },

    'a catalogue-wide question keeps the selected product first, the rest after it' => function () use ($api, $h7r) {
        foreach (['Hva er den billigste hodelykten?', 'Hva er best vurdert?'] as $q) {
            $g = $api($q);
            check(($g['ids'][0] ?? null) === $h7r() && count($g['ids']) > 1, "$q: " . json_encode($g['ids']));
            check(count($g['ids']) <= 5 && count($g['ids']) === count(array_unique($g['ids'])), "$q: at most five, no duplicates");
        }
        $g = $api('Hva koster H5R Core?');
        check($g['ids'][0] === $h7r() && in_array($id = (int) db()->query("SELECT id FROM products WHERE name LIKE 'Ledlenser H5R Core%'")->fetchColumn(), $g['ids'], true), 'a named product is added, not swapped in');
    },

    'H7R Core: verified specifications and stock for a named store' => function () use ($h7r) {
        $facts = Chat::facts([$h7r()]);
        check(str_contains($facts, 'Lysstyrke: 1 000 lm (oppgitt av Sør-Tre)') && str_contains($facts, 'Vanntetthet (IP): IP67 (oppgitt av Sør-Tre)'), 'specifications with their source');
        $store = (string) db()->query('SELECT name FROM warehouses WHERE id = ' . warehouse_id())->fetchColumn();
        check($store !== '' && str_contains($facts, "Lager i varehuset $store: ") && str_contains($facts, ProductRef::stock($h7r())), 'stock is a reference for the named store');
    },

    'MH cable: the documented «passer ikke» is in the facts and in the guard context' => function () use ($api, $h7r, $mh) {
        $g = $api('Passer MH-ladekabelen til denne lykten?');
        check($g['ids'][0] === $h7r(), 'the lamp first');
        $facts = Chat::facts($g['ids']);
        check(str_contains($facts, "PASSER IKKE: [{$mh()}]"), 'the misfit is in the facts with the cable\'s ID');
        $ctx = AnswerGuard::context($g['ids'], $facts);
        check(isset($ctx['notFit']["{$mh()}|{$h7r()}"]), 'the guard knows the pair');
        $out = AnswerGuard::check('MH-Serie ladekabelen passer fint til H7R Core.', $ctx);
        check(str_contains($out, 'passer ikke til') && !str_contains($out, 'passer fint'), 'a fit claim is replaced: ' . $out);
    },

    'a price stays with its own product' => function () use ($api, $h7r) {
        $g = $api('Er H5R Core billigere enn denne?');
        $h5r = (int) db()->query("SELECT id FROM products WHERE name LIKE 'Ledlenser H5R Core%'")->fetchColumn();
        $ctx = AnswerGuard::context($g['ids'], Chat::facts($g['ids']));
        check(AnswerGuard::check("H7R Core koster [[pris:$h5r]].", $ctx) === "H7R Core koster [[pris:{$h7r()}]].", 'the other product\'s reference is moved to the named one');
        $p = (int) db()->query("SELECT price_ore FROM products WHERE id = $h5r")->fetchColumn();
        check(!str_contains(ProductRef::resolve(AnswerGuard::check('H7R Core koster ' . kr($p) . '.', $ctx)), kr($p)), 'the other product\'s amount is not kept');
    },

    'no relation means no fit or misfit is stated, and the guard adds none' => function () use ($h7r, $id) {
        $other = $id('Ledlenser ML4%');
        $n = (int) db()->query("SELECT COUNT(*) FROM relations WHERE kind IN ('passer_sammen','passer_ikke') AND ((product_a = {$h7r()} AND product_b = $other) OR (product_a = $other AND product_b = {$h7r()}))")->fetchColumn();
        check($n === 0, 'H7R Core and ML4 have no fit relation in the catalogue');
        $facts = Chat::facts([$h7r(), $other]);
        check(!preg_match("/(Passer med|PASSER IKKE): \\[$other\\]/", $facts) && !preg_match("/(Passer med|PASSER IKKE): \\[{$h7r()}\\]/", $facts), 'neither is listed as a fit of the other');
        $ctx = AnswerGuard::context([$h7r(), $other], $facts);
        check(!isset($ctx['notFit']["$other|{$h7r()}"]), 'the guard has no misfit to enforce, so the answer is left to say it is unknown');
    },

    'a removed reference leaves no empty bold marks' => function () {
        check(ProductRef::clean('varenr. **[[pris:999999999]]** her', []) === 'varenr.  her', 'empty bold removed');
        check(ProductRef::clean('**H7R Core** er bra', []) === '**H7R Core** er bra', 'real bold kept');
    },
];
