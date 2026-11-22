<?php
declare(strict_types=1);

/**
 * What the database says is true, checked in Søren's answer before the customer sees it (feature 035).
 * The model explains; prices, totals and «passer ikke» come from the database.
 *
 * Totals: the server adds the prices in øre (integers, no rounding) and gives the model a [[sum:ID+ID]] reference. A plain
 * «hva koster de to sammen?» is answered by the server alone. If an answer to a total question leaves the total out, it is added.
 *
 * Every answer sentence is checked against the products in the facts, identified by reference, model code or the words
 * that set one product's name apart from the others':
 * 1. An amount in kroner the model wrote itself must be the price of the product the sentence names, the total of the
 *    products it names, or an amount that stands in the facts. A wrong amount for one named product becomes that product's
 *    own price reference; otherwise it is replaced by a pointer to the product card. A price is never moved to another product.
 * 2. A price reference next to another product's name is moved to the named product.
 * 3. A sentence that says two products fit, when the catalogue documents that they don't («passer ikke»), is replaced
 *    by the documented fact. Only «passer ikke» relations trigger this, so an uncertain fit is never "corrected" into a claim.
 *
 * Model answers are also validated as a whole (feature 040, violation()): a sentence that names a product the catalogue
 * doesn't have or that is outside the products the answer is grounded on, recommends a product of another type than the
 * one asked for, states a specification the catalogue has no evidence for, carries an unfilled note or a prompt label, or
 * contradicts a documented fit (also through «den», «denne kabelen» about the product named before), rejects the answer.
 * The caller then shows the server's own answer instead. Nothing is cut out of a sentence to make it pass.
 */
final class AnswerGuard
{
    private const PRICE_WORD = '/\b(koster|kostar|kostnad\w*|pris\w*|betale|betaler|blir det|blir)\b/u';
    private const TOGETHER = '/\b(sammen|tilsammen|hele pakken|pakken|alt|alle|begge|totalt?|totalen|summen|sum)\b/u';
    private const TOTAL_WORD = '/\b(totalt|totalen|totalpris\w*|summen|samlet pris|til sammen)\b/u';

    /** Words a plain total question can consist of. Anything else means there is more to answer than the sum. */
    private const TOTAL_ONLY = ['hva', 'koster', 'kostar', 'blir', 'det', 'de', 'dem', 'disse', 'to', 'tre', 'fire', 'begge', 'alle', 'alt', 'sammen',
        'tilsammen', 'til', 'totalt', 'total', 'totalen', 'sum', 'summen', 'hele', 'pakken', 'hvor', 'mye', 'må', 'jeg', 'betale', 'for', 'av',
        'er', 'pris', 'prisen', 'samlet', 'og', 'så', 'da', 'ok', 'takk', 'hei', 'kr', 'kroner'];

    private const AMOUNT = '/(?<![\d,.])(\d{1,3}(?:[ \x{00A0}\x{202F}.]\d{3})+|\d+)(?:,(\d{1,2})|,-)?\s?(?:kr|kroner|nok)(?!\p{L})/iu';
    private const FIT = '/\b(passer|passar|kompatib\w*|fungerer|virker|kan brukes|går (?:fint )?(?:sammen|med|til)|anbefaler|ved hjelp av|fits?|compatible|works with|recommend)\b/u';
    private const NEGATION = '/\b(ikke|ingen|aldri|neppe|not|never|no)\b|n\'t\b/u';
    private const GROUP = '/\b(begge|dem|de|disse|alle|lyktene|lampene|modellene|produktene|varene|hodelyktene)\b/u';

    /** A word that points back to a product named earlier in the answer. */
    private const PRONOUN = '/\b(den|denne|dette|disse|dem|kabelen|laderen|batteriet|lykten|lampen|modellen|it|this|that|these|they|them)\b/u';
    /** Words that make a number advice rather than a claim about a product («minst 500 lumen»). */
    private const THRESHOLD = '/\b(minst|over|under|mer enn|mindre enn|opptil|inntil|rundt|ca|cirka|omtrent|at least|more than|less than|up to|around|about)\s*$/u';
    /** Words a recommendation question uses: then the products named must be of the type asked for. */
    private const RECOMMEND = '/\b(anbefal\w*|egner\w*|best til|bra til|god til|forslag|recommend\w*|suggest\w*|good for|best for)\b/u';
    /** Technical words that look like model codes but name no product. */
    private const TECH = '/^(usbc|usb|ip\d{1,2}|ipx\d|li|lion|liion|led|cob|aa|aaa|cri\d*|\d+(?:mm|cm|m|km|kg|g|l|w|wh|v|mah|lm|h|t|x|stk|pk)|\d+x\d+)$/u';
    /** Units of a technical number, grouped so «1 kg» and «1000 g» compare. */
    private const UNITS = '/(\d+(?:[ \x{00A0}.]\d{3})*(?:,\d+)?)\s?(gram|g|kg|lumen|lm|mah|timer|time|hours|hour|h|volt|v)(?![\p{Ll}\d])/iu';

