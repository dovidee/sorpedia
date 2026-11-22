<?php
declare(strict_types=1);

/**
 * Gulv in the Control Tower (specs/032-control-tower-redo): products and shipments only. Customer analytics never
 * become objects on the floor; they live in Oversikt and Innsikt. Every object here is a stored row.
 *
 * Stations, in the order a product meets them:
 *   inntak    Varemottak: products on an inbound shipment that has arrived and waits for staff to check it in
 *   kontroll  Produktkontroll: products with a buyer change waiting for Flett inn, or an open improvement task
 *   kvalitet  Datakvalitet: products with an open error report (the specific value and reason)
 *   kunnskap  Varekunnskap: the knowledge base itself, drawn as stacks per source, never as products
 *   kobling   Kompatibilitet: products in a documented relationship (fits, or does not fit)
 *   klar      Klargjøring: products whose change was merged or whose task was completed in the last 30 days
 * Shipments on the way rest on their own dock (mottak, Sendinger inn) as pallets. When a shipment arrives on the
 * simulation clock its products move to Varemottak; checking it in (DemoDelivery::checkIn) clears them from there.
 */
final class Gulv
{
    public const STATIONS = ['inntak', 'kontroll', 'kvalitet', 'kunnskap', 'kobling', 'klar', 'mottak'];

    /** Task states as staff read them. 'apen' to 'klar_for_kontroll' are active; the rest are finished. */
    public const STATUSES = ['apen', 'under_behandling', 'klar_for_kontroll', 'fullfort', 'avvist', 'arkivert'];
    public const ACTIVE = ['apen', 'under_behandling', 'klar_for_kontroll'];

    /** Where a task came from. */
    public const SOURCES = ['sok', 'datakvalitet', 'produkt', 'kompatibilitet', 'mulighet', 'revisjon'];

    /** Most products drawn per station; totals always use full counts. */
    public const PER_STATION = 60;

    /** Days a finished product stays on Klargjøring. */
    public const READY_DAYS = 30;

