<?php
// Demo transport for the Control Tower map (specs/022-control-tower, specs/043-supply-and-simulation).
// No fleet or GPS source is connected: every row is a fixture with is_demo = 1 and shown as DEMO DATA.
// Two vehicles at every store, one finished delivery, and most vehicles on an ordered trip on the simulation clock.
// Routes run through the road network in src/Roads.php; positions come from the simulation clock (DemoDelivery).
//
//   php scripts/transport-demo.php    replaces only the demo transport rows; products, users and analytics are kept
//
// scripts/import.php runs the same seed as one of its steps.

declare(strict_types=1);

function seed_transport(PDO $pdo, string $root): int
{
    $pdo->exec((string) file_get_contents("$root/db/transport.sql"));
    Tower::ensure();
    // Child rows of demo shipments first, then the demo shipments, vehicles and drivers themselves
    foreach (['shipment_positions', 'shipment_route_points', 'shipment_items', 'shipment_plans'] as $child) {
        $pdo->exec("DELETE c FROM $child c JOIN shipments s ON s.id = c.shipment_id WHERE s.is_demo = 1");
    }
    // Picks that were on a demo vehicle go back on the order list
    $pdo->exec("UPDATE supply_list l JOIN shipments s ON s.id = l.shipment_id AND s.is_demo = 1 SET l.status = 'valgt', l.shipment_id = NULL, l.from_warehouse = NULL WHERE l.status = 'bestilt'");
    $pdo->exec('DELETE FROM shipments WHERE is_demo = 1');
    $pdo->exec('DELETE FROM vehicles WHERE is_demo = 1');
    $pdo->exec('DELETE FROM drivers WHERE is_demo = 1');
    $pdo->exec('DELETE FROM sim_clock');
    $at = fn(string $rel) => date('Y-m-d H:i:s', strtotime($rel));
    $town = DemoDelivery::TOWNS;
    // Each vehicle has a home base on the road network (Roads::NODES), spread over the country so each one reaches other stores
    // Two vehicles at every store (warehouses), so the whole country has traffic and every store has a vehicle at home
    $types = [['trekkvogn', 'Volvo', 'FH 500', 40000, 24000], ['lastebil', 'Scania', 'R 450', 26000, 14000], ['varebil', 'Mercedes-Benz', 'Sprinter 316', 3500, 1200],
        ['varebil', 'Volkswagen', 'Crafter 35', 3500, 1300], ['lastebil', 'MAN', 'TGL 12.250', 12000, 5500], ['varebil', 'Ford', 'Transit 350', 3500, 1250]];
    $hq = array_values(array_filter($pdo->query('SELECT name FROM warehouses ORDER BY id')->fetchAll(PDO::FETCH_COLUMN), fn($n) => isset(Roads::NODES[$n])));
    $addV = $pdo->prepare("INSERT INTO vehicles (fleet_no, type, make, model, model_year, registration, capacity_kg, payload_kg, base_city, is_demo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $addD = $pdo->prepare("INSERT INTO drivers (alias, driver_ref, phone, is_demo) VALUES (?, ?, NULL, 1)");
    $fleet = [];
    foreach ([0, 1] as $round) {
        foreach ($hq as $k => $city) {
            $n = count($fleet) + 1;
            [$type, $make, $model, $cap, $pay] = $types[($k + $round * 3) % count($types)];
            $addV->execute([sprintf('V-%02d', $n), $type, $make, $model, 2019 + $n % 5, sprintf('DEMO-%03d', $n), $cap, $pay, $city]);
            $addD->execute(['Sjåfør ' . $n, sprintf('D-%03d', 100 + $n)]);
            $fleet[] = [sprintf('V-%02d', $n), $city];
        }
    }
    $vehicle = $pdo->query("SELECT fleet_no, id FROM vehicles WHERE is_demo = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
    $driver = $pdo->query("SELECT driver_ref, id FROM drivers WHERE is_demo = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
    $pick = fn(string $theme, int $n) => $pdo->query('SELECT id FROM products WHERE theme = ' . $pdo->quote($theme) . " ORDER BY id LIMIT $n")->fetchAll(PDO::FETCH_COLUMN);

    // History: one finished delivery, so "delivered on time" has a row to count
    $pdo->prepare("INSERT INTO shipments (ref, direction, status, origin_city, dest_city, dest_point, warehouse_id, vehicle_id, driver_id, container_ref, container_type,
        planned_departure, actual_departure, planned_arrival, actual_arrival, checked_in_at, is_demo) VALUES ('SR-UT-0001', 'ut', 'levert', 'Kristiansand', 'Arendal', 'Sør-Tre Arendal', 7, ?, ?, 'CT-0001', 'Skapvarebil', ?, ?, ?, ?, ?, 1)")
        ->execute([$vehicle['V-04'], $driver['D-104'], $at('-6 hours'), $at('-6 hours'), $at('-4 hours'), $at('-4 hours 10 minutes'), $at('-4 hours')]);
    $hist = (int) $pdo->lastInsertId();
    foreach (['Kristiansand', 'Grimstad', 'Arendal'] as $i => $t) {
        $pdo->prepare("INSERT INTO shipment_route_points (shipment_id, seq, lat, lng, source) VALUES (?, ?, ?, ?, 'estimert')")->execute([$hist, $i, ...$town[$t]]);
    }
    $pdo->prepare("INSERT INTO shipment_positions (shipment_id, lat, lng, heading, observed_at, received_at, source) VALUES (?, ?, ?, 60, ?, ?, 'demo')")
        ->execute([$hist, ...$town['Arendal'], $at('-4 hours 10 minutes'), $at('-4 hours 10 minutes')]);
    foreach ($pick('kjetting', 1) as $pid) {
        $pdo->prepare("INSERT INTO shipment_items (shipment_id, product_id, qty, handling_unit) VALUES (?, ?, 6, 'kolli')")->execute([$hist, $pid]);
    }
    DemoDelivery::weigh($hist);

    // On the way: most vehicles carry an order from the list (supply_list, status bestilt) on the simulation clock. Each
    // drives from its base to a store that has the goods on the shelf and on to the receiving store, and starts part way
    // through its trip, so vehicles are spread over the whole road network when the clock plays. Lines seeded here have
    // created_by = 0 and are removed on the next seed.
    Supply::ensure($pdo);
    $pdo->exec('DELETE FROM supply_list WHERE created_by = 0');
    $home = Supply::home();
    [$hl, $hn] = Roads::NODES[$home] ?? Roads::NODES['Kristiansand'];
    $wid = $pdo->query('SELECT name, id FROM warehouses')->fetchAll(PDO::FETCH_KEY_PAIR);
    $goods = $pdo->prepare('SELECT product_id FROM stock WHERE warehouse_id = ? AND in_store >= 4 ORDER BY product_id LIMIT 3 OFFSET ?');
    $take = $pdo->prepare('UPDATE stock SET in_store = in_store - ? WHERE product_id = ? AND warehouse_id = ? AND in_store >= ?');
    $line = $pdo->prepare("INSERT INTO supply_list (product_id, qty, status, shipment_id, from_warehouse, created_by) VALUES (?, ?, 'bestilt', ?, ?, 0)");
    $plan = $pdo->prepare('SELECT t0, points FROM shipment_plans WHERE shipment_id = ?');
    $made = 1;
    foreach ($fleet as $i => [$fleetNo, $base]) {
        if ($i % 4 === 3 || $fleetNo === 'V-04') {
            continue; // every fourth vehicle stays free at its base, to order with in the demo
        }
        $from = $hq[($i * 5 + 3) % count($hq)];
        if ($from === $base || $from === $home) {
            $from = $hq[($i * 5 + 4) % count($hq)];
        }
        if ($from === $base || $from === $home) {
            continue;
        }
        $goods->execute([$wid[$from], $i % 7]);
        $lines = [];
        foreach ($goods->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $qty = 2 + $i % 3;
            $take->execute([$qty, $pid, $wid[$from], $qty]);
            if ($take->rowCount() === 1) {
                $lines[] = [(int) $pid, $qty];
            }
        }
        if (!$lines) {
            continue;
        }
        $stops = [[$base, ...Roads::NODES[$base], ...['start']], ['Sør-Tre ' . $from, ...Roads::NODES[$from], ...['hent']], ['Sør-Tre ' . $home, $hl, $hn, 'mal']];
        $id = DemoDelivery::dispatch((int) $vehicle[$fleetNo], $stops, $lines, 'Påfyll fra butikk', Supply::HOME);
        if ($id === null) {
            continue;
        }
        foreach ($lines as [$pid, $qty]) {
            $line->execute([$pid, $qty, $id, $wid[$from]]);
        }
        $plan->execute([$id]);
        $p = $plan->fetch();
        $pts = json_decode($p['points'], true);
        $back = DemoSim::plan($pts, (float) $p['t0'])['total'] * ((($i * 37) % 85) + 5) / 100;
        $pdo->prepare('UPDATE shipment_plans SET t0 = t0 - ? WHERE shipment_id = ?')->execute([$back, $id]);
        $pdo->prepare('UPDATE shipments SET planned_departure = planned_departure - INTERVAL ? SECOND, actual_departure = actual_departure - INTERVAL ? SECOND,
            eta = eta - INTERVAL ? SECOND, planned_arrival = planned_arrival - INTERVAL ? SECOND WHERE id = ?')->execute([(int) $back, (int) $back, (int) $back, (int) $back, $id]);
        $now = DemoSim::at(DemoSim::plan($pts, (float) $p['t0'] - $back), $back);
        $pdo->prepare('DELETE FROM shipment_positions WHERE shipment_id = ?')->execute([$id]);
        $pdo->prepare("INSERT INTO shipment_positions (shipment_id, lat, lng, heading, observed_at, received_at, source) VALUES (?, ?, ?, ?, NOW(), NOW(), ?)")
            ->execute([$id, round($now['lat'], 5), round($now['lng'], 5), $now['heading'], DemoDelivery::SOURCE]);
        $made++;
    }
    return $made;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require __DIR__ . '/../src/bootstrap.php';
    require_once __DIR__ . '/../src/helpers.php';
    echo 'Transport (demo): ' . seed_transport(db(), APP_ROOT) . " sendinger\n";
}
