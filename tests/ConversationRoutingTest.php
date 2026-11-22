<?php
declare(strict_types=1);

// Conversation routing for the shop chat (feature 044): conversational messages never reach the catalogue search, a
// product named outright is answered alone, follow-ups use the products shown before and the page, and product text is
// written as sentences. Read only: no Ollama call (Understand::read without the model) and no writes.

$id = fn(string $like) => (int) db()->query('SELECT id FROM products WHERE name LIKE ' . db()->quote($like) . ' ORDER BY id LIMIT 1')->fetchColumn();
// One question through the same steps as the shop chat, without the model
$ask = function (string $q, ?string $page = null, array $history = []): array {
    if ($b = Chat::blocked($q)) {
        return ['route' => 'blokkert', 'text' => $b[1], 'ids' => []];
    }
    if ($quick = QuickReply::match($q, false)) {
        return ['route' => $quick['route'], 'text' => $quick['text'], 'ids' => []];
    }
    $p = PageContext::resolve($page);
    $found = ProductRetriever::forChat([['role' => 'user', 'content' => $q]], $p, $history);
    if ($f = FactAnswer::answer($q, $found['ids'], $found['why'], null, $found['intent'])) {
        return ['route' => 'fakta', 'text' => ProductRef::resolve($f['text']), 'ids' => $f['ids']];
    }
    $r = ShopAnswer::answer($q, Understand::read($q, false), $found, $p);
    return ['route' => $r['kind'], 'text' => ProductRef::resolve($r['text']), 'ids' => $r['ids']];
};
$slug = fn(int $pid) => '/produkt/' . db()->query('SELECT slug FROM products WHERE id = ' . $pid)->fetchColumn();

