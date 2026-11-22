<?php
declare(strict_types=1);

/**
 * Refilling the store from the other stores (feature 043). Staff pick products that are out on the shelf in Innsikt;
 * the picks wait on the order list (supply_list) shown in Gulv. In Transport a vehicle can fetch what a store within
 * its reach has on the shelf, and an order sends it on the simulation clock (DemoDelivery) to the store. On arrival
 * the units go on the shelf and the shipment waits in Varemottak until staff check it in.
 *
 * Stock per store is demo data (rule 7), and so are the vehicles; every shipment made here is a demo row.
 */
final class Supply
{
    /** A vehicle fetches from stores within this many km of where it stands. */
    public const RADIUS_KM = 60;
    /** The receiving store: Sør-Tre's actual store. */
    public const HOME = 1;
    public const STATUSES = ['valgt', 'bestilt', 'mottatt', 'fjernet'];

    public static function ensure(PDO $pdo): void
    {
        DemoDelivery::ensure($pdo);
        if ($pdo->query("SHOW TABLES LIKE 'supply_list'")->fetch()) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS supply_list (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id INT UNSIGNED NOT NULL, qty SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            status ENUM('valgt','bestilt','mottatt','fjernet') NOT NULL DEFAULT 'valgt', shipment_id INT UNSIGNED NULL, from_warehouse SMALLINT UNSIGNED NULL,
            created_by INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY (status), KEY (product_id), KEY (shipment_id)
        ) ENGINE=InnoDB");
    }

    /** The receiving store's name. */
    public static function home(): string
    {
        return (string) (db()->query('SELECT name FROM warehouses WHERE id = ' . self::HOME)->fetchColumn() ?: 'Kristiansand');
    }

