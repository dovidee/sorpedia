<?php
declare(strict_types=1);

/**
 * Weather and other conditions along the shipment routes (feature 022). Filled by scripts/conditions.php, never during
 * a page request: weather and warnings from MET Norway, roadworks, closures and ferry notices from Statens vegvesen's
 * open traffic messages. Each source gets one attempt per run; a source that does not answer is replaced by
 * simulated rows marked demo, so the map always has something to show and never presents demo data as real.
 */
final class Conditions
{
    public const KINDS = ['weather', 'warning', 'roadwork', 'closure', 'ferry', 'rail', 'traffic'];
    /** Rough extra time in minutes a condition adds, used only for the "possible delay" estimate. */
    public const DELAY_MIN = ['ferry' => 40, 'closure' => 60, 'roadwork' => 10, 'rail' => 5, 'traffic' => 15, 'warning' => 20];
    public const NEAR_KM = 12;
    private const UA = 'Sorpedia-demo/1.0 route-conditions';

    public static function ensure(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS route_conditions (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kind       ENUM('weather','warning','roadwork','closure','ferry','rail','traffic') NOT NULL,
            severity   ENUM('info','slow','stop') NOT NULL DEFAULT 'info',
            title      VARCHAR(160) NOT NULL,
            detail     VARCHAR(500) NULL,
            lat        DECIMAL(8,5) NOT NULL,
            lng        DECIMAL(8,5) NOT NULL,
            radius_km  SMALLINT UNSIGNED NOT NULL DEFAULT 5,
            symbol     VARCHAR(48) NULL,
            temp_c     DECIMAL(4,1) NULL,
            wind_ms    DECIMAL(4,1) NULL,
            precip_mm  DECIMAL(5,1) NULL,
            valid_to   DATETIME NULL,
            source     VARCHAR(80) NOT NULL,
            is_demo    TINYINT(1) NOT NULL DEFAULT 0,
            fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY (kind)
        ) ENGINE=InnoDB");
    }

    // ---------- Pure rules ----------

    /** Weather class from a MET symbol code and wind: sol, sky, regn, sno or storm. */
    public static function sky(?string $symbol, ?float $wind = null): string
    {
        $s = (string) $symbol;
        return match (true) {
            str_contains($s, 'thunder') || ($wind !== null && $wind >= 17) => 'storm',
            str_contains($s, 'snow') => 'sno',
            str_contains($s, 'rain') || str_contains($s, 'sleet') => 'regn',
            str_starts_with($s, 'clearsky') || str_starts_with($s, 'fair') => 'sol',
            default => 'sky',
        };
    }

    /** The worst weather class among points: storm over snow over rain over cloud over sun. Null without weather. */
    public static function mood(array $weather): ?string
    {
        $rank = ['sol' => 0, 'sky' => 1, 'regn' => 2, 'sno' => 3, 'storm' => 4];
        $worst = null;
        foreach ($weather as $w) {
            $c = self::sky($w['symbol'] ?? null, isset($w['wind_ms']) ? (float) $w['wind_ms'] : null);
            $worst = $worst === null || $rank[$c] > $rank[$worst] ? $c : $worst;
        }
        return $worst;
    }

    /** Shortest distance in km from a point to a route (list of [lat, lng]), sampling each leg about every 2 km. */
    public static function distance(array $route, float $lat, float $lng): float
    {
        $best = INF;
        $n = count($route);
        for ($i = 0; $i < $n; $i++) {
            $a = $route[$i];
            $b = $route[min($i + 1, $n - 1)];
            $steps = max(1, (int) ceil(DemoDelivery::km($a[0], $a[1], $b[0], $b[1]) / 2));
            for ($k = 0; $k <= $steps; $k++) {
                $f = $k / $steps;
                $best = min($best, DemoDelivery::km($a[0] + ($b[0] - $a[0]) * $f, $a[1] + ($b[1] - $a[1]) * $f, $lat, $lng));
            }
        }
        return $best;
    }

