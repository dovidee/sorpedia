<?php
declare(strict_types=1);

/**
 * Søk med skrivefeil-toleranse, synonymer og bruksområder (feature 004).
 * Katalogen er liten (cirka 500 produkter), så vi scorer i PHP. Det gir full kontroll og forklarbarhet:
 * resultatet sier hvilke ord som ble rettet og hvilke bruksområder som ble gjenkjent.
 */
final class Search
{
    private const STOP = ['og', 'i', 'til', 'for', 'en', 'et', 'ei', 'på', 'med', 'å', 'av', 'jeg', 'vil', 'skal',
        'trenger', 'noe', 'som', 'kan', 'har', 'meg', 'min', 'mitt', 'en', 'det', 'den', 'de', 'er', 'hva', 'hvordan', 'the',
        // Engelske småord (søk på engelsk oversettes, se fromEnglish)
        'a', 'an', 'what', 'which', 'does', 'have', 'has', 'how', 'need', 'want', 'some', 'any', 'with', 'my', 'you', 'is', 'are',
        'can', 'do', 'me', 'show', 'find', 'please', 'good', 'of', 'in', 'it', 'that', 'this', 'products', 'product'];
    /** Ord som er vanlige i spørsmål til Søren, men aldri beskriver et produkt. */
    private const CHAT_STOP = ['greit', 'bruke', 'bruker', 'bra', 'best', 'beste', 'bedre', 'hvilken', 'hvilke', 'hvilket', 'lurer', 'tips',
        'anbefale', 'anbefaler', 'bør', 'dere', 'du', 'din', 'ditt', 'mine', 'hei', 'takk', 'ikke', 'eller', 'om', 'da', 'nå', 'her',
        'der', 'være', 'blir', 'få', 'gjøre', 'mye', 'lite', 'god', 'godt', 'gode', 'okei', 'ok', 'ja', 'nei', 'så', 'også', 'mer',
        'sånn', 'slik', 'passer', 'egentlig', 'helst', 'vet', 'si', 'fortell', 'kort', 'tenke', 'hvorfor', 'forskjellen', 'disse'];

    /** @return array{ids:int[], corrected:?string, matched:array, themes:string[]} */
    public static function run(string $q, int $limit = 60, bool $prefix = false, bool $strict = false): array
    {
        $q = mb_strtolower(trim($q));
        if ($q === '') {
            return ['ids' => [], 'corrected' => null, 'matched' => [], 'themes' => []];
        }
        $rows = self::rows();
        $vocab = self::vocab($rows);
        // 0. Engelsk: «screw» blir «skrue». Bare ord katalogen ikke kjenner fra før, så «grill» og «hammer» står som de er.
        [$q, $translated] = self::fromEnglish($q, $vocab);

        // 1. Bruksområder og synonymer ("male vegg", "lommelykt")
        $terms = [];
        $themes = [];
        $boost = [];
        $matched = [];
        foreach (db()->query('SELECT * FROM search_synonyms ORDER BY CHAR_LENGTH(term) DESC') as $s) {
            if (preg_match('/(^|\s)' . preg_quote($s['term'], '/') . '($|\s)/u', $q)) {
                $matched[] = $s;
                foreach (explode(' ', $s['expands']) as $e) {
                    if (str_starts_with($e, 'tema:')) {
                        // Bruksområde ("se i mørket") viser hele temaet. Synonym ("drill") gir bare lenke, ikke falske treff.
                        $s['kind'] === 'bruksomrade' ? $boost[] = substr($e, 5) : null;
                        $themes[] = substr($e, 5);
                    } else {
                        $terms[] = $e;
                    }
                }
            }
        }

        // 2. Ord fra spørringen, med retting av skrivefeil mot ord som finnes i katalogen
        $stop = $strict ? array_merge(self::STOP, self::CHAT_STOP) : self::STOP;
        $words = array_values(array_filter(preg_split('/[^\p{L}\d,.]+/u', $q) ?: [], fn($w) => mb_strlen($w) >= 2 && !in_array($w, $stop, true)));
        $fixed = [];
        $changed = false;
        foreach ($words as $i => $w) {
            $isLast = $i === count($words) - 1;
            if (isset($vocab[$w]) || ($prefix && $isLast && self::hasPrefix($vocab, $w)) || preg_match('/\d/', $w)) {
                $fixed[] = $w;
                continue;
            }
            // Sammensatte ord: "litiumbatteri" søker på "batteri"
            if (($head = self::head($w, $vocab)) !== null) {
                $fixed[] = $head;
                $changed = true;
                continue;
            }
            // Del av sammensatte navn: "skrue" i "Øyeskrue" og "Skruekrok" er ikke en skrivefeil
            if (mb_strlen($w) >= 4 && str_contains(self::names($rows), $w)) {
                $fixed[] = $w;
                continue;
            }
            // Strengt (Søren): bare én skrivefeil, og bare i lange ord, så "greit" ikke blir "rett"
            $best = $strict ? (mb_strlen($w) >= 6 ? self::nearest($w, $vocab, 1) : null) : self::nearest($w, $vocab);
            if ($strict && $best === null) {
                continue; // ukjent ord i en setning: hopp over i stedet for å gjette
            }
            $fixed[] = $best ?? $w;
            $changed = $changed || ($best !== null && $best !== $w);
        }
        $terms = array_merge($fixed, $terms);

        // 3. Poeng per produkt
        $scores = [];
        foreach ($rows as $r) {
            $s = 0;
            $hits = 0;
            foreach ($fixed as $i => $t) {
                $isLast = $i === count($fixed) - 1;
                $re = '/(^|\P{L})' . preg_quote($t, '/') . ($prefix && $isLast ? '' : '') . '/u';
                if (preg_match($re, $r['n'])) {
                    $s += 6;
                    $hits++;
                } elseif (str_contains($r['n'], $t)) {
                    $s += 3;
                    $hits++;
                } elseif (str_contains($r['t'], $t)) {
                    $s += 1;
                    $hits++;
                }
            }
            foreach (array_slice($terms, count($fixed)) as $t) {
                if (str_contains($r['n'], $t)) {
                    $s += 4;
                }
            }
            if (in_array($r['theme'], $boost, true)) {
                $s += 2;
            }
            if ($fixed && $hits === count($fixed)) {
                $s += 5; // alle ordene traff
            }
            if ($s > 0) {
                $scores[$r['id']] = $s;
            }
        }
        arsort($scores);
        // Bare de med minst halvparten av toppscoren, så "drill" ikke drukner i tilfeldige teksttreff
        $top = $scores ? reset($scores) : 0;
        $ids = array_keys(array_filter($scores, fn($s) => $s >= max(2, $top / 3)));

        return [
            'ids' => array_slice(array_map('intval', $ids), 0, $limit),
            'corrected' => $changed ? implode(' ', $fixed) : null,
            'translated' => $translated,
            'matched' => $matched,
            'themes' => array_values(array_unique($themes)),
        ];
    }

