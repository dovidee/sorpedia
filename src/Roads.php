<?php
declare(strict_types=1);

/**
 * The main road network the transport simulation drives on (feature 043). Pure: no database.
 *
 * Nodes are real towns and the real ferry quays (the quay coordinates are the ones in Ferries::CROSSINGS, from the
 * national stop register). Edges are the trunk roads between them, named as on the signs, plus the two E39 ferry
 * crossings on the west coast. A route is the fastest path through this network, so a trip from Bergen or Stavanger
 * to the south coast takes the ferries, and a trip from Oslo or Trondheim does not. Distances are straight lines
 * between the nodes times a road factor, so a route is an estimate along the right roads, not road geometry.
 */
final class Roads
{
    public const KMH = 70;
    /** Road distance over straight-line distance: the coast, and the mountain passes where the road winds more. */
    public const FACTOR = 1.25;
    public const FACTOR_MOUNTAIN = 1.5;

    /** Town or quay => [lat, lng]. */
    public const NODES = [
        'Oslo' => [59.92766, 10.84130], 'Drammen' => [59.74389, 10.20449], 'Larvik' => [59.05328, 10.03517], 'Porsgrunn' => [59.14054, 9.65614],
        'Arendal' => [58.46176, 8.77245], 'Grimstad' => [58.34128, 8.59321], 'Kristiansand' => [58.14671, 7.99560], 'Mandal' => [58.02941, 7.46080],
        'Lyngdal' => [58.13760, 7.07230], 'Flekkefjord' => [58.29704, 6.66024], 'Egersund' => [58.45130, 5.99969], 'Ålgård' => [58.76430, 5.85300],
        'Sandnes' => [58.85240, 5.73520], 'Stavanger' => [58.96998, 5.73311], 'Mortavika ferjekai' => [59.13715, 5.58799],
        'Arsvågen ferjekai' => [59.17051, 5.45270], 'Aksdal' => [59.42660, 5.44460], 'Haugesund' => [59.41378, 5.26796], 'Leirvik' => [59.77970, 5.50040],
        'Sandvikvåg ferjekai' => [59.96816, 5.33405], 'Halhjem ferjekai' => [60.14586, 5.42669], 'Bergen' => [60.39126, 5.32205],
        'Voss' => [60.62800, 6.41800], 'Gol' => [60.70000, 8.94500], 'Hønefoss' => [60.16800, 10.25600], 'Hamar' => [60.79450, 11.06800],
        'Lillehammer' => [61.11530, 10.46620], 'Dombås' => [62.07470, 9.12550], 'Oppdal' => [62.59400, 9.69120], 'Trondheim' => [63.43050, 10.39510],
    ];

