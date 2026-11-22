<?php
declare(strict_types=1);

/**
 * The transport simulation in the database (features 032 and 043). Every demo vehicle drives on one accelerated clock
 * (sim_clock) that the presenter can play, pause, speed up and switch between two scenarios: real conditions, or a
 * simulated storm. A trip is a plan (shipment_plans): the route through the road network (Roads) with what is known
 * about each leg when the trip is planned, read from the rows scripts/conditions.php stores:
 *
 *   weather   MET Norway Locationforecast, the nearest point to the leg (a demo row says MET did not answer)
 *   works     Statens vegvesen road works, closures and warnings near the leg
 *   ferry     Entur timetable (interval between departures) and the operator's status and messages
 *
 * Nothing is fetched here: a page request reads the database only (rule 8). In the storm scenario the weather on the
 * west coast legs is replaced by a storm that says it is simulated. Each tick stores one position row per moving
 * vehicle, so the map moves on stored rows only. Every row is demo (is_demo = 1, source "demo-simulering").
 */
final class DemoDelivery
{
    /** Demo weight per parcel when the catalogue has no product weight. */
    public const PARCEL_KG = 2.5;
    public const SOURCE = 'demo-simulering';
    /** Minutes spent loading at a store that hands over goods. */
    public const LOAD_MIN = 20;
    public const SCENARIOS = ['ekte', 'storm'];
    /** A detour round a closed road is taken only when the trip takes at most this many times as long. */
    public const DETOUR_MAX = 1.25;
    /** Town centres used by the demo transport fixtures. */
    public const TOWNS = [
        'Oslo' => [59.92766, 10.84130], 'Drammen' => [59.74389, 10.20449], 'Larvik' => [59.05328, 10.03517], 'Porsgrunn' => [59.14054, 9.65614],
        'Arendal' => [58.46176, 8.77245], 'Grimstad' => [58.34128, 8.59321], 'Kristiansand' => [58.14671, 7.99560], 'Bergen' => [60.39126, 5.32205],
        'Haugesund' => [59.41378, 5.26796], 'Stavanger' => [58.96998, 5.73311], 'Egersund' => [58.45130, 5.99969], 'Flekkefjord' => [58.29704, 6.66024],
        'Mandal' => [58.02941, 7.46080],
    ];

