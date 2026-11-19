<?php
declare(strict_types=1);

/** Floor rows from the database. See FloorStore. */
final class PdoFloorStore implements FloorStore
{
    public function __construct(private PDO $pdo)
    {
    }

    /** "type IN (?, ...)" for an explicit type list. An empty list matches no rows. */
    private static function typeIn(array $types): string
    {
        return $types ? 'type IN (' . implode(',', array_fill(0, count($types), '?')) . ')' : '0';
    }

    public function knownTypes(): array
    {
        return $this->pdo->query('SELECT DISTINCT type FROM analytics_events ORDER BY type')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function events(int $since, array $types, int $after, int $limit): array
    {
        $where = 'created_at >= FROM_UNIXTIME(?) AND id > ? AND ' . self::typeIn($types);
        $args = [$since, $after, ...$types];
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM analytics_events WHERE $where");
        $st->execute($args);
        $total = (int) $st->fetchColumn();
        $st = $this->pdo->prepare("SELECT id, type, data, UNIX_TIMESTAMP(created_at) at FROM analytics_events WHERE $where ORDER BY id DESC LIMIT " . max(0, $limit));
        $st->execute($args);
        $rows = array_map(fn($r) => ['id' => (int) $r['id'], 'type' => $r['type'], 'data' => json_decode((string) $r['data'], true), 'at' => (int) $r['at']],
            array_reverse($st->fetchAll()));
        $last = (int) $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM analytics_events')->fetchColumn();
        return ['total' => $total, 'last' => $last, 'rows' => $rows];
    }

    public function counts(int $since, array $types, int $bucket): array
    {
        $bucket = max(1, $bucket);
        $st = $this->pdo->prepare("SELECT FLOOR(UNIX_TIMESTAMP(created_at) / $bucket) * $bucket b, COUNT(*) n
            FROM analytics_events WHERE created_at >= FROM_UNIXTIME(?) AND " . self::typeIn($types) . ' GROUP BY b ORDER BY b');
        $st->execute([$since, ...$types]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public function workProducts(int $limit): array
    {
        $u = PdoShipmentStore::underwayIn();
        $rows = $this->pdo->query("SELECT p.id, p.name, p.theme,
                (SELECT COUNT(*) FROM spec_revisions r WHERE r.product_id = p.id AND r.status = 'aktiv') revisions,
                (SELECT COUNT(*) FROM spec_reports s WHERE s.product_id = p.id AND s.status = 'apen') reports,
                (SELECT COUNT(*) FROM support_tickets t WHERE t.product_id = p.id AND t.status IN ('apen','pagar')) tickets,
                (SELECT s.ref FROM shipment_items i JOIN shipments s ON s.id = i.shipment_id
                  WHERE i.product_id = p.id AND s.direction = 'inn' AND s.status IN $u ORDER BY s.eta LIMIT 1) inbound
            FROM products p
            WHERE EXISTS (SELECT 1 FROM spec_revisions r WHERE r.product_id = p.id AND r.status = 'aktiv')
               OR EXISTS (SELECT 1 FROM spec_reports s WHERE s.product_id = p.id AND s.status = 'apen')
               OR EXISTS (SELECT 1 FROM support_tickets t WHERE t.product_id = p.id AND t.status IN ('apen','pagar'))
               OR EXISTS (SELECT 1 FROM shipment_items i JOIN shipments s ON s.id = i.shipment_id
                          WHERE i.product_id = p.id AND s.direction = 'inn' AND s.status IN $u)
            ORDER BY p.id LIMIT " . max(0, $limit))->fetchAll();
        return array_map(fn($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'theme' => $r['theme'], 'revisions' => (int) $r['revisions'],
            'reports' => (int) $r['reports'], 'tickets' => (int) $r['tickets'], 'inbound' => $r['inbound']], $rows);
    }

    public function backlog(): array
    {
        return [
            'kvalitet' => (int) $this->pdo->query("SELECT (SELECT COUNT(*) FROM spec_revisions WHERE status = 'aktiv') + (SELECT COUNT(*) FROM spec_reports WHERE status = 'apen')")->fetchColumn(),
            'kundeservice' => (int) $this->pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status <> 'lukket'")->fetchColumn(),
        ];
    }
}