    /** Does the customer ask what several products cost together? */
    public static function isTotal(string $q): bool
    {
        $q = mb_strtolower($q);
        return (bool) preg_match(self::TOTAL_WORD, $q) || (preg_match(self::PRICE_WORD, $q) && preg_match(self::TOGETHER, $q));
    }

    /** A total question and nothing else: the server answers it without the model. */
    public static function pureTotal(string $q): bool
    {
        return self::isTotal($q) && !array_diff(ProductRetriever::tokens($q), self::TOTAL_ONLY);
    }

    /**
     * One of each product, summed in øre. A product that isn't in the catalogue, or has no price (0 means the shop has no
     * price for it, never «free»), is missing, and then there is no total.
     * @param int[] $ids
     * @return array{ids:int[], ore:int, missing:int[]}
     */
    public static function total(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (!$ids) {
            return ['ids' => [], 'ore' => 0, 'missing' => []];
        }
        $prices = db()->query('SELECT id, price_ore FROM products WHERE id IN (' . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_KEY_PAIR);
        $sum = 0;
        $missing = [];
        foreach ($ids as $id) {
            $ore = (int) ($prices[$id] ?? 0);
            $ore > 0 ? $sum += $ore : $missing[] = $id;
        }
        return ['ids' => $ids, 'ore' => $missing ? 0 : $sum, 'missing' => $missing];
    }

    /** The line in the facts for a total question. Empty when there aren't two products with prices. */
    public static function totalFact(array $ids): string
    {
        $t = self::total($ids);
        if (count($t['ids']) < 2) {
            return '';
        }
        if ($t['missing']) {
            return 'SUM: kan ikke regnes ut, fordi butikken mangler pris på ' . implode(', ', array_map(fn($id) => "[$id]", $t['missing'])) . '. Si det til kunden.';
        }
        return 'SUM regnet ut av butikken, en av hver av ' . implode(', ', array_map(fn($id) => "[$id]", $t['ids'])) . ': ' . ProductRef::sum($t['ids'])
            . '. Når kunden spør hva det koster til sammen, skriver du dette merket.';
    }

    /** The total in words for the customer, with references, or null when it can't be worked out. */
    public static function totalSentence(array $ids): ?string
    {
        $t = self::total($ids);
        if (count($t['ids']) < 2 || $t['missing']) {
            return null;
        }
        $names = array_map(fn($c) => self::short((string) $c['name']), Catalog::cards($t['ids']));
        $last = array_pop($names);
        $list = implode(', ', $names) . ' ' . t('og') . ' ' . $last;
        $each = implode(', ', array_map(fn($id) => ProductRef::price($id), $t['ids']));
        return t('Til sammen koster {list} {sum}, en av hver ({each}).', ['list' => $list, 'sum' => ProductRef::sum($t['ids']), 'each' => $each]);
    }

    /**
     * Models the customer named that the catalogue doesn't have (feature 036, found by ProductRetriever): one complete
     * sentence from the server, so the model is not asked to finish it. Empty when there are none.
     * @param string[] $names as the customer wrote them
     */
    public static function notSold(array $names): string
    {
        if (!$names) {
            return '';
        }
        $last = array_pop($names);
        $list = $names ? implode(', ', $names) . ' ' . t('eller') . ' ' . $last : $last;
        return t('Vi har dessverre ikke {name} i sortimentet.', ['name' => $list]);
    }

