<?php
declare(strict_types=1);

/**
 * Factual product questions answered by the server, without the language model (feature 039).
 * Price, stock, specifications, comparisons, fit and product identity are read from the database and written as finished
 * sentences. Prices and stock stay references ([[pris:ID]], [[lager:ID]]) that ProductRef fills in, as everywhere else.
 * The model is asked only when the question also wants advice or explanation («hvorfor», «hvordan», «anbefaler du»),
 * or names no product the server can identify.
 *
 * Fit has a fixed order: a documented «passer ikke» means no; «passer sammen» documented by Sør-Tre means yes; a rule-based
 * fit is reported as likely and not confirmed; no relation is unknown, and unknown is said as unknown.
 */
final class FactAnswer
{
    /** Questions that want advice or reasons: these go to the model, with the facts. */
    private const ADVICE = '/\b(hvorfor|hvordan|anbefal\w*|bør jeg|burde|egner|tips|råd|best til|bra til|god til|hva trenger|trenger jeg|hva kan jeg|hva skal jeg|why|how(?! much| many| long| heavy| bright)|recommend\w*|should)\b/u';

    /** «Fortell om …», «hva bør jeg tenke på»: an overview of one product, answered by ShopAnswer, not one fact or one pairing. */
    private const OVERVIEW = '/\b(fortell|forklar|beskriv|hva bør jeg tenke på|hva må jeg tenke på|tell me about|describe)\b/u';

    /** «disse forslagene», «forskjellen på dem»: a question about the set of products shown before (ShopAnswer::compare). */
    private const SET = '/\b(disse|dem|forslag\w*|alternativ\w*|begge|de to|de tre|these|them|both)\b/u';
    private const SET_ASK = '/\b(forskjell\w*|hvorfor|sammenlign\w*|passer|best|bedre|difference|why|compare)\b/u';

    private const PRICE = '/\b(koster|kostar|pris|prisen|kost|cost|costs|price)\b/u';
    private const CHEAPER = '/\b(billigere|dyrere|rimeligere|cheaper|more expensive)\b/u';
    private const STOCK = '/\b(lager|lageret|på lager|inne|tilgjengelig\w*|hvor mange|in stock|stock|available)\b/u';
    private const FIT = '/\b(passer|passar|kompatib\w*|fungerer med|virker med|kan brukes (?:med|til|på)|fit|fits|compatible)\b/u';
    private const IDENT = '/\b(varenummer\w*|varenr|artikkelnummer\w*|sku|merke\w*|produsent\w*|hvem lager|product number|brand)\b/u';
    private const DIFF = '/\b(forskjell\w*|skiller|sammenlign\w*|difference|compare)\b/u';
    private const HAVE = '/^\s*(har dere|selger dere|fører dere|do you (?:have|sell))\b/u';

    /** Question words for a specification, and the specification keys that answer them. An empty list: never in the catalogue. */
    private const SPECS = [
        'vekt' => ['/\b(veier|vekt|vekten|tung|weigh\w*|weight)\b/u', ['vekt_g'], '/(?i:vekt)\w*\s*:?\s*(\d[\d.,]*\s*(?:gram|kg|g))(?!\p{Ll})/u'],
        'lysstyrke' => ['/\b(lysstyrke\w*|lumen|lyssterk\w*|lysest|sterkest|kraftigst|brightness|bright\w*)\b/u', ['lysstyrke_lm'], '/(\d[\d .,]*\s*(?:lumen|Lumen|lm))(?!\p{Ll})/u'],
        'vanntett' => ['/\b(vanntett\w*|tåler vann|tåler regn|ip-?klasse|ip\d\d|waterproof|water)\b/u', ['ip_klasse'], '/\b(ip\s?\d{2}|ipx\d)\b/iu'],
        'batteri' => ['/\b(hvilket batteri|hva slags batteri|batteritype|batteriet|battery)\b/u', ['batteri'], null],
        'lading' => ['/\b(lades|lading|lade|lader|charging|charge)\b/u', ['lading'], null],
        'lengde' => ['/\b(lengde|hvor lang|length|long)\b/u', ['lengde_mm', 'x_lengde'], null],
        'materiale' => ['/\b(materiale|laget av|hva er den laget|material)\b/u', ['materiale'], null],
        'last' => ['/\b(tåler|belastning|arbeidslast|bruddstyrke|wll|load)\b/u', ['arbeidslast_kg', 'x_arbeidslast_kg', 'bruddstyrke_kg', 'x_bruddstyrke_kg'], null],
        'effekt' => ['/\b(watt|effekt|power)\b/u', ['effekt_w'], null],
        'sokkel' => ['/\b(sokkel|socket)\b/u', ['sokkel'], null],
        'folger_med' => ['/\b(følger med|inkludert|included|in the box)\b/u', ['folger_med'], null],
        'brenntid' => ['/\b(brenntid|lyser lenge|lyser lengst|hvor lenge lyser|batteritid|runtime|how long)\b/u', [], null],
    ];

