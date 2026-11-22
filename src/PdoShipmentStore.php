<?php
declare(strict_types=1);

/** Transport rows from the database. See ShipmentStore for the row shapes. */
final class PdoShipmentStore implements ShipmentStore
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Transport tables on databases built before feature 022. */
    public static function ensure(PDO $pdo): void
    {
        if (!$pdo->query("SHOW TABLES LIKE 'shipments'")->fetch()) {
            $pdo->exec((string) file_get_contents(APP_ROOT . '/db/transport.sql'));
        }
    }

    /** SQL list of the statuses still on the way, for "status IN ...". Values are fixed constants, not input. */
    public static function underwayIn(): string
    {
        return "('" . implode("','", ShipmentRules::UNDERWAY) . "')";
    }

    public function all(): array
    {
        $rows = $this->pdo->query("SELECT s.id, s.ref, s.direction, s.status, s.origin_city, s.dest_city, s.is_demo,
                UNIX_TIMESTAMP(s.eta) eta, UNIX_TIMESTAMP(s.planned_arrival) planned_arrival, UNIX_TIMESTAMP(s.actual_arrival) actual_arrival,
                v.fleet_no, v.type vehicle_type,
                p.lat, p.lng, p.heading, UNIX_TIMESTAMP(p.observed_at) observed_at, p.source pos_source
            FROM shipments s LEFT JOIN vehicles v ON v.id = s.vehicle_id
            LEFT JOIN shipment_positions p ON p.id = (SELECT id FROM shipment_positions WHERE shipment_id = s.id ORDER BY observed_at DESC, id DESC LIMIT 1)
            ORDER BY s.status = 'levert', s.eta")->fetchAll();
        $route = [];
        foreach ($this->pdo->query('SELECT shipment_id, lat, lng, source FROM shipment_route_points ORDER BY shipment_id, seq') as $r) {
            $route[$r['shipment_id']][] = [(float) $r['lat'], (float) $r['lng'], $r['source']];
        }
        foreach ($rows as &$r) {
            $r['route'] = $route[$r['id']] ?? [];
        }
        return $rows;
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT s.id, s.ref, s.direction, s.status, s.origin_city, s.origin_point, s.dest_city, s.dest_point,
                s.vehicle_id, s.driver_id, s.container_ref, s.container_type, s.eta_source, s.weight_declared_kg, s.weight_measured_kg,
                s.delay_reason, s.is_demo,
                UNIX_TIMESTAMP(s.planned_departure) planned_departure, UNIX_TIMESTAMP(s.actual_departure) actual_departure,
                UNIX_TIMESTAMP(s.eta) eta, UNIX_TIMESTAMP(s.eta_calculated_at) eta_calculated_at, UNIX_TIMESTAMP(s.planned_arrival) planned_arrival,
                UNIX_TIMESTAMP(s.actual_arrival) actual_arrival, w.name warehouse
            FROM shipments s LEFT JOIN warehouses w ON w.id = s.warehouse_id WHERE s.id = ?');
        $st->execute([$id]);
        $s = $st->fetch();
        if (!$s) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT fleet_no, type, make, model, model_year, capacity_kg, payload_kg, registration, is_demo FROM vehicles WHERE id = ?');
        $st->execute([$s['vehicle_id']]);
        $vehicle = $st->fetch() ?: null;
        $st = $this->pdo->prepare('SELECT alias, driver_ref, phone, is_demo FROM drivers WHERE id = ?');
        $st->execute([$s['driver_id']]);
        $driver = $st->fetch() ?: null;
        unset($s['vehicle_id'], $s['driver_id']);
        $st = $this->pdo->prepare("SELECT i.product_id, p.name, p.slug, i.qty, i.weight_kg, i.weight_basis, i.handling_unit,
                (SELECT path_m FROM product_images m WHERE m.product_id = p.id ORDER BY m.kind = 'sortre' DESC, m.sort, m.id LIMIT 1) img
            FROM shipment_items i JOIN products p ON p.id = i.product_id WHERE i.shipment_id = ? ORDER BY p.name");
        $st->execute([$id]);
        $items = $st->fetchAll();
        $st = $this->pdo->prepare('SELECT lat, lng, heading, UNIX_TIMESTAMP(observed_at) observed_at, UNIX_TIMESTAMP(received_at) received_at, source
            FROM shipment_positions WHERE shipment_id = ? ORDER BY observed_at');
        $st->execute([$id]);
        $positions = $st->fetchAll();
        $st = $this->pdo->prepare('SELECT lat, lng, source FROM shipment_route_points WHERE shipment_id = ? ORDER BY seq');
        $st->execute([$id]);
        $route = array_map(fn($r) => [(float) $r['lat'], (float) $r['lng'], $r['source']], $st->fetchAll());
        return ['shipment' => $s, 'vehicle' => $vehicle, 'driver' => $driver, 'items' => $items, 'positions' => $positions, 'route' => $route];
    }
}