    /** The line in the facts: the customer has been told, and nothing is to be said about these models. */
    public static function notSoldFact(array $names): string
    {
        return $names ? 'IKKE I SORTIMENTET: ' . implode(', ', $names) . '. Butikken har allerede sagt til kunden at Sør-Tre ikke selger '
            . (count($names) > 1 ? 'disse' : 'denne') . '. Si ingenting om hvordan ' . (count($names) > 1 ? 'de' : 'den') . ' er, og sammenlign ikke med '
            . (count($names) > 1 ? 'dem' : 'den') . '. Svar på resten av spørsmålet.' : '';
    }

    /**
     * What the checks need about one answer: the products in the facts, their prices and the words that identify them,
     * the products the answer is about, and the documented «passer ikke» pairs among them.
     */
    public static function context(array $focus, string $facts): array
    {
        $allowed = ProductRef::allowed($facts);
        $cands = [];
        if ($allowed) {
            foreach (db()->query('SELECT id, name, brand, price_ore FROM products WHERE id IN (' . implode(',', $allowed) . ')') as $p) {
                $tokens = ProductRetriever::tokens((string) preg_replace(['/\(ikke[^)]*\)/iu', '/passer\s+ikke.*$/iu', '/\bnb\b.*$/iu'], ' ', (string) $p['name']));
                $brand = mb_strtolower(trim((string) $p['brand']));
                $head = current(array_filter($tokens, fn($t) => $t !== $brand && !ProductRetriever::isCode($t) && mb_strlen($t) >= 3)) ?: null;
                $code = current(array_filter($tokens, fn($t) => ProductRetriever::isCode($t))) ?: null;
                $cands[(int) $p['id']] = ['name' => (string) $p['name'], 'tokens' => $tokens, 'head' => $head, 'code' => $code, 'price' => (int) $p['price_ore']];
            }
        }
        foreach ($cands as $id => &$c) {
            $others = [];
            foreach ($cands as $o => $oc) {
                if ($o !== $id) {
                    $others = array_merge($others, $oc['tokens']);
                }
            }
            $c['distinct'] = array_values(array_filter(array_diff($c['tokens'], $others), fn($t) => mb_strlen($t) >= 2 && !preg_match('/^\d{1,3}$/', $t)));
            // The model code a name starts with is its own, even when another name mentions it («batteri for H7R Core»)
            $c['codeUnique'] = $c['code'] !== null && count(array_filter($cands, fn($oc) => $oc['code'] === $c['code'])) === 1;
        }
        unset($c);
        $notFit = [];
        if ($cands) {
            $in = implode(',', array_keys($cands));
            foreach (db()->query("SELECT product_a, product_b, reason FROM relations WHERE kind = 'passer_ikke' AND product_a IN ($in) AND product_b IN ($in)") as $r) {
                $notFit[$r['product_a'] . '|' . $r['product_b']] = $notFit[$r['product_b'] . '|' . $r['product_a']] = (string) $r['reason'];
            }
        }
        preg_match_all(self::AMOUNT, $facts, $m, PREG_SET_ORDER);
        $literal = array_map(fn($x) => self::ore($x), $m);
        $focus = array_values(array_filter(array_map('intval', $focus), fn($id) => isset($cands[$id])));
        $total = self::total($focus);
        return ['cands' => $cands, 'focus' => $focus, 'notFit' => $notFit, 'literal' => $literal,
            'focusTotal' => count($focus) >= 2 && !$total['missing'] ? $total['ore'] : null];
    }

    /** Checks and corrects a piece of the answer, sentence by sentence. The text still has references; they are filled in after. */
    public static function check(string $text, array $ctx): string
    {
        if (!$ctx['cands'] || trim($text) === '') {
            return $text;
        }
        $parts = preg_split('/(?<=[.!?])(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        foreach ($parts as $i => $s) {
            if (trim($s) !== '') {
                $parts[$i] = self::sentence($s, $ctx);
            }
        }
        return implode('', $parts);
    }

