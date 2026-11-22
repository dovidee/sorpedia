<?php
declare(strict_types=1);

// Constrained understanding and the PHP commerce engine (feature 041): the model's reading of a question is validated
// against the customer's own words and the catalogue, and every product answer is written by PHP from the database.
// Read only, except the API test, which writes the rate limit rows inside a transaction that is rolled back.

$id = fn(string $like) => (int) db()->query('SELECT id FROM products WHERE name LIKE ' . db()->quote($like) . ' ORDER BY id LIMIT 1')->fetchColumn();
$h7r = fn() => $id('Ledlenser H7R Core%');
$fokus = fn() => $id('Osean Fokus 7000%');
$none = fn() => PageContext::resolve(null);
$product = fn() => PageContext::resolve('/produkt/ledlenser-h7r-core-1000-lumen-oppladbar-hodelykt');
// The whole server path for one question, with a given reading (as if the model had returned $raw)
$answer = function (string $q, ?array $raw = null, bool $onPage = false) use ($none, $product, $h7r): array {
    $page = $onPage ? $product() : $none();
    $found = ProductRetriever::forChat([['role' => 'user', 'content' => $q]], $page);
    if ($onPage) {
        $g = PublicApi::grounding($h7r(), $found);
        $found['ids'] = $g['ids'];
        $found['cards'] = $g['cards'];
    }
    $u = $raw === null ? Understand::read($q, false) : Understand::validate($raw, $q);
    return ShopAnswer::answer($q, $u, $found, $page, $onPage ? $h7r() : null) + ['u' => $u];
};
$raw = fn(array $over) => $over + ['intent' => 'finn_produkt', 'produkttype' => null, 'krav' => [], 'budsjett_kr' => null, 'mangler' => [], 'trenger_avklaring' => false];
// Every product a server text names must be one of its own IDs, and nothing is left unresolved
$clean = function (array $r, string $label) {
    $text = ProductRef::resolve($r['text']);
    check(!preg_match('/\[|\]|\{|\}/u', $text), "$label: no reference or bracket left: $text");
    foreach (db()->query('SELECT id, name FROM products')->fetchAll(PDO::FETCH_KEY_PAIR) as $pid => $name) {
        if (str_contains($text, FactAnswer::name((int) $pid, [(int) $pid]) . ':') && !in_array((int) $pid, $r['ids'], true)) {
            throw new RuntimeException("$label: names product $pid without listing it");
        }
    }
};

