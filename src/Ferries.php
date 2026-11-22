<?php
declare(strict_types=1);

/**
 * Live status for the road ferries near the shipment routes (feature 022), filled by scripts/conditions.php.
 * Departures and operator messages come from Entur's Journey Planner (SIRI-ET and SIRI-SX), vessel positions from
 * Entur's vehicle positions (SIRI-VM). Nothing is simulated: a crossing without an answer is shown as unknown.
 * A departure counts as live only when Entur marks it realtime; otherwise it is shown as the timetable.
 */
final class Ferries
{
    /** Crossings with their quays in the national stop register (NSR) and the line name Entur uses. */
    public const CROSSINGS = [
        'halhjem-sandvikvag' => ['name' => 'Halhjem - Sandvikvåg', 'road' => 'E39', 'line' => 'Halhjem - Sandvikvåg', 'quays' => [
            ['NSR:StopPlace:58463', 'Halhjem', 60.14586, 5.42669], ['NSR:StopPlace:58462', 'Sandvikvåg', 59.96816, 5.33405]]],
        'mortavika-arsvagen' => ['name' => 'Mortavika - Arsvågen', 'road' => 'E39', 'line' => 'Mortavika - Arsvågen', 'quays' => [
            ['NSR:StopPlace:58653', 'Mortavika', 59.13715, 5.58799], ['NSR:StopPlace:58499', 'Arsvågen', 59.17051, 5.45270]]],
        'moss-horten' => ['name' => 'Moss - Horten', 'road' => 'Rv. 19', 'line' => 'Rv. 19 Moss-Horten', 'quays' => [
            ['NSR:StopPlace:58092', 'Moss', 59.43049, 10.65435], ['NSR:StopPlace:58374', 'Horten', 59.41222, 10.48806]]],
    ];
    public const STATUSES = ['cancelled', 'disrupted', 'delayed', 'normal', 'scheduled', 'unknown'];
    public const DELAYED_MIN = 5;   // a live departure this many minutes late makes the crossing delayed
    private const CLIENT = 'sorpedia-demo';

    public static function ensure(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ferry_status (
            crossing   VARCHAR(40) PRIMARY KEY,
            status     ENUM('cancelled','disrupted','delayed','normal','scheduled','unknown') NOT NULL,
            delay_min  SMALLINT UNSIGNED NULL,
            departures TEXT NOT NULL,
            messages   TEXT NOT NULL,
            vessels    TEXT NOT NULL,
            source     VARCHAR(120) NOT NULL,
            checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
    }

    // ---------- Pure rules ----------

    /**
     * The crossing's status from its departures and operator messages. Order of weight: a cancelled departure,
     * an operator message, a live delay, live and on time, timetable only, nothing known.
     * @param list<array{aimed:int, expected:?int, live:bool, cancelled:bool}> $calls
     * @param list<string> $messages
     * @return array{0:string, 1:?int} status and the largest live delay in minutes
     */
    public static function status(array $calls, array $messages): array
    {
        $live = array_filter($calls, fn($c) => $c['live']);
        $delay = 0;
        foreach ($live as $c) {
            if ($c['expected'] !== null) {
                $delay = max($delay, intdiv($c['expected'] - $c['aimed'], 60));
            }
        }
        return match (true) {
            (bool) array_filter($calls, fn($c) => $c['live'] && $c['cancelled']) => ['cancelled', null],
            (bool) $messages => ['disrupted', $delay ?: null],
            $delay >= self::DELAYED_MIN => ['delayed', $delay],
            (bool) $live => ['normal', null],
            (bool) $calls => ['scheduled', null],
            default => ['unknown', null],
        };
    }

    /** Shipments whose route passes both quays of a crossing. @return list<int> */
    public static function ships(array $crossing, array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (count($r['route'] ?? []) < 2) {
                continue;
            }
            $near = true;
            foreach ($crossing['quays'] as [, , $lat, $lng]) {
                $near = $near && Conditions::distance($r['route'], $lat, $lng) <= Conditions::NEAR_KM;
            }
            if ($near) {
                $out[] = (int) $r['id'];
            }
        }
        return $out;
    }

    // ---------- Reading (page request, database only) ----------

    public static function current(array $rows): array
    {
        $pdo = db();
        self::ensure($pdo);
        $have = [];
        foreach ($pdo->query('SELECT crossing, status, delay_min, departures, messages, vessels, source, UNIX_TIMESTAMP(checked_at) checked FROM ferry_status') as $s) {
            $have[$s['crossing']] = $s;
        }
        $out = [];
        foreach (self::CROSSINGS as $key => $c) {
            $s = $have[$key] ?? null;
            $out[] = ['id' => $key, 'name' => $c['name'], 'road' => $c['road'],
                'quays' => array_map(fn($q) => ['name' => $q[1], 'lat' => $q[2], 'lng' => $q[3]], $c['quays']),
                'status' => $s['status'] ?? 'unknown', 'delay_min' => $s === null || $s['delay_min'] === null ? null : (int) $s['delay_min'],
                'departures' => $s ? json_decode($s['departures'], true) : [], 'messages' => $s ? json_decode($s['messages'], true) : [],
                'vessels' => $s ? json_decode($s['vessels'], true) : [], 'source' => $s['source'] ?? null,
                'checked' => $s ? (int) $s['checked'] : null, 'ships' => self::ships($c, $rows)];
        }
        return $out;
    }

