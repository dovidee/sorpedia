<?php
declare(strict_types=1);

// Deterministic product answers (feature 039): factual questions are answered by the server from the database, so the
// language model has no chance to state a price, a stock count, a specification or a fit. Read only.

$id = fn(string $like) => (int) db()->query('SELECT id FROM products WHERE name LIKE ' . db()->quote($like) . ' ORDER BY id LIMIT 1')->fetchColumn();
$h7r = fn() => $id('Ledlenser H7R Core%');
$h5r = fn() => $id('Ledlenser H5R Core%');
$mh = fn() => $id('%MH-Serie%');
// The public API path: the endpoint's product first, then what the retriever found
$api = function (string $q) use ($h7r): ?array {
    $found = ProductRetriever::forChat([['role' => 'user', 'content' => $q]], PageContext::resolve('/produkt/ledlenser-h7r-core-1000-lumen-oppladbar-hodelykt'));
    return FactAnswer::answer($q, PublicApi::grounding($h7r(), $found)['ids'], $found['why'], $h7r(), $found['intent']);
};
// The shop chat with no product page
$chat = function (string $q): ?array {
    $found = ProductRetriever::forChat([['role' => 'user', 'content' => $q]], PageContext::resolve(null));
    return FactAnswer::answer($q, $found['ids'], $found['why'], null, $found['intent']);
};
$shown = fn(?array $a) => $a === null ? '' : ProductRef::resolve($a['text']);
// What every server answer must avoid: internal IDs, references left unresolved, empty bold, en or em dashes from the template
$clean = function (string $text, string $label) {
    check(!preg_match('/\b29\d{4}\b|\[\[|\*\*\s*\*\*|\(\s*\)/u', $text), "$label: no internal ID, reference or empty mark: $text");
};

return [
    'H7R Core weight and stock: the weight from Sør-Tre\'s text, not shown as a checked value, and stock from the database' => function () use ($api, $shown, $h7r, $clean) {
        $a = $api('Hva veier den, og har dere den på lager?');
        check($a !== null && $a['kind'] === 'vekt+lager', 'answered by the server: ' . json_encode($a));
        $t = $shown($a);
        check(str_contains($t, '250 gram') && str_contains($t, 'ikke registrert som kontrollert'), 'the weight with its level: ' . $t);
        $n = (int) db()->query("SELECT in_store FROM stock WHERE product_id = {$h7r()} AND warehouse_id = " . warehouse_id())->fetchColumn();
        check(str_contains($t, "($n i butikk)"), "the stock count is the database's $n: $t");
        $clean($t, 'weight');
    },

    'MH cable and H7R Core: the documented «passer ikke» is the answer' => function () use ($api, $chat, $shown, $mh, $clean) {
        foreach ([$api('Passer MH-ladekabelen til denne lykten?'), $chat('Passer MH-ladekabelen til H7R Core?')] as $a) {
            $t = $shown($a);
            check(str_starts_with($t, 'Nei,') && str_contains($t, 'passer ikke til Ledlenser H7R Core') && str_contains($t, 'dokumentert'), $t);
            check(in_array($mh(), $a['ids'], true), 'the cable is among the products named');
            $clean($t, 'MH');
        }
    },

    'cheapest headlamp: only headlamps, ranked by price, never a hook, a bracket or a battery pack' => function () use ($api, $chat, $shown) {
        foreach ([$api('Hva er den billigste hodelykten?'), $chat('Hva er den billigste hodelykten?'), $chat('What is the cheapest headlamp?')] as $a) {
            check($a !== null && str_starts_with($a['kind'], 'katalog:billigst'), 'a catalogue answer');
            $names = db()->query('SELECT name, price_ore FROM products WHERE id IN (' . implode(',', $a['ids']) . ') ORDER BY FIELD(id, ' . implode(',', $a['ids']) . ')')->fetchAll();
            foreach ($names as $p) {
                check(stripos($p['name'], 'hodelykt') !== false && !preg_match('/\b(for|til)\b.*hodelykt/iu', $p['name']), 'a headlamp itself: ' . $p['name']);
            }
            $prices = array_map(fn($p) => (int) $p['price_ore'], $names);
            $sorted = $prices;
            sort($sorted);
            check($prices === $sorted, 'cheapest first');
            check(!preg_match('/S-krok|Vinkel/u', $shown($a)), $shown($a));
        }
    },

    'H5R Core against H7R Core: prices from the database, the difference worked out, no internal ID' => function () use ($api, $shown, $h5r, $h7r, $clean) {
        $a = $api('Er H5R Core billigere enn denne?');
        $t = $shown($a);
        $p = db()->query("SELECT id, price_ore FROM products WHERE id IN ({$h7r()}, {$h5r()})")->fetchAll(PDO::FETCH_KEY_PAIR);
        check(str_contains($t, kr((int) $p[$h7r()])) && str_contains($t, kr((int) $p[$h5r()])) && str_contains($t, kr(abs((int) $p[$h7r()] - (int) $p[$h5r()])) . ' billigere'), $t);
        $clean($t, 'H5R price');
        $d = $shown($api('Hva er forskjellen på denne og H5R Core?'));
        check(str_contains($d, 'Lysstyrke: 1 000 lm / 500 lm'), 'the difference on actual fields: ' . $d);
        $clean($d, 'H5R difference');
    },

    'ML4 and H7R Core: no relation, so the answer is unknown, said once, without a yes or a no' => function () use ($api, $shown, $clean) {
        $t = $shown($api('Passer laderen til ML4 campinglykten til denne?'));
        check(str_contains($t, 'har ikke registrert om') && str_contains($t, 'kan jeg ikke bekrefte'), $t);
        check(!preg_match('/\b(Ja|Nei)\b|passer ikke til|passer fint/u', $t), 'no contradiction, no verdict: ' . $t);
        $clean($t, 'ML4');
    },

    'a specification the catalogue lacks is unknown, not guessed' => function () use ($api, $shown, $clean) {
        $t = $shown($api('Hvor lenge lyser den på full styrke?'));
        check(str_contains($t, 'står ikke i katalogen') && !preg_match('/\d+\s*(t|timer|h|min)\b/u', $t), $t);
        $clean($t, 'missing');
    },

    'a verified specification carries its source' => function () use ($api, $shown) {
        check($shown($api('Er den vanntett?')) === 'Ledlenser H7R Core: Vanntetthet (IP) er IP67 (oppgitt av Sør-Tre).', $shown($api('Er den vanntett?')));
    },

    'stock for a named store is that store\'s row' => function () use ($api, $shown, $h7r) {
        $n = (int) db()->query("SELECT s.in_store FROM stock s JOIN warehouses w ON w.id = s.warehouse_id WHERE s.product_id = {$h7r()} AND w.name = 'Bergen'")->fetchColumn();
        $t = $shown($api('Har dere den i Bergen?'));
        check(str_contains($t, 'Bergen') && ($n > 0 ? str_contains($t, "($n i butikk)") : str_contains($t, 'ikke')), $t);
    },

    'advice, explanations and questions without a known product go to the model' => function () use ($api, $chat) {
        foreach (['Hvorfor trenger jeg et ekstra batteri?', 'Hvilken hodelykt anbefaler du til fjellet?', 'Har dere en kraftig hodelykt?'] as $q) {
            check($chat($q) === null, $q);
        }
        check($api('Hvordan lader jeg den?') === null, 'how-to on the API goes to the model');
    },

    'the answer is the same sentence each time' => function () use ($api, $shown) {
        $q = 'Hva veier den, og har dere den på lager?';
        check($shown($api($q)) === $shown($api($q)), 'deterministic');
    },
];
