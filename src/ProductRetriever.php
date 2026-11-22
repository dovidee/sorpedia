<?php
declare(strict_types=1);

/**
 * Which products Søren talks about (feature 034). One ranked list per question drives the facts the model gets, the
 * product cards and what the conversation remembers, so the cards always show the products the answer is about.
 *
 * Candidates come from: exact SKUs and model codes in the question, the products on the page, the products of the
 * previous answer (only when the question refers back to them), the store search (Search::run, unchanged, for words,
 * typos, synonyms and use cases), the catalogue's relations for accessories the customer asks for, and the same
 * category for "something similar". Every candidate keeps the signals that gave it points, so a ranking can be explained.
 *
 * Relations keep their meaning: documented and rule-based «passer sammen» can bring an accessory in, «passer ikke»
 * keeps a product out as an accessory, and «kjøpt sammen» is never used here (it says nothing about fit).
 * No model calls: rules over the database only.
 */
final class ProductRetriever
{
    /** Points per signal. Structural signals (anything but the word signals) keep a product even when words score higher. */
    public const WEIGHTS = [
        'sku' => 100,            // the question contains the product's SKU
        'model' => 100,          // the product's own model code (H5R, CS720) is in the question
        'exact_name' => 70,      // the whole product name is in the question
        'page' => 50,            // the product is on the page the customer is on
        'history' => 45,         // the previous answer was about it and the question refers back
        'relation' => 35,        // documented or rule-based fit with a product in focus, for an accessory the customer asks for
        'requirement' => 25,     // the best match for an asked-for accessory when the catalogue has no relation for it
        'similar' => 30,         // same category as the product in focus, when the customer asks for something similar
        'category' => 30,        // a specific word from the question is a word in the product name
        'use_case' => 20,        // the store search recognised a use case («henge opp bilde»)
        'synonym' => 15,         // the store search matched a synonym
        'model_mention' => 40,   // the model code is in the name, but the product is something else (a battery «for H7R Core»)
        'brand' => 10,           // the question names the product's brand
        'generic_word' => 2,     // a word that is in many product names («core», «hanske»)
        'wrong_theme' => -40,    // a word hit outside the theme of the products in focus, in a question about those products
        'other_model' => -40,    // the product has another model code than the one asked for (HF4R when asking for H5R)
        'unrelated_accessory' => -40, // an asked-for accessory with no relation to the product, when related ones exist (the 4,8 mm file for a 4,0 mm chain)
    ];

    /** A product needs this many points to be shown. */
    public const MIN_SCORE = 25;

    /** A product with word signals only needs at least this share of the best score, so weak word hits don't fill the slots. */
    public const RELATIVE = 0.5;

    /** How many products the model and the cards get. A catalogue-wide list keeps its top three, as the facts describe them. */
    public const LIMIT = 5;
    public const INTENT_LIMIT = 3;

    /** A word in more product names than this is generic. A model code in more names than this is a brand or series (1852). */
    private const GENERIC_NAMES = 10;
    private const MODEL_NAMES = 6;

    private const STRUCTURAL = ['sku', 'model', 'exact_name', 'page', 'history', 'relation', 'requirement', 'similar'];

    /**
     * Words that say what the customer wants, not which product: they are read as intent and never searched for
     * («komplett» found balcony stands, «sett» found a crab cooker, «sammen» found a folding grill).
     */
    private const INTENT_WORDS = ['sammen', 'begge', 'komplett', 'komplette', 'løsning', 'løsningen', 'sett', 'settet', 'oppsett', 'oppsettet',
        'lignende', 'liknende', 'tilsvarende', 'alternativ', 'alternativer', 'ekstra', 'egen', 'eget', 'egne', 'denne', 'dette', 'disse', 'den',
        'det', 'de', 'dem', 'samme', 'andre', 'annen', 'annet', 'to', 'tre', 'koster', 'kostar', 'pris', 'prisen', 'lager', 'varenummer',
        'artikkelnummer', 'finn', 'vis', 'trenger', 'kjøpe', 'passer', 'passe', 'virker', 'fungerer', 'lenge', 'lyser', 'full', 'styrke',
        'billigst', 'billigste', 'dyrest', 'dyreste', 'dere', 'har', 'hva', 'hvor', 'hvilken', 'hvilke', 'av', 'er', 'noe', 'meg', 'jeg',
        'vil', 'ha', 'til', 'med', 'og', 'en', 'et', 'ei', 'som', 'kan', 'på', 'i', 'for', 'eller'];