    // ---------- Filling (scripts/conditions.php) ----------

    /** One GraphQL POST to Entur with a short timeout and no retry. Null on any failure. */
    private static function graphql(string $url, string $query): ?array
    {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15,
            'header' => "Content-Type: application/json\r\nET-Client-Name: " . self::CLIENT, 'content' => json_encode(['query' => $query])]]);
        $body = @file_get_contents($url, false, $ctx);
        $data = $body === false ? null : json_decode($body, true);
        return is_array($data['data'] ?? null) && empty($data['errors']) ? $data['data'] : null;
    }

    /** @return array<string, string> what happened */
    public static function refresh(int $now): array
    {
        $ids = [];
        foreach (self::CROSSINGS as $c) {
            foreach ($c['quays'] as [$id]) {
                $ids[] = '"' . $id . '"';
            }
        }
        $calls = self::graphql('https://api.entur.io/journey-planner/v3/graphql', '{stopPlaces(ids:[' . implode(',', $ids) . ']){id
            estimatedCalls(numberOfDepartures:10, timeRange:10800, whiteListedModes:[water]){realtime cancellation aimedDepartureTime expectedDepartureTime
            serviceJourney{line{name}} situations{situationNumber summary{value} description{value}}}}}');
        $fleet = self::graphql('https://api.entur.io/realtime/v2/vehicles/graphql',
            '{vehicles(mode:FERRY){vehicleId lastUpdated bearing location{latitude longitude} line{lineName}}}');
        $byQuay = [];
        foreach ($calls['stopPlaces'] ?? [] as $sp) {
            $byQuay[$sp['id']] = $sp['estimatedCalls'] ?? [];
        }
        $rows = [];
        foreach (self::CROSSINGS as $key => $c) {
            $deps = [];
            $msgs = [];
            foreach ($c['quays'] as [$id, $quay]) {
                foreach ($byQuay[$id] ?? [] as $e) {
                    if (($e['serviceJourney']['line']['name'] ?? '') !== $c['line']) {
                        continue;
                    }
                    $deps[] = ['from' => $quay, 'aimed' => strtotime($e['aimedDepartureTime']),
                        'expected' => $e['expectedDepartureTime'] ? strtotime($e['expectedDepartureTime']) : null,
                        'live' => (bool) $e['realtime'], 'cancelled' => (bool) $e['cancellation']];
                    foreach ($e['situations'] ?? [] as $s) {
                        $text = trim((string) ($s['description'][0]['value'] ?? $s['summary'][0]['value'] ?? ''));
                        if ($text !== '') {
                            $msgs[$s['situationNumber'] ?? $text] = mb_substr($text, 0, 300);
                        }
                    }
                }
            }
            usort($deps, fn($a, $b) => $a['aimed'] <=> $b['aimed']);
            $deps = array_slice($deps, 0, 8);
            $msgs = array_values($msgs);
            [$status, $delay] = $calls === null ? ['unknown', null] : self::status($deps, $msgs);
            $vessels = [];
            foreach ($fleet['vehicles'] ?? [] as $v) {
                if (($v['line']['lineName'] ?? '') !== $c['line'] || !isset($v['location']['latitude'])) {
                    continue;
                }
                $at = strtotime((string) $v['lastUpdated']);
                $id = (string) $v['vehicleId'];
                if (!isset($vessels[$id]) || $vessels[$id]['at'] < $at) {
                    $vessels[$id] = ['id' => $id, 'mmsi' => preg_match('/^\d{9}$/', $id) ? $id : null, 'lat' => round((float) $v['location']['latitude'], 5),
                        'lng' => round((float) $v['location']['longitude'], 5), 'bearing' => $v['bearing'] === null ? null : (int) round((float) $v['bearing']), 'at' => $at];
                }
            }
            $source = implode(', ', array_filter([$calls !== null ? 'Entur SIRI-ET/SX' : null, $fleet !== null ? 'Entur SIRI-VM' : null])) ?: 'Entur';
            $rows[] = [$key, $status, $delay, json_encode($deps), json_encode($msgs, JSON_UNESCAPED_UNICODE), json_encode(array_values($vessels)), $source];
        }
        $pdo = db();
        self::ensure($pdo);
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM ferry_status');
        $st = $pdo->prepare('INSERT INTO ferry_status (crossing, status, delay_min, departures, messages, vessels, source) VALUES (?,?,?,?,?,?,?)');
        foreach ($rows as $r) {
            $st->execute($r);
        }
        $pdo->commit();
        $counts = array_count_values(array_column($rows, 1));
        return ['ferries' => $calls === null ? 'Entur svarte ikke, ferjestatus ukjent' : implode(', ', array_map(fn($k, $n) => "$n $k", array_keys($counts), $counts)),
            'vessels' => $fleet === null ? 'Entur posisjoner svarte ikke' : array_sum(array_map(fn($r) => count(json_decode($r[5], true)), $rows)) . ' ferjer med posisjon'];
    }
}