return [
    'a request for a human is a handoff, in the usual wordings, and does not claim the case exists yet' => function () use ($ask) {
        foreach (['kan jeg snakke med en menneske', 'kan jeg snakke med et menneske', 'jeg vil snakke med kundeservice', 'Kan jeg snakke med en person?',
            'kan jeg snakke med noen', 'jeg vil ha hjelp fra en person', 'snakke med en medarbeider', 'kundeservice'] as $q) {
            $r = $ask($q);
            check($r['route'] === 'menneske' && $r['ids'] === [], "«{$q}» is a handoff");
            check(!str_contains(mb_strtolower($r['text']), 'opprettet'), "«{$q}»: no case is claimed before it exists");
        }
        check($ask('Hodelykt som passer for en person med stort hode')['route'] !== 'menneske', 'the word «person» in a product question is not a handoff');
    },

    'identity, personality, banter, arithmetic, stop and store questions get a short reply with no products' => function () use ($ask) {
        $cases = ['hvem er du' => 'hvem', 'har du personlighet' => 'personlighet', 'du er feit' => 'uhoflig', 'er du?' => 'fyll',
            'hvor er sørtre sin butikk' => 'butikk', 'hvor er sørtres butikk' => 'butikk', 'Hvor ligger butikken?' => 'butikk',
            'når åpner dere' => 'butikk', 'slutt' => 'stopp', 'fortell en vits' => 'vits'];
        foreach ($cases as $q => $route) {
            $r = $ask($q);
            check($r['route'] === $route && $r['ids'] === [], "«{$q}» is $route: {$r['route']}");
        }
        $sum = $ask('1 + 1');
        check($sum['route'] === 'regning' && str_starts_with($sum['text'], '2.'), '1 + 1 is answered: ' . $sum['text']);
        $p = $ask('pikk');
        check($p['route'] === 'blokkert' && $p['ids'] === [] && !str_contains($p['text'], 'banning'), 'profanity gets a neutral redirect: ' . $p['text']);
    },

    'the store reply names the real store from the database and invents no address or opening hours' => function () use ($ask) {
        $t = $ask('hvor er sørtres butikk')['text'];
        $city = (string) db()->query('SELECT city FROM warehouses WHERE is_real = 1 ORDER BY id LIMIT 1')->fetchColumn();
        check(str_contains($t, $city) && !preg_match('/\d{4}|kl\.|\d{1,2}[:.]\d{2}/u', $t), $t);
    },

    'conversation on a product page does not repeat the product' => function () use ($ask, $id, $slug) {
        $page = $slug($id('Øyeskrue 6 x 16%'));
        foreach (['du er feit', 'er du?', '1 + 1', 'kan jeg snakke med en menneske', 'hvor er sørtres butikk'] as $q) {
            $r = $ask($q, $page);
            check($r['ids'] === [] && !str_contains($r['text'], 'Øyeskrue'), "«{$q}» on the product page: " . $r['text']);
        }
    },

    'a genuine product request still reaches the product search' => function () use ($ask) {
        $r = $ask('Jeg trenger en hammer');
        check($r['route'] === 'anbefaling' && $r['ids'] !== [], 'hammer: ' . $r['route']);
        check($ask('anbefal en hodelykt')['route'] === 'anbefaling', 'headlamp recommendation');
    },

    'a product named by its exact title is answered alone, with its title words not used as filters' => function () use ($ask, $id) {
        foreach (['1852 Bryggelys alu LED m/solcelle' => '1852 Bryggelys alu LED%', '1852 Bryggelys/vegglys alu LED m/solcelle' => '1852 Bryggelys/vegglys%',
            'Øyeskrue 6 x 16 x 2.8 MS P6' => 'Øyeskrue 6 x 16%'] as $name => $like) {
            $r = $ask("Fortell meg kort om $name. Hva passer den til, og hva bør jeg tenke på?");
            check($r['ids'] === [$id($like)], "$name alone: " . json_encode($r['ids']));
            check(!str_contains($r['text'], 'Merke') && !str_contains($r['text'], 'ikke registrert') && !str_contains($r['text'], ';'), "$name: no metadata dump: " . $r['text']);
        }
    },

    'the named eye screw gives its verified values and claims no load rating' => function () use ($ask) {
        $t = $ask('Fortell meg kort om Øyeskrue 6 x 16 x 2.8 MS P6')['text'];
        check(str_contains($t, 'diameter 6 mm') && str_contains($t, 'lengde 16 mm') && str_contains(mb_strtolower($t), 'messing'), $t);
        check(str_contains($t, 'ingen arbeidslast') && !preg_match('/\d+\s?kg/u', $t), 'load rating said as not given, none invented: ' . $t);
    },

    'a follow-up about the products shown compares them and is never a search for its own words' => function () use ($ask, $id) {
        $shown = [$id('Ledlenser H7R Core%'), $id('Osean LED 450%'), $id('Hodelykt LED, vanntett%')];
        foreach (['Hvorfor passer disse forslagene for meg, og hva er forskjellen på dem?', 'Hva er forskjellen på dem?'] as $q) {
            $r = $ask($q, null, $shown);
            check($r['route'] === 'sammenligning' && $r['ids'] === $shown, "«{$q}»: " . $r['route'] . ' ' . json_encode($r['ids']));
            check(!str_contains($r['text'], 'Ladekabel') && !str_contains($r['text'], '«passer»'), "«{$q}»: no search for the word: " . $r['text']);
        }
        check($ask('Hva er forskjellen på dem?')['route'] === 'avklaring', 'with nothing shown before, Søren asks which products');
        check(CatalogIntent::typeOf('Hvorfor passer disse forslagene, og hva er forskjellen?') === null, 'follow-up words name no product type');
    },

    'a one word question on a product or theme page uses the page, not a product sharing the word' => function () use ($ask, $id, $slug) {
        $light = $id('1852 Bryggelys/vegglys%');
        $r = $ask('batteri', $slug($light));
        check($r['route'] === 'oppfolging' && $r['ids'] === [] && str_contains($r['text'], '800 mAh') && !str_contains($r['text'], 'H7R2'), $r['text']);
        $t = $ask('batteri', '/tema/lys');
        check($t['route'] === 'oppfolging' && !str_contains($t['text'], 'H7R2'), $t['text']);
        check($ask('har denne IP68?', $slug($light))['ids'] === [$light], '«denne» is the product on the page');
        check($ask('jeg trenger batterier til fjernkontrollen', $slug($light))['route'] === 'anbefaling', 'a clear new search leaves the page');
    },
];
