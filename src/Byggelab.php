<?php
declare(strict_types=1);

/**
 * Byggelab (feature 023): a product configurator. The customer combines products from one category, and this class
 * checks the combination against the catalogue: price and stock from the database, specifications with source,
 * "goes well with" from the relations table and a small set of category rules over the stored specifications.
 *
 * Nothing is invented. A value that is not stored is reported as unknown, and a pair without a documented relation or a
 * rule is reported as unknown. The test run reads the same data and writes a report; it is not a physical test.
 */
final class Byggelab
{
    public const MAX_LINES = 24;
    public const MAX_QTY = 99;

    /** One builder per category: name and what it checks. */
    public const BUILDERS = [
        'lys' => ['Lysbygger', 'Lykter, batterier, lading og pærer, sjekket mot hverandre.'],
        'bygg' => ['Byggbenk', 'Festemidler og verktøy til én jobb, med antall og mål samlet.'],
        'maling' => ['Overflateplan', 'Ruller, pensler og slipepapir, med bredder og slipeorden.'],
        'balkong' => ['Utebygger', 'Glass, stendere og lister med samlet bredde og glasstykkelse.'],
        'kjetting' => ['Løfteoppsett', 'Kjetting, sjakler og kroker som ett lastkjede med svakeste ledd.'],
        'skog' => ['Skogsett', 'Sagkjede, fil og verneutstyr, med kjede og fil sjekket mot hverandre.'],
        'tur' => ['Tursett', 'Turutstyr og kokeutstyr med samlet vekt og volum.'],
    ];

    /** Rows in the workspace, front to back. Each product gets one lane from its stored specifications. */
    public const LANES = [
        'lys' => ['kilde' => 'Lyskilder', 'pare' => 'Pærer', 'strom' => 'Strøm og lading', 'tilbehor' => 'Tilbehør'],
        'kjetting' => ['last' => 'Lastkjede', 'tilbehor' => 'Tilbehør'],
        'skog' => ['kjede' => 'Sagkjeder', 'fil' => 'Filer', 'annet' => 'Verneutstyr og annet'],
        'default' => ['del' => 'Deler'],
    ];

    /** Specifications shown on the inventory cards, per category. */
    private const CARD_KEYS = [
        'lys' => ['lysstyrke_lm', 'batteri', 'lading', 'sokkel', 'brenntid_t'],
        'bygg' => ['diameter_mm', 'lengde_mm', 'pakning_stk', 'materiale'],
        'maling' => ['bredde_mm', 'kornstorrelse', 'materiale'],
        'balkong' => ['bredde_cm', 'hoyde_cm', 'tykkelse_mm'],
        'kjetting' => ['dimensjon_mm', 'arbeidslast_kg', 'bruddstyrke_kg'],
        'skog' => ['sverdlengde_tomme', 'drivlenker', 'x_kjededeling', 'x_anbefalt_rundfil', 'diameter_mm'],
        'tur' => ['volum_l', 'vekt_g', 'materiale'],
    ];

    /** Ready made examples for the light builder. Sør-Tre product IDs; missing products are skipped. */
    private const PRESETS = [
        'lys' => [
            ['Hodelykt med reserve', [291483 => 1, 291485 => 1, 291385 => 1, 291482 => 1]],
            ['Strømbruddkasse', [291445 => 1, 268745 => 1, 313059 => 1, 268747 => 2, 291451 => 1]],
            ['Arbeidsplass', [291857 => 1, 291858 => 1, 291487 => 1, 291485 => 1]],
            ['Brygge', [267356 => 4, 267355 => 2]],
            ['Feil kabel', [291482 => 1, 291480 => 1]],
        ],
    ];

    /** Order of strength when two relations describe the same pair. */
    private const RANK = ['ikke' => 0, 'dokumentert' => 1, 'regel' => 2, 'ukjent' => 3];

    public static function lanes(string $theme): array
    {
        return self::LANES[$theme] ?? self::LANES['default'];
    }

