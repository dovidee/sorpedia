<?php
declare(strict_types=1);

// Fixed replies to greetings, thanks, identity questions and filler (feature 042): the whole message only, from PHP,
// with no call to Ollama. Read only, except the API test, which writes the rate limit row inside a transaction that is rolled back.

$h7r = fn() => (int) db()->query("SELECT id FROM products WHERE name LIKE 'Ledlenser H7R Core%' ORDER BY id LIMIT 1")->fetchColumn();

return [
    'the listed greetings match whatever the case, spacing and punctuation' => function () {
        foreach (['hi', 'Hei', 'HELLO', 'hallo', '  hei  ', "hei\n", 'Hei!', 'hei!!', 'hei.', 'God  morgen!', 'god kveld', 'Hey', 'yo'] as $q) {
            check((QuickReply::match($q, false)['route'] ?? null) === 'hilsen', "«{$q}» is a greeting");
        }
        foreach (['takk' => 'takk', 'Ok' => 'takk', 'greit.' => 'takk', 'Hvem er du?' => 'hvem', 'who are you' => 'hvem',
            'Hva kan du?' => 'hjelp', 'Hvordan fungerer dette?' => 'hjelp', 'What can you do?' => 'hjelp', 'hjelp' => 'hjelp',
            'test' => 'fyll', 'asdf' => 'fyll', 'lol' => 'fyll'] as $q => $route) {
            check((QuickReply::match($q, false)['route'] ?? null) === $route, "«{$q}» is $route");
        }
    },

    'a greeting with a question keeps the question, and product questions are never caught' => function () {
        foreach (['Hei, har dere hodelykter?', 'hei hvilken hodelykt anbefaler du', 'Hjelp meg å velge en hodelykt', 'takk, hva koster H7R Core?',
            'Hva kan du om hodelykter?', 'ok men hva med batteriet', 'Hvilken hodelykt tåler regnvær?', 'Passer jakken til fergetur?', 'heisann', 'hoi'] as $q) {
            check(QuickReply::match($q, false) === null, "«{$q}» goes on to the question path");
        }
    },

    'weather and ferries: no internal address for customers, the Control Tower link for staff' => function () {
        foreach (['Hvordan blir været i morgen?', 'Går fergen til Hirtshals i dag?', "What's the weather like?"] as $q) {
            $c = QuickReply::match($q, false);
            check(($c['route'] ?? null) === 'forhold' && !str_contains($c['text'], '/ansatt') && !str_contains(mb_strtolower($c['text']), 'kontrolltårn'), "customer: $q");
            $s = QuickReply::match($q, true);
            check(($s['route'] ?? null) === 'forhold_ansatt' && str_contains($s['text'], '/ansatt/tarn'), "staff: $q");
        }
    },

    'a matched message never reaches Ollama, in the API, and is fast' => function () use ($h7r) {
        db()->beginTransaction();
        try {
            $before = Chat::$calls;
            $times = [];
            foreach (['hei', 'Hei!', 'takk', 'hva kan du'] as $q) {
                $t = microtime(true);
                $r = PublicApi::handle('POST', "/api/v1/products/{$h7r()}/questions", [], 'application/json', '',
                    ['raw' => json_encode(['question' => $q]), 'type' => 'application/json', 'client' => 'test-' . bin2hex(random_bytes(6))]);
                $times[] = (microtime(true) - $t) * 1000;
                $b = json_decode($r['body'], true);
                check($r['status'] === 200 && $b['model']['used'] === false && $b['products'] === [], "«{$q}»: answered by PHP: " . $r['status']);
            }
            check(Chat::$calls === $before, 'no call to Ollama: ' . (Chat::$calls - $before));
            sort($times);
            check($times[1] < 50, 'median under 50 ms: ' . json_encode(array_map(fn($x) => round($x, 1), $times)));
        } finally {
            db()->rollBack();
        }
    },
];
