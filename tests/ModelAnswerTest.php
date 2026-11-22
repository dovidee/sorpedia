<?php
declare(strict_types=1);

// Model answer validation (feature 040): a model answer that names a product the catalogue doesn't have, recommends
// another product type than the one asked for, states a specification without evidence, carries an unfilled note or
// contradicts a documented fit is rejected before the customer sees it. The products are the catalogue's own.
// Read only, except the rate limit tests, which write inside a transaction that is rolled back.

$id = fn(string $like) => (int) db()->query('SELECT id FROM products WHERE name LIKE ' . db()->quote($like) . ' ORDER BY id LIMIT 1')->fetchColumn();
$h7r = fn() => $id('Ledlenser H7R Core%');
$mh = fn() => $id('%MH-Serie%');
$hf8r = fn() => $id('Ledlenser HF8R Core%');
$fokus = fn() => $id('Osean Fokus 7000%');
$battery = fn() => $id('Ledlenser Batteri – 21700 – H7R Core%');
// The validation of one answer, sentence by sentence, as Chat::answer runs it: the first reason, or null when all pass
$check = function (string $q, array $ids, string $answer): ?string {
    $facts = Chat::facts($ids);
    $ctx = AnswerGuard::grounding(AnswerGuard::context($ids, $facts), $q, $facts);
    $state = [];
    foreach (preg_split('/(?<=[.!?])\s+/u', $answer) ?: [] as $s) {
        if (($why = AnswerGuard::violation($s, $state, $ctx)) !== null) {
            return $why;
        }
    }
    return null;
};
// A question to the public API, as the HTTP handler gets it
$ask = fn(int $product, string $q, string $client) => PublicApi::handle('POST', "/api/v1/products/$product/questions", [], 'application/json', '',
    ['raw' => json_encode(['question' => $q]), 'type' => 'application/json', 'client' => $client]);

