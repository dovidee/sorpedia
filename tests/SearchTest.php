<?php
declare(strict_types=1);

// Feature 004: search. Short words match whole words or word starts, never the inside of a compound,
// and a typo is never corrected to a word that is not searched.

$names = fn(string $q, bool $strict = false) => implode(' | ', array_map(fn($c) => mb_strtolower($c['name']), Catalog::cards(Search::run($q, 10, false, $strict)['ids'])));

return [
    'a short word does not match inside a compound' => fn() => check(!str_contains($names('tre'), 'formtre'), 'tre found formtre: ' . $names('tre')),
    'a short word still matches the start of a compound' => fn() => check(str_contains($names('sag'), 'sagkjede'), 'sag did not find sagkjede'),
    'a long word still matches inside a compound' => fn() => check(str_contains($names('skrue'), 'øyeskrue') || str_contains($names('skrue'), 'skruekrok'), 'skrue found no screws'),
    'a typo is not corrected to a stop word' => fn() => check(Search::run('bor', 10)['corrected'] !== 'for', 'bor was corrected to for'),
    'a verb in a chat question is not read as a product word' => fn() => check(!str_contains($names('Hvordan feller jeg et tre trygt?', true), 'filler'), 'feller became filler'),
];