    private static function sentence(string $s, array $ctx): string
    {
        $named = self::named($s, $ctx);
        $byRef = self::referenced($s);

        // 3. «Passer» about a pair the catalogue documents as not fitting
        $lower = mb_strtolower($s);
        $claims = array_filter(preg_split('/[,;:]|\s(?:men|but|selv om)\s/u', $lower) ?: [], fn($c) => preg_match(self::FIT, $c) && !preg_match(self::NEGATION, $c));
        if ($claims && $ctx['notFit']) {
            $about = array_values(array_unique(array_merge($named, $byRef)));
            $others = preg_match(self::GROUP, $lower) ? array_values(array_unique(array_merge($about, $ctx['focus']))) : $about;
            $wrong = [];
            foreach ($about as $x) {
                foreach ($others as $y) {
                    if ($x !== $y && isset($ctx['notFit']["$x|$y"]) && !isset($wrong["$y|$x"])) {
                        $wrong["$x|$y"] = [$x, $y];
                    }
                }
            }
            if ($wrong) {
                $lines = [];
                foreach ($wrong as [$x, $y]) {
                    $lines[] = t('{a} passer ikke til {b}. Det har Sør-Tre dokumentert.', ['a' => self::label($x, $ctx), 'b' => self::label($y, $ctx)]);
                }
                return implode(' ', array_unique($lines)) . (preg_match('/\s$/u', $s) ? ' ' : '');
            }
        }

        // 2. A price reference next to another product's name
        if (count($named) === 1 && preg_match_all('/\[\[pris:(\d{1,9})\]\]/u', $s, $refs) === 1) {
            $ref = (int) $refs[1][0];
            if ($ref !== $named[0] && !in_array($ref, $named, true) && isset($ctx['cands'][$named[0]])) {
                $s = str_replace("[[pris:$ref]]", ProductRef::price($named[0]), $s);
            }
        }

        // 1. Amounts the model wrote itself
        $prices = array_map(fn($id) => $ctx['cands'][$id]['price'], array_values(array_unique(array_merge($named, $byRef))));
        return (string) preg_replace_callback(self::AMOUNT, function ($m) use ($ctx, $named, $byRef, $prices) {
            $ore = self::ore($m);
            $mentioned = array_values(array_unique(array_merge($named, $byRef)));
            $ok = in_array($ore, $prices, true) || in_array($ore, $ctx['literal'], true)
                || (count($mentioned) >= 2 && self::total($mentioned)['ore'] === $ore)
                || (!$mentioned && (in_array($ore, array_column($ctx['cands'], 'price'), true) || $ore === $ctx['focusTotal']));
            if ($ok) {
                return $m[0];
            }
            return count($named) === 1 ? ProductRef::price($named[0]) : t('(prisen står på produktkortet)');
        }, $s);
    }

    /**
     * What validation needs besides context(): the question's product type for a recommendation, the documented fits,
     * the evidence text per product (name, Sør-Tre's product text, verified values), and the words the answer may use
     * without naming a product (the question, the page and the facts).
     */
    public static function grounding(array $ctx, string $question, string $known): array
    {
        $ctx['type'] = null;
        $l = mb_strtolower($question);
        if (preg_match(self::RECOMMEND, $l)) {
            $ctx['type'] = CatalogIntent::typeOf($l) ?? CatalogIntent::typeOf((string) Search::fromEnglish($l, [])[0]);
        }
        $ctx['fit'] = [];
        $ctx['evidence'] = [];
        if ($ctx['cands']) {
            $in = implode(',', array_keys($ctx['cands']));
            foreach (db()->query("SELECT product_a, product_b FROM relations WHERE kind = 'passer_sammen' AND level = 'dokumentert' AND product_a IN ($in) AND product_b IN ($in)") as $r) {
                $ctx['fit'][$r['product_a'] . '|' . $r['product_b']] = $ctx['fit'][$r['product_b'] . '|' . $r['product_a']] = true;
            }
            $text = db()->query("SELECT id, plain_text FROM products WHERE id IN ($in)")->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($ctx['cands'] as $id => $c) {
                $e = $c['name'] . ' ' . ($text[$id] ?? '');
                foreach (Catalog::specs((int) $id) as $sp) {
                    if ($sp['verifisert'] !== null) {
                        $e .= ' ' . $sp['verifisert']['value_text'] . ' ' . ($sp['unit'] ?? '');
                    }
                }
                $ctx['evidence'][$id] = $e; // own case kept: «250 gramTransport» in Sør-Tre's text ends the unit at the capital
            }
            $ctx['related'] = array_map('intval', db()->query("SELECT DISTINCT product_b FROM relations WHERE kind = 'passer_sammen' AND product_a IN ("
                . implode(',', $ctx['focus'] ?: [0]) . ')')->fetchAll(PDO::FETCH_COLUMN));
        }
        $ctx['related'] ??= [];
        $ctx['known'] = array_flip(array_map(fn($t) => str_replace('-', '', $t), self::words(mb_strtolower($question . ' ' . $known))));
        return $ctx;
    }