    /**
     * [from, to, road, ferry crossing key or null, road factor]. A ferry edge is travelled by boat; the crossing key
     * names its row in Ferries::CROSSINGS, where the timetable and the operator's messages come from.
     */
    public const EDGES = [
        ['Oslo', 'Drammen', 'E18', null, self::FACTOR], ['Drammen', 'Larvik', 'E18', null, self::FACTOR], ['Larvik', 'Porsgrunn', 'E18', null, self::FACTOR],
        ['Porsgrunn', 'Arendal', 'E18', null, self::FACTOR], ['Arendal', 'Grimstad', 'E18', null, self::FACTOR], ['Grimstad', 'Kristiansand', 'E18', null, self::FACTOR],
        ['Kristiansand', 'Mandal', 'E39', null, self::FACTOR], ['Mandal', 'Lyngdal', 'E39', null, self::FACTOR], ['Lyngdal', 'Flekkefjord', 'E39', null, self::FACTOR],
        ['Flekkefjord', 'Ålgård', 'E39', null, self::FACTOR], ['Ålgård', 'Sandnes', 'E39', null, self::FACTOR], ['Sandnes', 'Stavanger', 'E39', null, self::FACTOR],
        ['Flekkefjord', 'Egersund', 'Fv. 44', null, self::FACTOR_MOUNTAIN], ['Egersund', 'Sandnes', 'Fv. 44', null, self::FACTOR],
        ['Stavanger', 'Mortavika ferjekai', 'E39', null, self::FACTOR], ['Mortavika ferjekai', 'Arsvågen ferjekai', 'E39', 'mortavika-arsvagen', 1.0],
        ['Arsvågen ferjekai', 'Aksdal', 'E39', null, self::FACTOR], ['Aksdal', 'Haugesund', 'E134', null, self::FACTOR], ['Aksdal', 'Leirvik', 'E39', null, self::FACTOR],
        ['Leirvik', 'Sandvikvåg ferjekai', 'E39', null, self::FACTOR], ['Sandvikvåg ferjekai', 'Halhjem ferjekai', 'E39', 'halhjem-sandvikvag', 1.0],
        ['Halhjem ferjekai', 'Bergen', 'E39', null, self::FACTOR], ['Bergen', 'Voss', 'E16', null, self::FACTOR_MOUNTAIN], ['Voss', 'Gol', 'Rv. 7', null, self::FACTOR_MOUNTAIN],
        ['Gol', 'Hønefoss', 'Rv. 7', null, self::FACTOR], ['Hønefoss', 'Oslo', 'E16', null, self::FACTOR], ['Oslo', 'Hamar', 'E6', null, self::FACTOR],
        ['Hamar', 'Lillehammer', 'E6', null, self::FACTOR], ['Lillehammer', 'Dombås', 'E6', null, self::FACTOR_MOUNTAIN], ['Dombås', 'Oppdal', 'E6', null, self::FACTOR_MOUNTAIN],
        ['Oppdal', 'Trondheim', 'E6', null, self::FACTOR],
    ];

    /** Crossing minutes and minutes between departures when Entur has no timetable stored. Shown as estimates. */
    public const FERRY = ['halhjem-sandvikvag' => [45, 30], 'mortavika-arsvagen' => [25, 20], 'moss-horten' => [30, 30]];

    /** A point within this many km of a node starts or ends there; further away it gets its own local road leg. */
    public const SNAP_KM = 3.0;

    /** Seconds by road for a leg of $km straight-line km. */
    public static function roadSeconds(float $km, float $factor = self::FACTOR): float
    {
        return $km * $factor / self::KMH * 3600;
    }

    /** Nearest node to a point: [name, km]. */
    public static function nearest(float $lat, float $lng): array
    {
        $best = ['', INF];
        foreach (self::NODES as $name => [$a, $b]) {
            $km = DemoDelivery::km($lat, $lng, $a, $b);
            if ($km < $best[1]) {
                $best = [$name, $km];
            }
        }
        return $best;
    }

    /**
     * The fastest path between two nodes, as a list of node names. Ferry edges cost the crossing plus half the time
     * between departures, the usual wait. Edges in $avoid ("A|B") are left out. Null when the nodes are not connected.
     * @return list<string>|null
     */
    public static function path(string $from, string $to, array $avoid = []): ?array
    {
        $adj = [];
        foreach (self::EDGES as [$a, $b, , $ferry, $factor]) {
            if (isset($avoid["$a|$b"]) || isset($avoid["$b|$a"])) {
                continue; // a closed road: the path goes another way, or there is none
            }
            $cost = $ferry !== null ? (self::FERRY[$ferry][0] + self::FERRY[$ferry][1] / 2) * 60
                : self::roadSeconds(DemoDelivery::km(...self::NODES[$a], ...self::NODES[$b]), $factor);
            $adj[$a][$b] = $cost;
            $adj[$b][$a] = $cost;
        }
        if (!isset(self::NODES[$from], self::NODES[$to])) {
            return null;
        }
        $dist = [$from => 0.0];
        $prev = [];
        $open = [$from => 0.0];
        while ($open) {
            asort($open);
            $u = (string) array_key_first($open);
            unset($open[$u]);
            if ($u === $to) {
                break;
            }
            foreach ($adj[$u] ?? [] as $v => $c) {
                $d = $dist[$u] + $c;
                if (!isset($dist[$v]) || $d < $dist[$v]) {
                    $dist[$v] = $d;
                    $prev[$v] = $u;
                    $open[$v] = $d;
                }
            }
        }
        if (!isset($dist[$to])) {
            return null;
        }
        $path = [$to];
        while (end($path) !== $from) {
            $path[] = $prev[end($path)];
        }
        return array_reverse($path);
    }