    /** Sør-Tre categories used by the products in a category, for the inventory filter. */
    public static function categories(string $theme): array
    {
        $st = db()->prepare('SELECT c.id, c.name FROM product_categories pc JOIN categories c ON c.id = pc.category_id
            JOIN products p ON p.id = pc.product_id WHERE p.theme = ? GROUP BY c.id, c.name ORDER BY COUNT(*) DESC, c.name');
        $st->execute([$theme]);
        return $st->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Every product in the category with card data, lane, key specifications and category IDs. */
    public static function inventory(string $theme): array
    {
        $ids = Catalog::themeIds($theme);
        $cards = Catalog::cards($ids);
        $specs = self::specs($ids);
        $cats = self::productCategories($ids);
        $keys = self::CARD_KEYS[$theme] ?? [];
        $out = [];
        foreach ($cards as $c) {
            $sp = $specs[$c['id']] ?? [];
            $out[] = [
                'id' => $c['id'], 'name' => $c['name'], 'slug' => $c['slug'], 'img' => $c['img'] ? '/' . $c['img'] : null,
                'price_ore' => (int) $c['price_ore'], 'in_store' => (int) $c['in_store'], 'online' => (int) $c['online'],
                'lane' => self::lane($theme, $sp), 'cats' => $cats[$c['id']] ?? [],
                'weight_g' => isset($sp['vekt_g']['num']) ? (float) $sp['vekt_g']['num'] : null,
                'facts' => array_values(array_filter(array_map(fn($k) => isset($sp[$k]) ? ['label' => I18n::spec($k, $sp[$k]['label']), 'value' => self::show($sp[$k]), 'source' => $sp[$k]['source']] : null, $keys))),
            ];
        }
        return $out;
    }

    /**
     * Examples for a category: fixed ones for light, otherwise the product with the most documented partners and up to
     * three of those partners. Only products that exist are returned.
     */
    public static function presets(string $theme): array
    {
        $have = array_flip(Catalog::themeIds($theme));
        $out = [];
        foreach (self::PRESETS[$theme] ?? [] as [$name, $lines]) {
            $lines = array_intersect_key($lines, $have);
            if (count($lines) >= 2) {
                $out[] = ['name' => $name, 'lines' => $lines];
            }
        }
        if ($out) {
            return $out;
        }
        $st = db()->prepare("SELECT r.product_a, r.product_b FROM relations r JOIN products a ON a.id = r.product_a JOIN products b ON b.id = r.product_b
            WHERE a.theme = ? AND b.theme = a.theme AND r.kind = 'passer_sammen' AND r.level IN ('dokumentert','regelutledet')
            ORDER BY r.level = 'dokumentert' DESC, r.score DESC, r.product_a, r.product_b");
        $st->execute([$theme]);
        $partners = [];
        foreach ($st as $r) {
            $partners[(int) $r['product_a']][] = (int) $r['product_b'];
        }
        uasort($partners, fn($a, $b) => count($b) <=> count($a));
        foreach (array_slice($partners, 0, 2, true) as $hub => $ids) {
            $lines = [$hub => 1];
            foreach (array_slice(array_unique($ids), 0, 3) as $id) {
                $lines[$id] = 1;
            }
            $name = db()->prepare('SELECT name FROM products WHERE id = ?');
            $name->execute([$hub]);
            $out[] = ['name' => t('Rundt {name}', ['name' => self::short(['name' => (string) $name->fetchColumn()])]), 'lines' => $lines];
        }
        if (!$out && ($top = Catalog::popular(3, $theme))) {
            $out[] = ['name' => t('Populært nå'), 'lines' => array_fill_keys($top, 1)];
        }
        return $out;
    }

    /**
     * Cleans the lines a browser sent: only products in the category, at most MAX_LINES, quantity 1 to MAX_QTY.
     * @return array<int,int> product ID => quantity
     */
    public static function lines(string $theme, mixed $raw): array
    {
        $have = array_flip(Catalog::themeIds($theme));
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $row) {
            $id = (int) (is_array($row) ? ($row['id'] ?? 0) : 0);
            $qty = (int) (is_array($row) ? ($row['qty'] ?? 1) : 1);
            if (isset($have[$id]) && !isset($out[$id]) && count($out) < self::MAX_LINES) {
                $out[$id] = max(1, min(self::MAX_QTY, $qty));
            }
        }
        return $out;
    }

    /**
     * The full check of a configuration. With $simulate the report also gets the test run steps.
     * @param array<int,int> $lines product ID => quantity
     */
    public static function report(string $theme, array $lines, bool $simulate = false): array
    {
        $ids = array_keys($lines);
        $cards = [];
        foreach (Catalog::cards($ids) as $c) {
            $cards[$c['id']] = $c;
        }
        $specs = self::specs($ids);
        $items = [];
        foreach ($lines as $id => $qty) {
            if (!isset($cards[$id])) {
                continue;
            }
            $c = $cards[$id];
            $sp = $specs[$id] ?? [];
            $items[$id] = [
                'id' => $id, 'name' => $c['name'], 'slug' => $c['slug'], 'img' => $c['img'] ? '/' . $c['img'] : null,
                'qty' => $qty, 'price_ore' => (int) $c['price_ore'], 'in_store' => (int) $c['in_store'], 'online' => (int) $c['online'],
                'lane' => self::lane($theme, $sp), 'specs' => $sp,
                'weight_g' => isset($sp['vekt_g']['num']) ? (float) $sp['vekt_g']['num'] : null,
            ];
        }

        $r = ['theme' => $theme, 'items' => [], 'links' => [], 'missing' => [], 'warnings' => [], 'stats' => [], 'bars' => null,
            'notes' => [], 'module' => null];
        $r['totals'] = self::totals($items);
        $r['verification'] = self::verification($theme, $items);
        $links = self::relationLinks($items);
        $module = match ($theme) {
            'lys' => self::light($items, $links),
            'kjetting' => self::lifting($items, $links),
            'skog' => self::forestry($items, $links),
            'balkong' => self::outdoor($items),
            'maling' => self::surface($items),
            'bygg' => self::construction($items),
            'tur' => self::adventure($items),
            default => ['stats' => [], 'missing' => [], 'warnings' => [], 'notes' => [], 'step' => null, 'bars' => null, 'links' => []],
        };
        foreach ($module['links'] as $l) {
            self::addLink($links, $l);
        }
        $r['links'] = array_values($links);
        $r['stats'] = $module['stats'];
        $r['missing'] = $module['missing'];
        $r['warnings'] = $module['warnings'];
        $r['notes'] = $module['notes'];
        $r['bars'] = $module['bars'];
        $r['module'] = $module['step'];

        $count = array_count_values(array_column($r['links'], 'status')) + ['dokumentert' => 0, 'regel' => 0, 'ukjent' => 0, 'ikke' => 0];
        $r['counts'] = $count;
        $r['verdict'] = !$items ? 'tom' : ($count['ikke'] > 0 ? 'konflikt' : (($r['missing'] || $r['warnings']) ? 'mangler' : 'klar'));

        // Items go to the browser with the specifications they carry, each with source
        foreach ($items as $it) {
            $it['specs'] = array_values(array_map(fn($k, $s) => ['key' => $k, 'label' => I18n::spec($k, $s['label']), 'value' => self::show($s),
                'source' => $s['source'], 'evidence' => $s['evidence']], array_keys($it['specs']), $it['specs']));
            $r['items'][] = $it;
        }
        if ($simulate) {
            $r['steps'] = self::steps($r);
        }
        return $r;
    }

    // ---------- Shared parts ----------

    /** Effective specification per product and key: Verified if it exists, otherwise Open. */
    private static function specs(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));
        $rows = db()->query("SELECT v.product_id, v.`key`, v.version, v.value_text, v.value_num, v.source, v.evidence, k.label, k.unit
            FROM spec_values v JOIN spec_keys k ON k.`key` = v.`key` WHERE v.product_id IN ($in) ORDER BY k.sort, k.label, v.version = 'verifisert'");
        $out = [];
        foreach ($rows as $s) {
            // Ordered so the verified row comes last and wins
            $out[(int) $s['product_id']][$s['key']] = ['label' => $s['label'], 'unit' => $s['unit'], 'value' => $s['value_text'],
                'num' => $s['value_num'] !== null ? (float) $s['value_num'] : null, 'source' => $s['source'], 'evidence' => $s['evidence'], 'version' => $s['version']];
        }
        return $out;
    }

    private static function productCategories(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));
        $out = [];
        foreach (db()->query("SELECT product_id, category_id FROM product_categories WHERE product_id IN ($in)") as $r) {
            $out[(int) $r['product_id']][] = (int) $r['category_id'];
        }
        return $out;
    }

    /** Lane from stored specifications only. */
    private static function lane(string $theme, array $sp): string
    {
        return match ($theme) {
            // A light source states its lumen or its water rating; a battery or a charger states neither
            'lys' => isset($sp['sokkel']) ? 'pare' : (isset($sp['lysstyrke_lm']) || isset($sp['ip_klasse']) ? 'kilde' : (isset($sp['batteri']) || isset($sp['lading']) ? 'strom' : 'tilbehor')),
            'kjetting' => isset($sp['arbeidslast_kg']) || isset($sp['bruddstyrke_kg']) || isset($sp['dimensjon_mm']) ? 'last' : 'tilbehor',
            'skog' => isset($sp['drivlenker']) || isset($sp['x_kjededeling']) ? 'kjede' : (isset($sp['diameter_mm']) ? 'fil' : 'annet'),
            default => 'del',
        };
    }

    private static function show(array $s): string
    {
        $v = I18n::value($s['value']);
        if (!$s['unit'] || str_contains($v, (string) $s['unit'])) {
            return $v;
        }
        return $s['unit'] === '″' ? $v . '″' : "$v {$s['unit']}";
    }

    private static function num(float $n): string
    {
        $en = lang() === 'en';
        $d = abs($n - round($n)) < 0.005 ? 0 : (abs($n * 10 - round($n * 10)) < 0.05 ? 1 : 2);
        return number_format($n, $d, $en ? '.' : ',', $en ? ',' : ' ');
    }

    private static function short(array $it): string
    {
        $n = preg_split('/\s+[–-]\s+/u', $it['name'])[0] ?? $it['name'];
        return mb_strimwidth($n, 0, 40, '…');
    }

    private static function totals(array $items): array
    {
        $price = 0;
        $weight = 0.0;
        $known = 0;
        $units = 0;
        foreach ($items as $it) {
            $price += $it['price_ore'] * $it['qty'];
            $units += $it['qty'];
            if ($it['weight_g'] !== null) {
                $weight += $it['weight_g'] * $it['qty'];
                $known++;
            }
        }
        return ['price_ore' => $price, 'weight_g' => $known ? $weight : null, 'weight_known' => $known, 'lines' => count($items), 'units' => $units];
    }

    /**
     * For the facet specifications of the category: how many values come from Sør-Tre or staff, how many are derived
     * or from buyers, and how many are missing.
     */
    private static function verification(string $theme, array $items): array
    {
        $st = db()->prepare('SELECT `key` FROM spec_keys WHERE is_facet = 1 AND (themes IS NULL OR FIND_IN_SET(?, themes))');
        $st->execute([$theme]);
        $keys = $st->fetchAll(PDO::FETCH_COLUMN);
        $sum = ['bekreftet' => 0, 'utledet' => 0, 'mangler' => 0];
        $per = [];
        foreach ($items as $it) {
            $row = ['bekreftet' => 0, 'utledet' => 0, 'mangler' => []];
            foreach ($keys as $k) {
                $s = $it['specs'][$k] ?? null;
                if (!$s) {
                    $row['mangler'][] = $k;
                } elseif (in_array($s['source'], ['sortre', 'ansatt'], true)) {
                    $row['bekreftet']++;
                } else {
                    $row['utledet']++;
                }
            }
            $sum['bekreftet'] += $row['bekreftet'];
            $sum['utledet'] += $row['utledet'];
            $sum['mangler'] += count($row['mangler']);
            $per[$it['id']] = ['bekreftet' => $row['bekreftet'], 'utledet' => $row['utledet'], 'mangler' => count($row['mangler'])];
        }
        return $sum + ['per' => $per];
    }

    /** Relations from the catalogue between the chosen products, one per pair, the strongest claim kept. */
    private static function relationLinks(array $items): array
    {
        $links = [];
        if (count($items) < 2) {
            return $links;
        }
        $in = implode(',', array_keys($items));
        $rows = db()->query("SELECT product_a, product_b, kind, level, reason FROM relations
            WHERE product_a IN ($in) AND product_b IN ($in) AND product_a <> product_b AND kind IN ('passer_sammen','passer_ikke')");
        foreach ($rows as $r) {
            $status = match (true) {
                $r['kind'] === 'passer_ikke' && $r['level'] === 'dokumentert' => 'ikke',
                $r['kind'] === 'passer_sammen' && $r['level'] === 'dokumentert' => 'dokumentert',
                $r['kind'] === 'passer_sammen' && $r['level'] === 'regelutledet' => 'regel',
                default => 'ukjent', // assumed is never shown as certain
            };
            $reason = $status === 'ukjent' ? t('Antatt, ikke dokumentert: {r}', ['r' => $r['reason']]) : $r['reason'];
            self::addLink($links, ['a' => (int) $r['product_a'], 'b' => (int) $r['product_b'], 'status' => $status, 'reason' => $reason,
                'basis' => 'relasjon', 'level' => $r['level']]);
        }
        return $links;
    }

    private static function addLink(array &$links, array $l): void
    {
        $k = min($l['a'], $l['b']) . '-' . max($l['a'], $l['b']);
        if (!isset($links[$k]) || self::RANK[$l['status']] < self::RANK[$links[$k]['status']]) {
            $links[$k] = $l;
        }
    }

    private static function linked(array $links, int $a, int $b): ?array
    {
        return $links[min($a, $b) . '-' . max($a, $b)] ?? null;
    }

    private static function evidence(array ...$specs): string
    {
        return implode(' · ', array_map(fn($s) => $s['label'] . ': ' . $s['value'] . ' (' . t(SOURCE_LABEL[$s['source']][0] ?? $s['source']) . ')', $specs));
    }

    private static function empty(): array
    {
        return ['stats' => [], 'missing' => [], 'warnings' => [], 'notes' => [], 'step' => null, 'bars' => null, 'links' => []];
    }

    // ---------- Light builder ----------

    /** Battery type and count from a stored value such as "3xAAA", "2xD" or "21700". */
    public static function cells(?string $v): ?array
    {
        if ($v && preg_match('/(?:(\d+)\s*[x×]\s*|(?<![A-Z0-9]))(AAA|AA|C|D|CR123A|CR2016|CR2032|21700|18650)(?![A-Z0-9])/iu', $v, $m)) {
            return ['n' => (int) ($m[1] ?: 1), 'type' => strtoupper($m[2])];
        }
        return null;
    }

    /** A lamp that needs loose batteries: a battery type is stored and nothing says it is rechargeable. */
    private static function needsCells(array $it): ?array
    {
        $b = $it['specs']['batteri']['value'] ?? null;
        if (!$b || isset($it['specs']['lading']) || preg_match('/oppladbar|li-?ion|lithium|mah/iu', $b)) {
            return null;
        }
        return self::cells($b);
    }

    private static function light(array $items, array $links): array
    {
        $m = self::empty();
        $lamps = array_filter($items, fn($i) => $i['lane'] === 'kilde');
        $bulbs = array_filter($items, fn($i) => $i['lane'] === 'pare');
        $power = array_filter($items, fn($i) => $i['lane'] === 'strom');

        // Pairs: lamp and power. Same battery type is a rule; a missing type or a charger without a relation is unknown.
        foreach ($lamps as $l) {
            foreach ($power as $p) {
                if (self::linked($links, $l['id'], $p['id'])) {
                    continue;
                }
                $lc = self::cells($l['specs']['batteri']['value'] ?? null);
                $pc = self::cells($p['specs']['batteri']['value'] ?? null);
                if ($lc && $pc && $lc['type'] === $pc['type']) {
                    $m['links'][] = ['a' => $l['id'], 'b' => $p['id'], 'status' => 'regel', 'basis' => 'regel',
                        'reason' => t('Lykten bruker {need}, batteriet er type {type}', ['need' => $l['specs']['batteri']['value'], 'type' => $pc['type']]),
                        'evidence' => self::evidence($l['specs']['batteri'], $p['specs']['batteri'])];
                } elseif (!($lc && $pc)) {
                    $m['links'][] = ['a' => $l['id'], 'b' => $p['id'], 'status' => 'ukjent', 'basis' => 'ingen',
                        'reason' => match (true) {
                            isset($p['specs']['lading']) => t('Ingen dokumentert kobling mellom lykten og laderen.'),
                            $lc || $pc => t('Ingen dokumentert kobling, og batteritypen er ikke oppgitt for begge.'),
                            default => t('Ingen dokumentert kobling og ingen regel som dekker paret.'),
                        }];
                }
            }
        }

        // Lumen
        $rows = [];
        $sum = 0.0;
        $known = 0;
        foreach ($lamps as $l) {
            $lm = $l['specs']['lysstyrke_lm']['num'] ?? null;
            $rows[] = ['id' => $l['id'], 'name' => self::short($l), 'value' => $lm, 'source' => $l['specs']['lysstyrke_lm']['source'] ?? null];
            if ($lm !== null) {
                $sum += $lm * $l['qty'];
                $known++;
            }
        }
        usort($rows, fn($a, $b) => ($b['value'] ?? -1) <=> ($a['value'] ?? -1));
        if ($rows) {
            $m['bars'] = ['label' => t('Lysstyrke'), 'unit' => 'lm', 'rows' => $rows];
            $m['stats'][] = ['label' => t('Samlet lysstyrke'), 'value' => $known ? self::num($sum) . ' lm' : t('Ukjent'),
                'note' => $known ? t('Sum av oppgitte maksverdier ganger antall, kjent for {k} av {n} lyskilder. Ikke en måling.', ['k' => $known, 'n' => count($rows)])
                    : t('Lysstyrke er ikke oppgitt for lyskildene.')];
        }

        // Batteries for lamps that take loose cells
        $need = [];
        foreach ($lamps as $l) {
            if ($c = self::needsCells($l)) {
                $need[$c['type']]['n'] = ($need[$c['type']]['n'] ?? 0) + $c['n'] * $l['qty'];
                $need[$c['type']]['lamps'][] = $l;
            }
        }
        $have = [];
        foreach ($power as $p) {
            if ($c = self::cells($p['specs']['batteri']['value'] ?? null)) {
                $pack = $p['specs']['pakning_stk']['num'] ?? null;
                $have[$c['type']] ??= ['n' => 0, 'unknown' => false];
                $pack === null ? $have[$c['type']]['unknown'] = true : $have[$c['type']]['n'] += (int) $pack * $p['qty'];
            }
        }
        foreach ($need as $type => $n) {
            $names = implode(', ', array_map([self::class, 'short'], $n['lamps']));
            $h = $have[$type] ?? null;
            if (!$h) {
                $m['missing'][] = ['kind' => 'komponent', 'text' => t('Ingen {type}-batterier i oppsettet. {names} bruker {n} stk.', ['type' => $type, 'names' => $names, 'n' => $n['n']])];
            } elseif (!$h['unknown'] && $h['n'] < $n['n']) {
                $m['missing'][] = ['kind' => 'komponent', 'text' => t('Oppsettet har {have} {type}-batterier. Lyktene trenger {n} for én fylling.', ['have' => $h['n'], 'type' => $type, 'n' => $n['n']])];
            }
            $m['stats'][] = ['label' => t('Batterier {type}', ['type' => $type]),
                'value' => t('{have} stk, trenger {n}', ['have' => $h ? ($h['unknown'] ? '?' : $h['n']) : 0, 'n' => $n['n']]),
                'note' => t('I oppsettet, mot det lyktene trenger for én fylling.')];
            // Runtime: only from a stored burn time, multiplied by whole fillings
            if ($h && !$h['unknown'] && $h['n'] >= $n['n']) {
                $fills = intdiv($h['n'], $n['n']);
                foreach ($n['lamps'] as $l) {
                    if ($bt = $l['specs']['brenntid_t']['num'] ?? null) {
                        $m['stats'][] = ['label' => t('Brenntid {name}', ['name' => self::short($l)]),
                            'value' => t('ca. {t} t', ['t' => self::num($bt * $fills)]),
                            'note' => t('Beregnet: {bt} t per fylling (oppgitt) × {f} fyllinger.', ['bt' => self::num($bt), 'f' => $fills])];
                    }
                }
            }
        }
        foreach ($lamps as $l) {
            if (($bt = $l['specs']['brenntid_t']['num'] ?? null) && !self::needsCells($l)) {
                $m['stats'][] = ['label' => t('Brenntid {name}', ['name' => self::short($l)]), 'value' => self::num($bt) . ' t', 'note' => t('Oppgitt per lading.')];
            }
        }

        // Charging
        $byMethod = [];
        foreach (array_merge($lamps, $power) as $i) {
            if ($v = $i['specs']['lading']['value'] ?? null) {
                $byMethod[$v][] = self::short($i);
            }
        }
        foreach ($byMethod as $method => $names) {
            $m['stats'][] = ['label' => t('Lading: {m}', ['m' => I18n::value($method)]), 'value' => (string) count($names), 'note' => implode(', ', $names)];
        }
        foreach ($lamps as $l) {
            if (!isset($l['specs']['lading'])) {
                continue;
            }
            $charged = false;
            foreach ($power as $p) {
                $k = self::linked($links, $l['id'], $p['id']);
                $charged = $charged || ($k && in_array($k['status'], ['dokumentert', 'regel'], true) && isset($p['specs']['lading']));
            }
            if (!$charged) {
                $m['missing'][] = isset($l['specs']['folger_med'])
                    ? ['kind' => 'info', 'text' => t('{name}: følger med {v}.', ['name' => self::short($l), 'v' => $l['specs']['folger_med']['value']])]
                    : ['kind' => 'data', 'text' => t('{name} lader med «{m}». Om kabelen følger med er ikke oppgitt, og ingen dokumentert lader er i oppsettet.', ['name' => self::short($l), 'm' => I18n::value($l['specs']['lading']['value'])])];
            }
        }

        // Sockets and voltage
        $sockets = [];
        foreach ($bulbs as $b) {
            $sockets[$b['specs']['sokkel']['value']][] = $b;
        }
        if ($sockets) {
            $m['stats'][] = ['label' => t('Sokler'), 'value' => implode(', ', array_map(fn($k, $v) => "$k × " . array_sum(array_column($v, 'qty')), array_keys($sockets), $sockets)),
                'note' => t('Armaturen er ikke i katalogen, så sokkelen der må du sjekke selv.')];
            if (count($sockets) > 1) {
                $m['warnings'][] = ['text' => t('Pærene har ulike sokler ({s}). Hver pære passer bare i en armatur med samme sokkel.', ['s' => implode(', ', array_keys($sockets))])];
            }
        }
        $volts = [];
        foreach ($items as $i) {
            if ($v = $i['specs']['x_spenning']['value'] ?? null) {
                $volts[$v][] = self::short($i);
            }
        }
        if ($volts) {
            $m['stats'][] = ['label' => t('Spenning'), 'value' => implode(', ', array_keys($volts)),
                'note' => t('Kjent for {k} av {n} produkter.', ['k' => array_sum(array_map('count', $volts)), 'n' => count($items)])];
        }

        $m['step'] = ['title' => t('Lysberegning'), 'text' => $rows
            ? t('{n} lyskilder, samlet {lm} lm etter oppgitte verdier ({k} kjent). {b} batterityper sjekket mot behovet.', ['n' => count($rows), 'lm' => self::num($sum), 'k' => $known, 'b' => count($need)])
            : t('Ingen lyskilder i oppsettet.')];
        return $m;
    }

    // ---------- Lifting ----------

    private static function lifting(array $items, array $links): array
    {
        $m = self::empty();
        $load = array_filter($items, fn($i) => $i['lane'] === 'last');
        $wll = [];
        $unknown = [];
        $break = [];
        $dims = [];
        foreach ($load as $i) {
            ($v = $i['specs']['arbeidslast_kg']['num'] ?? null) !== null ? $wll[$i['id']] = $v : $unknown[] = self::short($i);
            if (($v = $i['specs']['bruddstyrke_kg']['num'] ?? null) !== null) {
                $break[$i['id']] = $v;
            }
            if (($v = $i['specs']['dimensjon_mm']['num'] ?? null) !== null) {
                $dims[self::num($v)][] = $i;
            }
        }
        if ($wll) {
            $min = array_keys($wll, min($wll))[0];
            $m['stats'][] = ['label' => $unknown ? t('Laveste kjente arbeidslast') : t('Svakeste ledd (arbeidslast)'),
                'value' => self::num($wll[$min]) . ' kg', 'note' => self::short($items[$min]), 'weakest' => $min];
        }
        if ($unknown) {
            $m['missing'][] = ['kind' => 'data', 'text' => t('Arbeidslast er ikke oppgitt for {names}. Svakeste ledd kan ikke fastslås for hele kjeden.', ['names' => implode(', ', $unknown)])];
        }
        if ($break) {
            $min = array_keys($break, min($break))[0];
            $m['stats'][] = ['label' => t('Laveste bruddstyrke'), 'value' => self::num($break[$min]) . ' kg', 'note' => self::short($items[$min])];
        }
        if ($dims) {
            $m['stats'][] = ['label' => t('Dimensjoner'), 'value' => implode(', ', array_map(fn($d) => "$d mm", array_keys($dims))), 'note' => ''];
            if (count($dims) > 1) {
                $m['warnings'][] = ['text' => t('Delene har ulike dimensjoner ({d} mm). Sjekk at sjakkel og krok passer kjettingen.', ['d' => implode(', ', array_keys($dims))])];
            }
        }
        // Same dimension is a rule; different dimension leaves the pair unknown
        $ids = array_keys($load);
        foreach ($ids as $x => $a) {
            foreach (array_slice($ids, $x + 1) as $b) {
                if (self::linked($links, $a, $b)) {
                    continue;
                }
                $da = $items[$a]['specs']['dimensjon_mm'] ?? null;
                $db = $items[$b]['specs']['dimensjon_mm'] ?? null;
                if ($da && $db) {
                    $same = abs($da['num'] - $db['num']) < 0.01;
                    $m['links'][] = ['a' => $a, 'b' => $b, 'status' => $same ? 'regel' : 'ukjent', 'basis' => $same ? 'regel' : 'ingen',
                        'reason' => $same ? t('Samme dimensjon ({d} mm)', ['d' => self::num($da['num'])]) : t('Ulik dimensjon ({a} mm og {b} mm), ingen dokumentert kobling', ['a' => self::num($da['num']), 'b' => self::num($db['num'])]),
                        'evidence' => self::evidence($da, $db)];
                }
            }
        }
        $m['notes'][] = t('Arbeidslast gjelder rett løft. Vinkel, stropp og rykk er ikke regnet med.');
        $m['step'] = ['title' => t('Lastkjede'), 'text' => $wll && !$unknown
            ? t('Lasten går gjennom {n} ledd. Svakeste ledd tåler {kg} kg arbeidslast.', ['n' => count($load), 'kg' => self::num(min($wll))])
            : t('Lasten går gjennom {n} ledd. Arbeidslast mangler for {u} av dem.', ['n' => count($load), 'u' => count($unknown)])];
        return $m;
    }

    // ---------- Forestry ----------

    private static function forestry(array $items, array $links): array
    {
        $m = self::empty();
        $chains = array_filter($items, fn($i) => $i['lane'] === 'kjede');
        $files = array_filter($items, fn($i) => $i['lane'] === 'fil');
        foreach ($chains as $c) {
            $parts = [];
            foreach (['sverdlengde_tomme', 'drivlenker', 'x_kjededeling'] as $k) {
                if (isset($c['specs'][$k])) {
                    $parts[] = self::show($c['specs'][$k]);
                }
            }
            $m['stats'][] = ['label' => self::short($c), 'value' => $parts ? implode(' · ', $parts) : t('Ukjent'), 'note' => t('Sverd, drivlenker og deling slik Sør-Tre oppgir dem.')];
            $rec = $c['specs']['x_anbefalt_rundfil'] ?? null;
            $recMm = $rec && preg_match('/\(([\d,.]+)\s*mm\)/u', $rec['value'], $mm) ? (float) str_replace(',', '.', $mm[1]) : null;
            $partner = false;
            foreach ($items as $o) {
                $k = self::linked($links, $c['id'], $o['id']);
                $partner = $partner || ($k && in_array($k['status'], ['dokumentert', 'regel'], true));
            }
            if (!$files && !$partner && $rec) {
                $m['missing'][] = ['kind' => 'komponent', 'text' => t('Ingen rundfil i oppsettet. {name} anbefaler {v}.', ['name' => self::short($c), 'v' => $rec['value']])];
            }
            foreach ($files as $f) {
                if (self::linked($links, $c['id'], $f['id']) || $recMm === null || !isset($f['specs']['diameter_mm'])) {
                    continue;
                }
                $d = $f['specs']['diameter_mm']['num'];
                $same = abs($d - $recMm) < 0.05;
                $m['links'][] = ['a' => $c['id'], 'b' => $f['id'], 'status' => $same ? 'regel' : 'ukjent', 'basis' => $same ? 'regel' : 'ingen',
                    'reason' => t('Kjedet anbefaler rundfil {r} mm, filen er {d} mm', ['r' => self::num($recMm), 'd' => self::num($d)]),
                    'evidence' => self::evidence($rec, $f['specs']['diameter_mm'])];
                if (!$same) {
                    $m['warnings'][] = ['text' => t('{f} er {d} mm, men {c} anbefaler {r} mm.', ['f' => self::short($f), 'd' => self::num($d), 'c' => self::short($c), 'r' => self::num($recMm)])];
                }
            }
        }
        $m['step'] = ['title' => t('Kjede og fil'), 'text' => t('{c} sagkjeder og {f} filer sjekket mot anbefalt rundfil.', ['c' => count($chains), 'f' => count($files)])];
        return $m;
    }

    // ---------- Measures for the other builders ----------

    private static function outdoor(array $items): array
    {
        $m = self::empty();
        $w = 0.0;
        $known = 0;
        $thick = [];
        $heights = [];
        foreach ($items as $i) {
            if (($v = $i['specs']['bredde_cm']['num'] ?? null) !== null) {
                $w += $v * $i['qty'];
                $known++;
            }
            if (($v = $i['specs']['tykkelse_mm']['num'] ?? null) !== null) {
                $thick[self::num($v)] = true;
            }
            if (($v = $i['specs']['hoyde_cm']['num'] ?? null) !== null) {
                $heights[self::num($v)] = true;
            }
        }
        if ($known) {
            $m['stats'][] = ['label' => t('Samlet bredde'), 'value' => self::num($w) . ' cm', 'note' => t('Oppgitt bredde ganger antall, kjent for {k} av {n} produkter.', ['k' => $known, 'n' => count($items)])];
        }
        if ($heights) {
            $m['stats'][] = ['label' => t('Høyder'), 'value' => implode(', ', array_keys($heights)) . ' cm', 'note' => ''];
        }
        if ($thick) {
            $m['stats'][] = ['label' => t('Glasstykkelse'), 'value' => implode(', ', array_keys($thick)) . ' mm', 'note' => ''];
            if (count($thick) > 1) {
                $m['warnings'][] = ['text' => t('Glassene har ulik tykkelse ({t} mm). Lister og profiler må passe hver tykkelse.', ['t' => implode(', ', array_keys($thick))])];
            }
        }
        $m['step'] = ['title' => t('Mål'), 'text' => $known ? t('Samlet bredde {w} cm etter oppgitte mål.', ['w' => self::num($w)]) : t('Bredde er ikke oppgitt for produktene.')];
        return $m;
    }

    private static function surface(array $items): array
    {
        $m = self::empty();
        $grits = [];
        $widths = [];
        foreach ($items as $i) {
            if (($v = $i['specs']['kornstorrelse']['num'] ?? null) !== null) {
                $grits[] = (int) $v;
            }
            if (($v = $i['specs']['bredde_mm']['num'] ?? null) !== null) {
                $widths[] = ['n' => self::short($i), 'v' => $v];
            }
        }
        if ($grits) {
            sort($grits);
            $m['stats'][] = ['label' => t('Slipeorden'), 'value' => implode(' → ', array_map(fn($g) => "P$g", array_unique($grits))), 'note' => t('Grovt før fint, sortert på oppgitt kornstørrelse.')];
        }
        foreach ($widths as $w) {
            $m['stats'][] = ['label' => $w['n'], 'value' => self::num($w['v']) . ' mm', 'note' => t('Bredde')];
        }
        $m['step'] = ['title' => t('Slipeorden og bredder'), 'text' => t('{g} slipepapir og {w} verktøy med oppgitt bredde.', ['g' => count($grits), 'w' => count($widths)])];
        return $m;
    }

    private static function construction(array $items): array
    {
        $m = self::empty();
        $pieces = 0;
        $unknown = 0;
        foreach ($items as $i) {
            $p = $i['specs']['pakning_stk']['num'] ?? null;
            $p === null ? $unknown++ : $pieces += (int) $p * $i['qty'];
            $d = $i['specs']['diameter_mm'] ?? $i['specs']['dimensjon_mm'] ?? null;
            $l = $i['specs']['lengde_mm'] ?? null;
            if ($d || $l) {
                $m['stats'][] = ['label' => self::short($i), 'value' => trim(($d ? self::num($d['num'] ?? 0) : '?') . ' × ' . ($l ? self::num($l['num'] ?? 0) : '?') . ' mm'), 'note' => t('Diameter × lengde')];
            }
        }
        array_unshift($m['stats'], ['label' => t('Antall stykk'), 'value' => self::num($pieces), 'note' => $unknown ? t('Pakningsstørrelse ukjent for {n} produkter.', ['n' => $unknown]) : t('Fra oppgitt antall i pakken.')]);
        $m['step'] = ['title' => t('Antall og mål'), 'text' => t('{p} stykk etter oppgitt pakningsstørrelse.', ['p' => self::num($pieces)])];
        return $m;
    }

    private static function adventure(array $items): array
    {
        $m = self::empty();
        $vol = 0.0;
        $known = 0;
        foreach ($items as $i) {
            if (($v = $i['specs']['volum_l']['num'] ?? null) !== null) {
                $vol += $v * $i['qty'];
                $known++;
            }
        }
        if ($known) {
            $m['stats'][] = ['label' => t('Samlet volum'), 'value' => self::num($vol) . ' l', 'note' => t('Kjent for {k} av {n} produkter.', ['k' => $known, 'n' => count($items)])];
        }
        $m['step'] = ['title' => t('Vekt og volum'), 'text' => t('Vekt og volum summert der Sør-Tre oppgir dem.')];
        return $m;
    }

    // ---------- Test run ----------

    private static function steps(array $r): array
    {
        $v = $r['verification'];
        $c = $r['counts'];
        $t = $r['totals'];
        $steps = [
            ['title' => t('Inspiserer produktene'), 'state' => 'ok',
                'text' => t('{l} linjer, {u} enheter. Navn, pris og lager er lest fra databasen.', ['l' => $t['lines'], 'u' => $t['units']])],
            ['title' => t('Validerer spesifikasjoner'), 'state' => $v['mangler'] ? 'obs' : 'ok',
                'text' => t('{b} verdier fra Sør-Tre eller ansatt, {d} utledet eller fra kjøper, {m} mangler.', ['b' => $v['bekreftet'], 'd' => $v['utledet'], 'm' => $v['mangler']])],
            ['title' => t('Sjekker kompatibilitet'), 'state' => $c['ikke'] ? 'feil' : ($c['ukjent'] ? 'obs' : 'ok'),
                'text' => t('{d} dokumentert, {r} regelutledet, {u} ukjent, {i} dokumentert ikke kompatibel.', ['d' => $c['dokumentert'], 'r' => $c['regel'], 'u' => $c['ukjent'], 'i' => $c['ikke']])],
            ['title' => t('Ser etter manglende informasjon'), 'state' => $r['missing'] || $r['warnings'] ? 'obs' : 'ok',
                'text' => t('{m} mangler og {w} merknader.', ['m' => count($r['missing']), 'w' => count($r['warnings'])])],
        ];
        if ($r['module']) {
            $steps[] = ['title' => $r['module']['title'], 'state' => 'ok', 'text' => $r['module']['text']];
        }
        $steps[] = ['title' => t('Resultat'), 'state' => ['klar' => 'ok', 'mangler' => 'obs', 'konflikt' => 'feil', 'tom' => 'obs'][$r['verdict']],
            'text' => t(self::VERDICT[$r['verdict']])];
        return $steps;
    }

    public const VERDICT = [
        'klar' => 'Ingen konflikter eller mangler funnet i dataene.',
        'mangler' => 'Ingen dokumenterte konflikter, men noe mangler eller er ukjent.',
        'konflikt' => 'Minst ett par er dokumentert ikke kompatibelt.',
        'tom' => 'Oppsettet er tomt.',
    ];
}
