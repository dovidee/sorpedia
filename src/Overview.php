<?php
declare(strict_types=1);

/**
 * The Control Tower's first page (feature 030): the state of the shop at a glance. Every figure is counted here from
 * stored rows; nothing is estimated or made up, and a figure without data is returned as null ("ukjent").
 * Money is only included for the admin role, as on the rest of the tower.
 */
final class Overview
{
    /** Shipment statuses that count as on the road. */
    private const OPEN = ['ikke_startet', 'lastes', 'underveis', 'stoppet', 'losses', 'forsinket'];

    public static function build(int $now, bool $admin, array $shipments, array $ferries, array $conditions): array
    {
        $pdo = db();
        $week = $now - 7 * 86400;
        $count = fn(string $type) => (int) $pdo->query('SELECT COUNT(*) FROM analytics_events WHERE type = ' . $pdo->quote($type)
            . " AND created_at >= FROM_UNIXTIME($week)")->fetchColumn();

        // Product data: filter values present, of every filter a product's theme has
        $q = Tower::quality($week, [], 0);
        $slots = (int) $pdo->query('SELECT COUNT(*) FROM products p JOIN spec_keys k ON k.is_facet = 1 AND (k.themes IS NULL OR FIND_IN_SET(p.theme, k.themes))')->fetchColumn();

        // Deliveries: arrived on or before the planned time, of those that arrived in the last 30 days
        $d = $pdo->query('SELECT COUNT(*) n, SUM(actual_arrival <= planned_arrival) ok, SUM(is_demo) demo FROM shipments
            WHERE status = "levert" AND actual_arrival IS NOT NULL AND planned_arrival IS NOT NULL AND actual_arrival >= NOW() - INTERVAL 30 DAY')->fetch();
        $open = array_values(array_filter($shipments, fn($s) => in_array($s['status'], self::OPEN, true)));
        $late = count(array_filter($open, fn($s) => $s['status'] === 'forsinket' || $s['status'] === 'stoppet'));

        // Customer service: waiting for staff (apen) and being handled (pagar); the oldest waiting case is the top action
        $t = $pdo->query("SELECT SUM(status = 'apen') waiting, SUM(status = 'pagar') handling FROM support_tickets")->fetch();
        $oldest = $pdo->query("SELECT id, summary, UNIX_TIMESTAMP(created_at) at FROM support_tickets WHERE status = 'apen' ORDER BY created_at LIMIT 1")->fetch() ?: null;

        // Orders the last 7 days
        $o = $pdo->query("SELECT COUNT(*) n, COALESCE(SUM(total_ore), 0) ore, SUM(is_demo_seed) demo FROM orders
            WHERE status <> 'kansellert' AND created_at >= FROM_UNIXTIME($week)")->fetch();
        $refunds = $pdo->query("SELECT COUNT(DISTINCT order_id) n, COALESCE(SUM(amount_ore), 0) ore FROM order_refunds WHERE created_at >= FROM_UNIXTIME($week)")->fetch();

        $searches = $count('search') + $count('search_zero');
        $chat = $count('chat');
        $misses = Tower::failedSearches($week);

        return [
            'now' => $now,
            'data' => ['slots' => $slots, 'filled' => max(0, $slots - $q['missingValues']), 'products' => $q['products'], 'missingValues' => $q['missingValues'],
                'unverified' => $q['unverified'], 'reports' => $q['reports']],
            'delivery' => ['arrived' => (int) $d['n'], 'onTime' => (int) $d['ok'], 'demo' => (int) $d['demo'] > 0,
                'open' => count($open), 'late' => $late, 'stale' => count(array_filter($open, fn($s) => $s['stale']))],
            'cases' => ['waiting' => (int) $t['waiting'], 'handling' => (int) $t['handling'], 'oldest' => $oldest === null ? null
                : ['id' => (int) $oldest['id'], 'summary' => (string) $oldest['summary'], 'at' => (int) $oldest['at']]],
            'orders' => ['n' => (int) $o['n'], 'demo' => (int) $o['demo'], 'refunds' => (int) $refunds['n']]
                + ($admin ? ['ore' => (int) $o['ore'], 'refundOre' => (int) $refunds['ore']] : []),
            'search' => ['n' => $searches, 'zero' => $count('search_zero'), 'top' => $misses[0] ?? null],
            'soren' => ['n' => $chat, 'escalated' => $count('escalate')],
            'ferries' => array_map(fn($f) => ['name' => $f['name'], 'status' => $f['status'], 'delay' => $f['delay_min']], $ferries),
            'roads' => ['closures' => count(array_filter($conditions, fn($c) => $c['kind'] === 'closure')),
                'warnings' => count(array_filter($conditions, fn($c) => $c['kind'] === 'warning')),
                'roadwork' => count(array_filter($conditions, fn($c) => $c['kind'] === 'roadwork')),
                'demo' => (bool) array_filter($conditions, fn($c) => (int) $c['is_demo'] === 1)],
            'warehouses' => self::warehouses($q['products']),
            'activity' => self::activity($admin),
            'backup' => Insight::lastBackup(),
        ];
    }

    /** Stock and traffic per warehouse. Stock per store is demo data (rule 7) and flagged as such. */
    public static function warehouses(int $products): array
    {
        $pdo = db();
        $road = $pdo->query("SELECT warehouse_id, COUNT(*) FROM shipments WHERE warehouse_id IS NOT NULL AND status IN ('" . implode("','", self::OPEN) . "') GROUP BY warehouse_id")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        $orders = $pdo->query('SELECT warehouse_id, COUNT(*) FROM orders WHERE status <> "kansellert" AND created_at >= NOW() - INTERVAL 30 DAY GROUP BY warehouse_id')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        $rows = $pdo->query('SELECT w.id, w.name, w.is_real, COALESCE(SUM(s.in_store > 0), 0) skus, COALESCE(SUM(GREATEST(s.in_store, 0)), 0) units,
            COALESCE(SUM(s.in_store <= 0), 0) empty FROM warehouses w LEFT JOIN stock s ON s.warehouse_id = w.id GROUP BY w.id ORDER BY w.is_real DESC, units DESC')->fetchAll();
        return array_map(fn($w) => ['id' => (int) $w['id'], 'name' => $w['name'], 'real' => (bool) $w['is_real'], 'skus' => (int) $w['skus'],
            'units' => (int) $w['units'], 'empty' => (int) $w['empty'], 'cover' => $products ? (int) round(100 * $w['skus'] / $products) : null,
            'orders' => (int) ($orders[$w['id']] ?? 0), 'road' => (int) ($road[$w['id']] ?? 0)], $rows);
    }

    /**
     * The latest things that happened, newest first: orders, new cases, buyer edits, arrived shipments and logged staff
     * actions. Order amounts only for the admin role.
     */
    public static function activity(bool $admin, int $limit = 7): array
    {
        $pdo = db();
        $sql = "(SELECT UNIX_TIMESTAMP(o.created_at) at, 'order' kind, o.id ref, CONCAT(COALESCE(SUM(i.qty), 0)) a, w.name b, o.total_ore c, o.is_demo_seed demo
                FROM orders o LEFT JOIN order_items i ON i.order_id = o.id LEFT JOIN warehouses w ON w.id = o.warehouse_id GROUP BY o.id ORDER BY o.created_at DESC LIMIT $limit)
            UNION ALL (SELECT UNIX_TIMESTAMP(created_at), 'case', id, summary, NULL, NULL, 0 FROM support_tickets ORDER BY created_at DESC LIMIT $limit)
            UNION ALL (SELECT UNIX_TIMESTAMP(r.created_at), 'edit', r.id, p.name, NULL, NULL, 0 FROM spec_revisions r JOIN products p ON p.id = r.product_id ORDER BY r.created_at DESC LIMIT $limit)
            UNION ALL (SELECT UNIX_TIMESTAMP(actual_arrival), 'arrived', id, ref, dest_city, NULL, is_demo FROM shipments WHERE actual_arrival IS NOT NULL ORDER BY actual_arrival DESC LIMIT $limit)
            UNION ALL (SELECT UNIX_TIMESTAMP(at), 'audit', id, action, target, NULL, 0 FROM audit_log WHERE kind IN ('admin','drift') AND action <> 'backup_request' ORDER BY id DESC LIMIT $limit)
            ORDER BY at DESC LIMIT $limit";
        return array_map(fn($r) => ['at' => (int) $r['at'], 'kind' => $r['kind'], 'ref' => (int) $r['ref'], 'a' => $r['a'], 'b' => $r['b'],
            'ore' => $admin && $r['c'] !== null ? (int) $r['c'] : null, 'demo' => (bool) $r['demo']], $pdo->query($sql)->fetchAll());
    }
}