return [
    'a product type the customer did not write, or that the catalogue does not have, is dropped' => function () use ($raw) {
        $u = Understand::validate($raw(['produkttype' => 'Pentax K-5000']), 'Hva er best til fjellet?');
        check($u['type'] === null && in_array('produkttype:pentax k-5000', $u['dropped'], true), 'an invented product: ' . json_encode($u['dropped']));
        $u = Understand::validate($raw(['produkttype' => 'hodelykt']), 'Hva anbefaler du til fjellturen?');
        check($u['type'] === null, 'a type the customer never wrote is not taken from the model');
        $u = Understand::validate($raw(['produkttype' => 'hodelykt']), 'Hvilken av hodelyktene anbefaler du?');
        check($u['type'] !== null && $u['type'][1] === 'hodelykt', 'the customer\'s own type word is kept');
        $u = Understand::validate($raw(['produkttype' => 'headlamp']), 'Which headlamp do you recommend?');
        check($u['type'] !== null && $u['type'][1] === 'hodelykt', 'English through the search translation');
    },

    'requirements and budgets must be in the message, in the direction the customer wrote them' => function () use ($raw) {
        $q = 'Jeg vil ha en hodelykt med minst 500 lumen under 1000 kr';
        $u = Understand::validate($raw(['produkttype' => 'hodelykt', 'budsjett_kr' => 1000, 'krav' => [
            ['egenskap' => 'lysstyrke_lm', 'min' => 500, 'max' => null, 'verdi' => null],
            ['egenskap' => 'vekt_g', 'min' => null, 'max' => 150, 'verdi' => null]]]), $q);
        check($u['budget_ore'] === 100000, 'the budget the customer wrote');
        check(count($u['requirements']) === 1 && $u['requirements'][0]['key'] === 'lysstyrke_lm' && $u['requirements'][0]['min'] === 500.0, 'only the written requirement: ' . json_encode($u['requirements']));
        check(in_array('krav:vekt_g:tall', $u['dropped'], true), 'a weight the customer never wrote is dropped');
        $u = Understand::validate($raw(['krav' => [['egenskap' => 'lysstyrke_lm', 'min' => null, 'max' => 500, 'verdi' => null]]]), $q);
        check(in_array('krav:lysstyrke_lm:tall', $u['dropped'], true) && $u['requirements'] === [['key' => 'lysstyrke_lm', 'min' => 500.0, 'max' => null, 'value' => null]],
            '«minst 500» read as a maximum is dropped, and the catalogue\'s own reading is a minimum: ' . json_encode($u['requirements']));
        $u = Understand::validate($raw(['budsjett_kr' => 1500]), 'Hvilken hodelykt er best?');
        check($u['budget_ore'] === null && in_array('budsjett', $u['dropped'], true), 'a budget the customer never wrote');
        $u = Understand::validate($raw(['krav' => [['egenskap' => 'finnes_ikke', 'min' => 1, 'max' => null, 'verdi' => null]]]), $q);
        check(!in_array('finnes_ikke', array_column($u['requirements'], 'key'), true) && in_array('krav:ukjent_nokkel', $u['dropped'], true), 'a key outside spec_keys');
        $u = Understand::validate($raw(['krav' => [['egenskap' => 'bruksomrade', 'min' => null, 'max' => null, 'verdi' => 'fjell'],
            ['egenskap' => 'bruksomrade', 'min' => null, 'max' => null, 'verdi' => 'sjø']]]), 'en lykt til fjell eller sjø');
        check(in_array('krav:motstridende', $u['dropped'], true), 'two model readings of one property are both dropped');
    },

    'cart and order intents need the customer\'s own words' => function () use ($raw) {
        check(Understand::validate($raw(['intent' => 'handlekurv']), 'Jeg trenger en vanntett hodelykt')['intent'] === 'uklart', 'no cart word');
        check(Understand::validate($raw(['intent' => 'ordre']), 'Hei der')['intent'] === 'uklart', 'no order word');
        check(Understand::validate($raw(['intent' => 'ordre']), 'Hvor er bestillingen min?')['intent'] === 'ordre', 'an order question');
    },

    'the schema offers only filterable keys from the database' => function () {
        $enum = Understand::schema()['properties']['krav']['items']['properties']['egenskap']['enum'];
        $db = db()->query('SELECT `key` FROM spec_keys WHERE is_facet = 1')->fetchAll(PDO::FETCH_COLUMN);
        check(sort($enum) && sort($db) && $enum === $db, 'enum equals spec_keys where is_facet = 1');
    },

    'a headlamp recommendation lists only headlamps, chosen by PHP' => function () use ($answer, $raw, $fokus, $clean) {
        foreach (['Hvilken av dere sine hodelykter anbefaler du til fjellturer?' => null,
            'Which headlamp do you recommend for hiking?' => null,
            'Hvilken hodelykt anbefaler du?' => $raw(['produkttype' => 'hodelykt'])] as $q => $r0) {
            $r = $answer($q, $r0);
            check($r['kind'] === 'anbefaling' && $r['ids'], "$q: a recommendation: " . json_encode($r['kind']));
            check(!in_array($fokus(), $r['ids'], true), "$q: no flashlight");
            foreach ($r['ids'] as $pid) {
                check(str_contains(mb_strtolower((string) db()->query("SELECT name FROM products WHERE id = $pid")->fetchColumn()), 'hodelykt'), "$q: $pid is a headlamp");
            }
            $clean($r, $q);
        }
    },

    'requirements filter on verified values; unknown is said as not registered' => function () use ($answer, $h7r) {
        $q = 'Jeg trenger en vanntett hodelykt med minst 500 lumen under 1000 kr';
        check(FactAnswer::answer($q, [$h7r()], [], $h7r(), null) === null, 'a search with limits is not a question about the product on the page');
        $r = $answer($q);
        check($r['ids'] !== [], 'something matches');
        foreach ($r['ids'] as $pid) {
            check((int) db()->query("SELECT price_ore FROM products WHERE id = $pid")->fetchColumn() <= 100000, "$pid within the budget");
            check(ShopAnswer::meets($pid, ['key' => 'lysstyrke_lm', 'min' => 500.0, 'max' => null, 'value' => null]) === true, "$pid has at least 500 lm verified");
            check(ShopAnswer::meets($pid, ['key' => 'ip_klasse', 'min' => null, 'max' => null, 'value' => '*']) === true, "$pid has a verified IP rating");
        }
        $t = ProductRef::resolve(ShopAnswer::line($id = (int) db()->query("SELECT id FROM products WHERE name LIKE 'Ledlenser HF8R Core%'")->fetchColumn(), ['ip_klasse']));
        check(str_contains($t, 'Vanntetthet (IP) er ikke oppgitt') && !str_contains($t, ';'), 'HF8R Core has no IP rating in the catalogue, and the text says so in a sentence: ' . $t);
    },

    'a how-to question about the product is answered from the database, with the documented misfit' => function () use ($answer, $clean) {
        $r = $answer('Hvordan lader jeg den?', null, true);
        $t = ProductRef::resolve($r['text']);
        check(str_starts_with($r['kind'], 'fakta:') && str_contains($t, 'Magnetisk ladekabel') && str_contains($t, 'MH-Serie'), 'charging method and misfit: ' . $t);
        check(!str_contains(mb_strtolower($t), 'usb-c'), 'no USB-C claim for H7R Core');
        $clean($r, 'how-to');
    },

    'small talk gets a fixed reply, and only without products' => function () use ($answer, $raw) {
        check($answer('Hei, hvordan går det?')['kind'] === 'smaprat', 'a greeting');
        check($answer('Hei, har dere hodelykter?', $raw(['intent' => 'smaprat']))['kind'] !== 'smaprat', 'a product question labelled small talk by the model is not small talk');
        check($answer('Hei, hvordan går det?', null, true)['kind'] === 'smaprat', 'a greeting on a product page is still small talk');
        check($answer('Er H5R Core bra?', $raw(['intent' => 'smaprat']), true)['kind'] !== 'smaprat', 'a question that names a product is not small talk');
    },

    'a question with nothing to go on gets a question back, not a guess' => function () use ($answer) {
        $r = $answer('asdf qwerty');
        check($r['kind'] === 'avklaring' && $r['ids'] === [] && str_contains($r['text'], 'Hva slags vare'), 'a clarification: ' . $r['text']);
    },

    'order questions without login point to login and customer service' => function () use ($answer) {
        $r = $answer('Hvor er bestillingen min?');
        check($r['kind'] === 'ordre' && str_contains($r['text'], 'Logg inn') && str_contains($r['text'], 'kundeservice'), $r['text']);
    },
    'a cart or order word decides the intent, also when the model reads it as something else' => function () use ($raw) {
        check(Understand::validate($raw(['intent' => 'smaprat']), 'Hva ligger i handlekurven min?')['intent'] === 'handlekurv', 'cart');
        check(Understand::validate($raw(['intent' => 'finn_produkt']), 'Hvor er bestillingen min?')['intent'] === 'ordre', 'order');
    },

    'a value no product of the type has is not used to filter, and the answer does not list it' => function () use ($answer, $raw) {
        $r = $answer('Which headlamp do you recommend for hiking?', $raw(['produkttype' => 'headlamp', 'krav' => [['egenskap' => 'bruksomrade', 'min' => null, 'max' => null, 'verdi' => 'hiking']]]));
        check($r['kind'] === 'anbefaling' && count($r['ids']) > 0, 'headlamps are still listed: ' . $r['text']);
        check(!str_contains($r['text'], 'ikke brukt til å velge') && !str_contains($r['text'], 'not used to choose'), 'no note about the unused requirement: ' . $r['text']);
    },

    '«den» after several products asks which one; on a product page it is the page\'s product' => function () use ($h7r, $none) {
        $hf8r = (int) db()->query("SELECT id FROM products WHERE name LIKE 'Ledlenser HF8R Core%' ORDER BY id LIMIT 1")->fetchColumn();
        $found = ['ids' => [$h7r(), $hf8r], 'cards' => [$h7r(), $hf8r], 'why' => [$h7r() => ['signals' => ['history' => 1]], $hf8r => ['signals' => ['history' => 1]]], 'intent' => null];
        $q = 'Hvordan lader jeg den?';
        $r = ShopAnswer::answer($q, Understand::read($q, false), $found, $none());
        check($r['kind'] === 'avklaring' && str_contains($r['text'], 'Hvilken av dem') && count($r['ids']) === 2, 'asks which: ' . $r['text']);
        $found['why'][$h7r()]['signals']['page'] = 1;
        $r = ShopAnswer::answer($q, Understand::read($q, false), $found, $none());
        check($r['ids'] === [$h7r()] && str_contains(ProductRef::resolve($r['text']), 'Magnetisk ladekabel'), 'the page product only: ' . json_encode($r['ids']));
    },
    'a number counts for a property only next to its unit' => function () use ($raw) {
        $u = Understand::validate($raw(['krav' => [['egenskap' => 'lysstyrke_lm', 'min' => 500, 'max' => 1000, 'verdi' => '500']]]), 'Jeg trenger en hodelykt med minst 500 lumen under 1000 kr');
        check(in_array('krav:lysstyrke_lm:enhet', $u['dropped'], true), 'kroner read as lumen is dropped');
        check($u['requirements'] === [['key' => 'lysstyrke_lm', 'min' => 500.0, 'max' => null, 'value' => null]] && $u['budget_ore'] === 100000,
            'the catalogue reads 500 lumen and a budget of 1000 kr: ' . json_encode($u['requirements']));
    },
];