    /**
     * Why a sentence of a model answer can't be shown, or null. Checked on the model's own text, before any cleaning.
     * $state carries the products named in the sentences before, so «den», «denne kabelen» are read against them.
     */
    public static function violation(string $raw, array &$state, array $ctx): ?string
    {
        if (trim($raw) === '') {
            return null;
        }
        if (self::placeholder($raw)) {
            return 'plassholder';
        }
        if (($code = self::unknownProduct($raw, $ctx)) !== null) {
            return $code;
        }
        $s = ProductRef::clean($raw, array_keys($ctx['cands']));
        $lower = mb_strtolower($s);
        $named = array_values(array_unique(array_merge(self::named($s, $ctx), self::referenced($raw))));
        $named = array_values(array_filter($named, fn($id) => isset($ctx['cands'][$id]) && self::clearly($id, $s, $ctx)));
        if ($named) {
            $state['last'] = $named;
        }
        $points = (bool) preg_match(self::PRONOUN, $lower);
        $about = $named ?: ($points ? ($state['last'] ?? []) : []);
        // A product of another type than the one the customer asked to have recommended
        if ($ctx['type'] !== null) {
            foreach ($named as $id) {
                if (!in_array($id, $ctx['type'][0], true) && !in_array($id, $ctx['related'], true)) {
                    return 'feil_type';
                }
            }
        }
        // Fit: a claim that reverses what Sør-Tre documented, in this sentence or about the product named before
        foreach (preg_split('/[;:]|\s(?:men|but|selv om|although)\s/u', $lower) ?: [] as $clause) {
            if (!preg_match(self::FIT, $clause)) {
                continue;
            }
            $negated = (bool) preg_match(self::NEGATION, $clause);
            $with = array_values(array_unique(array_merge($about, $ctx['focus'])));
            foreach ($about as $x) {
                foreach ($with as $y) {
                    if ($x !== $y && !$negated && isset($ctx['notFit']["$x|$y"])) {
                        return 'motsier_passer_ikke';
                    }
                    if ($x !== $y && $negated && isset($ctx['fit']["$x|$y"])) {
                        return 'motsier_passer';
                    }
                }
            }
        }
        // Specifications: numbers only about a product the sentence names or points back to («minst 500 lumen» is advice);
        // a charging method is always about a product, so without a name it is the products named before or in focus
        $earlier = ($state['last'] ?? []) ?: $ctx['focus'];
        $subject = $named ?: ($points ? $earlier : []);
        if (($why = self::unsupported($lower, $named ?: $earlier, $subject, $ctx)) !== null) {
            return $why;
        }
        return null;
    }

    /**
     * Is the product named, not just described? Its reference, its model code, or one of its own words that the products
     * in focus don't have in their own text («magnetisk ladekabel» describes H7R Core's charging, it doesn't name a cable).
     */
    private static function clearly(int $id, string $s, array $ctx): bool
    {
        $c = $ctx['cands'][$id];
        $tokens = ProductRetriever::tokens((string) preg_replace('/\[\[[^\]]*\]\]/u', ' ', $s));
        if (in_array($id, self::referenced($s), true) || in_array($id, $ctx['focus'], true)) {
            return true;
        }
        // A code that is only a number («1000») is a code only next to the name's first word («Fokus 1000»), not in «1000 lumen»
        if ($c['codeUnique'] && in_array($c['code'], $tokens, true) && (preg_match('/\p{L}/u', (string) $c['code']) || ($c['head'] !== null && self::hasWord($tokens, $c['head'])))) {
            return true;
        }
        $hits = array_values(array_intersect($c['distinct'], $tokens));
        $focusText = mb_strtolower(implode(' ', array_map(fn($f) => $ctx['evidence'][$f] ?? '', array_diff($ctx['focus'], [$id]))));
        foreach ($hits as $h) {
            if (!preg_match('/\b' . preg_quote($h, '/') . '/u', $focusText)) {
                return true;
            }
        }
        return false;
    }

