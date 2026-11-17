<?php
declare(strict_types=1);

/**
 * Search with typo tolerance, synonyms and use cases (feature 004).
 * The catalog is small (about 500 products), so we score in PHP. That gives full control and explainability:
 * the result says which words were corrected and which use cases were recognized.
 */
final class Search
{
    private const STOP = ['og', 'i', 'til', 'for', 'en', 'et', 'ei', 'på', 'med', 'å', 'av', 'jeg', 'vil', 'skal',
        'trenger', 'noe', 'som', 'kan', 'har', 'meg', 'min', 'mitt', 'en', 'det', 'den', 'de', 'er', 'hva', 'hvordan', 'the',
        // Small English words (English searches are translated, see fromEnglish)
        'a', 'an', 'what', 'which', 'does', 'have', 'has', 'how', 'need', 'want', 'some', 'any', 'with', 'my', 'you', 'is', 'are',
        'can', 'do', 'me', 'show', 'find', 'please', 'good', 'of', 'in', 'it', 'that', 'this', 'products', 'product'];
    /** Words that are common in questions to Søren but never describe a product. */
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
        // 0. English: "screw" becomes "skrue". Only words the catalog doesn't already know, so "grill" and "hammer" stay as they are.
        [$q, $translated] = self::fromEnglish($q, $vocab);

        // 1. Use cases and synonyms ("male vegg", "lommelykt")
        $terms = [];
        $themes = [];
        $boost = [];
        $matched = [];
        foreach (db()->query('SELECT * FROM search_synonyms ORDER BY CHAR_LENGTH(term) DESC') as $s) {
            if (preg_match('/(^|\s)' . preg_quote($s['term'], '/') . '($|\s)/u', $q)) {
                $matched[] = $s;
                foreach (explode(' ', $s['expands']) as $e) {
                    if (str_starts_with($e, 'tema:')) {
                        // A use case ("se i mørket") shows the whole theme. A synonym ("drill") only gives a link, not false hits.
                        $s['kind'] === 'bruksomrade' ? $boost[] = substr($e, 5) : null;
                        $themes[] = substr($e, 5);
                    } else {
                        $terms[] = $e;
                    }
                }
            }
        }

        // 2. Words from the query, with typo correction against words that exist in the catalog
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
            // Compound words: "litiumbatteri" searches for "batteri"
            if (($head = self::head($w, $vocab)) !== null) {
                $fixed[] = $head;
                $changed = true;
                continue;
            }
            // Part of compound names: "skrue" in "Øyeskrue" and "Skruekrok" is not a typo
            if (mb_strlen($w) >= 4 && str_contains(self::names($rows), $w)) {
                $fixed[] = $w;
                continue;
            }
            // Strict (Søren): only one typo, and only in long words, so "greit" doesn't become "rett"
            $best = $strict ? (mb_strlen($w) >= 6 ? self::nearest($w, $vocab, 1) : null) : self::nearest($w, $vocab);
            if ($strict && $best === null) {
                continue; // unknown word in a sentence: skip it instead of guessing
            }
            $fixed[] = $best ?? $w;
            $changed = $changed || ($best !== null && $best !== $w);
        }
        $terms = array_merge($fixed, $terms);

        // 3. Score per product
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
                $s += 5; // all the words matched
            }
            if ($s > 0) {
                $scores[$r['id']] = $s;
            }
        }
        arsort($scores);
        // Only those with at least half the top score, so "drill" doesn't drown in random text hits
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

    /** Translates English product words to Norwegian. Returns [query, Norwegian text if anything was translated]. */
    public static function fromEnglish(string $q, array $vocab): array
    {
        $out = $q;
        // Multi word phrases first ("hearing protection", "work light")
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
            // Plurals: "screws", "batteries", "brushes"
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
            // "(ikke Core)" and "Passer ikke Core" must not match "core"
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

    /** The last part of a compound word, if it is a known word of at least five letters. */
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

    /** Nearest word by Levenshtein distance. Allows 1 error for short words, 2 for longer ones. */
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
