<?php
declare(strict_types=1);

/** Queries against the product catalog. All prices and stock come from here (constitution point 1). */
final class Catalog
{
    public const THEMES = [
        'lys' => ['Lys og lykter', 'Hodelykter, lommelykter og arbeidslys til jobb, tur og strømbrudd, med batterier som passer.'],
        'bygg' => ['Bygg og feste', 'Verktøy og festemidler til snekring: hammer, spiker, skruer, beslag og det du måler med.'],
        'maling' => ['Maling og sparkel', 'Det du trenger rundt malingen: ruller, pensler, sparkel, maskering og slipepapir.'],
        'balkong' => ['Balkong og levegg', 'Balkongglass, stendere og lister som tar vinden og innsynet uten å stenge utsikten.'],
        'kjetting' => ['Kjetting og løft', 'Sjakler, kjetting og kroker til løft, sikring og fortøyning, på land og i båten.'],
        'skog' => ['Skog og ved', 'Til vedskogen: sagkjeder og filer til motorsagen, hansker og hørselvern.'],
        'tur' => ['Tur og friluft', 'Kikkerter, grill og kokeutstyr til turen, hytta og båten.'],
    ];

    /** Card data for a list of product IDs, in the same order. */
    public static function cards(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));
        $wh = warehouse_id();
        $rows = db()->query("
            SELECT p.id, p.name, p.slug, p.theme, p.brand, p.price_ore, p.regular_ore,
              (SELECT path_m FROM product_images i WHERE i.product_id = p.id ORDER BY kind = 'sortre' DESC, sort LIMIT 1) img,
              (SELECT ROUND(AVG(rating),1) FROM reviews r WHERE r.product_id = p.id) rating,
              (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) n_reviews,
              s.in_store, s.online, s.preorder_eta
            FROM products p LEFT JOIN stock s ON s.product_id = p.id AND s.warehouse_id = $wh
            WHERE p.id IN ($in)")->fetchAll(PDO::FETCH_UNIQUE);
        $out = [];
        foreach ($ids as $id) {
            if (isset($rows[$id])) {
                $out[] = ['id' => (int) $id] + $rows[$id];
            }
        }
        return $out;
    }

    public static function product(string $slug): ?array
    {
        $st = db()->prepare('SELECT * FROM products WHERE slug = ?');
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    }

    public static function images(int $id): array
    {
        $st = db()->prepare("SELECT i.* FROM product_images i
                             WHERE product_id = ? ORDER BY kind = 'sortre' DESC, sort, id");
        $st->execute([$id]);
        return $st->fetchAll();
    }

    /** Specifications grouped per key with both versions: [key => [label, unit, verifisert, apen]] */
    public static function specs(int $id): array
    {
        $st = db()->prepare('SELECT v.*, k.label, k.unit, k.datatype, k.created_by key_creator, ' . Privacy::sql() . ' author
            FROM spec_values v JOIN spec_keys k ON k.`key` = v.`key`
            LEFT JOIN spec_revisions r ON r.id = v.revision_id LEFT JOIN users u ON u.id = r.user_id
            WHERE v.product_id = ? ORDER BY k.sort, k.label');
        $st->execute([$id]);
        $out = [];
        foreach ($st as $r) {
            $out[$r['key']] ??= ['label' => $r['label'], 'unit' => $r['unit'], 'verifisert' => null, 'apen' => null];
            $out[$r['key']][$r['version']] = $r;
        }
        return $out;
    }

    /** Effective value: Verified if it exists, otherwise Open. */
    public static function effective(array $spec): ?array
    {
        return $spec['verifisert'] ?? $spec['apen'];
    }

    public static function stock(int $id): array
    {
        $st = db()->prepare('SELECT w.id, w.name, w.is_real, s.in_store, s.online, s.preorder_eta
            FROM stock s JOIN warehouses w ON w.id = s.warehouse_id WHERE s.product_id = ? ORDER BY w.is_real DESC, w.name');
        $st->execute([$id]);
        return $st->fetchAll(PDO::FETCH_UNIQUE);
    }

    /** @return array{passer:array,ikke:array,kjopt:array} */
    public static function relations(int $id, int $limit = 8): array
    {
        $st = db()->prepare('SELECT product_b id, kind, level, reason FROM relations WHERE product_a = ?
            ORDER BY level = "dokumentert" DESC, score DESC, product_b');
        $st->execute([$id]);
        $out = ['passer' => [], 'ikke' => [], 'kjopt' => []];
        $map = ['passer_sammen' => 'passer', 'passer_ikke' => 'ikke', 'kjopt_sammen' => 'kjopt'];
        foreach ($st as $r) {
            $k = $map[$r['kind']] ?? null;
            if ($k && count($out[$k]) < $limit) {
                $out[$k][$r['id']] = $r;
            }
        }
        // Variety: not eight nearly identical nails. At most two from the same subcategory in "goes well with".
        $out['passer'] = self::diversify($out['passer'], 4);
        foreach ($out as $k => $rows) {
            $cards = self::cards(array_keys($rows));
            foreach ($cards as &$c) {
                $c['rel'] = $rows[$c['id']];
            }
            $out[$k] = $cards;
        }
        return $out;
    }

    private static function diversify(array $rows, int $max): array
    {
        if (!$rows) {
            return [];
        }
        $in = implode(',', array_keys($rows));
        $cat = db()->query("SELECT product_id, MAX(category_id) FROM product_categories WHERE product_id IN ($in) GROUP BY product_id")->fetchAll(PDO::FETCH_KEY_PAIR);
        $seen = [];
        $out = [];
        foreach ($rows as $id => $r) {
            $c = $cat[$id] ?? 0;
            if ($r['level'] === 'dokumentert' || ($seen[$c] ?? 0) < 2) {
                $out[$id] = $r;
                $seen[$c] = ($seen[$c] ?? 0) + 1;
            }
            if (count($out) >= $max) {
                break;
            }
        }
        return $out;
    }

    public static function reviews(int $id): array
    {
        $st = db()->prepare('SELECT r.*, ' . Privacy::sql() . ' name FROM reviews r JOIN users u ON u.id = r.user_id WHERE product_id = ? ORDER BY created_at DESC');
        $st->execute([$id]);
        return $st->fetchAll();
    }

    /**
     * The attributes products in a theme can be compared on: facet keys that Sør-Tre states (verified version,
     * source sortre) for at least $min products, most stated first. Counted from the database.
     */
    public static function compareKeys(string $theme, int $min = 4, int $limit = 6): array
    {
        $st = db()->prepare("SELECT k.`key`, k.label, k.unit, COUNT(DISTINCT v.product_id) n
            FROM spec_keys k JOIN spec_values v ON v.`key` = k.`key` AND v.version = 'verifisert' AND v.source = 'sortre'
            JOIN products p ON p.id = v.product_id AND p.theme = ?
            WHERE k.is_facet = 1 GROUP BY k.`key`, k.label, k.unit HAVING n >= ? ORDER BY n DESC, k.sort LIMIT " . (int) $limit);
        $st->execute([$theme, $min]);
        return $st->fetchAll();
    }

    /**
     * Facets for a theme. Which groups exist is decided by the whole theme, but the values and counts
     * in each group come from the products that match every other active filter, so an option that
     * would give zero hits disappears. Checked values always stay visible so they can be unchecked.
     */
    public static function facets(string $theme, array $f): array
    {
        $st = db()->prepare("SELECT * FROM spec_keys WHERE is_facet = 1 AND (themes IS NULL OR FIND_IN_SET(?, themes)) ORDER BY sort");
        $st->execute([$theme]);
        $keys = $st->fetchAll(PDO::FETCH_UNIQUE);
        $all = self::themeIds($theme);
        if (!$all || !$keys) {
            return [];
        }
        $out = [];
        foreach ($keys as $k => $meta) {
            $q = db()->quote($k);
            $in = implode(',', $all);
            $other = $f;
            unset($other['v'][$k], $other['min'][$k], $other['max'][$k]);
            $ids = implode(',', self::filter($theme, $other, 'navn')) ?: '0';
            if ($meta['datatype'] === 'number') {
                $whole = db()->query("SELECT MIN(value_num) lo, MAX(value_num) hi, COUNT(DISTINCT product_id) n FROM spec_values WHERE `key` = $q AND product_id IN ($in)")->fetch();
                if ($whole['n'] < 2 || $whole['lo'] >= $whole['hi']) {
                    continue;
                }
                $r = db()->query("SELECT MIN(value_num) lo, MAX(value_num) hi, COUNT(DISTINCT product_id) n FROM spec_values WHERE `key` = $q AND product_id IN ($ids)")->fetch();
                $lo = $r['n'] ? $r['lo'] : $whole['lo'];
                $hi = $r['n'] ? $r['hi'] : $whole['hi'];
                $out[$k] = $meta + ['type' => 'range', 'lo' => (float) $lo, 'hi' => (float) $hi, 'n' => (int) $r['n']];
            } else {
                $distinct = (int) db()->query("SELECT COUNT(DISTINCT value_text) FROM spec_values WHERE `key` = $q AND product_id IN ($in)")->fetchColumn();
                if ($distinct < 2) {
                    continue;
                }
                $vals = db()->query("SELECT value_text, COUNT(DISTINCT product_id) n, MAX(source = 'utledet') derived FROM spec_values
                                     WHERE `key` = $q AND product_id IN ($ids) GROUP BY value_text ORDER BY n DESC")->fetchAll();
                $shown = array_column($vals, 'value_text');
                foreach (array_filter((array) ($f['v'][$k] ?? []), 'strlen') as $checked) {
                    if (!in_array($checked, $shown, true)) {
                        $vals[] = ['value_text' => $checked, 'n' => 0, 'derived' => 0];
                    }
                }
                $out[$k] = $meta + ['type' => 'values', 'values' => $vals];
            }
        }
        return $out;
    }

    /** Product IDs in a theme after filters and sorting. */
    public static function filter(string $theme, array $f, string $sort): array
    {
        $where = ['p.theme = ?'];
        $args = [$theme];
        if (($f['pris_min'] ?? '') !== '') {
            $where[] = 'p.price_ore >= ?';
            $args[] = (int) round((float) $f['pris_min'] * 100);
        }
        if (($f['pris_max'] ?? '') !== '') {
            $where[] = 'p.price_ore <= ?';
            $args[] = (int) round((float) $f['pris_max'] * 100);
        }
        foreach ($f['v'] ?? [] as $key => $values) {
            $values = array_values(array_filter((array) $values, 'strlen'));
            if ($values) {
                $where[] = 'EXISTS (SELECT 1 FROM spec_values v WHERE v.product_id = p.id AND v.`key` = ? AND v.value_text IN (' . implode(',', array_fill(0, count($values), '?')) . '))';
                array_push($args, $key, ...$values);
            }
        }
        foreach ($f['min'] ?? [] as $key => $v) {
            if ($v !== '') {
                $where[] = 'EXISTS (SELECT 1 FROM spec_values v WHERE v.product_id = p.id AND v.`key` = ? AND v.value_num >= ?)';
                array_push($args, $key, (float) str_replace(',', '.', $v));
            }
        }
        foreach ($f['max'] ?? [] as $key => $v) {
            if ($v !== '') {
                $where[] = 'EXISTS (SELECT 1 FROM spec_values v WHERE v.product_id = p.id AND v.`key` = ? AND v.value_num <= ?)';
                array_push($args, $key, (float) str_replace(',', '.', $v));
            }
        }
        if (!empty($f['veiledning'])) {
            $where[] = 'p.id IN (' . (implode(',', array_keys(self::manualIds())) ?: '0') . ')';
        }
        if (!empty($f['lager'])) {
            $where[] = 'EXISTS (SELECT 1 FROM stock s WHERE s.product_id = p.id AND s.warehouse_id = ? AND s.in_store > 0)';
            $args[] = warehouse_id();
        }
        $order = match ($sort) {
            'pris' => 'p.price_ore ASC',
            'pris_desc' => 'p.price_ore DESC',
            'navn' => 'p.name ASC',
            'vurdering' => '(SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.id) IS NULL, (SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.id) DESC, (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) DESC, p.name',
            default => '(SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) + (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) DESC, p.name',
        };
        $st = db()->prepare('SELECT p.id FROM products p WHERE ' . implode(' AND ', $where) . " ORDER BY $order");
        $st->execute($args);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function themeIds(string $theme): array
    {
        $st = db()->prepare('SELECT id FROM products WHERE theme = ?');
        $st->execute([$theme]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function popular(int $n = 8, ?string $theme = null): array
    {
        $sql = 'SELECT p.id FROM products p WHERE EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id)'
            . ($theme ? ' AND p.theme = ' . db()->quote($theme) : '')
            . ' ORDER BY (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) * 2 + (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) DESC, p.id LIMIT ' . $n;
        return array_map('intval', db()->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Guides that apply to the product, by match_rule: "cat:7678,7680" or "re:sagkjede|rundfil". */
    /**
     * All products that have at least one assembly guide: [product ID => [guide, ...]].
     * Same rules as manualsFor, but computed once for the whole catalog.
     */
    public static function manualIds(): array
    {
        static $out = null;
        if ($out !== null) {
            return $out;
        }
        $out = [];
        $manuals = db()->query('SELECT id, slug, title, theme, match_rule FROM manuals ORDER BY title')->fetchAll();
        $cats = [];
        foreach (db()->query('SELECT product_id, category_id FROM product_categories') as $r) {
            $cats[(int) $r['product_id']][] = (int) $r['category_id'];
        }
        foreach (db()->query('SELECT id, name FROM products') as $p) {
            foreach ($manuals as $m) {
                [$kind, $rule] = explode(':', (string) $m['match_rule'], 2) + [1 => ''];
                $hit = $kind === 'cat' ? (bool) array_intersect($cats[(int) $p['id']] ?? [], array_map('intval', explode(',', $rule)))
                    : ($kind === 're' && preg_match('/' . str_replace('/', '\\/', $rule) . '/iu', $p['name']));
                if ($hit) {
                    $out[(int) $p['id']][] = $m;
                }
            }
        }
        return $out;
    }

    public static function hasManual(int $id): bool
    {
        return isset(self::manualIds()[$id]);
    }

    /**
     * Ranked lists for questions about the whole catalog ("best rated", "cheapest"). Facts from the database, not the model.
     * $within limits it to a list of product IDs (for example a theme or search hits).
     */
    public static function ranked(string $what, int $n = 5, ?array $within = null): array
    {
        $in = $within !== null ? ' AND p.id IN (' . (implode(',', array_map('intval', $within)) ?: '0') . ')' : '';
        $avg = '(SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.id)';
        $cnt = '(SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id)';
        $sql = match ($what) {
            'best' => "SELECT p.id FROM products p WHERE $cnt > 0$in ORDER BY $avg DESC, $cnt DESC, p.name",
            'verst' => "SELECT p.id FROM products p WHERE $cnt > 0$in ORDER BY $avg ASC, $cnt DESC, p.name",
            'billigst' => "SELECT p.id FROM products p WHERE 1$in ORDER BY p.price_ore ASC, p.name",
            'dyrest' => "SELECT p.id FROM products p WHERE 1$in ORDER BY p.price_ore DESC, p.name",
            'manual' => "SELECT p.id FROM products p WHERE p.id IN (" . (implode(',', array_keys(self::manualIds())) ?: '0') . ")$in ORDER BY $cnt DESC, p.name",
        };
        return array_map('intval', db()->query("$sql LIMIT $n")->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function manualsFor(array $p): array
    {
        $st = db()->prepare('SELECT category_id FROM product_categories WHERE product_id = ?');
        $st->execute([$p['id']]);
        $cats = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $out = [];
        foreach (db()->query('SELECT * FROM manuals ORDER BY title') as $m) {
            [$kind, $rule] = explode(':', (string) $m['match_rule'], 2) + [1 => ''];
            $hit = $kind === 'cat' ? (bool) array_intersect($cats, array_map('intval', explode(',', $rule)))
                : ($kind === 're' && preg_match('/' . str_replace('/', '\\/', $rule) . '/iu', $p['name']));
            if ($hit) {
                $out[] = $m;
            }
        }
        return $out;
    }
}