    /** An unfilled note: any bracket left after the valid references, a question in parentheses, a drafting mark or a prompt label. */
    public static function placeholder(string $raw): bool
    {
        $t = (string) preg_replace(['/\[\[(?:pris|lager):\d{1,9}\]\]|\[\[sum:[\d+]{3,99}\]\]|\[(?:pris|lager):\d{1,9}\]|\[\[?\d{5,9}\]\]?/u',
            '/\[([^\[\]\n]{1,120})\]\(\s*(?:https?:\/\/|www\.)[^)\s]*\s*\)/iu'], ['', '$1'], $raw);
        return (bool) preg_match('/[\[\]{}]|\([^()\n]*\?\s*\)|\b(?:TODO|TBD|FIXME|XXX)\b|(?<![\p{L}])\p{Lu}{5,}(?:\s\p{Lu}{4,})*\s?:/u', $t);
    }

    /**
     * A model code or product name the answer uses that is not in the facts: «Pentax K-5000». A code that is in the
     * catalogue but not among the products the answer is grounded on is also refused, since its facts were not checked.
     */
    private static function unknownProduct(string $raw, array $ctx): ?string
    {
        static $catalogue = null;
        if ($catalogue === null) {
            $catalogue = [];
            foreach (db()->query('SELECT name, brand FROM products')->fetchAll() as $p) {
                foreach (self::words(mb_strtolower($p['name'] . ' ' . $p['brand'])) as $w) {
                    $catalogue[str_replace('-', '', $w)] = true;
                }
            }
        }
        $own = [];
        foreach ($ctx['cands'] as $c) {
            foreach (self::words(mb_strtolower($c['name'])) as $w) {
                $own[str_replace('-', '', $w)] = true;
            }
        }
        $text = (string) preg_replace('/\[\[[^\]]*\]\]|\[\d{5,9}\]/u', ' ', $raw);
        foreach (self::words($text) as $w) {
            $code = mb_strtolower(str_replace('-', '', $w));
            // A compound with a Norwegian word in it («3,7V-ladestykker», «USB-C-opplading») is a description, not a code
            $isCode = preg_match('/\p{L}/u', $w) && preg_match('/\d/u', $w) && mb_strlen($code) >= 2 && !preg_match('/(?:^|-)\p{Ll}{4,}(?:-|$)/u', $w);
            $isName = preg_match('/^\p{Lu}{2,}-\p{Lu}{2,}$|^\p{Lu}\p{Ll}+\p{Lu}\p{Ll}+$/u', $w); // «LK-SLXW», «CoStar»
            if ((!$isCode && !$isName) || preg_match(self::TECH, $code) || isset($own[$code]) || isset($ctx['known'][$code])) {
                continue;
            }
            return isset($catalogue[$code]) ? 'utenfor_utvalget' : 'oppdiktet_produkt';
        }
        return null;
    }

