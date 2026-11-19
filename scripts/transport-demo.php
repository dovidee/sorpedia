<?php
// Demo transport for the Control Tower map (specs/022-control-tower, DELIVERY 5.9).
// No fleet or GPS source is connected: every row is a fixture with is_demo = 1 and shown as DEMO DATA.
// City coordinates are the real town centres. Routes are estimates through towns on the way, not road geometry.
// Times are relative to the run, so positions go stale as time passes, exactly as a stopped feed would.
//
//   php scripts/transport-demo.php    replaces only the demo transport rows; products, users and analytics are kept
//
// scripts/import.php runs the same seed as one of its steps.

declare(strict_types=1);

function seed_transport(PDO $pdo, string $root): int
{
    $pdo->exec((string) file_get_contents("$root/db/transport.sql"));
    // Child rows of demo shipments first, then the demo shipments, vehicles and drivers themselves
    foreach (['shipment_positions', 'shipment_route_points', 'shipment_items'] as $child) {
        $pdo->exec("DELETE c FROM $child c JOIN shipments s ON s.id = c.shipment_id WHERE s.is_demo = 1");
    }
    $pdo->exec('DELETE FROM shipments WHERE is_demo = 1');
    $pdo->exec('DELETE FROM vehicles WHERE is_demo = 1');
    $pdo->exec('DELETE FROM drivers WHERE is_demo = 1');
    $at = fn(string $rel) => date('Y-m-d H:i:s', strtotime($rel));
    $town = [
        'Oslo' => [59.92766, 10.84130], 'Drammen' => [59.74389, 10.20449], 'Larvik' => [59.05328, 10.03517], 'Porsgrunn' => [59.14054, 9.65614],
        'Arendal' => [58.46176, 8.77245], 'Grimstad' => [58.34128, 8.59321], 'Kristiansand' => [58.14671, 7.99560], 'Bergen' => [60.39126, 5.32205],
        'Haugesund' => [59.41378, 5.26796], 'Stavanger' => [58.96998, 5.73311], 'Egersund' => [58.45130, 5.99969], 'Flekkefjord' => [58.29704, 6.66024],
        'Mandal' => [58.02941, 7.46080],
    ];
    $pdo->exec("INSERT INTO vehicles (fleet_no, type, make, model, model_year, registration, capacity_kg, payload_kg, is_demo) VALUES
        ('V-01', 'trekkvogn', 'Volvo', 'FH 500', 2021, 'DEMO-001', 40000, 24000, 1),
        ('V-02', 'lastebil', 'Scania', 'R 450', 2019, 'DEMO-002', 26000, 14000, 1),
        ('V-03', 'varebil', 'Mercedes-Benz', 'Sprinter 316', 2022, 'DEMO-003', 3500, 1200, 1),
        ('V-04', 'varebil', 'Volkswagen', 'Crafter 35', 2020, 'DEMO-004', 3500, 1300, 1)");
    $pdo->exec("INSERT INTO drivers (alias, driver_ref, phone, is_demo) VALUES ('Sjåfør A', 'D-101', NULL, 1), ('Sjåfør B', 'D-102', NULL, 1), ('Sjåfør C', 'D-103', NULL, 1), ('Sjåfør D', 'D-104', NULL, 1)");
    $pick = fn(string $theme, int $n) => $pdo->query('SELECT id FROM products WHERE theme = ' . $pdo->quote($theme) . " ORDER BY id LIMIT $n")->fetchAll(PDO::FETCH_COLUMN);
    $shipments = [
        // ref, dir, status, from, to, warehouse, vehicle, driver, container, departure planned/actual, eta, planned arrival, actual arrival, kg, delay, route, positions [town, minutes ago, heading], items
        ['SR-IN-0001', 'inn', 'underveis', 'Oslo', 'Kristiansand', 1, 1, 1, ['CT-4471', 'Semitrailer 13,6 m'], ['-5 hours', '-4 hours 50 minutes'], '+50 minutes', '+40 minutes', null, 9800, null,
            ['Oslo', 'Drammen', 'Larvik', 'Porsgrunn', 'Arendal', 'Grimstad', 'Kristiansand'], [['Drammen', 230, 225], ['Larvik', 140, 200], ['Porsgrunn', 4, 235]],
            [['lys', 3, 24], ['bygg', 2, 40]]],
        ['SR-IN-0002', 'inn', 'forsinket', 'Bergen', 'Kristiansand', 1, 2, 2, ['CT-2210', 'Skaplastebil'], ['-9 hours', '-8 hours 30 minutes'], '+3 hours', '+1 hour', null, null, 'Ventetid på ferje (demo)',
            ['Bergen', 'Haugesund', 'Stavanger', 'Egersund', 'Flekkefjord', 'Mandal', 'Kristiansand'], [['Haugesund', 300, 180], ['Stavanger', 45, 160]],
            [['tur', 2, 12], ['skog', 2, 18]]],
        ['SR-IN-0003', 'inn', 'lastes', 'Drammen', 'Kristiansand', 1, 3, 3, [null, null], ['+30 minutes', null], null, '+5 hours', null, null, null,
            ['Drammen', 'Larvik', 'Porsgrunn', 'Arendal', 'Kristiansand'], [['Drammen', 2, null]],
            [['maling', 2, 30]]],
        ['SR-UT-0004', 'ut', 'levert', 'Kristiansand', 'Arendal', 7, 4, 4, [null, null], ['-6 hours', '-6 hours'], null, '-4 hours', '-4 hours 10 minutes', null, null,
            ['Kristiansand', 'Grimstad', 'Arendal'], [['Arendal', 250, 60]],
            [['kjetting', 1, 6]]],
    ];
    $insS = $pdo->prepare('INSERT INTO shipments (ref, direction, status, origin_city, dest_city, dest_point, warehouse_id, vehicle_id, driver_id, container_ref, container_type,
        planned_departure, actual_departure, eta, eta_source, eta_calculated_at, planned_arrival, actual_arrival, weight_declared_kg, delay_reason, is_demo)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
    $insR = $pdo->prepare('INSERT INTO shipment_route_points (shipment_id, seq, lat, lng, source) VALUES (?,?,?,?,"estimert")');
    $insP = $pdo->prepare('INSERT INTO shipment_positions (shipment_id, lat, lng, heading, observed_at, received_at, source) VALUES (?,?,?,?,?,?,"demo")');
    $insI = $pdo->prepare('INSERT INTO shipment_items (shipment_id, product_id, qty, weight_kg, weight_basis, handling_unit) VALUES (?,?,?,NULL,NULL,"kolli")');
    $vehicle = $pdo->query("SELECT CAST(SUBSTRING(fleet_no, 3) AS UNSIGNED), id FROM vehicles WHERE is_demo = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
    $driver = $pdo->query("SELECT CAST(SUBSTRING(driver_ref, 3) AS UNSIGNED) - 100, id FROM drivers WHERE is_demo = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($shipments as [$ref, $dir, $status, $from, $to, $wid, $vid, $did, [$cref, $ctype], [$pdep, $adep], $eta, $parr, $aarr, $kg, $delay, $route, $positions, $items]) {
        $insS->execute([$ref, $dir, $status, $from, $to, "Sør-Tre $to", $wid, $vehicle[$vid], $driver[$did], $cref, $ctype, $at($pdep), $adep ? $at($adep) : null,
            $eta ? $at($eta) : null, $eta ? 'Demo: fast reisetid' : null, $eta ? $at('-10 minutes') : null, $at($parr), $aarr ? $at($aarr) : null, $kg, $delay]);
        $sid = (int) $pdo->lastInsertId();
        foreach ($route as $i => $t) {
            $insR->execute([$sid, $i, ...$town[$t]]);
        }
        foreach ($positions as [$t, $ago, $heading]) {
            $insP->execute([$sid, ...$town[$t], $heading, $at("-$ago minutes"), $at("-$ago minutes")]);
        }
        foreach ($items as [$theme, $n, $qty]) {
            foreach ($pick($theme, $n) as $pid) {
                $insI->execute([$sid, $pid, $qty]);
            }
        }
    }
    return count($shipments);
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require __DIR__ . '/../src/bootstrap.php';
    Tower::ensure();
    echo 'Transport (demo): ' . seed_transport(db(), APP_ROOT) . " sendinger\n";
}