    /** Asked-for accessories and the name words that satisfy them (a compound counts: «rundfil» is a «fil»). */
    private const ACCESSORIES = [
        'batteri' => ['batteri'], 'lader' => ['lader', 'ladekabel'], 'ladekabel' => ['ladekabel', 'lader'], 'kabel' => ['kabel'],
        'fil' => ['fil', 'filsett'], 'sverd' => ['sverd'], 'pære' => ['pære'], 'sjakkel' => ['sjakkel'], 'kjetting' => ['kjetting'],
    ];

    /**
     * The product list for one chat question.
     * «unknown» lists model codes the customer named that are surely not in the catalogue, as written (feature 036).
     * @param array $messages the conversation, last message the customer's question
     * @param array $page PageContext::resolve()
     * @param int[] $history the products of the previous answer in this conversation (already checked by the caller)
     * @return array{ids:int[], cards:int[], why:array<int,array{score:int, signals:array<string,int>}>, intent:?array, kind:?string, unknown:string[]}
     */
    public static function forChat(array $messages, array $page, array $history = []): array
    {
        $q = Chat::lastUser($messages);
        $catalog = self::catalog();
        $history = array_values(array_filter(array_map('intval', $history), fn($id) => isset($catalog[$id])));
        $a = self::analyse($q, $catalog);
        $unknown = self::asWritten($q, $a['unknown']);

        $focusRef = $history && ($a['refPlural'] || $a['refSingular'] || $a['accessories'] || $a['similar']);
        // A new product named outright is a new topic, unless the customer explicitly points back («de to», «begge»)
        $named = array_keys($a['exact']);
        $topicChange = $named && !array_intersect($named, $history) && !$a['refPlural'];
        $useHistory = $focusRef && !$topicChange;

        // «den billigste av dem»: an order over the products in focus, not over the catalogue
        $kind = CatalogIntent::detect($q);
        if ($useHistory && in_array($kind, ['billigst', 'dyrest'], true)) {
            $ids = $history;
            usort($ids, fn($x, $y) => ($catalog[$x]['price'] <=> $catalog[$y]['price']) * ($kind === 'billigst' ? 1 : -1) ?: $x <=> $y);
            $why = [];
            foreach ($ids as $id) {
                $why[$id] = ['score' => self::WEIGHTS['history'], 'signals' => ['history' => self::WEIGHTS['history'], "order_$kind" => 0]];
            }
            return ['ids' => $ids, 'cards' => $ids, 'why' => $why, 'intent' => null, 'kind' => null, 'unknown' => $unknown];
        }
        // Questions about the whole catalogue: the database finds the list (CatalogIntent, unchanged)
        if ($kind !== null && !$useHistory && !$a['exact']) {
            $intent = CatalogIntent::build($kind, $q, $page);
            $ids = array_slice(array_values(array_unique(array_map('intval', $intent['ids']))), 0, self::INTENT_LIMIT);
            $why = [];
            foreach ($ids as $i => $id) {
                $why[$id] = ['score' => 0, 'signals' => ["catalog_$kind" => self::LIMIT - $i]];
            }
            return ['ids' => $ids, 'cards' => $ids, 'why' => $why, 'intent' => $intent, 'kind' => $kind, 'unknown' => $unknown];
        }

        $sig = [];
        $add = function (int $id, string $signal, ?int $points = null) use (&$sig, $catalog): void {
            if (isset($catalog[$id]) && !isset($sig[$id][$signal])) {
                $sig[$id][$signal] = $points ?? self::WEIGHTS[$signal];
            }
        };
        foreach ($a['exact'] as $id => $signal) {
            $add($id, $signal);
        }
        foreach ($a['mention'] as $id) {
            $add($id, 'model_mention');
        }
        foreach ($page['ids'] as $id) {
            $add((int) $id, 'page');
        }
        if ($useHistory) {
            foreach ($history as $id) {
                $add($id, 'history');
            }
        }

        // The products in focus: named outright, on the page, or referred back to
        $focus = array_values(array_unique(array_merge(array_keys($a['exact']), array_map('intval', $page['ids']), $useHistory ? $history : [])));
        $themes = array_values(array_unique(array_map(fn($id) => $catalog[$id]['theme'], $focus)));

        // Words: the store search finds the candidates (typos, synonyms, use cases), the words decide the points
        if ($a['words']) {
            $s = Search::run(implode(' ', $a['words']), 20, false, true);
            // The product words a recognised synonym or use case stands for («henge opp bilde» means «bildehempe»)
            $expands = ['synonym' => [], 'use_case' => []];
            foreach ($s['matched'] as $m) {
                foreach (explode(' ', (string) $m['expands']) as $e) {
                    if ($e !== '' && !str_contains($e, ':')) {
                        $expands[$m['kind'] === 'bruksomrade' ? 'use_case' : 'synonym'][] = $e;
                    }
                }
            }
            // The search's own reading of the words counts too: typos it corrected («hammmer») and English it translated
            $words = $a['words'];
            foreach ([$s['corrected'] ?? null, $s['translated'] ?? null] as $alt) {
                $words = is_string($alt) ? array_values(array_unique(array_merge($words, self::tokens($alt)))) : $words;
            }
            foreach ($s['ids'] as $id) {
                $tokens = $catalog[$id]['tokens'];
                $hit = self::wordScore($words, $tokens, $a['generic']);
                foreach ($expands as $signal => $terms) {
                    if ($terms && self::wordScore($terms, $tokens, []) > 0) {
                        $add($id, $signal);
                        $hit = max($hit, self::WEIGHTS['category']); // the product word the customer meant, in other words
                    }
                }
                if ($hit > 0) {
                    $add($id, $hit >= self::WEIGHTS['category'] ? 'category' : 'generic_word', $hit);
                }
            }
        }

        // Accessories the customer asks for, around the products in focus
        $byRelation = [];
        if ($a['accessories'] && $focus) {
            $rel = self::relations($focus);
            foreach ($a['accessories'] as $acc) {
                $want = self::ACCESSORIES[$acc];
                if (array_filter($focus, fn($id) => self::nameHas($catalog[$id]['tokens'], $want))) {
                    continue; // a product in focus already is that accessory (the charger on its own page)
                }
                $found = 0;
                foreach ($rel['fits'] as [$from, $to]) {
                    if ($found < 2 && !in_array($to, $rel['not'], true) && self::nameHas($catalog[$to]['tokens'] ?? [], $want)) {
                        $add($to, 'relation');
                        $found++;
                    }
                }
                if ($found > 0) {
                    $byRelation[] = $want;
                } else {
                    // No relation in the catalogue: the best search hit for the accessory from the same brand (or theme), never one
                    // the catalogue documents as not fitting. It is a candidate, not a claim: the facts say the fit is not documented.
                    $brands = array_filter(array_map(fn($id) => $catalog[$id]['brand'], $focus));
                    foreach (Search::run($acc, 20, false, true)['ids'] as $id) {
                        $p = $catalog[$id];
                        $same = $brands ? in_array($p['brand'], $brands, true) : in_array($p['theme'], $themes, true);
                        if ($same && !in_array($id, $focus, true) && !in_array($id, $rel['not'], true) && self::nameHas($p['tokens'], $want)) {
                            $add($id, 'requirement');
                            break;
                        }
                    }
                }
            }
        }

        // «Noe lignende»: the narrowest category of the first product in focus
        if ($a['similar'] && $focus) {
            foreach (self::sameCategory($focus[0], $catalog) as $id) {
                $add($id, 'similar');
            }
        }

        // Penalties and the brand
        $codes = $a['codes'];
        $matchedCodes = array_filter($codes, fn($c) => isset($a['codeHits'][$c]));
        $aboutFocus = $useHistory || $a['accessories'] || in_array($page['kind'], ['byggelab', 'sammenlign', 'guide'], true);
        foreach ($sig as $id => $s) {
            $p = $catalog[$id];
            $structural = (bool) array_intersect_key($s, array_flip(self::STRUCTURAL));
            if ($p['brand'] !== '' && str_contains($a['text'], mb_strtolower($p['brand']))) {
                $sig[$id]['brand'] = self::WEIGHTS['brand'];
            }
            if (!$structural && $matchedCodes && $p['code'] !== null && !in_array($p['code'], $matchedCodes, true) && !isset($s['model_mention'])) {
                $sig[$id]['other_model'] = self::WEIGHTS['other_model'];
            }
            if (isset($s['model_mention']) && (isset($s['relation']) || isset($s['requirement']))) {
                unset($sig[$id]['model_mention']); // the same link to the named product, counted once
            }
            // The catalogue's relations said which of these fit: a word hit for the same accessory that isn't related is left out
            foreach ($byRelation as $want) {
                if (!$structural && self::nameHas($p['tokens'], $want)) {
                    $sig[$id]['unrelated_accessory'] = self::WEIGHTS['unrelated_accessory'];
                }
            }
            if (!$structural && $aboutFocus && $themes && !in_array($p['theme'], $themes, true)) {
                $sig[$id]['wrong_theme'] = self::WEIGHTS['wrong_theme'];
            }
        }

        // A model code the catalogue doesn't have («DUC254»): the customer asked for that model, so word hits are not an answer
        if ($codes && !$matchedCodes) {
            $sig = array_filter($sig, fn($s) => (bool) array_intersect_key($s, array_flip(self::STRUCTURAL)));
        }

        // Rank: the products in focus before what was found around them (an asked-for battery comes after its lamp), then
        // score, then the order the signals were added in, then ID
        $order = array_flip(array_keys($sig));
        $rows = [];
        foreach ($sig as $id => $s) {
            $rows[] = ['id' => $id, 'score' => array_sum($s), 'signals' => $s, 'structural' => (bool) array_intersect_key($s, array_flip(self::STRUCTURAL)),
                'focus' => in_array($id, $focus, true)];
        }
        usort($rows, fn($x, $y) => $y['focus'] <=> $x['focus'] ?: $y['score'] <=> $x['score'] ?: $order[$x['id']] <=> $order[$y['id']] ?: $x['id'] <=> $y['id']);
        $top = $rows ? max(array_column($rows, 'score')) : 0;
        $rows = array_values(array_filter($rows, fn($r) => $r['score'] >= self::MIN_SCORE && ($r['structural'] || $r['score'] >= $top * self::RELATIVE)));
        $rows = array_slice($rows, 0, self::LIMIT);

        $ids = array_column($rows, 'id');
        $why = [];
        foreach ($rows as $r) {
            $why[$r['id']] = ['score' => $r['score'], 'signals' => $r['signals']];
        }
        return ['ids' => $ids, 'cards' => $ids, 'why' => $why, 'intent' => null, 'kind' => null, 'unknown' => $unknown];
    }