    /**
     * Conditions on each route: shipment ID => condition IDs within reach, plus the estimated extra minutes.
     * @return array<int, array{ids: list<int>, delay_min: int}>
     */
    public static function attach(array $rows, array $conditions): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (count($r['route'] ?? []) < 2) {
                continue;
            }
            $ids = [];
            $delay = 0;
            foreach ($conditions as $c) {
                if (self::distance($r['route'], (float) $c['lat'], (float) $c['lng']) <= max(self::NEAR_KM, (int) $c['radius_km'])) {
                    $ids[] = (int) $c['id'];
                    $delay += $c['kind'] === 'weather' ? (self::sky($c['symbol'], $c['wind_ms'] === null ? null : (float) $c['wind_ms']) === 'storm' ? 20 : 0)
                        : (self::DELAY_MIN[$c['kind']] ?? 0);
                }
            }
            $out[(int) $r['id']] = ['ids' => $ids, 'delay_min' => $delay];
        }
        return $out;
    }

    /** Points to ask for weather: start, end and about every 120 km between, at most six per route. */
    public static function samples(array $route): array
    {
        $pts = [];
        $acc = 0.0;
        foreach ($route as $i => $p) {
            $acc += $i ? DemoDelivery::km($route[$i - 1][0], $route[$i - 1][1], $p[0], $p[1]) : 0;
            if ($i === 0 || $i === count($route) - 1 || $acc >= 120) {
                $pts[] = [round($p[0], 2), round($p[1], 2)];
                $acc = 0.0;
            }
        }
        return array_slice(array_values(array_unique($pts, SORT_REGULAR)), 0, 6);
    }

    // ---------- Reading ----------

    /** Current conditions and the routes they touch, for the transport view. */
    public static function current(array $rows): array
    {
        $pdo = db();
        self::ensure($pdo);
        $list = $pdo->query('SELECT id, kind, severity, title, detail, lat, lng, radius_km, symbol, temp_c, wind_ms, precip_mm, source, is_demo,
                UNIX_TIMESTAMP(fetched_at) fetched FROM route_conditions WHERE valid_to IS NULL OR valid_to > NOW() ORDER BY kind, id')->fetchAll();
        $weather = array_values(array_filter($list, fn($c) => $c['kind'] === 'weather'));
        $night = $weather && !array_filter($weather, fn($w) => !str_ends_with((string) $w['symbol'], '_night'));
        return ['items' => $list, 'routes' => self::attach($rows, $list), 'mood' => self::mood($weather), 'night' => $night,
            'fetched' => $list ? max(array_column($list, 'fetched')) : null];
    }

    // ---------- Filling (scripts/conditions.php) ----------

    /** One GET with a short timeout and no retry. Null on any failure. */
    private static function get(string $url, array $headers = []): ?string
    {
        $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => false,
            'header' => implode("\r\n", ['User-Agent: ' . self::UA, ...$headers])]]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }

    /**
     * Rebuilds the table from the open sources, with demo rows for each source that failed.
     * @return array<string, string> source => what happened
     */
    public static function refresh(array $rows, int $now): array
    {
        $report = [];
        $live = [];
        $points = [];
        foreach ($rows as $r) {
            foreach (self::samples($r['route']) as $p) {
                $points[implode(',', $p)] = $p;
            }
        }
        // Weather: MET Norway Locationforecast. The first failure stops further calls this run.
        $weather = [];
        foreach ($points as [$lat, $lng]) {
            $json = self::get("https://api.met.no/weatherapi/locationforecast/2.0/compact?lat=$lat&lon=$lng");
            $d = $json ? json_decode($json, true) : null;
            $t = $d['properties']['timeseries'][0]['data'] ?? null;
            if (!$t) {
                $weather = [];
                break;
            }
            $weather[] = ['kind' => 'weather', 'severity' => 'info', 'lat' => $lat, 'lng' => $lng, 'radius_km' => 40,
                'symbol' => $t['next_1_hours']['summary']['symbol_code'] ?? $t['next_6_hours']['summary']['symbol_code'] ?? null,
                'temp_c' => $t['instant']['details']['air_temperature'] ?? null, 'wind_ms' => $t['instant']['details']['wind_speed'] ?? null,
                'precip_mm' => $t['next_1_hours']['details']['precipitation_amount'] ?? null, 'title' => 'Vær', 'source' => 'MET Norway', 'is_demo' => 0];
        }
        $report['weather'] = $weather ? count($weather) . ' punkter fra MET Norway' : 'MET Norway svarte ikke, demovær brukt';
        $live = [...$live, ...$weather];
        // Weather warnings: MET Norway MetAlerts. A warning counts when one of its polygons holds a route point.
        $alerts = $weather ? self::get('https://api.met.no/weatherapi/metalerts/2.0/current.json') : null;
        if ($alerts !== null) {
            $n = 0;
            foreach ((json_decode($alerts, true)['features'] ?? []) as $f) {
                $hit = self::hit($f['geometry'] ?? [], $rows);
                if ($hit) {
                    $pr = $f['properties'] ?? [];
                    $level = (string) ($pr['awareness_level'] ?? '');
                    $live[] = ['kind' => 'warning', 'severity' => str_contains($level, 'red') || str_contains($level, 'orange') ? 'stop' : 'slow',
                        'title' => mb_substr((string) ($pr['title'] ?? $pr['event'] ?? 'Farevarsel'), 0, 160), 'detail' => mb_substr((string) ($pr['description'] ?? ''), 0, 500),
                        'lat' => $hit[0], 'lng' => $hit[1], 'radius_km' => 30, 'source' => 'MET Norway farevarsel', 'is_demo' => 0];
                    $n++;
                }
            }
            $report['warnings'] = "$n farevarsler på rutene";
        }
        // Roadworks, closures, ferries and road warnings: Statens vegvesen's open traffic messages (no registration)
        $json = self::get('https://traffic-info.atlas.vegvesen.no/traffic-information/messages',
            ['X-System-ID: ' . self::UA, 'Accept: application/vnd.svv.v1+geo+json']);
        $roads = $json !== null ? self::messages($json, $rows) : null;
        $report['roads'] = $roads === null ? 'Vegvesen svarte ikke, demohendelser brukt' : count($roads) . ' trafikkmeldinger fra Statens vegvesen på rutene';
        $live = [...$live, ...($roads ?? [])];
        $demo = self::demo($rows, $now, !$weather, $roads === null);
        $pdo = db();
        self::ensure($pdo);
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM route_conditions');
        $ins = $pdo->prepare('INSERT INTO route_conditions (kind, severity, title, detail, lat, lng, radius_km, symbol, temp_c, wind_ms, precip_mm, source, is_demo)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ([...$live, ...$demo] as $c) {
            $ins->execute([$c['kind'], $c['severity'], $c['title'], $c['detail'] ?? null, $c['lat'], $c['lng'], $c['radius_km'] ?? 5,
                $c['symbol'] ?? null, $c['temp_c'] ?? null, $c['wind_ms'] ?? null, $c['precip_mm'] ?? null, $c['source'], $c['is_demo']]);
        }
        $pdo->commit();
        $report['demo'] = count($demo) . ' demorader';
        return $report;
    }

    /** First route point inside a GeoJSON (Multi)Polygon, or null. */
    private static function hit(array $geom, array $rows): ?array
    {
        $polys = match ($geom['type'] ?? '') {
            'Polygon' => [$geom['coordinates']],
            'MultiPolygon' => $geom['coordinates'],
            default => [],
        };
        foreach ($rows as $r) {
            foreach ($r['route'] as [$lat, $lng]) {
                foreach ($polys as $poly) {
                    if (self::inside($poly[0] ?? [], $lng, $lat)) {
                        return [$lat, $lng];
                    }
                }
            }
        }
        return null;
    }

    /** Ray casting point in polygon; the ring is a list of [lng, lat]. */
    public static function inside(array $ring, float $x, float $y): bool
    {
        $in = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $in = !$in;
            }
        }
        return $in;
    }

    /**
     * Active traffic messages (GeoJSON) that lie near a route. Closures stop, ferry notices, roadworks and warnings slow.
     * Messages without traffic impact are left out.
     */
    public static function messages(string $json, array $rows): array
    {
        $out = [];
        foreach (json_decode($json, true)['features'] ?? [] as $f) {
            $p = $f['properties'] ?? [];
            [$lng, $lat] = ($f['geometry']['type'] ?? '') === 'Point' ? $f['geometry']['coordinates'] + [null, null] : [null, null];
            if (!is_numeric($lat) || !is_numeric($lng) || ($p['isActiveNow'] ?? false) !== true || ($p['trafficImpact'] ?? '') === 'none') {
                continue;
            }
            $text = str_replace('|', ' ', (string) ($p['descriptionOfTrafficMessage'] ?? ''));
            $type = (string) ($p['type'] ?? '');
            $kind = match (true) {
                str_starts_with($type, 'RoadClosed') => 'closure',
                (bool) preg_match('/^Ferje\b/u', $text) => 'ferry',
                $type === 'RoadWork' => 'roadwork',
                str_contains($type, 'Warning') => 'warning',
                default => null,
            };
            if ($kind === null) {
                continue;
            }
            $near = false;
            foreach ($rows as $r) {
                $near = $near || (count($r['route']) > 1 && self::distance($r['route'], (float) $lat, (float) $lng) <= self::NEAR_KM);
            }
            if (!$near) {
                continue;
            }
            $where = (string) ($p['locationDescriptionDetails']['simpleLocationDescription'] ?? $p['descriptionOfLocation'] ?? '');
            $out[] = ['kind' => $kind, 'severity' => $kind === 'closure' ? 'stop' : 'slow',
                'title' => mb_substr((string) ($p['trafficEvent']['trafficEventDescription'] ?? ['closure' => 'Stengt veg', 'ferry' => 'Ferjemelding', 'roadwork' => 'Vegarbeid', 'warning' => 'Trafikkvarsel'][$kind]), 0, 160),
                'detail' => mb_substr(trim($where . ($where !== '' && $text !== '' ? ': ' : '') . $text), 0, 500) ?: null,
                'lat' => (float) $lat, 'lng' => (float) $lng, 'radius_km' => 3, 'source' => 'Statens vegvesen trafikkmeldinger', 'is_demo' => 0];
        }
        return $out;
    }

    /**
     * Simulated conditions for sources that did not answer, fixed for the hour so the map does not flicker.
     * Weather changes with the hour; obstacles sit at fixed shares of each route.
     */
    public static function demo(array $rows, int $now, bool $weather, bool $roads): array
    {
        $out = [];
        $hour = date('YmdH', $now);
        $month = (int) date('n', $now);
        $codes = ['clearsky_day', 'fair_day', 'partlycloudy_day', 'cloudy', 'lightrain', 'rain', 'heavyrainandthunder', 'snow'];
        foreach ($rows as $r) {
            $route = $r['route'];
            if (count($route) < 2) {
                continue;
            }
            $seed = crc32($hour . ':' . $r['id']);
            if ($weather) {
                foreach (self::samples($route) as $i => [$lat, $lng]) {
                    $west = $lng < 7.5; // the west coast gets more rain
                    $code = $codes[($seed >> ($i * 3)) % ($west ? 8 : 6) + ($west ? 0 : (($seed >> 9) % 2))];
                    if ($month <= 3 || $month >= 11) {
                        $code = $code === 'rain' ? 'snow' : $code;
                    }
                    $out[] = ['kind' => 'weather', 'severity' => 'info', 'title' => 'Vær', 'lat' => $lat, 'lng' => $lng, 'radius_km' => 40, 'symbol' => $code,
                        'temp_c' => ($month >= 5 && $month <= 8 ? 14 : 2) + ($seed >> ($i + 2)) % 9, 'wind_ms' => 2 + ($seed >> ($i + 4)) % 14,
                        'precip_mm' => str_contains($code, 'rain') || $code === 'snow' ? 0.4 + (($seed >> 5) % 30) / 10 : 0, 'source' => 'Demo', 'is_demo' => 1];
                }
            }
            if ($roads) {
                $at = fn(float $f) => $route[(int) floor($f * (count($route) - 1))];
                // Ferries are never simulated: src/Ferries.php shows them from Entur or as unknown
                if ($r['id'] % 2 === 0 || ($seed % 3) === 0) {
                    [$la, $ln] = $at(0.45);
                    $out[] = ['kind' => 'roadwork', 'severity' => 'slow', 'title' => 'Vegarbeid', 'detail' => 'Manuell dirigering, ett felt åpent.', 'lat' => $la, 'lng' => $ln, 'radius_km' => 3, 'source' => 'Demo', 'is_demo' => 1];
                }
                if ($r['id'] % 3 === 1) {
                    [$la, $ln] = $at(0.7);
                    $out[] = ['kind' => 'rail', 'severity' => 'slow', 'title' => 'Planovergang', 'detail' => 'Jernbaneovergang på ruten. Bommene kan være nede noen minutter.', 'lat' => $la, 'lng' => $ln, 'radius_km' => 2, 'source' => 'Demo', 'is_demo' => 1];
                }
                if ($r['id'] % 4 === 3) {
                    [$la, $ln] = $at(0.6);
                    $out[] = ['kind' => 'closure', 'severity' => 'stop', 'title' => 'Stengt veg', 'detail' => 'Vegen er stengt etter et uhell. Omkjøring skiltet.', 'lat' => $la, 'lng' => $ln, 'radius_km' => 3, 'source' => 'Demo', 'is_demo' => 1];
                }
                [$la, $ln] = $at(0.85);
                $summer = $month >= 6 && $month <= 8;
                $out[] = ['kind' => 'traffic', 'severity' => 'slow', 'title' => $summer ? 'Sommertrafikk' : 'Kø inn mot byen',
                    'detail' => $summer ? 'Mange turister og bobiler på vegen.' : 'Tett trafikk i rushtiden.', 'lat' => $la, 'lng' => $ln, 'radius_km' => 5, 'source' => 'Demo', 'is_demo' => 1];
            }
        }
        return $out;
    }
}
