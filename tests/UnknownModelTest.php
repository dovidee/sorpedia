<?php
declare(strict_types=1);

// Models the catalogue doesn't have (feature 036): the server says a product is not sold only when the customer named a
// code that is surely a product model and is nowhere in the catalogue. Read only.

$ask = fn(string $q, array $history = [], ?string $page = null) => ProductRetriever::forChat([['role' => 'user', 'content' => $q]], PageContext::resolve($page), $history);
$h7r = fn() => (int) db()->query("SELECT id FROM products WHERE name LIKE 'Ledlenser H7R Core%'")->fetchColumn();

return [
    'an unknown model gets one complete sentence from the server, and no products' => function () use ($ask) {
        foreach (['Har dere H9R Signature?', 'Hva koster H9R Signature?'] as $q) {
            $r = $ask($q);
            check($r['unknown'] === ['H9R Signature'], "$q: " . json_encode($r['unknown']));
            check($r['ids'] === [] && $r['cards'] === [], "$q: no products in place of it");
        }
        check(AnswerGuard::notSold(['H9R Signature']) === 'Vi har dessverre ikke H9R Signature i sortimentet.', 'the sentence');
    },

    'a known model goes through the normal pipeline' => function () use ($ask, $h7r) {
        $r = $ask('Har dere H7R Core?');
        check($r['unknown'] === [] && ($r['ids'][0] ?? null) === $h7r(), 'H7R Core is found first, nothing unknown');
        check(AnswerGuard::notSold($r['unknown']) === '' && AnswerGuard::notSoldFact($r['unknown']) === '', 'no notice and no fact line');
    },

    'a known and an unknown model: the known product stays, the unknown one is named' => function () use ($ask, $h7r) {
        $r = $ask('Hva er forskjellen på H7R Core og H9R Signature?');
        check(in_array($h7r(), $r['ids'], true), 'H7R Core is still in the products');
        check($r['unknown'] === ['H9R Signature'], 'H9R Signature is unknown');
        $fact = AnswerGuard::notSoldFact($r['unknown']);
        check(str_contains($fact, 'H9R Signature') && str_contains($fact, 'sammenlign ikke'), 'the model is told not to describe or compare it');
    },

    'two unknown models are both named' => function () use ($ask) {
        $r = $ask('Har dere H9R og XT40?');
        check($r['unknown'] === ['H9R', 'XT40'], json_encode($r['unknown']));
        check(AnswerGuard::notSold($r['unknown']) === 'Vi har dessverre ikke H9R eller XT40 i sortimentet.', 'one sentence for both');
    },

    'a description without a model code is never «not sold»' => function () use ($ask) {
        foreach (['Har dere en kraftig hodelykt?', 'Jeg trenger noe til å henge opp et bilde', 'Har dere en gassgrill til hytta?'] as $q) {
            check($ask($q)['unknown'] === [], $q);
        }
    },

    'a likely typo or part of a known code is not «not sold»' => function () use ($ask) {
        foreach (['Har dere H7RR?', 'Har dere HR7 Core?', 'Har dere H7?', 'Har dere HF6?'] as $q) {
            check($ask($q)['unknown'] === [], $q);
        }
    },

    'sizes, sockets, grits and standards are not product models' => function () use ($ask) {
        foreach (['Har dere pære med E27 sokkel og IP67?', 'Trenger sandpapir P120 og K80', 'Har dere 18V batteri og 2x4?',
            'Har dere CR2032 batterier?', 'Er hørselvernet EN352 godkjent?', 'Har dere M8 bolter og GU10 pærer?'] as $q) {
            check($ask($q)['unknown'] === [], $q . ': ' . json_encode($ask($q)['unknown']));
        }
    },

    'an unknown model in a follow-up keeps the products in focus' => function () use ($ask, $h7r) {
        $r = $ask('Er H9R bedre enn den?', [$h7r()]);
        check($r['ids'] === [$h7r()], 'the previous product stays the only product: ' . json_encode($r['ids']));
        check($r['unknown'] === ['H9R'], 'H9R is unknown');
    },

    'the server sentence is complete: no colon, no ID, no reference, ends with a full stop' => function () {
        foreach ([['H9R Signature'], ['H9R', 'XT40'], ['Makita DUC254', 'H9R', 'XT40']] as $names) {
            $s = AnswerGuard::notSold($names);
            check(!str_contains($s, ':') && !preg_match('/\d{5,}|\[\[/', $s) && str_ends_with($s, 'i sortimentet.'), $s);
        }
    },

    'the name is shown as written, with a brand before it, and nothing else from the question' => function () use ($ask) {
        check($ask('Har dere Makita DUC254 motorsag?')['unknown'] === ['Makita DUC254'], 'brand kept, lower-case word after left out');
        check($ask('Finnes DUC254?')['unknown'] === ['DUC254'], 'the first word of the sentence is not a brand');
        check($ask('har dere <b>duc254</b>?')['unknown'] === ['DUC254'], 'no markup, code in capitals');
    },

    'the public API path: the endpoint product stays, the unknown model is named' => function () use ($ask, $h7r) {
        $r = $ask('Er denne bedre enn H9R?', [], '/produkt/ledlenser-h7r-core-1000-lumen-oppladbar-hodelykt');
        check(in_array($h7r(), $r['ids'], true) && $r['unknown'] === ['H9R'], json_encode($r));
    },
];