    /** The catalogue as the retriever needs it, read once per request. */
    private static function catalog(): array
    {
        static $rows = null;
        if ($rows !== null) {
            return $rows;
        }
        $rows = [];
        foreach (db()->query('SELECT id, sku, brand, theme, name, price_ore FROM products') as $p) {
            $tokens = self::tokens((string) preg_replace(['/\(ikke[^)]*\)/iu', '/passer\s+ikke.*$/iu'], ' ', (string) $p['name']));
            $code = null;
            foreach ($tokens as $t) {
                if (self::isCode($t)) {
                    $code = $t;
                    break;
                }
            }
            $rows[(int) $p['id']] = ['sku' => mb_strtolower(trim((string) $p['sku'])), 'brand' => trim((string) $p['brand']), 'theme' => (string) $p['theme'],
                'name' => self::normal((string) $p['name']), 'tokens' => $tokens, 'code' => $code, 'price' => (int) $p['price_ore']];
        }
        return $rows;
    }

    /** What the question says: codes, SKUs, the exact products named, references, accessories and the words to search for. */
    private static function analyse(string $q, array $catalog): array
    {
        $text = self::normal($q);
        $tokens = self::tokens($q);
        $exact = [];
        $mention = [];
        $codes = [];
        $codeHits = [];
        $codeKnown = [];
        foreach ($tokens as $t) {
            if (self::isCode($t)) {
                $codes[] = $t;
            }
        }
        $codes = array_values(array_unique($codes));
        foreach ($codes as $c) {
            $own = array_keys(array_filter($catalog, fn($p) => $p['code'] === $c));
            $in = array_keys(array_filter($catalog, fn($p) => $p['code'] !== $c && in_array($c, $p['tokens'], true)));
            if (count($own) + count($in) > self::MODEL_NAMES && count($own) > self::MODEL_NAMES) {
                $codeKnown[$c] = true;
                continue; // a brand or a series number («1852»), not one model
            }
            if ($own || $in) {
                $codeKnown[$c] = true;
            }
            foreach ($own as $id) {
                $exact[$id] = 'model';
                $codeHits[$c] = true;
            }
            foreach ($in as $id) {
                $mention[] = $id;
            }
        }
        foreach ($tokens as $t) {
            if (preg_match('/^\d{5,}$/', $t)) {
                foreach ($catalog as $id => $p) {
                    if ($p['sku'] === $t) {
                        $exact[$id] = 'sku';
                    }
                }
            }
        }
        foreach ($catalog as $id => $p) {
            if (!isset($exact[$id]) && substr_count($p['name'], ' ') >= 1 && str_contains(" $text ", " {$p['name']} ")) {
                $exact[$id] = 'exact_name';
            }
        }
        $mention = array_values(array_diff(array_unique($mention), array_keys($exact)));

        $words = [];
        $accessories = [];
        foreach ($tokens as $t) {
            if (mb_strlen($t) < 3 || in_array($t, self::INTENT_WORDS, true) || in_array($t, $codes, true) || preg_match('/^\d+$/', $t)) {
                continue;
            }
            $words[] = $t;
            foreach (self::ACCESSORIES as $acc => $_) {
                if (self::stemOf($t) === $acc || $t === $acc) {
                    $accessories[] = $acc;
                }
            }
        }
        $generic = [];
        foreach (array_unique($words) as $w) {
            $n = 0;
            foreach ($catalog as $p) {
                if (self::wordScore([$w], $p['tokens'], []) > 0 && ++$n > self::GENERIC_NAMES) {
                    $generic[] = $w;
                    break;
                }
            }
        }
        return [
            'text' => $text, 'codes' => $codes, 'codeHits' => $codeHits, 'exact' => $exact, 'mention' => $mention,
            'unknown' => array_values(array_filter($codes, fn($c) => !isset($codeKnown[$c]) && self::surelyModel($c))),
            'words' => array_values(array_unique($words)), 'generic' => $generic, 'accessories' => array_values(array_unique($accessories)),
            'refPlural' => (bool) preg_match('/\b(de to|begge|dem|disse|de)\b/u', $text),
            'refSingular' => (bool) preg_match('/\b(den|det|samme|den andre)\b/u', $text),
            'similar' => (bool) preg_match('/\b(lignende|liknende|tilsvarende|alternativ\w*|noe annet)\b/u', $text),
        ];
    }