    /**
     * The answer, or null when the model should answer. $ids is the product list for the question (ProductRetriever, the
     * public API's product first); $why its signals, so only products the customer named, is looking at or referred back to
     * are answered about.
     * @return array{text: string, ids: int[], kind: string}|null
     */
    public static function answer(string $q, array $ids, array $why, ?int $selected = null, ?array $intent = null): ?array
    {
        if (preg_match(self::ADVICE, mb_strtolower($q)) || self::overview($q) || self::aboutSet($q)) {
            return null;
        }
        // A budget or a limit on a value («minst 500 lumen», «under 1000 kr») is a search for products, not a question
        // about one (feature 041: ShopAnswer filters the catalogue on it)
        $limits = array_filter(Understand::requirements($q), fn($r) => $r['min'] !== null || $r['max'] !== null);
        if ($limits || Understand::rules($q)['budget_ore'] !== null) {
            return null;
        }
        return self::facts($q, $ids, $why, $selected, $intent);
    }

    /** Whether the question compares or asks why about the products shown before (SET and SET_ASK), naming none. */
    public static function aboutSet(string $q): bool
    {
        $l = mb_strtolower($q);
        return preg_match(self::SET, $l) && preg_match(self::SET_ASK, $l);
    }

    /** Whether the question asks for an overview of a product (OVERVIEW). */
    public static function overview(string $q): bool
    {
        return (bool) preg_match(self::OVERVIEW, mb_strtolower($q));
    }

    /**
     * The factual part of a question that goes to the model (feature 040): for «Hvordan lader jeg den?» the charging
     * method and the documented chargers. The server shows it first and the model only explains it. Null when the
     * question has no factual part the server can answer.
     * @return array{text: string, ids: int[], kind: string}|null
     */
    public static function basis(string $q, array $ids, array $why, ?int $selected = null, ?array $intent = null): ?array
    {
        return self::facts($q, $ids, $why, $selected, $intent);
    }

    /** @return array{text: string, ids: int[], kind: string}|null */
    private static function facts(string $q, array $ids, array $why, ?int $selected, ?array $intent): ?array
    {
        $l = mb_strtolower($q);
        // Cheapest, most expensive, best or worst rated, manuals: CatalogIntent has ranked within the product type already
        if ($intent !== null && ($intent['answer'] ?? '') !== '') {
            return ['text' => (string) $intent['answer'], 'ids' => $intent['ids'], 'kind' => 'katalog:' . $intent['kind']];
        }
        if ($ids === []) {
            return null;
        }
        $focus = self::focus($ids, $why, $selected);
        $specs = array_keys(array_filter(self::SPECS, fn($s) => preg_match($s[0], $l)));
        $fit = (bool) preg_match(self::FIT, $l);
        if ($fit) {
            $specs = array_values(array_diff($specs, ['lading'])); // «passer laderen» is a fit question, not about charging
        }
        $parts = [];
        $kinds = [];
        $used = $focus;

        if ($fit) {
            $r = self::fit($l, $focus, $ids);
            if ($r === null) {
                return null;
            }
            [$text, $about] = $r;
            $parts[] = $text;
            $used = array_values(array_unique(array_merge($used, $about)));
            $kinds[] = 'passer';
        }
        if (!$focus) {
            return $parts ? self::done($parts, $used, $kinds) : null;
        }
        if (preg_match(self::CHEAPER, $l) && count($focus) >= 2) {
            $parts[] = self::cheaper($focus[0], $focus[1]);
            $kinds[] = 'pris';
        } elseif (preg_match(self::PRICE, $l) && !AnswerGuard::isTotal($q)) {
            foreach ($focus as $id) {
                $parts[] = t('{name} koster {price}.', ['name' => self::name($id, $focus), 'price' => ProductRef::price($id)]);
            }
            $kinds[] = 'pris';
        }
        if (preg_match(self::DIFF, $l) && count($focus) >= 2) {
            $parts[] = self::differences($focus[0], $focus[1]);
            $kinds[] = 'forskjell';
        }
        foreach ($specs as $s) {
            foreach ($focus as $id) {
                $parts[] = self::spec($id, $s, $focus);
                // Charging: the documented chargers and cables are part of the answer, so a misfit is never left to the model
                if ($s === 'lading' && ($chargers = self::fitsOf($id, 'lader', false)) !== '') {
                    $parts[] = $chargers;
                }
            }
            $kinds[] = $s;
        }
        if (preg_match(self::IDENT, $l)) {
            foreach ($focus as $id) {
                $parts[] = self::identity($id, $focus);
            }
            $kinds[] = 'vare';
        }
        if (preg_match(self::STOCK, $l) || (preg_match(self::HAVE, $l) && !$kinds)) {
            $store = self::store($l);
            foreach ($focus as $id) {
                $parts[] = self::stock($id, $store, $focus);
            }
            $kinds[] = 'lager';
        }
        return $parts ? self::done($parts, $used, $kinds) : null;
    }

