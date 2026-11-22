<?php
declare(strict_types=1);

/**
 * Control Tower reads that carry no rules of their own: the product inspector, tickets, combined missing keys,
 * data quality and the KPI definitions (specs/022-control-tower).
 * Floor rules live in Floor and ControlFloor, transport in ShipmentRules and Transport, drafts in DraftPrompt and Drafts.
 */
final class Tower
{
    /** Tables and columns added by feature 022 on databases built before it. */
    public static function ensure(): void
    {
        $pdo = db();
        PdoShipmentStore::ensure($pdo);
        Gulv::ensure($pdo);
        Drill::ensure($pdo);
        Refund::ensure($pdo);
        Supply::ensure($pdo);
        if (!$pdo->query("SHOW COLUMNS FROM support_tickets LIKE 'product_id'")->fetch()) {
            // IF NOT EXISTS: two first requests can both see the column missing, and the second must not fail
            $pdo->exec('ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS product_id INT UNSIGNED NULL AFTER chat_log, ADD KEY IF NOT EXISTS product_id (product_id)');
        }
    }

    /** Open tickets for the customer service list, newest first, as on the support page. Customers are shown by alias only. */
    public static function tickets(): array
    {
        return db()->query("SELECT t.id, t.status, t.summary, t.product_id, p.name product, UNIX_TIMESTAMP(t.created_at) at, " . Privacy::sql() . " customer,
                (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id) messages
            FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN products p ON p.id = t.product_id
            WHERE t.status <> 'lukket' ORDER BY t.id DESC LIMIT 30")->fetchAll();
    }

    /** Every ticket for the customer service view, open first, then newest. Customers are shown by alias only. */
    public static function desk(): array
    {
        return db()->query("SELECT t.id, t.status, t.summary, t.product_id, p.name product, UNIX_TIMESTAMP(t.created_at) at, " . Privacy::sql() . " customer,
                (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id) messages,
                (SELECT sender FROM support_messages m WHERE m.ticket_id = t.id ORDER BY m.id DESC LIMIT 1) last_from,
                (SELECT UNIX_TIMESTAMP(MAX(created_at)) FROM support_messages m WHERE m.ticket_id = t.id) last_at
            FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN products p ON p.id = t.product_id
            ORDER BY t.status = 'lukket', t.id DESC LIMIT 60")->fetchAll();
    }

    /** Search terms without results since a time, most frequent first, with the latest event so staff can act on it. */
    public static function failedSearches(int $since): array
    {
        $st = db()->prepare("SELECT JSON_UNQUOTE(JSON_EXTRACT(data, '$.q')) q, COUNT(*) n, MAX(id) event, UNIX_TIMESTAMP(MAX(created_at)) at
            FROM analytics_events WHERE type = 'search_zero' AND created_at >= FROM_UNIXTIME(?) GROUP BY q ORDER BY n DESC, at DESC LIMIT 30");
        $st->execute([$since]);
        return $st->fetchAll();
    }

    /** One ticket with its stored chat log and the messages added since. */
    public static function ticket(int $id): ?array
    {
        $st = db()->prepare("SELECT t.id, t.status, t.summary, t.chat_log, t.product_id, p.name product, p.slug, UNIX_TIMESTAMP(t.created_at) at, " . Privacy::sql() . " customer
            FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN products p ON p.id = t.product_id WHERE t.id = ?");
        $st->execute([$id]);
        $t = $st->fetch();
        if (!$t) {
            return null;
        }
        $t['chat'] = array_map(fn($m) => ['role' => $m['role'] ?? '', 'content' => (string) ($m['content'] ?? '')], json_decode((string) $t['chat_log'], true) ?: []);
        unset($t['chat_log']);
        $st = db()->prepare('SELECT sender, body, UNIX_TIMESTAMP(created_at) at FROM support_messages WHERE ticket_id = ? ORDER BY id');
        $st->execute([$id]);
        $t['messages'] = $st->fetchAll();
        return $t;
    }

    /**
     * Missing facet keys summed over several products, for a multi-selection.
     * @param int[] $ids
     */
    public static function missing(array $ids): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, ControlFloor::LIMIT);
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT k.label, COUNT(*) n FROM products p JOIN spec_keys k
                ON k.is_facet = 1 AND (k.themes IS NULL OR FIND_IN_SET(p.theme, k.themes))
            WHERE p.id IN ($in) AND NOT EXISTS (SELECT 1 FROM spec_values v WHERE v.product_id = p.id AND v.`key` = k.`key`)
            GROUP BY k.`key`, k.label ORDER BY n DESC, k.label LIMIT 20");
        $st->execute($ids);
        return $st->fetchAll();
    }

    /**
     * Data quality for the KPI column (PRIORITY phase 5). Catalogue counts are current state; unknown events follow the
     * window and the type filter. Stale positions come from Transport.
     * @param string[] $types
     */
    public static function quality(int $since, array $types, int $staleUnderway): array
    {
        $pdo = db();
        $unknown = array_values(array_diff($types, array_keys(Floor::ROUTES)));
        $in = $unknown ? 'type IN (' . implode(',', array_fill(0, count($unknown), '?')) . ')' : '0';
        $st = $pdo->prepare("SELECT COUNT(*) FROM analytics_events WHERE created_at >= FROM_UNIXTIME(?) AND $in");
        $st->execute([$since, ...$unknown]);
        $facets = "FROM products p JOIN spec_keys k ON k.is_facet = 1 AND (k.themes IS NULL OR FIND_IN_SET(p.theme, k.themes))
            WHERE NOT EXISTS (SELECT 1 FROM spec_values v WHERE v.product_id = p.id AND v.`key` = k.`key`)";
        return [
            'products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
            'missingProducts' => (int) $pdo->query("SELECT COUNT(DISTINCT p.id) $facets")->fetchColumn(),
            'missingValues' => (int) $pdo->query("SELECT COUNT(*) $facets")->fetchColumn(),
            'unverified' => (int) $pdo->query("SELECT COUNT(DISTINCT product_id, `key`) FROM spec_values v WHERE version = 'apen'
                AND NOT EXISTS (SELECT 1 FROM spec_values w WHERE w.product_id = v.product_id AND w.`key` = v.`key` AND w.version = 'verifisert')")->fetchColumn(),
            'reports' => (int) $pdo->query("SELECT COUNT(*) FROM spec_reports WHERE status = 'apen'")->fetchColumn(),
            'noPrice' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE price_ore IS NULL OR price_ore <= 0')->fetchColumn(),
            'unknownEvents' => (int) $st->fetchColumn(),
            'stalePositions' => $staleUnderway,
        ];
    }

    /** Everything the inspector shows for one product. Name, price and stock come from the database (rule 1). */
    public static function product(int $id): ?array
    {
        $card = Catalog::cards([$id])[0] ?? null;
        if (!$card) {
            return null;
        }
        $specs = [];
        foreach (Catalog::specs($id) as $key => $s) {
            $v = Catalog::effective($s);
            $specs[] = ['key' => $key, 'label' => $s['label'], 'unit' => $s['unit'], 'value' => $v['value_text'] ?? null,
                'source' => $v['source'] ?? null, 'version' => $v['version'] ?? null];
        }
        $st = db()->prepare("SELECT k.`key`, k.label FROM spec_keys k
            WHERE k.is_facet = 1 AND (k.themes IS NULL OR FIND_IN_SET(?, k.themes))
              AND NOT EXISTS (SELECT 1 FROM spec_values v WHERE v.product_id = ? AND v.`key` = k.`key`)
            ORDER BY k.sort, k.label");
        $st->execute([$card['theme'], $id]);
        $missing = $st->fetchAll();
        $st = db()->prepare("SELECT r.id, r.diff, r.comment, UNIX_TIMESTAMP(r.created_at) at, " . Privacy::sql() . " author
            FROM spec_revisions r LEFT JOIN users u ON u.id = r.user_id WHERE r.product_id = ? AND r.status = 'aktiv' ORDER BY r.id");
        $st->execute([$id]);
        $revisions = array_map(fn($r) => ['diff' => json_decode($r['diff'], true)] + $r, $st->fetchAll());
        $st = db()->prepare("SELECT MAX(r.id) id, r.`key`, k.label, COUNT(*) n, UNIX_TIMESTAMP(MIN(r.created_at)) at,
                GROUP_CONCAT(r.reason ORDER BY r.id SEPARATOR '\n') reasons
            FROM spec_reports r JOIN spec_keys k ON k.`key` = r.`key`
            WHERE r.product_id = ? AND r.status = 'apen' GROUP BY r.`key`, k.label ORDER BY n DESC");
        $st->execute([$id]);
        $reports = $st->fetchAll();
        $stock = array_values(array_map(fn($w) => ['name' => $w['name'], 'in_store' => (int) $w['in_store'], 'online' => (int) $w['online']], Catalog::stock($id)));
        $st = db()->prepare("SELECT t.id, t.status, t.summary, UNIX_TIMESTAMP(t.created_at) at,
                (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id) messages
            FROM support_tickets t WHERE t.product_id = ? AND t.status IN ('apen','pagar') ORDER BY t.id");
        $st->execute([$id]);
        $tickets = $st->fetchAll();
        // Documented relationships only: both products, the kind and Sør-Tre's own reason (rule-based ones are counted on the floor)
        $st = db()->prepare("SELECT o.id, o.name, r.kind, r.level, r.reason,
                (SELECT path_m FROM product_images i WHERE i.product_id = o.id ORDER BY kind = 'sortre' DESC, sort LIMIT 1) img
            FROM relations r JOIN products o ON o.id = IF(r.product_a = ?, r.product_b, r.product_a)
            WHERE (r.product_a = ? OR r.product_b = ?) AND r.level = 'dokumentert' ORDER BY r.kind = 'passer_ikke' DESC, o.name LIMIT 20");
        $st->execute([$id, $id, $id]);
        $relations = $st->fetchAll();
        $st = db()->prepare('SELECT s.id, s.ref, s.status, s.origin_city, s.dest_city, UNIX_TIMESTAMP(s.eta) eta, i.qty, s.is_demo
            FROM shipment_items i JOIN shipments s ON s.id = i.shipment_id
            WHERE i.product_id = ? AND s.direction = "inn" AND s.status IN ' . PdoShipmentStore::underwayIn() . ' ORDER BY s.eta');
        $st->execute([$id]);
        return [
            'relations' => $relations,
            'id' => $id, 'name' => $card['name'], 'slug' => $card['slug'], 'theme' => $card['theme'], 'price_ore' => (int) $card['price_ore'], 'img' => $card['img'] ?? null,
            'stock' => $stock, 'stock_demo' => true,
            'specs' => $specs, 'missing' => $missing, 'revisions' => $revisions, 'reports' => $reports,
            'tickets' => $tickets, 'inbound' => $st->fetchAll(),
        ];
    }

    /**
     * The KPI definitions from the metrics page, unchanged. Seed data is counted separately so it can be labelled.
     * Event counts follow the panel's window (lower bound) and types when given; the metrics page passes nothing and gets all time.
     * The catalog blocks (coverage, relations, contributions, turnover, users) are current state and never filtered.
     */
    public static function kpis(?int $since = null, ?array $types = null): array
    {
        $pdo = db();
        $since = $since !== null ? ' AND created_at >= FROM_UNIXTIME(' . $since . ')' : '';
        $count = fn(string $type) => $types !== null && !in_array($type, $types, true) ? 0
            : (int) $pdo->query('SELECT COUNT(*) FROM analytics_events WHERE type = ' . $pdo->quote($type) . $since)->fetchColumn();
        $zero = $types === null || in_array('search_zero', $types, true);
        return [
            'kpi' => [
                'searches' => $count('search') + $count('search_zero'),
                'zero' => $count('search_zero'),
                'guide' => $count('guide_done'),
                'chat' => $count('chat'),
                'escalate' => $count('escalate'),
                'compare' => $count('compare'),
                'purchases' => $count('purchase'),
            ],
            'zeroTerms' => $zero ? $pdo->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(data, '$.q')) q, COUNT(*) n FROM analytics_events WHERE type = 'search_zero'$since GROUP BY q ORDER BY n DESC LIMIT 10")->fetchAll() : [],
            'via' => $pdo->query("SELECT COALESCE(added_via, 'ukjent') via, COUNT(*) n, SUM(qty * price_ore) ore FROM order_items GROUP BY via ORDER BY n DESC")->fetchAll(),
            'viaDemo' => (int) $pdo->query('SELECT COUNT(*) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.is_demo_seed = 1')->fetchColumn(),
            'coverage' => $pdo->query('SELECT version, source, COUNT(*) n FROM spec_values GROUP BY version, source ORDER BY version, n DESC')->fetchAll(),
            'rel' => $pdo->query('SELECT level, kind, COUNT(*) n FROM relations GROUP BY level, kind ORDER BY level')->fetchAll(),
            'users' => $pdo->query('SELECT role, COUNT(*) n, SUM(email_verified_at IS NOT NULL) verified FROM users GROUP BY role')->fetchAll(),
            'contrib' => $pdo->query('SELECT status, COUNT(*) n FROM spec_revisions GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR),
        ];
    }
}