    /**
     * Products with nothing on the shelf in the receiving store, most sold in the last 60 days first, with what the
     * other stores have and whether the product is already picked or on its way.
     */
    public static function missing(int $limit = 60): array
    {
        $pdo = db();
        $home = self::HOME;
        $rows = $pdo->query("SELECT p.id, p.name, p.theme,
                (SELECT path_m FROM product_images i WHERE i.product_id = p.id ORDER BY kind = 'sortre' DESC, sort LIMIT 1) img,
                (SELECT COALESCE(SUM(oi.qty), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = p.id AND o.status = 'betalt'
                    AND o.created_at >= NOW() - INTERVAL 60 DAY) sold,
                (SELECT COALESCE(SUM(x.in_store), 0) FROM stock x WHERE x.product_id = p.id AND x.warehouse_id <> $home) elsewhere,
                (SELECT COUNT(*) FROM stock x WHERE x.product_id = p.id AND x.warehouse_id <> $home AND x.in_store > 0) stores,
                (SELECT l.status FROM supply_list l WHERE l.product_id = p.id AND l.status IN ('valgt','bestilt') ORDER BY l.id DESC LIMIT 1) listed
            FROM products p JOIN stock s ON s.product_id = p.id AND s.warehouse_id = $home
            WHERE s.in_store <= 0 ORDER BY sold DESC, elsewhere DESC, p.name LIMIT " . max(1, $limit))->fetchAll();
        $total = (int) $pdo->query("SELECT COUNT(*) FROM stock WHERE warehouse_id = $home AND in_store <= 0")->fetchColumn();
        return ['total' => $total, 'home' => self::home(), 'rows' => array_map(fn($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'img' => $r['img'],
            'sold' => (int) $r['sold'], 'elsewhere' => (int) $r['elsewhere'], 'stores' => (int) $r['stores'], 'listed' => $r['listed']], $rows)];
    }

    /** Puts a product on the order list, or changes the quantity of the pick that is already there. */
    public static function add(int $product, int $qty, int $user): ?int
    {
        $pdo = db();
        $qty = max(1, min(50, $qty));
        $st = $pdo->prepare('SELECT 1 FROM products WHERE id = ?');
        $st->execute([$product]);
        if (!$st->fetchColumn()) {
            return null;
        }
        $st = $pdo->prepare("SELECT id FROM supply_list WHERE product_id = ? AND status = 'valgt' LIMIT 1");
        $st->execute([$product]);
        if ($id = $st->fetchColumn()) {
            $pdo->prepare('UPDATE supply_list SET qty = ? WHERE id = ?')->execute([$qty, $id]);
            return (int) $id;
        }
        $pdo->prepare('INSERT INTO supply_list (product_id, qty, created_by) VALUES (?, ?, ?)')->execute([$product, $qty, $user]);
        return (int) $pdo->lastInsertId();
    }

    /** Takes a pick off the list. Ordered lines stay: they are on a vehicle. */
    public static function remove(int $id): bool
    {
        $st = db()->prepare("UPDATE supply_list SET status = 'fjernet' WHERE id = ? AND status = 'valgt'");
        $st->execute([$id]);
        return $st->rowCount() === 1;
    }

    /**
     * The order list: picks waiting for a vehicle, then what is on its way and what came in the last two days, each with
     * the stores (not the receiving one) that have it on the shelf.
     */
    public static function list(): array
    {
        $pdo = db();
        $rows = $pdo->query("SELECT l.id, l.product_id, l.qty, l.status, l.shipment_id, l.from_warehouse, w.name from_name, s.ref, s.status ship_status,
                UNIX_TIMESTAMP(l.created_at) at, p.name, (SELECT path_m FROM product_images i WHERE i.product_id = p.id ORDER BY kind = 'sortre' DESC, sort LIMIT 1) img
            FROM supply_list l JOIN products p ON p.id = l.product_id LEFT JOIN warehouses w ON w.id = l.from_warehouse LEFT JOIN shipments s ON s.id = l.shipment_id
            WHERE l.status IN ('valgt','bestilt') OR (l.status = 'mottatt' AND COALESCE(l.updated_at, l.created_at) >= NOW() - INTERVAL 2 DAY)
            ORDER BY FIELD(l.status, 'valgt', 'bestilt', 'mottatt'), l.id DESC LIMIT 80")->fetchAll();
        $ids = array_values(array_unique(array_map(fn($r) => (int) $r['product_id'], $rows)));
        $have = [];
        if ($ids) {
            foreach ($pdo->query('SELECT x.product_id, x.warehouse_id, w.name, x.in_store FROM stock x JOIN warehouses w ON w.id = x.warehouse_id
                WHERE x.in_store > 0 AND x.warehouse_id <> ' . self::HOME . ' AND x.product_id IN (' . implode(',', $ids) . ') ORDER BY x.in_store DESC') as $s) {
                $have[(int) $s['product_id']][] = ['id' => (int) $s['warehouse_id'], 'name' => $s['name'], 'qty' => (int) $s['in_store']];
            }
        }
        return array_map(fn($r) => ['id' => (int) $r['id'], 'product' => (int) $r['product_id'], 'name' => $r['name'], 'img' => $r['img'], 'qty' => (int) $r['qty'],
            'status' => $r['status'], 'shipment' => $r['shipment_id'] !== null ? (int) $r['shipment_id'] : null, 'ref' => $r['ref'], 'ship_status' => $r['ship_status'],
            'from' => $r['from_name'], 'at' => (int) $r['at'], 'stock' => $have[(int) $r['product_id']] ?? []], $rows);
    }

    /**
     * Every demo vehicle and where it stands: on a trip, the latest stored position; otherwise at its home base.
     * busy is the shipment it is driving.
     */
    public static function vehicles(): array
    {
        $pdo = db();
        $rows = $pdo->query("SELECT v.id, v.fleet_no, v.type, v.make, v.model, v.payload_kg, v.base_city,
                (SELECT s.id FROM shipments s WHERE s.vehicle_id = v.id AND s.status IN " . PdoShipmentStore::underwayIn() . " ORDER BY s.id DESC LIMIT 1) busy
            FROM vehicles v WHERE v.is_demo = 1 ORDER BY v.fleet_no")->fetchAll();
        $pos = $pdo->prepare('SELECT lat, lng FROM shipment_positions WHERE shipment_id = ? ORDER BY observed_at DESC, id DESC LIMIT 1');
        // A free vehicle stays where its last trip ended; one that has never driven stands at its base
        $last = $pdo->prepare('SELECT p.lat, p.lng FROM shipment_positions p JOIN shipments s ON s.id = p.shipment_id
            WHERE s.vehicle_id = ? ORDER BY s.id DESC, p.observed_at DESC, p.id DESC LIMIT 1');
        return array_map(function ($v) use ($pos, $last) {
            $base = (string) ($v['base_city'] ?: 'Kristiansand');
            [$lat, $lng] = $home = Roads::NODES[$base] ?? Roads::NODES['Kristiansand'];
            $at = $base;
            $q = $v['busy'] !== null ? $pos : $last;
            $q->execute([$v['busy'] ?? $v['id']]);
            if ($p = $q->fetch()) {
                [$lat, $lng] = [(float) $p['lat'], (float) $p['lng']];
                $at = Roads::nearest($lat, $lng)[0];
            }
            return ['id' => (int) $v['id'], 'fleet_no' => $v['fleet_no'], 'type' => $v['type'], 'make' => $v['make'], 'model' => $v['model'],
                'payload_kg' => $v['payload_kg'] !== null ? (int) $v['payload_kg'] : null, 'base' => $base, 'base_lat' => $home[0], 'base_lng' => $home[1], 'at' => $at, 'lat' => $lat, 'lng' => $lng,
                'busy' => $v['busy'] !== null ? (int) $v['busy'] : null];
        }, $rows);
    }

    /** Stores within reach of a point: [id, name, lat, lng, km], nearest first. The receiving store is never a source. */
    public static function storesNear(float $lat, float $lng): array
    {
        $out = [];
        foreach (db()->query('SELECT id, name FROM warehouses WHERE id <> ' . self::HOME) as $w) {
            $at = DemoDelivery::storeAt((string) $w['name']);
            if ($at === null) {
                continue;
            }
            $km = DemoDelivery::km($lat, $lng, $at[0], $at[1]);
            if ($km <= self::RADIUS_KM) {
                $out[] = ['id' => (int) $w['id'], 'name' => $w['name'], 'lat' => $at[0], 'lng' => $at[1], 'km' => round($km, 1)];
            }
        }
        usort($out, fn($a, $b) => $a['km'] <=> $b['km']);
        return $out;
    }

    /**
     * Who can fetch what. Pure over plain values: for each free vehicle, the picks a store within its reach has on the
     * shelf (the store with most units, then the nearest). In a batch each pick goes to one vehicle, the one with the
     * nearest store that has it; a pick no vehicle can reach is left uncovered, with the stores that do have it.
     * @param list<array> $picks rows from list() with status valgt
     * @param list<array> $vehicles rows from vehicles(), each with a 'near' list of stores from storesNear()
     * @param list<int>|null $only vehicles taking part; null = every free vehicle
     */
    public static function assign(array $picks, array $vehicles, ?array $only = null): array
    {
        $per = [];
        $covered = [];
        $options = [];
        foreach ($vehicles as $v) {
            if ($v['busy'] !== null || ($only !== null && !in_array($v['id'], $only, true))) {
                continue;
            }
            $near = array_column($v['near'], null, 'id');
            foreach ($picks as $p) {
                $best = null;
                foreach ($p['stock'] as $s) {
                    if (isset($near[$s['id']]) && ($best === null || $s['qty'] > $best['qty'] || ($s['qty'] === $best['qty'] && $near[$s['id']]['km'] < $near[$best['id']]['km']))) {
                        $best = $s;
                    }
                }
                if ($best !== null) {
                    $options[$p['id']][] = ['vehicle' => $v['id'], 'store' => $best['id'], 'store_name' => $best['name'], 'have' => $best['qty'], 'km' => $near[$best['id']]['km']];
                }
            }
        }
        foreach ($picks as $p) {
            $opts = $options[$p['id']] ?? [];
            if (!$opts) {
                continue;
            }
            usort($opts, fn($a, $b) => $a['km'] <=> $b['km'] ?: $a['vehicle'] <=> $b['vehicle']);
            $o = $opts[0];
            $per[$o['vehicle']][] = ['pick' => $p['id'], 'product' => $p['product'], 'name' => $p['name'], 'img' => $p['img'], 'qty' => min($p['qty'], $o['have']),
                'want' => $p['qty'], 'store' => $o['store'], 'store_name' => $o['store_name']];
            $covered[$p['id']] = true;
        }
        $uncovered = array_values(array_map(fn($p) => ['pick' => $p['id'], 'name' => $p['name'], 'img' => $p['img'], 'qty' => $p['qty'], 'stock' => $p['stock']],
            array_filter($picks, fn($p) => !isset($covered[$p['id']]))));
        return ['vehicles' => $per, 'uncovered' => $uncovered, 'options' => $options];
    }

    /** The current picks, vehicles with the stores in reach and the assignment, in one read for the transport view. */
    public static function overview(?array $only = null): array
    {
        $picks = array_values(array_filter(self::list(), fn($r) => $r['status'] === 'valgt'));
        $vehicles = array_map(fn($v) => $v + ['near' => self::storesNear($v['lat'], $v['lng'])], self::vehicles());
        $stores = [];
        foreach (db()->query('SELECT id, name, is_real FROM warehouses ORDER BY name') as $w) {
            if ($at = DemoDelivery::storeAt((string) $w['name'])) {
                $stores[] = ['id' => (int) $w['id'], 'name' => $w['name'], 'lat' => $at[0], 'lng' => $at[1], 'home' => (int) $w['id'] === self::HOME, 'real' => (bool) $w['is_real']];
            }
        }
        return ['picks' => $picks, 'vehicles' => $vehicles, 'assign' => self::assign($picks, $vehicles, $only), 'radius' => self::RADIUS_KM, 'home' => self::home(),
            'stores' => $stores];
    }

    /**
     * Orders picks with the given vehicles: vehicle id => list of pick ids. Each pick must be waiting and in stock at a
     * store within the vehicle's reach; the units are taken off that store's shelf and the vehicle drives there and on
     * to the receiving store on the simulation clock. Returns the shipments made, and the picks that could not be.
     * @param array<int, list<int>> $plan
     */
    public static function order(array $plan, int $user): array
    {
        $ov = self::overview();
        $picks = array_column($ov['picks'], null, 'id');
        $vehicles = array_column($ov['vehicles'], null, 'id');
        $made = [];
        $skipped = [];
        $home = self::home();
        [$hl, $hn] = DemoDelivery::storeAt($home) ?? Roads::NODES['Kristiansand'];
        $pdo = db();
        foreach ($plan as $vid => $ids) {
            $v = $vehicles[(int) $vid] ?? null;
            if ($v === null || $v['busy'] !== null) {
                $skipped = [...$skipped, ...array_map('intval', $ids)];
                continue;
            }
            $one = self::assign(array_values(array_filter(array_map(fn($i) => $picks[(int) $i] ?? null, $ids))), [$v]);
            $lines = $one['vehicles'][$v['id']] ?? [];
            $skipped = [...$skipped, ...array_column($one['uncovered'], 'pick')];
            if (!$lines) {
                continue;
            }
            $pdo->beginTransaction();
            $take = $pdo->prepare('UPDATE stock SET in_store = in_store - ? WHERE product_id = ? AND warehouse_id = ? AND in_store >= ?');
            $ok = [];
            foreach ($lines as $l) {
                $take->execute([$l['qty'], $l['product'], $l['store'], $l['qty']]);
                if ($take->rowCount() === 1) {
                    $ok[] = $l;
                } else {
                    $skipped[] = $l['pick'];
                }
            }
            if (!$ok) {
                $pdo->rollBack();
                continue;
            }
            // Pick-ups in order of distance from the vehicle, then the receiving store
            $stops = [[$v['at'], $v['lat'], $v['lng'], 'start']];
            $stores = array_values(array_unique(array_column($ok, 'store')));
            $near = array_column($v['near'], null, 'id');
            usort($stores, fn($a, $b) => $near[$a]['km'] <=> $near[$b]['km']);
            foreach ($stores as $s) {
                $stops[] = ['Sør-Tre ' . $near[$s]['name'], $near[$s]['lat'], $near[$s]['lng'], 'hent'];
            }
            $stops[] = ['Sør-Tre ' . $home, $hl, $hn, 'mal'];
            $qty = [];
            foreach ($ok as $l) {
                $qty[$l['product']] = ($qty[$l['product']] ?? 0) + $l['qty'];
            }
            $id = DemoDelivery::dispatch($v['id'], $stops, array_map(null, array_keys($qty), array_values($qty)), 'Påfyll fra butikk', self::HOME);
            if ($id === null) {
                $pdo->rollBack();
                $skipped = [...$skipped, ...array_column($ok, 'pick')];
                continue;
            }
            $mark = $pdo->prepare("UPDATE supply_list SET status = 'bestilt', shipment_id = ?, from_warehouse = ?, qty = ? WHERE id = ? AND status = 'valgt'");
            foreach ($ok as $l) {
                $mark->execute([$id, $l['store'], $l['qty'], $l['pick']]);
            }
            $pdo->commit();
            Audit::log('admin', 'supply_order', 'sending #' . $id, ['vehicle' => $v['fleet_no'], 'lines' => count($ok), 'by' => $user]);
            $made[] = ['shipment' => $id, 'vehicle' => $v['fleet_no'], 'lines' => count($ok)];
        }
        if ($made && (bool) DemoDelivery::clock()['paused']) {
            DemoDelivery::control('play');
        }
        return ['made' => $made, 'skipped' => array_values(array_unique($skipped))];
    }

    /** A demo trip without goods from a free vehicle to a point on the map, to watch a route, its ferries and its weather. */
    public static function tour(int $vehicle, float $lat, float $lng): ?int
    {
        if ($lat < 57.5 || $lat > 71.5 || $lng < 4 || $lng > 31.5) {
            return null;
        }
        $v = array_column(self::vehicles(), null, 'id')[$vehicle] ?? null;
        if ($v === null || $v['busy'] !== null) {
            return null;
        }
        [$node] = Roads::nearest($lat, $lng);
        $id = DemoDelivery::dispatch($v['id'], [[$v['at'], $v['lat'], $v['lng'], 'start'], ['Valgt punkt ved ' . $node, round($lat, 3), round($lng, 3), 'mal']], [], 'Demotur uten last', null);
        if ($id !== null && (bool) DemoDelivery::clock()['paused']) {
            DemoDelivery::control('play');
        }
        return $id;
    }
}