    /** A charging method or a number with a unit said about products whose evidence doesn't have it. */
    private static function unsupported(string $lower, array $charging, array $subject, array $ctx): ?string
    {
        $raw = implode(' ', array_map(fn($id) => $ctx['evidence'][$id] ?? '', $charging));
        $evidence = (string) preg_replace('/usb[\s-]?c\b/u', 'usb-c', mb_strtolower($raw));
        foreach (['usb-c' => '/usb[\s-]?c\b/u', 'solcelle' => '/\bsolcelle\w*|\bsolar\b/u', 'aa-batterier' => '/\b(aa|aaa)[\s-]?(batteri\w*|batter\w+)?\b/u'] as $what => $re) {
            if ($charging && preg_match($re, $lower) && !preg_match(self::NEGATION, $lower)) {
                $found = match ($what) {
                    'usb-c' => str_contains($evidence, 'usb-c'),
                    'solcelle' => (bool) preg_match('/solcelle|solar/u', $evidence),
                    default => (bool) preg_match('/\b(aa|aaa)\b/u', $evidence),
                };
                if (!$found) {
                    return 'udokumentert_spesifikasjon';
                }
            }
        }
        if (!$subject) {
            return null;
        }
        $own = implode(' ', array_map(fn($id) => $ctx['evidence'][$id] ?? '', $subject));
        // A water rating («IP67») the catalogue doesn't have for the product
        preg_match_all('/\bip\s?(x?\d{1,2})\b/u', $lower, $ips);
        foreach ($ips[1] as $ip) {
            if (!preg_match('/\bip\s?' . $ip . '\b/iu', $own)) {
                return 'udokumentert_spesifikasjon';
            }
        }
        $have = self::measures($own);
        preg_match_all(self::UNITS, $lower, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($all as $m) {
            if (preg_match(self::THRESHOLD, mb_substr(substr($lower, 0, $m[0][1]), -24))) {
                continue; // «minst 500 lumen» is advice, not a claim about the product
            }
            [$value, $unit] = self::measure($m[1][0], $m[2][0]);
            if (!in_array($value, $have[$unit] ?? [], true)) {
                return 'udokumentert_spesifikasjon';
            }
        }
        return null;
    }

    /** @return array<string, list<string>> numbers in a text by unit group */
    private static function measures(string $t): array
    {
        $out = [];
        preg_match_all(self::UNITS, $t, $all, PREG_SET_ORDER);
        foreach ($all as $m) {
            [$v, $u] = self::measure($m[1], $m[2]);
            $out[$u][] = $v;
        }
        return $out;
    }

    /** A number and its unit in one form: grams, lumen, mAh, hours, volt. @return array{0: string, 1: string} */
    private static function measure(string $n, string $unit): array
    {
        $n = (string) preg_replace('/[ \x{00A0}]|\.(?=\d{3}\b)/u', '', $n);
        $v = (float) str_replace(',', '.', $n);
        $u = mb_strtolower($unit);
        [$group, $f] = match (true) {
            in_array($u, ['g', 'gram'], true) => ['g', 1],
            $u === 'kg' => ['g', 1000],
            in_array($u, ['lumen', 'lm'], true) => ['lm', 1],
            $u === 'mah' => ['mah', 1],
            in_array($u, ['volt', 'v'], true) => ['v', 1],
            default => ['h', 1],
        };
        return [rtrim(rtrim(number_format($v * $f, 2, '.', ''), '0'), '.'), $group];
    }

    /** Words of a text with hyphens kept inside a word («K-5000», «USB-C»). @return string[] */
    private static function words(string $s): array
    {
        preg_match_all('/[\p{L}\d]+(?:-[\p{L}\d]+)*/u', $s, $m);
        return $m[0];
    }

    /** Products a text names (see named()), for callers outside the guard such as FactAnswer. */
    public static function mentioned(string $s, array $ctx): array
    {
        return self::named($s, $ctx);
    }

    /** Products a sentence names: by their model code, or by the first word of the name together with a word only that name has. */
    private static function named(string $s, array $ctx): array
    {
        $tokens = ProductRetriever::tokens((string) preg_replace('/\[\[[^\]]*\]\]/u', ' ', $s));
        $out = [];
        foreach ($ctx['cands'] as $id => $c) {
            if (($c['codeUnique'] && in_array($c['code'], $tokens, true))
                || ($c['head'] !== null && self::hasWord($tokens, $c['head']) && array_intersect($c['distinct'], $tokens))) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /** The word, or an inflected form of a word of four letters or more («ladekabelen» for «ladekabel»). */
    private static function hasWord(array $tokens, string $word): bool
    {
        foreach ($tokens as $t) {
            if ($t === $word || (mb_strlen($word) >= 4 && str_starts_with($t, $word) && preg_match('/^(en|et|a|ene|er|ne|s)$/u', mb_substr($t, mb_strlen($word))))) {
                return true;
            }
        }
        return false;
    }

    /** Products a sentence points to with a reference or a bare ID. */
    private static function referenced(string $s): array
    {
        preg_match_all('/\[\[(?:pris|lager):(\d{1,9})\]\]|\[(\d{5,9})\]/u', $s, $m);
        return array_values(array_unique(array_map('intval', array_filter(array_merge($m[1], $m[2]), fn($x) => $x !== ''))));
    }

    /** An amount match in øre. */
    private static function ore(array $m): int
    {
        return (int) preg_replace('/\D/', '', $m[1]) * 100 + (int) str_pad((string) ($m[2] ?? ''), 2, '0');
    }

    /** The short name, or the full name (without notes such as «NB!») when another product in the facts has the same short name. */
    private static function label(int $id, array $ctx): string
    {
        $short = self::short($ctx['cands'][$id]['name']);
        foreach ($ctx['cands'] as $o => $c) {
            if ($o !== $id && self::short($c['name']) === $short) {
                return trim((string) preg_replace('/[.\s]*\bNB!?.*$/u', '', $ctx['cands'][$id]['name']));
            }
        }
        return $short;
    }

    /** The product name up to the first dash: «Ledlenser H5R Core». */
    private static function short(string $name): string
    {
        return trim((string) preg_split('/\s[–-]\s/u', $name)[0]);
    }
}
