<?php
declare(strict_types=1);

/**
 * Questions about the whole catalog: "which products have a manual?", "best rated", "cheapest flashlight".
 * A search on the words in the question doesn't find the answer to such questions, so the model got random products
 * and guessed. Here the database answers: we find the list in code, give it to Søren as the answer key and show the right cards.
 * The answer in fallback() is also used if the model doesn't answer, so the demo never ends with a random "best hit".
 */
final class CatalogIntent
{
    /** Patterns in Norwegian and English. The order matters: "dårligst vurdert" (worst rated) must not become "best". */
    private const PATTERNS = [
        'manual' => '/\b(manual\w*|manuell\w*|veiledning\w*|bruksanvisning\w*|monteringsanvisning\w*|instruks\w*|instructions?|how[- ]?to|assembly guides?|user guides?|guide til montering)\b/iu',
        'verst' => '/\b(dårligst\w*|lavest (vurdert|rangert|karakter)|verst\w*|minst populær\w*|worst|lowest[- ]rated|poorly rated|badly rated|least popular|dårlige anmeldelser|bad reviews)\b/iu',
        'best' => '/\b(best (vurdert|rangert|anmeldt)|høyest (vurdert|rangert)|beste anmeldelse\w*|best[- ]rated|highest[- ]rated|top[- ]rated|best reviewed|mest populær\w*|most popular|flest stjerner|most stars|best reviews)\b/iu',
        'billigst' => '/\b(billigst\w*|rimeligst\w*|lavest pris|lavest prisen|cheapest|lowest price|least expensive|most affordable)\b/iu',
        'dyrest' => '/\b(dyrest\w*|høyest pris|most expensive|priciest|highest price)\b/iu',
    ];

    public static function detect(string $q): ?string
    {
        foreach (self::PATTERNS as $kind => $re) {
            if (preg_match($re, $q)) {
                return $kind;
            }
        }
        return null;
    }

    /**
     * @return array{kind:string, ids:int[], facts:string, answer:string, scope:?string}
     *   ids: the products shown as cards, facts: the answer key for Søren (Norwegian), answer: a finished answer in the customer's language
     */
    public static function build(string $kind, string $q, array $page): array
    {
        [$within, $scope] = self::scope($kind, $q, $page);
        return match ($kind) {
            'manual' => self::manuals($within, $scope),
            default => self::ranking($kind, $within, $scope),
        };
    }

    /** Limit to the words left in the question ("billigst lommelykt"), otherwise the category the customer is in, otherwise everything. */
    private static function scope(string $kind, string $q, array $page): array
    {
        $rest = trim((string) preg_replace(self::PATTERNS[$kind], ' ', $q));
        $rest = self::strip($rest);
        if (mb_strlen($rest) >= 3) {
            // The product type the customer named («hodelykten») limits the list before it is ranked, so «billigste hodelykt»
            // can never be an S-hook (feature 039). Accessories made for that type («batteripakke for … hodelykt») don't count.
            $typed = self::ofType($rest);
            if ($typed !== null) {
                return $typed;
            }
            $r = Search::run($rest, 80, false, true);
            // An English word («headlamp») is matched on the Norwegian word the search translated it to
            if (!empty($r['translated']) && ($typed = self::ofType((string) $r['translated'])) !== null) {
                return $typed;
            }
            if ($r['ids']) {
                return [$r['ids'], $r['translated'] ?? $rest];
            }
            // A product word was named but nothing matches: no list, never the whole store
            if (preg_match('/\p{L}{4,}/u', $rest)) {
                return [[], $rest];
            }
        }
        if (($page['kind'] ?? '') === 'tema' && !empty($page['theme'])) {
            return [Catalog::themeIds($page['theme']), Catalog::THEMES[$page['theme']][0]];
        }
        return [null, null];
    }