    /** Points for the question's words in a product name: specific words count fully, generic ones barely. */
    private static function wordScore(array $words, array $tokens, array $generic): int
    {
        $score = 0;
        $seen = [];
        foreach ($words as $w) {
            $stem = self::stemOf($w);
            if (isset($seen[$stem])) {
                continue; // «sagkjeden» and the search's «sagkjede» are one word
            }
            $seen[$stem] = true;
            foreach ($tokens as $t) {
                // The word itself, its base form with a short ending, or the first part of a compound («bilde» in «bildehempe»)
                if ($t === $w || $t === $stem || (mb_strlen($stem) >= 5 && str_starts_with($t, $stem) && mb_strlen($t) - mb_strlen($stem) <= 3)
                    || (mb_strlen($w) >= 5 && str_starts_with($t, $w))) {
                    $score += in_array($w, $generic, true) ? self::WEIGHTS['generic_word'] : self::WEIGHTS['category'];
                    break;
                }
            }
        }
        return $score;
    }

    /** The Norwegian base form of a noun the simple way: «batteriet», «hanskene», «laderen» lose the ending. */
    private static function stemOf(string $w): string
    {
        foreach (['ene', 'ane', 'en', 'et', 'er', 'a', 'e'] as $end) {
            if (mb_strlen($w) - mb_strlen($end) >= 4 && str_ends_with($w, $end)) {
                $base = mb_substr($w, 0, mb_strlen($w) - mb_strlen($end));
                // «hanske» keeps its e: «hanskene» becomes «hanske», «laderen» becomes «lader»
                return $end === 'ene' && in_array($base . 'e', ['hanske', 'pære', 'lykte'], true) ? $base . 'e' : $base;
            }
        }
        return $w;
    }