    /** The edge between two neighbouring nodes: [road, ferry key or null, factor]. */
    public static function edge(string $a, string $b): ?array
    {
        foreach (self::EDGES as [$x, $y, $road, $ferry, $factor]) {
            if (($x === $a && $y === $b) || ($x === $b && $y === $a)) {
                return [$road, $ferry, $factor];
            }
        }
        return null;
    }

    /**
     * A drivable route through stops given as [name, lat, lng, kind]. Between two stops the fastest path through the
     * network is used; a stop away from every node gets a local road leg to its nearest node. Each point says how the
     * leg that ends at it is travelled: mode vei or ferje, the road, and the ferry crossing key.
     * Edges in $avoid ("A|B", either direction) are not used; with $avoid set, null means no route avoids them.
     * @param list<array{0: string, 1: float, 2: float, 3: string}> $stops kinds: start, hent (pick up), mal (destination)
     * @param array<string, true> $avoid
     * @return list<array{name: string, lat: float, lng: float, mode: string, road: ?string, ferry: ?string, factor: float, kind: string}>
     */
    public static function route(array $stops, array $avoid = []): ?array
    {
        $out = [];
        $push = function (string $name, float $lat, float $lng, string $mode, ?string $road, ?string $ferry, float $factor, string $kind) use (&$out): void {
            $last = $out ? $out[count($out) - 1] : null;
            if ($last && DemoDelivery::km($last['lat'], $last['lng'], $lat, $lng) < 0.05) {
                // The same place twice (a stop on a node): keep one point, the stop's kind wins over a plain via
                if ($kind !== 'via') {
                    $out[count($out) - 1]['kind'] = $kind;
                    $out[count($out) - 1]['name'] = $name;
                }
                return;
            }
            $out[] = ['name' => $name, 'lat' => $lat, 'lng' => $lng, 'mode' => $mode, 'road' => $road, 'ferry' => $ferry, 'factor' => $factor, 'kind' => $kind];
        };
        foreach ($stops as $i => [$name, $lat, $lng, $kind]) {
            [$node, $km] = self::nearest($lat, $lng);
            if ($i === 0) {
                $push($name, $lat, $lng, 'start', null, null, self::FACTOR, $kind);
                if ($km > self::SNAP_KM) {
                    $push($node, ...self::NODES[$node], ...['vei', 'Lokal vei', null, self::FACTOR, 'via']);
                }
                continue;
            }
            [$from] = self::nearest($out[count($out) - 1]['lat'], $out[count($out) - 1]['lng']);
            $path = self::path($from, $node, $avoid);
            if ($path === null && $avoid) {
                return null; // no way round the closed roads
            }
            $path ??= [$from, $node];
            for ($k = 1; $k < count($path); $k++) {
                [$road, $ferry, $factor] = self::edge($path[$k - 1], $path[$k]) ?? ['Lokal vei', null, self::FACTOR];
                $push($path[$k], ...self::NODES[$path[$k]], ...[$ferry ? 'ferje' : 'vei', $road, $ferry, $factor, 'via']);
            }
            if ($km > self::SNAP_KM) {
                $push($name, $lat, $lng, 'vei', 'Lokal vei', null, self::FACTOR, $kind);
            } else {
                $out[count($out) - 1]['kind'] = $kind;
                $out[count($out) - 1]['name'] = $name;
            }
        }
        return $out;
    }

    /** Points to ask MET Norway for weather so every leg of the network has a forecast: the nodes that are not quays. */
    public static function weatherPoints(): array
    {
        return array_values(array_map(fn($p) => [round($p[0], 2), round($p[1], 2)], array_filter(self::NODES, fn($k) => !str_contains($k, 'ferjekai'), ARRAY_FILTER_USE_KEY)));
    }
}