    /** The question without question words, small words and follow-up words («passer», «forslagene», «forskjellen»), so what is left names the product. */
    private static function strip(string $q): string
    {
        $q = trim((string) preg_replace('/\b(hva|hvem|er|den|det|de|dem|kan|jeg|vil|ha|kjøpe|selger|hvilke|hvilken|hvilket|produkter|produktene|varer|har|med|som|finnes|dere|du|vis|meg|what|which|products?|items?|have|has|with|do|you|show|me|the|are|is|there|any|og|and|i|in|på|en|et|a|an|of|for|til|alle|all|sine|deres|your|anbefal\w*|recommend\w*|egner\w*|suggest\w*|passer|passe|forslag\w*|alternativ\w*|forskjell\w*|disse|hvorfor|sammenlign\w*|fortell|forklar|beskriv|tenke|bør|kort|difference|why|these|those|compare\w*)\b/iu', ' ', $q));
        return trim((string) preg_replace('/[^\p{L}\d ]+/u', ' ', $q));
    }

    /**
     * The product type a question names («Hvilken hodelykt anbefaler du?»): the products of that type and the word, or null
     * (feature 040: a recommendation must stay within the type).
     * @return array{0: int[], 1: string}|null
     */
    public static function typeOf(string $q): ?array
    {
        return self::ofType(self::strip($q));
    }

    /**
     * Products whose name contains a word from the question, in its base form («hodelykten» is «hodelykt»), without
     * products made for that type, and the word that matched. Null when no word of four letters or more is in a name.
     * @return array{0: int[], 1: string}|null
     */
    private static function ofType(string $rest): ?array
    {
        // A word that names a product type the usage rules know («hodelykt») wins over a describing word in a name («vanntett»)
        $found = [];
        foreach (preg_split('/\s+/u', mb_strtolower($rest)) ?: [] as $w) {
            if (mb_strlen($w) < 4) {
                continue;
            }
            $stems = [$w];
            $single = null;
            if (preg_match('/^(.{4,}?)(ene|ane|en|et|er|ar|a|ne|e)$/u', $w, $m)) {
                $stems[] = $m[1];
            }
            foreach ($stems as $stem) {
                $like = db()->quote('%' . addcslashes($stem, '%_\\') . '%');
                $rows = db()->query("SELECT id, name FROM products WHERE LOWER(name) LIKE $like")->fetchAll();
                $ids = [];
                foreach ($rows as $r) {
                    if (!preg_match('/\b(for|til)\b.*' . preg_quote($stem, '/') . '/iu', (string) $r['name'])) {
                        $ids[] = (int) $r['id'];
                    }
                }
                // The word as written, unless only one name holds it («batterier» in «inkl. batterier»): then its base form
                if ($ids && (count($ids) > 1 || count($stems) === 1 || ($stem !== $w && $single === null))) {
                    $found[] = [$ids, $stem];
                    continue 2;
                }
                $single ??= $ids ? [$ids, $stem] : null;
            }
            if ($single !== null) {
                $found[] = $single;
            }
        }
        foreach ($found as $f) {
            if (Usage::rule($f[1]) !== null) {
                return $f;
            }
        }
        return $found[0] ?? null;
    }