    /** Oversetter engelske produktord til norsk. Returnerer [spørring, norsk tekst hvis noe ble oversatt]. */
    public static function fromEnglish(string $q, array $vocab): array
    {
        $out = $q;
        // Uttrykk med flere ord først («hearing protection», «work light»)
        foreach (I18nEn::SEARCH as $en => $no) {
            if (str_contains($en, ' ') && preg_match('/(^|\s)' . preg_quote($en, '/') . 's?($|\s)/u', $out)) {
                $out = (string) preg_replace('/(^|\s)' . preg_quote($en, '/') . 's?($|\s)/u', '$1' . $no . '$2', $out);
            }
        }
        $words = preg_split('/(\s+)/u', $out, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($words as $i => $w) {
            if ($w === '' || ctype_space($w) || isset($vocab[$w])) {
                continue;
            }
            $hit = I18nEn::SEARCH[$w] ?? null;
            // Flertall: «screws», «batteries», «brushes»
            foreach ([['ies', 'y'], ['es', ''], ['s', '']] as [$suf, $rep]) {
                if ($hit === null && str_ends_with($w, $suf)) {
                    $hit = I18nEn::SEARCH[substr($w, 0, -strlen($suf)) . $rep] ?? null;
                }
            }
            if ($hit !== null) {
                $words[$i] = $hit;
            }
        }
        $out = implode('', $words);
        return [$out, $out !== $q ? $out : null];
    }

    private static function rows(): array
    {
        static $rows = null;
        return $rows ??= array_map(fn($r) => [
            'id' => (int) $r['id'], 'theme' => $r['theme'],
            // "(ikke Core)" og "Passer ikke Core" skal ikke gi treff på "core"
            'n' => mb_strtolower((string) preg_replace(['/\\(ikke[^)]*\\)/iu', '/passer\\s+ikke.*$/iu'], ' ', $r['name'])), 't' => mb_strtolower(mb_substr((string) $r['plain_text'], 0, 1500)),
        ], db()->query('SELECT id, theme, name, plain_text FROM products')->fetchAll());
    }

    private static function names(array $rows): string
    {
        static $all = null;
        return $all ??= implode(' ', array_column($rows, 'n'));
    }

    private static function vocab(array $rows): array
    {
        $v = [];
        foreach ($rows as $r) {
            foreach (preg_split('/[^\p{L}]+/u', $r['n']) ?: [] as $w) {
                if (mb_strlen($w) >= 3) {
                    $v[$w] = true;
                }
            }
        }
        foreach (db()->query('SELECT term FROM search_synonyms') as $s) {
            foreach (explode(' ', $s['term']) as $w) {
                $v[$w] = true;
            }
        }
        return $v;
    }

    /** Siste ledd i et sammensatt ord, hvis det er et kjent ord på minst fem bokstaver. */
    private static function head(string $w, array $vocab): ?string
    {
        $len = mb_strlen($w);
        for ($i = 3; $i <= $len - 5; $i++) {
            $tail = mb_substr($w, $i);
            if (isset($vocab[$tail])) {
                return $tail;
            }
        }
        return null;
    }

    private static function hasPrefix(array $vocab, string $w): bool
    {
        foreach ($vocab as $v => $_) {
            if (str_starts_with($v, $w)) {
                return true;
            }
        }
        return false;
    }

    /** Nærmeste ord med Levenshtein-avstand. Tillater 1 feil for korte ord, 2 for lengre. */
    private static function nearest(string $w, array $vocab, ?int $limit = null): ?string
    {
        $len = mb_strlen($w);
        if ($len < 3) {
            return null;
        }
        $max = min($limit ?? 2, $len <= 4 ? 1 : 2);
        $best = null;
        $bestD = $max + 1;
        $a = self::ascii($w);
        foreach ($vocab as $v => $_) {
            if (abs(mb_strlen($v) - $len) > $max) {
                continue;
            }
            $d = levenshtein($a, self::ascii((string) $v));
            if ($d < $bestD) {
                $best = (string) $v;
                $bestD = $d;
            }
        }
        return $best;
    }

    private static function ascii(string $s): string
    {
        return strtr($s, ['æ' => 'e', 'ø' => 'o', 'å' => 'a', 'é' => 'e']);
    }
}