    /** Great-circle distance in km. */
    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = fn(float $d) => $d * M_PI / 180;
        $a = sin($r($lat2 - $lat1) / 2) ** 2 + cos($r($lat1)) * cos($r($lat2)) * sin($r($lng2 - $lng1) / 2) ** 2;
        return 6371 * 2 * asin(min(1, sqrt($a)));
    }

    /**
     * The clock, the plans, each vehicle's home base and the goods-in receipt, on databases built before feature 043.
     * Run once per request by Tower::ensure, never inside a transaction (a CREATE or ALTER would commit it).
     */
    public static function ensure(PDO $pdo): void
    {
        if ($pdo->query("SHOW TABLES LIKE 'shipment_plans'")->fetch()) {
            return;
        }
        Conditions::ensure($pdo);
        Ferries::ensure($pdo);
        $pdo->exec("CREATE TABLE IF NOT EXISTS sim_clock (
            id TINYINT UNSIGNED PRIMARY KEY, sim_anchor DOUBLE NOT NULL, real_anchor DOUBLE NOT NULL, rate SMALLINT UNSIGNED NOT NULL,
            paused TINYINT(1) NOT NULL DEFAULT 1, scenario VARCHAR(8) NOT NULL DEFAULT 'ekte'
        ) ENGINE=InnoDB");
        $pdo->exec("CREATE TABLE IF NOT EXISTS shipment_plans (
            shipment_id INT UNSIGNED PRIMARY KEY, t0 DOUBLE NOT NULL, points MEDIUMTEXT NOT NULL, scenario VARCHAR(8) NOT NULL DEFAULT 'ekte',
            stocked TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        if (!$pdo->query("SHOW COLUMNS FROM vehicles LIKE 'base_city'")->fetch()) {
            $pdo->exec('ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS base_city VARCHAR(40) NULL AFTER payload_kg');
        }
        if (!$pdo->query("SHOW COLUMNS FROM shipments LIKE 'checked_in_at'")->fetch()) {
            $pdo->exec('ALTER TABLE shipments ADD COLUMN IF NOT EXISTS checked_in_at DATETIME NULL AFTER actual_arrival');
        }
    }

    // ---------- The clock ----------

    /** The clock row. A new clock starts paused at the real time now. */
    public static function clock(): array
    {
        $pdo = db();
        $c = $pdo->query('SELECT * FROM sim_clock WHERE id = 1')->fetch();
        if (!$c) {
            $pdo->prepare('INSERT IGNORE INTO sim_clock (id, sim_anchor, real_anchor, rate, paused, scenario) VALUES (1, ?, ?, ?, 1, ?)')
                ->execute([time(), microtime(true), DemoSim::DEFAULT_RATE, 'ekte']);
            $c = $pdo->query('SELECT * FROM sim_clock WHERE id = 1')->fetch();
        }
        return $c;
    }

    /** Simulated unix time now. */
    public static function now(?array $c = null): float
    {
        $c ??= self::clock();
        return DemoSim::elapsed((float) $c['sim_anchor'], (float) $c['real_anchor'], (int) $c['rate'], (bool) $c['paused'], microtime(true));
    }

    /** Play, pause, a new rate or a new scenario. The simulated time already passed is kept. */
    public static function control(string $what, int $rate = 0, string $scenario = ''): bool
    {
        $c = self::clock();
        $now = microtime(true);
        $sim = self::now($c);
        $paused = ['pause' => 1, 'play' => 0][$what] ?? (int) $c['paused'];
        $rate = $what === 'rate' && in_array($rate, DemoSim::RATES, true) ? $rate : (int) $c['rate'];
        $scen = $what === 'scenario' && in_array($scenario, self::SCENARIOS, true) ? $scenario : (string) $c['scenario'];
        if (!in_array($what, ['play', 'pause', 'rate', 'scenario'], true)) {
            return false;
        }
        db()->prepare('UPDATE sim_clock SET sim_anchor = ?, real_anchor = ?, rate = ?, paused = ?, scenario = ? WHERE id = 1')->execute([$sim, $now, $rate, $paused, $scen]);
        if ($scen !== (string) $c['scenario']) {
            self::rescenario($scen);
        }
        return true;
    }

    // ---------- Planning ----------

    /** Coordinates of a store by its name (the warehouses table has none): the road network node of the same town. */
    public static function storeAt(string $name): ?array
    {
        return Roads::NODES[$name] ?? null;
    }

    /**
     * The route through the stops with what is known about each leg. A leg between two network nodes with a road
     * closure (Statens vegvesen, or demo) is avoided when the network has another way: the route is planned again
     * without those roads, and the closed stretches are kept on the first point as 'detour' so the map can show them.
     * Without another way, or when the other way takes more than DETOUR_MAX times as long, the trip keeps the closed
     * road and its delay.
     */
    public static function planRoute(array $stops, string $scenario): array
    {
        $pts = self::legs(Roads::route($stops) ?? [], $scenario);
        $closed = [];
        $node = function (array $p): ?string {
            [$n, $km] = Roads::nearest($p['lat'], $p['lng']);
            return $km < 0.05 ? $n : null;
        };
        foreach ($pts as $i => $p) {
            $shut = array_values(array_filter($p['works'] ?? [], fn($w) => $w['kind'] === 'closure'));
            $a = $i > 0 ? $node($pts[$i - 1]) : null;
            $b = $node($p);
            if ($shut && $a !== null && $b !== null && Roads::edge($a, $b) !== null) {
                $closed["$a|$b"] = ['a' => [$pts[$i - 1]['lat'], $pts[$i - 1]['lng']], 'b' => [$p['lat'], $p['lng']], 'road' => $p['road'], 'text' => $shut[0]['text'], 'src' => $shut[0]['src']];
            }
        }
        if (!$closed) {
            return $pts;
        }
        $round = Roads::route($stops, array_fill_keys(array_keys($closed), true));
        if ($round === null || !$round) {
            return $pts;
        }
        $alt = self::legs($round, $scenario);
        // Only a reasonable detour is taken: a sparse network can make the other way many hours longer
        if (DemoSim::plan($alt, 0.0)['total'] > DemoSim::plan($pts, 0.0)['total'] * self::DETOUR_MAX) {
            return $pts;
        }
        $alt[0]['detour'] = array_values($closed);
        return $alt;
    }

    /**
     * What is known about each leg of a route, from the stored conditions: weather, road works and the ferry. In the
     * storm scenario the west coast weather is replaced by a simulated storm (DemoSim::storm).
     */
    public static function legs(array $pts, string $scenario): array
    {
        $pdo = db();
        $cond = $pdo->query('SELECT kind, severity, title, detail, lat, lng, radius_km, symbol, temp_c, wind_ms, source, is_demo, UNIX_TIMESTAMP(fetched_at) at
            FROM route_conditions WHERE valid_to IS NULL OR valid_to > NOW()')->fetchAll();
        $weather = array_values(array_filter($cond, fn($c) => $c['kind'] === 'weather'));
        $obstacles = array_values(array_filter($cond, fn($c) => in_array($c['kind'], ['roadwork', 'closure', 'warning'], true)));
        $ferries = [];
        foreach ($pdo->query('SELECT crossing, status, departures, messages, source, UNIX_TIMESTAMP(checked_at) at FROM ferry_status') as $f) {
            $ferries[$f['crossing']] = $f;
        }
        $day = fn(int $at) => date('d.m H:i', $at);
        foreach ($pts as $i => &$p) {
            $p['dwell'] = $p['kind'] === 'hent' ? self::LOAD_MIN * 60 : 0;
            if ($i === 0) {
                continue;
            }
            $a = $pts[$i - 1];
            $mid = [($a['lat'] + $p['lat']) / 2, ($a['lng'] + $p['lng']) / 2];
            $best = null;
            foreach ($weather as $w) {
                $km = self::km($mid[0], $mid[1], (float) $w['lat'], (float) $w['lng']);
                if ($km <= 60 && ($best === null || $km < $best[0])) {
                    $best = [$km, $w];
                }
            }
            $p['wx'] = $best === null ? null : ['sky' => Conditions::sky($best[1]['symbol'], $best[1]['wind_ms'] === null ? null : (float) $best[1]['wind_ms']),
                'wind' => $best[1]['wind_ms'] === null ? null : (int) round((float) $best[1]['wind_ms']), 'temp' => $best[1]['temp_c'] === null ? null : (int) round((float) $best[1]['temp_c']),
                'src' => (int) $best[1]['is_demo'] ? 'Demo (MET svarte ikke)' : 'MET Norway, varsel hentet ' . $day((int) $best[1]['at']), 'demo' => (bool) (int) $best[1]['is_demo']];
            $p['works'] = [];
            if ($p['mode'] === 'ferje') {
                $key = (string) $p['ferry'];
                [$cross, $every] = Roads::FERRY[$key] ?? [30, 30];
                $f = $ferries[$key] ?? null;
                $quay = trim(str_replace('ferjekai', '', $a['name']));
                $deps = $f ? array_values(array_filter(json_decode($f['departures'], true) ?: [], fn($d) => ($d['from'] ?? '') === $quay)) : [];
                $fx = ['cross' => $cross * 60, 'every' => $every * 60, 'offset' => 0, 'cancel' => false, 'msg' => [],
                    'src' => 'Anslått avgang hvert ' . $every . ' min (ingen rutetabell lagret)'];
                if (count($deps) >= 2) {
                    $times = array_map(fn($d) => (int) $d['aimed'], $deps);
                    sort($times);
                    $gaps = [];
                    for ($k = 1; $k < count($times); $k++) {
                        $gaps[] = $times[$k] - $times[$k - 1];
                    }
                    sort($gaps);
                    $gap = max(600, $gaps[intdiv(count($gaps), 2)]);
                    $fx['every'] = $gap;
                    $fx['offset'] = $times[0] % $gap;
                    $fx['src'] = 'Entur rutetabell, avgang hvert ' . (int) round($gap / 60) . ' min, hentet ' . $day((int) $f['at']);
                }
                if ($f && $f['status'] === 'cancelled') {
                    $fx['cancel'] = 'entur';
                }
                if ($f) {
                    $fx['msg'] = array_slice(json_decode($f['messages'], true) ?: [], 0, 1);
                    $fx['msgsrc'] = 'Entur, melding hentet ' . $day((int) $f['at']);
                }
                $p['fx'] = $fx;
                continue;
            }
            foreach ($obstacles as $o) {
                // A road message counts only for the road the leg drives on (its text names the road, as "E39 Håtong ..."); a
                // weather warning from MET covers an area and counts for every road through it
                $road = (string) ($p['road'] ?? '');
                $onRoad = $o['kind'] === 'warning' ? !str_starts_with((string) $o['source'], 'Statens') || self::names((string) $o['detail'], $road)
                    : self::names((string) $o['detail'], $road);
                $reach = $o['kind'] === 'warning' ? max(3, (int) $o['radius_km']) : 3;
                if ($onRoad && Conditions::distance([[$a['lat'], $a['lng']], [$p['lat'], $p['lng']]], (float) $o['lat'], (float) $o['lng']) <= $reach) {
                    // A closed ramp (av- or påkjøringsveg) does not stop the through traffic: it slows it like road works
                    $kind = $o['kind'] === 'closure' && preg_match('/kjøringsveg|rampe/iu', (string) $o['detail']) ? 'roadwork' : $o['kind'];
                    $p['works'][] = ['kind' => $kind, 'min' => Conditions::DELAY_MIN[$kind] ?? 10, 'text' => mb_substr((string) ($o['detail'] ?: $o['title']), 0, 160),
                        'src' => (int) $o['is_demo'] ? 'Demo (Vegvesen svarte ikke)' : (string) $o['source']];
                }
            }
        }
        unset($p);
        return $scenario === 'storm' ? DemoSim::storm($pts) : $pts;
    }

    /**
     * Sends a vehicle on a trip through the given stops on the simulation clock and returns the shipment id. Stops are
     * [name, lat, lng, kind] with the vehicle's position first. $items are [product_id, qty] lines carried to the store.
     */
    public static function dispatch(int $vehicle, array $stops, array $items, string $what, ?int $warehouse, ?float $start = null): ?int
    {
        $pdo = db();
        $c = self::clock();
        $pts = self::planRoute($stops, (string) $c['scenario']);
        if (count($pts) < 2) {
            return null;
        }
        $t0 = $start ?? self::now($c);
        $booked = DemoSim::plan($pts, $t0, []);
        $at = fn(float $t) => date('Y-m-d H:i:s', (int) round($t));
        $st = $pdo->prepare('SELECT v.fleet_no, (SELECT d.id FROM drivers d WHERE d.is_demo = 1 ORDER BY d.driver_ref LIMIT 1 OFFSET ' . max(0, $vehicle - 1) . ') driver FROM vehicles v WHERE v.id = ?');
        $st->execute([$vehicle]);
        $v = $st->fetch();
        if (!$v) {
            return null;
        }
        $next = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM shipments')->fetchColumn();
        $first = $pts[0];
        $last = $pts[count($pts) - 1];
        $pdo->prepare("INSERT INTO shipments (ref, direction, status, origin_city, origin_point, dest_city, dest_point, warehouse_id, vehicle_id, driver_id, container_ref, container_type,
            planned_departure, actual_departure, eta, eta_source, eta_calculated_at, planned_arrival, is_demo)
            VALUES (?, 'inn', 'underveis', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Simulert tid, ruteplan', ?, ?, 1)")
            ->execute([sprintf('SR-%s-%04d', str_replace('-', '', (string) $v['fleet_no']), $next), $stops[0][0], $first['name'], $warehouse !== null ? (string) $pdo->query('SELECT city FROM warehouses WHERE id = ' . $warehouse)->fetchColumn() : $last['name'],
                $last['name'], $warehouse, $vehicle, $v['driver'] ?: null, sprintf('CT-%04d', $next), $what, $at($t0), $at($t0), $at($t0 + $booked['total']), $at($t0), $at($t0 + $booked['total'])]);
        $id = (int) $pdo->lastInsertId();
        $r = $pdo->prepare("INSERT INTO shipment_route_points (shipment_id, seq, lat, lng, source) VALUES (?, ?, ?, ?, 'estimert')");
        foreach ($pts as $i => $p) {
            $r->execute([$id, $i, $p['lat'], $p['lng']]);
        }
        $add = $pdo->prepare("INSERT INTO shipment_items (shipment_id, product_id, qty, weight_kg, weight_basis, handling_unit) VALUES (?, ?, ?, NULL, NULL, 'kolli')");
        foreach ($items as [$pid, $qty]) {
            $add->execute([$id, (int) $pid, (int) $qty]);
        }
        self::weigh($id);
        $pdo->prepare('INSERT INTO shipment_plans (shipment_id, t0, points, scenario) VALUES (?, ?, ?, ?)')
            ->execute([$id, $t0, json_encode($pts, JSON_UNESCAPED_UNICODE), $c['scenario']]);
        self::store($id, DemoSim::at(DemoSim::plan($pts, $t0), 0));
        return $id;
    }

    /**
     * Trips still on the way take the new scenario from now on: the legs are read again with the same route and start
     * time, so real weather (MET Norway) replaces a simulated storm, and the other way round.
     */
    private static function rescenario(string $scenario): void
    {
        $pdo = db();
        $rows = $pdo->query("SELECT p.shipment_id, p.points FROM shipment_plans p JOIN shipments s ON s.id = p.shipment_id
            WHERE s.is_demo = 1 AND s.actual_arrival IS NULL AND p.scenario <> " . $pdo->quote($scenario))->fetchAll();
        $up = $pdo->prepare('UPDATE shipment_plans SET points = ?, scenario = ? WHERE shipment_id = ?');
        foreach ($rows as $r) {
            $old = json_decode((string) $r['points'], true);
            $stops = array_map(fn($p) => [$p['name'], $p['lat'], $p['lng'], $p['kind']], array_values(array_filter($old, fn($p) => $p['kind'] !== 'via')));
            $up->execute([json_encode(self::planRoute($stops, $scenario), JSON_UNESCAPED_UNICODE), $scenario, $r['shipment_id']]);
        }
    }

    /**
     * Plays a trip again from its start on the clock as it is now, with the current scenario. Stock already received is
     * not received twice (shipment_plans.stocked), so a replay is safe to show more than once.
     */
    public static function replay(int $id): bool
    {
        $pdo = db();
        $st = $pdo->prepare('SELECT p.points FROM shipment_plans p JOIN shipments s ON s.id = p.shipment_id WHERE p.shipment_id = ? AND s.is_demo = 1');
        $st->execute([$id]);
        $raw = $st->fetchColumn();
        if ($raw === false) {
            return false;
        }
        $c = self::clock();
        $old = json_decode((string) $raw, true);
        $stops = array_map(fn($p) => [$p['name'], $p['lat'], $p['lng'], $p['kind']], array_values(array_filter($old, fn($p) => $p['kind'] !== 'via')));
        $pts = self::planRoute($stops, (string) $c['scenario']);
        $t0 = self::now($c);
        $booked = DemoSim::plan($pts, $t0, []);
        $at = fn(float $t) => date('Y-m-d H:i:s', (int) round($t));
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE shipment_plans SET t0 = ?, points = ?, scenario = ? WHERE shipment_id = ?')->execute([$t0, json_encode($pts, JSON_UNESCAPED_UNICODE), $c['scenario'], $id]);
        $pdo->prepare('DELETE FROM shipment_positions WHERE shipment_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM shipment_route_points WHERE shipment_id = ?')->execute([$id]);
        $r = $pdo->prepare("INSERT INTO shipment_route_points (shipment_id, seq, lat, lng, source) VALUES (?, ?, ?, ?, 'estimert')");
        foreach ($pts as $i => $p) {
            $r->execute([$id, $i, $p['lat'], $p['lng']]);
        }
        $pdo->prepare("UPDATE shipments SET status = 'underveis', delay_reason = NULL, actual_departure = ?, planned_departure = ?, eta = ?, planned_arrival = ?,
            eta_calculated_at = ?, eta_source = 'Simulert tid, ruteplan', actual_arrival = NULL, checked_in_at = NULL WHERE id = ?")
            ->execute([$at($t0), $at($t0), $at($t0 + $booked['total']), $at($t0 + $booked['total']), $at($t0), $id]);
        $pdo->commit();
        self::store($id, DemoSim::at(DemoSim::plan($pts, $t0), 0));
        return true;
    }

    /**
     * Fills line weights and the declared total for a demo shipment: product weight (spec vekt_g) times quantity where the
     * catalogue has it (basis estimert), otherwise a fixed demo weight per parcel (basis deklarert). Demo rows only.
     */
    public static function weigh(int $id): void
    {
        $pdo = db();
        $pdo->prepare("UPDATE shipment_items i JOIN shipments s ON s.id = i.shipment_id AND s.is_demo = 1
            LEFT JOIN spec_values v ON v.product_id = i.product_id AND v.`key` = 'vekt_g' AND v.version = 'verifisert'
            SET i.weight_kg = ROUND(i.qty * COALESCE(v.value_num / 1000, ?), 2),
                i.weight_basis = IF(v.value_num IS NULL, 'deklarert', 'estimert')
            WHERE i.shipment_id = ?")->execute([self::PARCEL_KG, $id]);
        $pdo->prepare('UPDATE shipments SET weight_declared_kg = (SELECT CEIL(COALESCE(SUM(weight_kg), 0)) FROM shipment_items WHERE shipment_id = ?)
            WHERE id = ? AND is_demo = 1')->execute([$id, $id]);
    }

    // ---------- The tick ----------

    /**
     * Reads the clock once and moves every planned trip to the simulated second it shows: one stored position per
     * moving vehicle, the ETA and status when news arrives, and the delivery at the end. Returns the clock and the
     * state of each trip (the latest forty), for the map and the control strip.
     */
    public static function tick(): array
    {
        $pdo = db();
        $c = self::clock();
        $sim = self::now($c);
        $rows = $pdo->query('SELECT p.shipment_id id, p.t0, p.points, p.scenario, s.status, s.ref, UNIX_TIMESTAMP(s.actual_arrival) arrived, UNIX_TIMESTAMP(s.eta) eta,
                s.container_type, v.id vehicle_id, v.fleet_no, v.type vehicle_type
            FROM shipment_plans p JOIN shipments s ON s.id = p.shipment_id LEFT JOIN vehicles v ON v.id = s.vehicle_id
            ORDER BY s.actual_arrival IS NULL DESC, p.t0 DESC LIMIT 40')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $t0 = (float) $r['t0'];
            $pts = json_decode((string) $r['points'], true);
            $full = DemoSim::plan($pts, $t0);
            $booked = DemoSim::plan($pts, $t0, []);
            $e = $sim - $t0;
            $known = DemoSim::knownBy($full, $e);
            $cur = DemoSim::plan($pts, $t0, $known);
            $now = DemoSim::at($full, $e);
            $delay = (int) round(($cur['total'] - $booked['total']) / 60);
            $eta = $t0 + $cur['total'];
            $reasons = array_values(array_map(function ($k) use ($full, $pts) {
                $ev = $full['events'][$k];
                $leg = $pts[$ev['leg']];
                return ['type' => $ev['type'], 'road' => $leg['road'] ?? '', 'place' => $leg['name'], 'min' => $ev['min'],
                    'src' => $ev['type'] === 'works' ? ($leg['works'][0]['src'] ?? '') : ($ev['type'] === 'storm' ? ($leg['wx']['src'] ?? '') : ($leg['fx']['src'] ?? ''))];
            }, $known));
            if ($r['arrived'] === null) {
                if (!$c['paused'] && $e >= 0) {
                    self::store($id, $now);
                }
                // The stored shipment follows the news: a delay changes the status, the ETA and the reason, once per change
                $etaS = (int) round($eta);
                if ($delay > 0 && ((int) $r['eta'] !== $etaS || $r['status'] !== 'forsinket') && !$now['done']) {
                    $why = implode('; ', array_map(fn($x) => match ($x['type']) {
                        'works' => "Vegarbeid på {$x['road']} ({$x['src']})", 'storm' => "Storm ved {$x['place']} ({$x['src']})", default => "Ferjeavgang innstilt ved {$x['place']}",
                    }, $reasons));
                    $pdo->prepare("UPDATE shipments SET status = 'forsinket', eta = ?, eta_source = 'Simulert tid, ny beregning', eta_calculated_at = ?, delay_reason = ? WHERE id = ? AND is_demo = 1")
                        ->execute([date('Y-m-d H:i:s', $etaS), date('Y-m-d H:i:s', (int) round($sim)), mb_substr($why, 0, 200), $id]);
                }
                if ($now['done']) {
                    self::arrive($id, (int) round($t0 + $full['total']));
                    $r['arrived'] = (int) round($t0 + $full['total']);
                }
            }
            $legs = array_map(fn($p) => ['a' => [$pts[$p['a']]['lat'], $pts[$p['a']]['lng']], 'b' => [$pts[$p['b']]['lat'], $pts[$p['b']]['lng']]],
                array_values(array_filter($full['phases'], fn($p) => $p['kind'] === 'ferje')));
            $out[] = ['id' => $id, 'ref' => $r['ref'], 'vehicle' => $r['vehicle_id'] !== null ? (int) $r['vehicle_id'] : null, 'fleet_no' => $r['fleet_no'],
                'what' => $r['container_type'], 'scenario' => $r['scenario'], 't0' => $t0, 'elapsed' => max(0.0, min($e, $full['total'])), 'total' => $full['total'],
                'progress' => $full['total'] > 0 ? max(0.0, min(1.0, $e / $full['total'])) : 1.0, 'done' => $r['arrived'] !== null, 'now' => $now,
                'planned' => $t0 + $booked['total'], 'eta' => $eta, 'delay' => $delay > 0 ? ['min' => $delay, 'reasons' => $reasons] : null,
                'notes' => array_values(array_filter(DemoSim::notes($full, $booked), fn($n) => $n['at'] <= $e)),
                'stops' => array_map(fn($p) => ['name' => $p['name'], 'lat' => $p['lat'], 'lng' => $p['lng'], 'kind' => $p['kind'], 'road' => $p['road'] ?? null], $pts),
                'ferries' => $legs, 'detour' => $pts[0]['detour'] ?? []];
        }
        // Nothing left on the road: the clock stops, so simulated time does not run days ahead of the next order
        if (!$c['paused'] && !array_filter($out, fn($t) => !$t['done'])) {
            self::control('pause');
            $c['paused'] = 1;
        }
        return ['clock' => ['sim' => $sim, 'rate' => (int) $c['rate'], 'rates' => DemoSim::RATES, 'paused' => (bool) $c['paused'], 'scenario' => $c['scenario']], 'trips' => $out];
    }

    /**
     * Marks the delivery as delivered and puts its items on the shelf of the receiving store. The status change and the
     * stock change share one transaction, and stock is added once per plan (a replay adds none).
     */
    public static function arrive(int $id, int $now): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        $st = $pdo->prepare("UPDATE shipments SET status = 'levert', actual_arrival = ?, eta = NULL WHERE id = ? AND actual_arrival IS NULL");
        $st->execute([date('Y-m-d H:i:s', $now), $id]);
        $items = [];
        $once = $pdo->prepare('UPDATE shipment_plans SET stocked = 1 WHERE shipment_id = ? AND stocked = 0');
        $once->execute([$id]);
        if ($st->rowCount() === 1 && $once->rowCount() === 1) {
            $q = $pdo->prepare('SELECT i.product_id, i.qty, s.warehouse_id FROM shipment_items i JOIN shipments s ON s.id = i.shipment_id WHERE i.shipment_id = ? AND s.warehouse_id IS NOT NULL');
            $q->execute([$id]);
            $items = $q->fetchAll();
            $add = $pdo->prepare('INSERT INTO stock (product_id, warehouse_id, in_store) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE in_store = in_store + VALUES(in_store)');
            foreach ($items as $i) {
                $add->execute([$i['product_id'], $i['warehouse_id'], $i['qty']]);
            }
            $pdo->prepare("UPDATE supply_list SET status = 'mottatt' WHERE shipment_id = ? AND status = 'bestilt'")->execute([$id]);
        }
        $pdo->commit();
        if ($st->rowCount() === 1) {
            self::goBack($id, $now);
        }
        if ($items) {
            emit('stock', 'restock', ['shipment' => $id]);
        }
        foreach ($items as $i) {
            emit('stock', 'stock', ['product' => (int) $i['product_id']]);
        }
    }

    /**
     * After a delivery with goods the vehicle drives back, without cargo, to where the job started: its base, or the
     * place a demo trip left it. A demo trip and a return trip end where they end, so a vehicle moved on the map stays.
     */
    private static function goBack(int $id, int $now): void
    {
        $pdo = db();
        $st = $pdo->prepare('SELECT s.vehicle_id, s.warehouse_id, p.points FROM shipments s JOIN shipment_plans p ON p.shipment_id = s.id WHERE s.id = ? AND s.is_demo = 1');
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r || $r['warehouse_id'] === null || $r['vehicle_id'] === null) {
            return;
        }
        $pts = json_decode((string) $r['points'], true);
        $from = $pts[0];
        $end = $pts[count($pts) - 1];
        if (self::km($from['lat'], $from['lng'], $end['lat'], $end['lng']) <= Roads::SNAP_KM) {
            return;
        }
        self::dispatch((int) $r['vehicle_id'], [[$end['name'], $end['lat'], $end['lng'], 'start'], ['Tilbake til ' . $from['name'], $from['lat'], $from['lng'], 'mal']],
            [], 'Retur uten last', null, (float) $now);
    }

    /** Goods in: staff confirm that a delivered shipment was received and put away. */
    public static function checkIn(int $id): bool
    {
        $st = db()->prepare("UPDATE shipments SET checked_in_at = NOW() WHERE id = ? AND status = 'levert' AND checked_in_at IS NULL");
        $st->execute([$id]);
        return $st->rowCount() === 1;
    }

    /** Whether a road message is about this road: the road's name stands on its own in the text ("E39 ...", not "E390"). */
    public static function names(string $text, string $road): bool
    {
        return $road !== '' && $road !== 'Lokal vei' && (bool) preg_match('/(^|[\s(,])' . preg_quote($road, '/') . '(?![\d])/u', $text);
    }

    private static function store(int $id, array $at): void
    {
        $last = db()->prepare('SELECT lat, lng FROM shipment_positions WHERE shipment_id = ? ORDER BY id DESC LIMIT 1');
        $last->execute([$id]);
        $l = $last->fetch();
        // One row per real movement: a van waiting at a quay or loading does not fill the history
        if ($l && self::km((float) $l['lat'], (float) $l['lng'], $at['lat'], $at['lng']) < 0.3) {
            return;
        }
        $t = date('Y-m-d H:i:s');
        db()->prepare('INSERT INTO shipment_positions (shipment_id, lat, lng, heading, observed_at, received_at, source) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, round($at['lat'], 5), round($at['lng'], 5), $at['heading'], $t, $t, self::SOURCE]);
    }
}