    /** Brings improvement_tasks from the first version (apen/lukket, event only) up to the task lifecycle. */
    public static function ensure(PDO $pdo): void
    {
        EventDesk::ensure($pdo);
        if ($pdo->query("SHOW COLUMNS FROM improvement_tasks LIKE 'source'")->fetch()) {
            return;
        }
        $pdo->exec("ALTER TABLE improvement_tasks MODIFY status ENUM('apen','lukket','under_behandling','klar_for_kontroll','fullfort','avvist','arkivert') NOT NULL DEFAULT 'apen'");
        $pdo->exec("UPDATE improvement_tasks SET status = 'fullfort' WHERE status = 'lukket'");
        $pdo->exec("ALTER TABLE improvement_tasks
            MODIFY status ENUM('apen','under_behandling','klar_for_kontroll','fullfort','avvist','arkivert') NOT NULL DEFAULT 'apen',
            ADD COLUMN IF NOT EXISTS source VARCHAR(24) NOT NULL DEFAULT 'sok' AFTER product_id,
            ADD COLUMN IF NOT EXISTS spec_key VARCHAR(48) NULL AFTER source,
            ADD COLUMN IF NOT EXISTS revision_id INT UNSIGNED NULL AFTER spec_key,
            ADD COLUMN IF NOT EXISTS origin VARCHAR(160) NULL AFTER revision_id,
            ADD COLUMN IF NOT EXISTS note VARCHAR(500) NULL AFTER title,
            ADD COLUMN IF NOT EXISTS outcome VARCHAR(300) NULL AFTER note,
            ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            ADD KEY IF NOT EXISTS product_id (product_id),
            ADD KEY IF NOT EXISTS origin (origin)");
    }

    /** Everything the floor and its two side panels show, read in one request. */
    public static function data(): array
    {
        $pdo = db();
        $u = PdoShipmentStore::underwayIn();
        $active = "'" . implode("','", self::ACTIVE) . "'";
        $per = self::PER_STATION;
        $since = 'NOW() - INTERVAL ' . self::READY_DAYS . ' DAY';
        // One row per product and the reason it is on the floor; a product rests at the first station that applies
        $why = [
            'kvalitet' => "SELECT product_id id, COUNT(*) n, UNIX_TIMESTAMP(MIN(created_at)) at FROM spec_reports WHERE status = 'apen' GROUP BY product_id",
            'kontroll' => "SELECT id, SUM(n) n, MIN(at) at FROM (
                    SELECT product_id id, COUNT(*) n, UNIX_TIMESTAMP(MIN(created_at)) at FROM spec_revisions WHERE status = 'aktiv' GROUP BY product_id
                    UNION ALL SELECT product_id, COUNT(*), UNIX_TIMESTAMP(MIN(created_at)) FROM improvement_tasks WHERE product_id IS NOT NULL AND status IN ($active) GROUP BY product_id
                ) x GROUP BY id",
            'inntak' => "SELECT i.product_id id, SUM(i.qty) n, UNIX_TIMESTAMP(MAX(s.actual_arrival)) at FROM shipment_items i JOIN shipments s ON s.id = i.shipment_id
                WHERE s.direction = 'inn' AND s.status = 'levert' AND s.checked_in_at IS NULL GROUP BY i.product_id",
            'kobling' => "SELECT id, COUNT(*) n, NULL at FROM (SELECT product_a id FROM relations WHERE level = 'dokumentert' UNION ALL SELECT product_b FROM relations WHERE level = 'dokumentert') r GROUP BY id",
            'klar' => "SELECT id, COUNT(*) n, MAX(at) at FROM (
                    SELECT product_id id, UNIX_TIMESTAMP(reviewed_at) at FROM spec_revisions WHERE status = 'flettet' AND reviewed_at >= $since
                    UNION ALL SELECT product_id, UNIX_TIMESTAMP(COALESCE(updated_at, created_at)) FROM improvement_tasks
                      WHERE product_id IS NOT NULL AND status = 'fullfort' AND COALESCE(updated_at, created_at) >= $since
                ) x GROUP BY id",
        ];
        $placed = [];
        $totals = [];
        $products = [];
        foreach ($why as $station => $sql) {
            $rows = $pdo->query($sql)->fetchAll();
            $rows = array_values(array_filter($rows, fn($r) => !isset($placed[(int) $r['id']])));
            $totals[$station] = count($rows);
            foreach (array_slice($rows, 0, $per) as $r) {
                $placed[(int) $r['id']] = true;
                $products[] = ['id' => (int) $r['id'], 'station' => $station, 'n' => (int) $r['n'], 'at' => $r['at'] !== null ? (int) $r['at'] : null];
            }
        }
        $cards = $products ? $pdo->query('SELECT p.id, p.name, p.theme,
                (SELECT path_m FROM product_images i WHERE i.product_id = p.id ORDER BY kind = \'sortre\' DESC, sort LIMIT 1) img
            FROM products p WHERE p.id IN (' . implode(',', array_column($products, 'id')) . ')')->fetchAll(PDO::FETCH_UNIQUE) : [];
        $products = array_values(array_filter(array_map(fn($p) => isset($cards[$p['id']]) ? $p + $cards[$p['id']] : null, $products)));
        return [
            'products' => $products,
            'totals' => $totals,
            'shipments' => self::inbound(),
            'tasks' => self::tasks(),
            'knowledge' => self::knowledge(),
            'incoming' => self::incoming(),
            'received' => self::received(),
            'picks' => Supply::list(),
        ];
    }

    /** Inbound shipments still on the way, each with its cargo lines and product photos. Demo rows say so. */
    public static function inbound(): array
    {
        $pdo = db();
        $u = PdoShipmentStore::underwayIn();
        $rows = $pdo->query("SELECT s.id, s.ref, s.status, s.origin_city, s.dest_city, s.is_demo, s.delay_reason,
                UNIX_TIMESTAMP(s.eta) eta, UNIX_TIMESTAMP(s.planned_arrival) planned, UNIX_TIMESTAMP(s.actual_departure) departed
            FROM shipments s WHERE s.direction = 'inn' AND s.status IN $u ORDER BY s.eta IS NULL, s.eta LIMIT 12")->fetchAll();
        if (!$rows) {
            return [];
        }
        $items = $pdo->query('SELECT i.shipment_id, i.product_id id, i.qty, p.name,
                (SELECT path_m FROM product_images m WHERE m.product_id = p.id ORDER BY kind = \'sortre\' DESC, sort LIMIT 1) img
            FROM shipment_items i JOIN products p ON p.id = i.product_id WHERE i.shipment_id IN (' . implode(',', array_column($rows, 'id')) . ') ORDER BY i.shipment_id, i.qty DESC')->fetchAll();
        $by = [];
        foreach ($items as $i) {
            $by[(int) $i['shipment_id']][] = ['id' => (int) $i['id'], 'name' => $i['name'], 'qty' => (int) $i['qty'], 'img' => $i['img']];
        }
        return array_map(fn($s) => ['id' => (int) $s['id'], 'ref' => $s['ref'], 'status' => $s['status'], 'from' => $s['origin_city'], 'to' => $s['dest_city'],
            'demo' => (int) $s['is_demo'] === 1, 'delay' => $s['delay_reason'], 'eta' => $s['eta'] !== null ? (int) $s['eta'] : null,
            'planned' => $s['planned'] !== null ? (int) $s['planned'] : null, 'departed' => $s['departed'] !== null ? (int) $s['departed'] : null,
            'items' => $by[(int) $s['id']] ?? []], $rows);
    }

    /** Shipments that have arrived and wait to be checked in, with their cargo lines and product photos. */
    public static function received(): array
    {
        $pdo = db();
        $rows = $pdo->query("SELECT s.id, s.ref, s.origin_city, s.is_demo, s.container_type, UNIX_TIMESTAMP(s.actual_arrival) arrived, v.fleet_no
            FROM shipments s LEFT JOIN vehicles v ON v.id = s.vehicle_id WHERE s.direction = 'inn' AND s.status = 'levert' AND s.checked_in_at IS NULL
            ORDER BY s.actual_arrival DESC LIMIT 12")->fetchAll();
        if (!$rows) {
            return [];
        }
        $items = $pdo->query('SELECT i.shipment_id, i.product_id id, i.qty, p.name,
                (SELECT path_m FROM product_images m WHERE m.product_id = p.id ORDER BY kind = \'sortre\' DESC, sort LIMIT 1) img
            FROM shipment_items i JOIN products p ON p.id = i.product_id WHERE i.shipment_id IN (' . implode(',', array_column($rows, 'id')) . ') ORDER BY i.shipment_id, i.qty DESC')->fetchAll();
        $by = [];
        foreach ($items as $i) {
            $by[(int) $i['shipment_id']][] = ['id' => (int) $i['id'], 'name' => $i['name'], 'qty' => (int) $i['qty'], 'img' => $i['img']];
        }
        return array_map(fn($s) => ['id' => (int) $s['id'], 'ref' => $s['ref'], 'from' => $s['origin_city'], 'vehicle' => $s['fleet_no'], 'what' => $s['container_type'],
            'demo' => (int) $s['is_demo'] === 1, 'arrived' => $s['arrived'] !== null ? (int) $s['arrived'] : null, 'items' => $by[(int) $s['id']] ?? []], $rows);
    }

    /**
     * Varemottak panel: products entering the product-data workflow, newest first. Two sources, both stored rows:
     * a line on an inbound shipment, and a buyer's change to the open version that waits for staff.
     */
    public static function incoming(): array
    {
        $pdo = db();
        $u = PdoShipmentStore::underwayIn();
        $rows = $pdo->query("SELECT * FROM (
                SELECT i.product_id id, 'sending' source, s.ref, s.status, UNIX_TIMESTAMP(COALESCE(s.actual_departure, s.created_at)) at, s.is_demo demo, i.qty
                FROM shipment_items i JOIN shipments s ON s.id = i.shipment_id WHERE s.direction = 'inn' AND s.status IN $u
                UNION ALL
                SELECT r.product_id, 'bidrag', CONCAT('#', r.id), r.status, UNIX_TIMESTAMP(r.created_at), 0, NULL FROM spec_revisions r WHERE r.status = 'aktiv'
            ) x ORDER BY at DESC LIMIT 24")->fetchAll();
        if (!$rows) {
            return [];
        }
        $cards = $pdo->query('SELECT p.id, p.name,
                (SELECT path_m FROM product_images i WHERE i.product_id = p.id ORDER BY kind = \'sortre\' DESC, sort LIMIT 1) img
            FROM products p WHERE p.id IN (' . implode(',', array_map('intval', array_column($rows, 'id'))) . ')')->fetchAll(PDO::FETCH_UNIQUE);
        return array_values(array_filter(array_map(fn($r) => isset($cards[(int) $r['id']]) ? ['id' => (int) $r['id'], 'name' => $cards[(int) $r['id']]['name'],
            'img' => $cards[(int) $r['id']]['img'], 'source' => $r['source'], 'ref' => $r['ref'], 'status' => $r['status'], 'at' => (int) $r['at'],
            'demo' => (int) $r['demo'] === 1, 'qty' => $r['qty'] !== null ? (int) $r['qty'] : null] : null, $rows)));
    }

    /** Specifications per source and missing filter values: what the knowledge base holds right now. */
    public static function knowledge(): array
    {
        $pdo = db();
        $src = $pdo->query("SELECT source, COUNT(*) n FROM spec_values GROUP BY source")->fetchAll(PDO::FETCH_KEY_PAIR);
        $missing = (int) $pdo->query("SELECT COUNT(*) FROM products p JOIN spec_keys k ON k.is_facet = 1 AND (k.themes IS NULL OR FIND_IN_SET(p.theme, k.themes))
            WHERE NOT EXISTS (SELECT 1 FROM spec_values v WHERE v.product_id = p.id AND v.`key` = k.`key`)")->fetchColumn();
        $rel = $pdo->query('SELECT level, COUNT(*) n FROM relations GROUP BY level')->fetchAll(PDO::FETCH_KEY_PAIR);
        return ['sortre' => (int) ($src['sortre'] ?? 0), 'ansatt' => (int) ($src['ansatt'] ?? 0), 'utledet' => (int) ($src['utledet'] ?? 0),
            'bruker' => (int) ($src['bruker'] ?? 0), 'missing' => $missing, 'manuals' => (int) $pdo->query('SELECT COUNT(*) FROM manuals')->fetchColumn(),
            'documented' => (int) ($rel['dokumentert'] ?? 0), 'rules' => (int) ($rel['regelutledet'] ?? 0), 'assumed' => (int) ($rel['antatt'] ?? 0)];
    }

    /** Every task that is not archived: active first (oldest first, so nothing waits forever), then the last finished. */
    public static function tasks(): array
    {
        $active = "'" . implode("','", self::ACTIVE) . "'";
        $rows = db()->query("SELECT t.id, t.title, t.note, t.outcome, t.status, t.source, t.spec_key, k.label spec_label, t.revision_id, t.event_id, t.origin,
                t.product_id, p.name product, (SELECT path_m FROM product_images i WHERE i.product_id = p.id ORDER BY kind = 'sortre' DESC, sort LIMIT 1) img,
                UNIX_TIMESTAMP(t.created_at) at, UNIX_TIMESTAMP(t.updated_at) changed, " . Privacy::sql() . " who
            FROM improvement_tasks t LEFT JOIN products p ON p.id = t.product_id LEFT JOIN spec_keys k ON k.`key` = t.spec_key
            LEFT JOIN users u ON u.id = COALESCE(t.closed_by, t.created_by)
            WHERE t.status <> 'arkivert'
            ORDER BY t.status IN ($active) DESC, CASE WHEN t.status IN ($active) THEN t.id END ASC, t.updated_at DESC, t.id DESC LIMIT 80")->fetchAll();
        return array_map(fn($t) => ['id' => (int) $t['id'], 'product_id' => $t['product_id'] !== null ? (int) $t['product_id'] : null,
            'event_id' => $t['event_id'] !== null ? (int) $t['event_id'] : null, 'revision_id' => $t['revision_id'] !== null ? (int) $t['revision_id'] : null,
            'at' => (int) $t['at'], 'changed' => $t['changed'] !== null ? (int) $t['changed'] : null] + $t, $rows);
    }

    /**
     * Creates a task. A title is required; a product, when given, must exist. A task for the same origin that is still
     * active is not created twice: the existing one is returned with dup = true, so the caller can say so.
     * @return array{id: int, dup: bool}|null
     */
    public static function create(array $in, int $user): ?array
    {
        $title = mb_substr(trim((string) ($in['title'] ?? '')), 0, 200);
        $source = in_array($in['source'] ?? '', self::SOURCES, true) ? $in['source'] : 'produkt';
        $product = isset($in['product']) && $in['product'] !== '' && $in['product'] !== null ? (int) $in['product'] : null;
        if ($title === '') {
            return null;
        }
        $pdo = db();
        if ($product !== null) {
            $st = $pdo->prepare('SELECT 1 FROM products WHERE id = ?');
            $st->execute([$product]);
            if (!$st->fetchColumn()) {
                return null;
            }
        }
        $origin = mb_substr(trim((string) ($in['origin'] ?? '')), 0, 160) ?: null;
        if ($origin !== null) {
            $st = $pdo->prepare("SELECT id FROM improvement_tasks WHERE origin = ? AND status IN ('" . implode("','", self::ACTIVE) . "') LIMIT 1");
            $st->execute([$origin]);
            if ($id = $st->fetchColumn()) {
                return ['id' => (int) $id, 'dup' => true];
            }
        }
        $key = mb_substr(trim((string) ($in['key'] ?? '')), 0, 48) ?: null;
        $event = isset($in['event']) && (int) $in['event'] > 0 ? (int) $in['event'] : null;
        $rev = isset($in['revision']) && (int) $in['revision'] > 0 ? (int) $in['revision'] : null;
        $note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 500) ?: null;
        $pdo->prepare('INSERT INTO improvement_tasks (event_id, product_id, source, spec_key, revision_id, origin, title, note, created_by) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$event, $product, $source, $key, $rev, $origin, $title, $note, $user]);
        return ['id' => (int) $pdo->lastInsertId(), 'dup' => false];
    }

    /** Moves a task to a new state, with what happened in words when the state finishes it. */
    public static function move(int $id, string $status, int $user, ?string $outcome = null): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }
        $st = db()->prepare('UPDATE improvement_tasks SET status = ?, outcome = COALESCE(?, outcome), closed_by = ? WHERE id = ?');
        $st->execute([$status, $outcome !== null ? mb_substr($outcome, 0, 300) : null, $user, $id]);
        return $st->rowCount() > 0;
    }

    /**
     * After Flett inn or Avvis on a buyer change: the tasks it settles change state, each with the outcome in words.
     * A task tied to the change itself, or to one of the keys it changed on the same product, is settled. Other active
     * tasks on the product stay as they are; the reply names them, so it is clear what still needs doing.
     * @param array<int, array{key: string, from: ?string, to: string}> $diff
     * @return array{settled: array<int, array{id: int, title: string, status: string}>, open: array<int, array{id: int, title: string, status: string}>}
     */
    public static function settle(int $revision, int $product, array $diff, bool $merged, int $user): array
    {
        $pdo = db();
        $keys = array_values(array_unique(array_map(fn($d) => (string) $d['key'], $diff)));
        $active = "'" . implode("','", self::ACTIVE) . "'";
        $st = $pdo->prepare("SELECT id, title, status, revision_id, spec_key FROM improvement_tasks WHERE product_id = ? AND status IN ($active) ORDER BY id");
        $st->execute([$product]);
        $settled = [];
        $open = [];
        $what = implode(', ', array_map(fn($d) => $d['key'] . ': ' . ($d['from'] ?? 'tom') . ' > ' . $d['to'], $diff));
        foreach ($st->fetchAll() as $t) {
            if ((int) $t['revision_id'] === $revision || ($t['spec_key'] !== null && in_array($t['spec_key'], $keys, true))) {
                $status = $merged ? 'fullfort' : 'avvist';
                self::move((int) $t['id'], $status, $user, ($merged ? 'Flettet inn i verifisert: ' : 'Endringen ble avvist: ') . $what);
                $settled[] = ['id' => (int) $t['id'], 'title' => $t['title'], 'status' => $status];
            } else {
                $open[] = ['id' => (int) $t['id'], 'title' => $t['title'], 'status' => $t['status']];
            }
        }
        return ['settled' => $settled, 'open' => $open];
    }
}