    /** Is one of the words, or a compound ending in it («rundfil»), in the name? */
    private static function nameHas(array $tokens, array $want): bool
    {
        foreach ($tokens as $t) {
            foreach ($want as $w) {
                if ($t === $w || (mb_strlen($t) > mb_strlen($w) + 2 && str_ends_with($t, $w)) || str_starts_with($t, $w)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Letter prefixes of standards, sizes and battery types (IP67, GU10, CR2032, EN352, DN50), never a product model. */
    private const STANDARDS = ['ip', 'gu', 'mr', 'ba', 'bay', 'cr', 'lr', 'en', 'iso', 'din', 'dn', 'pn', 'nr', 'co', 'rj', 'usb', 'hdmi', 'aa', 'aaa'];

    /**
     * A code the shop can say it doesn't sell (feature 036): shaped like a product model (two letters or more before the digits,
     * or letters on both sides: DUC254, H9R), not a standard or size, not anywhere in the catalogue, and not a likely typo or
     * part of a code the catalogue has (same digits and close: H7RR, HR7, H7 for H7R). Single letter and digits (P120, K80,
     * E27, M8) are grits, sockets and threads, so they never count.
     */
    private static function surelyModel(string $c): bool
    {
        if (!preg_match('/^([a-z]{1,4})(\d{1,5})([a-z]{0,3})\d{0,2}$/', $c, $m) || strlen($c) < 3 || (strlen($m[1]) < 2 && $m[3] === '')
            || in_array($m[1], self::STANDARDS, true)) {
            return false;
        }
        static $known = null;
        if ($known === null) {
            $known = [];
            foreach (db()->query('SELECT name, sku FROM products') as $p) {
                foreach (array_merge(self::tokens((string) $p['name']), [mb_strtolower(trim((string) $p['sku']))]) as $t) {
                    if ($t !== '' && preg_match('/\d/', $t) && preg_match('/\p{L}/u', $t)) {
                        $known[$t] = true;
                    }
                }
            }
        }
        if (isset($known[$c])) {
            return false;
        }
        $digits = preg_replace('/\D/', '', $c);
        $sorted = function (string $x): string {
            $chars = str_split($x);
            sort($chars);
            return implode('', $chars);
        };
        foreach (array_keys($known) as $k) {
            $k = (string) $k;
            if (preg_replace('/\D/', '', $k) === $digits && (levenshtein($c, $k) <= 1 || $sorted($c) === $sorted($k)
                || str_starts_with($k, $c) || str_starts_with($c, $k))) {
                return false;
            }
        }
        return true;
    }

    /**
     * The unknown codes as the customer wrote them, with a brand right before («Makita DUC254») and up to two capitalised
     * words after («H9R Signature»). Letters, digits, spaces and hyphens only, at most 40 characters.
     * @param string[] $codes
     * @return string[]
     */
    private static function asWritten(string $q, array $codes): array
    {
        $out = [];
        foreach ($codes as $c) {
            $shown = strtoupper($c);
            if (preg_match('/(?:(?<![\p{L}\d])(\p{Lu}\p{Ll}+)\s+)?(?<![\p{L}\d])((?i:' . preg_quote($c, '/') . '))(?![\p{L}\d])((?:\s+\p{Lu}[\p{L}\d]*){0,2})/u', $q, $m, PREG_OFFSET_CAPTURE)) {
                // A capitalised word that starts the sentence («Finnes H9R?») is not a brand
                $brand = $m[1][1] >= 0 && $m[1][0] !== '' && !preg_match('/(^|[.!?]\s+)$/u', substr($q, 0, $m[1][1])) ? $m[1][0] . ' ' : '';
                $shown = trim($brand . strtoupper($m[2][0]) . ($m[3][0] ?? ''));
            }
            $out[] = mb_substr(trim((string) preg_replace('/[^\p{L}\d\s-]+/u', '', $shown)), 0, 40);
        }
        return array_values(array_unique($out));
    }

    /** A model code: letters and digits together (H5R, CS720, 3/8″ is not), or a number of four to six digits (21700). */
    public static function isCode(string $t): bool
    {
        return (bool) preg_match('/^(?=\p{L}*\d)(?=\d*\p{L})[\p{L}\d]{2,10}$/u', $t) && !preg_match('/^\d+(mm|cm|m|kg|g|l|w|v|pk|stk|oz|x\d+)$/u', $t)
            || (bool) preg_match('/^\d{4,6}$/', $t);
    }

    private static function normal(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[^\p{L}\d]+/u', ' ', mb_strtolower($s))));
    }

    /** The words of a text, lower case, without punctuation. @return string[] */
    public static function tokens(string $s): array
    {
        $n = self::normal($s);
        return $n === '' ? [] : explode(' ', $n);
    }

    /** Documented and rule-based fits from the products in focus, and the products documented as not fitting them. */
    private static function relations(array $focus): array
    {
        $in = implode(',', array_map('intval', $focus));
        $fits = [];
        $not = [];
        foreach (db()->query("SELECT product_a, product_b, kind FROM relations WHERE product_a IN ($in) AND kind IN ('passer_sammen', 'passer_ikke')
            AND level IN ('dokumentert', 'regelutledet') ORDER BY level = 'dokumentert' DESC, score DESC, product_b") as $r) {
            $r['kind'] === 'passer_ikke' ? $not[] = (int) $r['product_b'] : $fits[] = [(int) $r['product_a'], (int) $r['product_b']];
        }
        return ['fits' => $fits, 'not' => $not];
    }

    /**
     * Other products in the narrowest category of $id, nearest in price first. When at least two of them share the product's
     * type word (the last word of its name, «hodelykt»), only those: a category can hold both headlamps and flashlights.
     */
    private static function sameCategory(int $id, array $catalog): array
    {
        $cat = db()->prepare('SELECT c.id FROM product_categories pc JOIN categories c ON c.id = pc.category_id JOIN product_categories pc2 ON pc2.category_id = c.id
            WHERE pc.product_id = ? GROUP BY c.id HAVING COUNT(pc2.product_id) > 1 ORDER BY COUNT(pc2.product_id) LIMIT 1');
        $cat->execute([$id]);
        $c = $cat->fetchColumn();
        if ($c === false) {
            return [];
        }
        $st = db()->prepare('SELECT p.id FROM product_categories pc JOIN products p ON p.id = pc.product_id WHERE pc.category_id = ? AND p.id <> ?
            ORDER BY ABS(CAST(p.price_ore AS SIGNED) - (SELECT CAST(price_ore AS SIGNED) FROM products WHERE id = ?)), p.id');
        $st->execute([(int) $c, $id, $id]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $type = end($catalog[$id]['tokens']);
        $same = is_string($type) && mb_strlen($type) >= 5 && !preg_match('/\d/', $type)
            ? array_values(array_filter($ids, fn($o) => in_array($type, $catalog[$o]['tokens'] ?? [], true))) : [];
        return array_slice(count($same) >= 2 ? $same : $ids, 0, 4);
    }
}