    private static function manuals(?array $within, ?string $scope): array
    {
        $all = Catalog::manualIds();
        $ids = $within === null ? array_keys($all) : array_values(array_filter($within, fn($id) => isset($all[$id])));
        // Group per guide: which products each of them applies to
        $by = [];
        foreach ($ids as $id) {
            foreach ($all[$id] as $m) {
                $by[$m['slug']] ??= ['m' => $m, 'ids' => []];
                $by[$m['slug']]['ids'][] = $id;
            }
        }
        uasort($by, fn($a, $b) => count($b['ids']) <=> count($a['ids']));
        $names = [];
        foreach (Catalog::cards($ids) as $c) {
            $names[$c['id']] = $c['name'];
        }
        $facts = ["KATALOGOVERSIKT (hentet fra databasen nå. Dette er fasit for spørsmålet):"];
        if (!$by) {
            $facts[] = $scope ? "Ingen av produktene som passer «{$scope}» har monteringsveiledning." : 'Butikken har ingen monteringsveiledninger.';
        } else {
            $facts[] = ($scope ? "Blant produktene som passer «{$scope}» har " . count($ids) . ' produkter monteringsveiledning.' : 'Butikken har ' . count($by) . ' monteringsveiledninger (demo, ikke fra produsenten).')
                . ' Hver veiledning gjelder flere produkter:';
            foreach (array_values($by) as $i => $g) {
                $ex = array_slice(array_map(fn($id) => $names[$id] ?? '', $g['ids']), 0, 3);
                $facts[] = ($i + 1) . ". «{$g['m']['title']}»: " . count($g['ids']) . ' produkter, for eksempel ' . implode('; ', $ex) . '.';
            }
            $facts[] = 'Produkter med veiledning har merket «Veiledning» på produktkortet. På kategorisidene kan kunden huke av «Har monteringsveiledning».';
        }
        // Cards: one product per guide first, so the customer sees the range
        $cards = array_values(array_unique(array_merge(array_map(fn($g) => $g['ids'][0], $by), $ids)));

        if (!$by) {
            $answer = $scope ? t('Ingen av produktene som passer «{scope}» har monteringsveiledning.', ['scope' => $scope])
                : t('Butikken har ingen monteringsveiledninger ennå.');
        } else {
            $list = implode(', ', array_map(fn($g) => t('«{title}» ({n} produkter)', ['title' => $g['m']['title'], 'n' => count($g['ids'])]), $by));
            $answer = ($scope ? t('Blant produktene som passer «{scope}» har {n} produkter monteringsveiledning: {list}.', ['scope' => $scope, 'n' => count($ids), 'list' => $list])
                : t('Vi har {n} monteringsveiledninger: {list}.', ['n' => count($by), 'list' => $list]))
                . ' ' . t('Produkter med veiledning har merket «Veiledning» på kortet, og du kan filtrere på det i hver kategori.');
        }
        return ['kind' => 'manual', 'ids' => array_slice($cards, 0, 4), 'facts' => implode("\n", $facts), 'answer' => $answer, 'scope' => $scope];
    }

    private static function ranking(string $kind, ?array $within, ?string $scope): array
    {
        $ids = Catalog::ranked($kind, 5, $within);
        $cards = Catalog::cards($ids);
        $head = [
            'best' => ['Best vurdert av kjøpere (snitt av anmeldelser, det er meninger og ikke fakta)', 'Best vurdert'],
            'verst' => ['Dårligst vurdert av kjøpere (snitt av anmeldelser, det er meninger og ikke fakta)', 'Dårligst vurdert'],
            'billigst' => ['Lavest pris', 'Billigst'],
            'dyrest' => ['Høyest pris', 'Dyrest'],
        ][$kind];
        $facts = ["KATALOGOVERSIKT (hentet fra databasen nå. Dette er fasit for spørsmålet):",
            $head[0] . ($scope ? " blant produktene som passer «{$scope}»" : ' i hele butikken') . ':'];
        $lines = [];
        foreach ($cards as $i => $c) {
            $rating = $c['rating'] !== null ? number_format((float) $c['rating'], 1, ',', '') . " av 5 ({$c['n_reviews']} anmeldelser)" : 'ingen anmeldelser';
            $facts[] = ($i + 1) . ". [{$c['id']}] {$c['name']}: " . ProductRef::price((int) $c['id']) . ", $rating.";
            $lines[] = in_array($kind, ['best', 'verst'], true)
                ? t('{name} ({r} av 5 fra {n} kjøpere)', ['name' => $c['name'], 'r' => number_format((float) $c['rating'], 1, lang() === 'en' ? '.' : ',', ''), 'n' => $c['n_reviews']])
                : t('{name} ({price})', ['name' => $c['name'], 'price' => ProductRef::price((int) $c['id'])]); // filled in when shown
        }
        if (!$cards) {
            $facts[] = in_array($kind, ['best', 'verst'], true) ? 'Ingen av disse produktene har anmeldelser ennå.' : 'Fant ingen produkter.';
        }
        $answer = !$cards
            ? t(in_array($kind, ['best', 'verst'], true) ? 'Ingen av disse produktene har anmeldelser ennå.' : 'Fant ingen produkter som passer.')
            : t($head[1]) . ($scope ? ' ' . t('blant «{scope}»', ['scope' => $scope]) : '') . ': ' . implode('; ', array_slice($lines, 0, 3)) . '.'
                . (in_array($kind, ['best', 'verst'], true) ? ' ' . t('Vurderingene er meninger fra kjøpere.') : '');
        return ['kind' => $kind, 'ids' => array_slice($ids, 0, 4), 'facts' => implode("\n", $facts), 'answer' => $answer, 'scope' => $scope];
    }
}