return [
    'products the catalogue does not have are rejected, in Norwegian and English' => function () use ($check, $h7r, $hf8r) {
        $ids = [$h7r(), $hf8r()];
        check($check('Hvilken hodelykt anbefaler du?', $ids, 'Pentax K-5000 er også et godt valg.') === 'oppdiktet_produkt', 'a made-up model code');
        check($check('Hvilken hodelykt anbefaler du?', $ids, 'CoStar LK-SLXW har god batteridrift.') === 'oppdiktet_produkt', 'a made-up brand and code');
        check($check('Which headlamp do you recommend?', $ids, 'The Pentax K-5000 is also a good choice.') === 'oppdiktet_produkt', 'English');
        check($check('Hvilken hodelykt anbefaler du?', $ids, 'Ledlenser HF8R Core har 900 lumen og magnetisk lading.') === null, 'a product in the facts passes');
        check($check('Hvordan lader jeg den?', [$h7r()], 'Batteriet er et 3,7V-batteri som lades med magnetisk ladekabel.') === null, 'a technical compound is not a product code');
    },

    'a flashlight in a headlamp recommendation is rejected' => function () use ($check, $h7r, $hf8r, $fokus) {
        check($fokus() > 0, 'Osean Fokus 7000 is in the catalogue');
        $ids = [$h7r(), $hf8r(), $fokus()];
        check($check('Hvilken av dere sine hodelykter anbefaler du til fjellturer?', $ids, 'Osean Fokus 7000 er sterk og vanntett.') === 'feil_type', 'Norwegian');
        check($check('Which headlamp do you recommend for hiking?', $ids, 'Osean Fokus 7000 is very bright.') === 'feil_type', 'English');
        check($check('Hvilken av dere sine hodelykter anbefaler du til fjellturer?', $ids, 'Ledlenser HF8R Core er et godt valg.') === null, 'a headlamp passes');
    },

    'a charging method the catalogue does not document is rejected' => function () use ($check, $h7r) {
        check($check('Hvordan lader jeg den?', [$h7r()], 'Lykten har også USB-C-lading direkte.') === 'udokumentert_spesifikasjon', 'USB-C on H7R Core');
        check($check('How do I charge it?', [$h7r()], 'It also charges directly with USB-C.') === 'udokumentert_spesifikasjon', 'English');
        check($check('Hvordan lader jeg den?', [$h7r()], 'Det finnes kort som viser hvor lenge batteriet lades via solceller.') === 'udokumentert_spesifikasjon', 'a charging claim without a named product is about the product in focus');
        check($check('Hvordan lader jeg den?', [$h7r()], 'Den lades med magnetisk ladekabel, ikke med USB-C.') === null, 'the documented method, and a denial, pass');
    },

    'a weight, runtime, brightness or water rating without evidence is rejected; the documented one passes' => function () use ($check, $h7r, $hf8r) {
        $q = 'Er den god til fjellturer?';
        check($check($q, [$h7r()], 'Den veier 180 gram.') === 'udokumentert_spesifikasjon', 'a made-up weight');
        check($check($q, [$h7r()], 'Den lyser i 12 timer på full styrke.') === 'udokumentert_spesifikasjon', 'burn time is not in the catalogue, so it stays unknown');
        check($check($q, [$h7r()], 'Ledlenser H7R Core har 1200 lumen.') === 'udokumentert_spesifikasjon', 'a wrong brightness');
        check($check($q, [$h7r()], 'Den veier 250 gram og gir 1000 lumen.') === null, 'the values in Sør-Tre\'s product text pass');
        check($check($q, [$h7r()], 'Til fjellet bør du ha minst 500 lumen.') === null, 'advice with a threshold is not a claim about the product');
        check($check('Hvilken hodelykt anbefaler du?', [$h7r(), $hf8r()], 'Ledlenser HF8R Core er et godt valg. Den har IP67.') === 'udokumentert_spesifikasjon', 'a water rating the catalogue lacks for that product');
        check($check($q, [$h7r()], 'Ledlenser H7R Core er IP67-klassifisert.') === null, 'the documented rating passes');
    },

    'placeholders, nested notes, drafting marks and prompt labels are rejected' => function () use ($check, $h7r) {
        foreach (['Den veier [vekt fra fakta?].', 'Mye mer lyssterk ([.]]) enn de fleste.', 'Solcelle [[har Sør-Tre dem?] neir] er fint.',
            'Batteriet (dokumenteringsgrad?) er godt.', 'VERIFISERT: lykten er god.', 'TODO sjekk vekten.', 'Prisen er {pris}.'] as $s) {
            check($check('Er den god?', [$h7r()], $s) === 'plassholder', "placeholder: $s");
        }
        check($check('Er den god?', [$h7r()], "Ledlenser H7R Core koster [[pris:{$h7r()}]] og er på lager [[lager:{$h7r()}]].") === null, 'valid references pass');
    },

    'a fit claim through a pronoun keeps the documented misfit' => function () use ($check, $h7r, $mh) {
        check($mh() > 0, 'the MH cable is in the catalogue');
        $q = 'Hvordan lader jeg den?';
        check($check($q, [$h7r()], 'Vi har Ledlenser Ladekabel MH-Serie på lager. Denne kabelen passer perfekt til lykten.') === 'motsier_passer_ikke', 'two sentences');
        check($check($q, [$h7r()], 'We stock the Ledlenser Ladekabel MH-Serie. It fits your lamp perfectly.') === 'motsier_passer_ikke', 'English, through «it»');
        check($check($q, [$h7r()], 'For å lade den anbefaler vi MH-serien, Ledlenser Ladekabel USB/magnetisk lading.') === 'motsier_passer_ikke', 'a recommendation is a fit claim');
        check($check($q, [$h7r()], 'MH-kabelen passer ikke til H7R Core. Den lades med magnetisk ladekabel.') === null, 'the misfit said as a misfit passes');
    },

    'a documented fit is kept, and a model that denies it is rejected' => function () use ($check, $h7r, $battery) {
        check($battery() > 0, 'the 21700 battery is in the catalogue');
        $q = 'Passer batteriet til denne?';
        check($check($q, [$h7r(), $battery()], 'Ledlenser Batteri 21700 passer til H7R Core.') === null, 'the documented fit passes');
        check($check($q, [$h7r(), $battery()], 'Ledlenser Batteri 21700 passer ikke til H7R Core.') === 'motsier_passer', 'contradicting the database');
    },

    'the MH cable is identified by name in English and Norwegian' => function () use ($h7r, $mh) {
        $page = PageContext::resolve('/produkt/ledlenser-h7r-core-1000-lumen-oppladbar-hodelykt');
        foreach (['Does the MH charging cable fit H7R Core?' => 'en', 'Passer MH-ladekabelen til H7R Core?' => 'no'] as $q => $lang) {
            Chat::$lang = I18n::$force = $lang;
            $found = ProductRetriever::forChat([['role' => 'user', 'content' => $q]], $page);
            $a = FactAnswer::answer($q, PublicApi::grounding($h7r(), $found)['ids'], $found['why'], $h7r(), $found['intent']);
            check($a !== null && $a['kind'] === 'passer' && in_array($mh(), $a['ids'], true), "$q: the cable is in the answer: " . json_encode($a));
            $t = ProductRef::resolve($a['text']);
            check(str_starts_with($t, $lang === 'en' ? 'No, ' : 'Nei, ') && str_contains($t, 'MH-Serie'), "$q: a direct no about the cable: $t");
        }
        Chat::$lang = I18n::$force = 'no';
    },

    'a charging question gets the documented charging facts from the server, unknown accessories stay unknown' => function () use ($h7r) {
        $page = PageContext::resolve('/produkt/ledlenser-h7r-core-1000-lumen-oppladbar-hodelykt');
        $q = 'Hvordan lader jeg den?';
        $found = ProductRetriever::forChat([['role' => 'user', 'content' => $q]], $page);
        $ids = PublicApi::grounding($h7r(), $found)['ids'];
        check(FactAnswer::answer($q, $ids, $found['why'], $h7r(), $found['intent']) === null, 'a how-to question still goes to the model');
        $b = FactAnswer::basis($q, $ids, $found['why'], $h7r(), $found['intent']);
        $t = $b === null ? '' : ProductRef::resolve($b['text']);
        check(str_contains($t, 'Magnetisk ladekabel') && str_contains($t, 'Passer ikke') && str_contains($t, 'MH-Serie'), 'the method and the documented misfit: ' . $t);
        check(str_contains($t, 'har ikke registrert tilbehør'), 'no documented charger is said as not registered: ' . $t);
    },

    'a server answer does not use the model allowance; when it is used up, questions are read by rules' => function () use ($ask, $h7r) {
        db()->beginTransaction();
        try {
            $client = 'test-' . bin2hex(random_bytes(6));
            $limit = config()['api_ask_limit'];
            for ($i = 0; $i < $limit - 1; $i++) {
                PublicApi::limited($client, 'model', $limit);
            }
            $r = $ask($h7r(), 'Hva koster den?', $client);
            check($r['status'] === 200 && json_decode($r['body'], true)['model']['used'] === false, 'answered by the server: ' . $r['status']);
            check(PublicApi::limited($client, 'model', $limit) === 0, 'the last model slot was still free after the server answer');
            $r = $ask($h7r(), 'Hvilken hodelykt anbefaler du?', $client);
            $b = json_decode($r['body'], true);
            check($r['status'] === 200 && $b['model']['used'] === false && str_contains((string) $b['notice'], 'allowance for this client is used up'),
                'with the model allowance used up the question is read by rules and answered by the server (feature 041): ' . $r['status'] . ' ' . $b['notice']);
            $r = $ask($h7r(), 'Hva veier den?', $client);
            check($r['status'] === 200, 'server answers still work when the model allowance is used up');
            foreach (['product_id', 'question', 'answer', 'reasoning', 'model', 'notice', 'products', '_links'] as $k) {
                check(array_key_exists($k, json_decode($r['body'], true)), "the response keeps the field $k");
            }
        } finally {
            db()->rollBack();
        }
    },

    'the general question limit still applies to server answers' => function () use ($ask, $h7r) {
        db()->beginTransaction();
        try {
            $client = 'test-' . bin2hex(random_bytes(6));
            $limit = config()['api_question_limit'];
            for ($i = 0; $i < $limit; $i++) {
                PublicApi::limited($client, 'question', $limit);
            }
            $r = $ask($h7r(), 'Hva koster den?', $client);
            check($r['status'] === 429 && isset($r['headers']['Retry-After']), 'too many questions: ' . $r['status']);
        } finally {
            db()->rollBack();
        }
    },
];
