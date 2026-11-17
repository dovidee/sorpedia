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
        $rest = trim((string) preg_replace('/\b(hvilke|hvilken|hvilket|produkter|produktene|varer|har|med|som|finnes|dere|du|vis|meg|what|which|products?|items?|have|has|with|do|you|show|me|the|are|is|there|any|og|and|i|in|på|en|et|a|an|of|for|til|alle|all)\b/iu', ' ', $rest));
        $rest = trim((string) preg_replace('/[^\p{L}\d ]+/u', ' ', $rest));
        if (mb_strlen($rest) >= 3) {
            $r = Search::run($rest, 80, false, true);
            if ($r['ids']) {
                return [$r['ids'], $r['translated'] ?? $rest];
            }
        }
        if (($page['kind'] ?? '') === 'tema' && !empty($page['theme'])) {
            return [Catalog::themeIds($page['theme']), Catalog::THEMES[$page['theme']][0]];
        }
        return [null, null];
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