    /** The finished answer: whole sentences in the order asked. */
    private static function done(array $parts, array $ids, array $kinds): array
    {
        return ['text' => implode(' ', array_map('trim', $parts)), 'ids' => array_slice(array_values(array_unique($ids)), 0, 5), 'kind' => implode('+', array_unique($kinds))];
    }

    /** The products the question is about: the selected product, then products named, on the page or referred back to. */
    public static function focus(array $ids, array $why, ?int $selected): array
    {
        $out = $selected !== null ? [$selected] : [];
        foreach ($ids as $id) {
            $sig = $why[$id]['signals'] ?? [];
            if (array_intersect_key($sig, array_flip(['sku', 'model', 'exact_name', 'page', 'history']))) {
                $out[] = (int) $id;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * «Passer A til B?»: the products are identified by name in the question among the products in the facts (including
     * what the catalogue relates to them), then the relations between them decide.
     * @return array{0: string, 1: int[]}|null
     */
    private static function fit(string $l, array $focus, array $ids): ?array
    {
        $facts = Chat::facts($focus ?: $ids);
        $ctx = AnswerGuard::context($focus ?: $ids, $facts);
        // An English question names the product in English («charging cable»): the search's translation finds the Norwegian name
        $named = array_values(array_diff(array_unique([...AnswerGuard::mentioned($l, $ctx), ...AnswerGuard::mentioned(Search::fromEnglish($l, [])[0], $ctx)]), $focus));
        $pairs = [];
        if ($named && $focus) {
            foreach ($named as $n) {
                foreach ($focus as $f) {
                    $pairs[] = [$n, $f];
                }
            }
        } elseif (count($focus) >= 2) {
            $pairs[] = [$focus[1], $focus[0]];
        } elseif (count($focus) === 1) {
            return [self::fitsOf($focus[0], $l), [$focus[0]]];
        } else {
            return null;
        }
        $all = array_values(array_unique(array_merge(...$pairs)));
        $lines = [];
        foreach (array_slice($pairs, 0, 4) as [$a, $b]) {
            $lines[] = self::pair($a, $b, $all);
        }
        return [implode(' ', $lines), $all];
    }

    /** One pair, decided by the relations in both directions. */
    private static function pair(int $a, int $b, array $all): string
    {
        $st = db()->prepare("SELECT kind, level, reason FROM relations WHERE ((product_a = ? AND product_b = ?) OR (product_a = ? AND product_b = ?))
            AND kind IN ('passer_ikke', 'passer_sammen') ORDER BY kind = 'passer_ikke' DESC, level = 'dokumentert' DESC LIMIT 1");
        $st->execute([$a, $b, $b, $a]);
        $r = $st->fetch();
        $v = ['a' => self::name($a, $all), 'b' => self::name($b, $all)];
        if (!$r) {
            return t('Sør-Tre har ikke registrert om {a} passer til {b}, så det kan jeg ikke bekrefte. Sjekk produsentens oversikt over tilbehør, eller spør i butikken.', $v)
                . self::common($a, $b, $all);
        }
        if ($r['kind'] === 'passer_ikke') {
            return t('Nei, {a} passer ikke til {b}. Det har Sør-Tre dokumentert.', $v);
        }
        return $r['level'] === 'dokumentert'
            ? t('Ja, {a} passer til {b}. Det har Sør-Tre dokumentert.', $v)
            : t('Trolig, men ikke bekreftet: en regel i katalogen sier at {a} passer til {b} ({reason}). Sjekk før du kjøper.', $v + ['reason' => self::reason((string) $r['reason'])]);
    }

    /** With no relation: the fit properties both products have verified values for, side by side, without a conclusion. */
    private static function common(int $a, int $b, array $all): string
    {
        $sa = Catalog::specs($a);
        $sb = Catalog::specs($b);
        $out = [];
        foreach (['lading', 'batteri', 'sokkel', 'dimensjon_mm', 'diameter_mm'] as $k) {
            $va = $sa[$k]['verifisert']['value_text'] ?? null;
            $vb = $sb[$k]['verifisert']['value_text'] ?? null;
            if ($va !== null && $vb !== null) {
                $out[] = t('{label}: {a} har «{va}», {b} har «{vb}».', ['label' => I18n::spec($k, $sa[$k]['label']), 'a' => self::name($a, $all), 'va' => I18n::value($va), 'b' => self::name($b, $all), 'vb' => I18n::value($vb)]);
            }
        }
        return $out ? ' ' . t('Det katalogen vet:') . ' ' . implode(' ', $out) : '';
    }

    /** «Hvilken lader passer til H7R Core?»: the documented and rule-based fits and misfits of one product, for the accessory asked. */
    private static function fitsOf(int $id, string $l, bool $sayNone = true): string
    {
        $st = db()->prepare("SELECT r.kind, r.level, p.id, p.name FROM relations r JOIN products p ON p.id = r.product_b
            WHERE r.product_a = ? AND r.kind IN ('passer_sammen', 'passer_ikke') AND r.level IN ('dokumentert', 'regelutledet')
            ORDER BY r.kind = 'passer_ikke', r.level = 'dokumentert' DESC, r.score DESC");
        $st->execute([$id]);
        $rows = $st->fetchAll();
        $want = null;
        foreach (['lader' => 'lade|lader', 'ladekabel' => 'lade|lader', 'kabel' => 'kabel', 'batteri' => 'batteri', 'fil' => 'fil', 'pære' => 'pære', 'sverd' => 'sverd'] as $w => $re) {
            if (preg_match('/\b' . $w . '\w*/u', $l)) {
                $want = $re;
                break;
            }
        }
        if ($want !== null) {
            $rows = array_values(array_filter($rows, fn($r) => preg_match('/(' . $want . ')/iu', (string) $r['name'])));
        }
        $all = array_merge([$id], array_map(fn($r) => (int) $r['id'], $rows));
        $name = self::name($id, $all);
        $yes = $likely = $no = [];
        foreach (array_slice($rows, 0, 6) as $r) {
            $label = self::name((int) $r['id'], $all) . ' (' . ProductRef::price((int) $r['id']) . ')';
            if ($r['kind'] === 'passer_ikke') {
                $no[] = self::name((int) $r['id'], $all);
            } elseif ($r['level'] === 'dokumentert') {
                $yes[] = $label;
            } else {
                $likely[] = $label;
            }
        }
        $out = [];
        if ($yes) {
            $out[] = t('Dokumentert av Sør-Tre som passende til {name}: {list}.', ['name' => $name, 'list' => implode('; ', $yes)]);
        }
        if ($likely) {
            $out[] = t('Passer trolig ifølge en regel i katalogen, ikke bekreftet: {list}.', ['list' => implode('; ', $likely)]);
        }
        if ($no) {
            $out[] = t('Passer ikke, dokumentert av Sør-Tre: {list}.', ['list' => implode('; ', $no)]);
        }
        if (!$sayNone && !$out) {
            return '';
        }
        if (!$yes && !$likely) {
            array_unshift($out, t('Sør-Tre har ikke registrert tilbehør av den typen som passer til {name}.', ['name' => $name]));
        }
        return implode(' ', $out);
    }

    private static function cheaper(int $a, int $b): string
    {
        $p = db()->query("SELECT id, price_ore FROM products WHERE id IN ($a, $b)")->fetchAll(PDO::FETCH_KEY_PAIR);
        $all = [$a, $b];
        $v = ['a' => self::name($a, $all), 'b' => self::name($b, $all), 'pa' => ProductRef::price($a), 'pb' => ProductRef::price($b)];
        if (($p[$a] ?? 0) <= 0 || ($p[$b] ?? 0) <= 0) {
            return t('{a} koster {pa} og {b} koster {pb}.', $v);
        }
        if ($p[$a] === $p[$b]) {
            return t('{a} og {b} koster det samme, {pa}.', $v);
        }
        [$lo, $hi] = $p[$a] < $p[$b] ? [$a, $b] : [$b, $a];
        return t('{a} koster {pa} og {b} koster {pb}, så {low} er {diff} billigere.', $v + ['low' => self::name($lo, $all), 'diff' => kr(abs((int) $p[$a] - (int) $p[$b]))]);
    }

    /** The verified specifications where two products differ, and the ones only one of them has a value for. */
    private static function differences(int $a, int $b): string
    {
        $all = [$a, $b];
        $sa = Catalog::specs($a);
        $sb = Catalog::specs($b);
        $diff = [];
        foreach (array_unique(array_merge(array_keys($sa), array_keys($sb))) as $k) {
            $va = $sa[$k]['verifisert'] ?? null;
            $vb = $sb[$k]['verifisert'] ?? null;
            if (($va === null && $vb === null) || $k === 'egenskaper') {
                continue; // «Egenskaper» is free text, not a comparable value
            }
            $label = I18n::spec((string) $k, ($sa[$k] ?? $sb[$k])['label']);
            $unit = (string) (($sa[$k] ?? $sb[$k])['unit'] ?? '');
            $fa = $va ? trim(I18n::value($va['value_text']) . ' ' . $unit) : t('ikke registrert');
            $fb = $vb ? trim(I18n::value($vb['value_text']) . ' ' . $unit) : t('ikke registrert');
            if (mb_strtolower($fa) !== mb_strtolower($fb)) {
                $diff[] = "$label: $fa / $fb";
            }
        }
        if (!$diff) {
            return t('Katalogen har ingen kontrollerte spesifikasjoner som skiller {a} og {b}.', ['a' => self::name($a, $all), 'b' => self::name($b, $all)]);
        }
        return t('Forskjeller ifølge Sør-Tre ({a} / {b}): {list}.', ['a' => self::name($a, $all), 'b' => self::name($b, $all), 'list' => implode('; ', array_slice($diff, 0, 6))]);
    }

    /**
     * One specification: the verified value with its source; else a buyer's or a rule's value, said to be unchecked; else
     * the line in Sør-Tre's own product text; else unknown.
     */
    private static function spec(int $id, string $what, array $all): string
    {
        [, $keys, $textRe] = self::SPECS[$what];
        $specs = Catalog::specs($id);
        $name = self::name($id, $all);
        foreach ($keys as $k) {
            if ($v = $specs[$k]['verifisert'] ?? null) {
                $src = match ($v['source']) { 'ansatt' => t('godkjent av en ansatt'), 'utledet' => t('utledet fra produktteksten, ikke kontrollert'), default => t('oppgitt av Sør-Tre') };
                return t('{name}: {label} er {value} ({src}).', ['name' => $name, 'label' => I18n::spec($k, $specs[$k]['label']),
                    'value' => trim(I18n::value($v['value_text']) . ' ' . ($specs[$k]['unit'] ?? '')), 'src' => $src]);
            }
        }
        foreach ($keys as $k) {
            $a = $specs[$k]['apen'] ?? null;
            if ($a && (($a['datatype'] ?? 'text') !== 'number' || Guard::isNumberish((string) $a['value_text']))) {
                return t('{name}: {label} er ikke kontrollert av Sør-Tre. En kjøper eller en regel har oppgitt «{value}».', ['name' => $name,
                    'label' => I18n::spec($k, $specs[$k]['label']), 'value' => Guard::forPrompt(trim($a['value_text'] . ' ' . ($specs[$k]['unit'] ?? '')), 40)]);
            }
        }
        if ($textRe !== null) {
            $st = db()->prepare('SELECT plain_text FROM products WHERE id = ?');
            $st->execute([$id]);
            if (preg_match($textRe, (string) $st->fetchColumn(), $m)) {
                return t('{name}: produktbeskrivelsen fra Sør-Tre oppgir {value}. Det er ikke registrert som kontrollert spesifikasjon.', ['name' => $name, 'value' => trim($m[1])]);
            }
        }
        $label = $keys ? I18n::spec($keys[0], (string) (db()->query('SELECT label FROM spec_keys WHERE `key` = ' . db()->quote($keys[0]))->fetchColumn() ?: $what)) : t('brenntid');
        return t('{label} for {name} står ikke i katalogen, så det kan jeg ikke svare sikkert på.', ['label' => self::ucfirst($label), 'name' => $name]);
    }

    private static function identity(int $id, array $all): string
    {
        $st = db()->prepare('SELECT sku, brand FROM products WHERE id = ?');
        $st->execute([$id]);
        $p = $st->fetch() ?: [];
        $sku = trim((string) ($p['sku'] ?? ''));
        $brand = trim((string) ($p['brand'] ?? ''));
        return t('{name}: varenummer {sku}, merke {brand}.', ['name' => self::name($id, $all), 'sku' => $sku !== '' ? $sku : t('ikke registrert'), 'brand' => $brand !== '' ? $brand : t('ikke registrert')]);
    }

    /** Stock for one store: the store named in the question, else the customer's store, else the default store. */
    private static function stock(int $id, array $store, array $all): string
    {
        $st = db()->prepare('SELECT in_store, online FROM stock WHERE product_id = ? AND warehouse_id = ?');
        $st->execute([$id, $store['id']]);
        $s = $st->fetch() ?: ['in_store' => 0, 'online' => 0];
        $v = ['name' => self::name($id, $all), 'store' => $store['name'], 'n' => (int) $s['in_store']];
        if ((int) $s['in_store'] > 0) {
            return t('{name} er på lager i {store} ({n} i butikk).', $v);
        }
        return (int) $s['online'] > 0
            ? t('{name} er ikke i butikken i {store}, men finnes på nettlageret.', $v)
            : t('{name} er ikke på lager i {store} eller på nettlageret.', $v);
    }

    /** @return array{id: int, name: string} */
    private static function store(string $l): array
    {
        $rows = db()->query('SELECT id, name FROM warehouses')->fetchAll();
        foreach ($rows as $w) {
            if (preg_match('/\b' . preg_quote(mb_strtolower((string) $w['name']), '/') . '\b/u', $l)) {
                return ['id' => (int) $w['id'], 'name' => (string) $w['name']];
            }
        }
        foreach ($rows as $w) {
            if ((int) $w['id'] === warehouse_id()) {
                return ['id' => (int) $w['id'], 'name' => (string) $w['name']];
            }
        }
        return ['id' => warehouse_id(), 'name' => '?'];
    }

    /** The short name («Ledlenser H7R Core»), or the full name without notes when another product in the answer shares it. */
    public static function name(int $id, array $all): string
    {
        static $names = [];
        foreach (array_diff(array_unique(array_merge([$id], $all)), array_keys($names)) as $x) {
            $st = db()->prepare('SELECT name FROM products WHERE id = ?');
            $st->execute([(int) $x]);
            $names[(int) $x] = (string) $st->fetchColumn();
        }
        $short = fn(string $n) => trim((string) preg_split('/\s[–-]\s/u', $n)[0]);
        // How many products in the catalogue share each short name: a shared one doesn't tell the customer which product
        static $shared = null;
        if ($shared === null) {
            $shared = array_count_values(array_map(fn($n) => mb_strtolower($short((string) $n)), db()->query('SELECT name FROM products')->fetchAll(PDO::FETCH_COLUMN)));
        }
        $mine = $short($names[$id]);
        if (($shared[mb_strtolower($mine)] ?? 1) > 1) {
            $full = trim((string) preg_replace('/[.\s]*\bNB!?.*$/u', '', $names[$id]));
            return str_replace([' – ', ' - '], ', ', $full);
        }
        return $mine;
    }

    /** The relation's reason, without the «Sør-Tre skriver» lead-in. */
    private static function reason(string $r): string
    {
        return trim((string) preg_replace('/^Sør-Tre skriver(?: at)?:?\s*/u', '', $r));
    }

    private static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }
}
