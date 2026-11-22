<?php
declare(strict_types=1);

/**
 * The accelerated transport simulation (features 032 and 043), as pure functions of one simulation clock.
 *
 * A plan is built from a route (Roads::route) whose legs carry what is known about them: the weather (MET Norway, or
 * the simulated storm of the storm scenario), road works and closures (Statens vegvesen), and for a ferry leg the
 * crossing, the timetable (Entur) and the operator's status. Movement, ferry waits, crossings, weather, delays, the ETA
 * and the notifications are all read from the same plan at the same simulated second, so nothing runs on its own clock.
 *
 * Events are the things that make a journey slower than booked: road works or a closure on a leg, a storm on a leg,
 * a cancelled ferry departure. Each becomes known a little before the van reaches it (a storm warning earlier). The
 * ETA at a simulated second is the plan with the events known by then, so it changes exactly when news arrives, and
 * the original ETA is the plan with no events at all.
 */
final class DemoSim
{
    /** Simulated seconds per real second the presenter can choose. */
    public const RATES = [60, 240, 600, 1800];
    public const DEFAULT_RATE = 600;
    /** A storm cancels the first ferry departure it meets and slows the crossing and the road by these factors. */
    public const STORM_CROSSING = 1.6;
    public const STORM_ROAD = 1.25;
    /** Seconds before reaching a leg that road works on it become known, and that a storm warning comes. */
    public const KNOW_WORKS = 900;
    public const KNOW_STORM = 3600;
    /** Road works on one leg add at most this many minutes together. */
    public const WORKS_CAP_MIN = 45;

    /**
     * The events of a route in route order: works on a leg, a storm on a leg, a cancelled departure on a ferry leg.
     * @return list<array{leg: int, type: string, min: int}>
     */
    public static function events(array $pts): array
    {
        $ev = [];
        foreach ($pts as $i => $p) {
            if ($i === 0) {
                continue;
            }
            $min = min(self::WORKS_CAP_MIN, array_sum(array_column($p['works'] ?? [], 'min')));
            if ($min > 0) {
                $ev[] = ['leg' => $i, 'type' => 'works', 'min' => $min];
            }
            if (($p['wx']['sky'] ?? null) === 'storm' && ($p['wx']['scenario'] ?? false)) {
                $ev[] = ['leg' => $i, 'type' => 'storm', 'min' => 0];
            }
            if ($p['mode'] === 'ferje' && ($p['fx']['cancel'] ?? false)) {
                $ev[] = ['leg' => $i, 'type' => 'cancel', 'min' => 0];
            }
        }
        return $ev;
    }

    /**
     * The plan from departure at t0 (unix seconds, simulated) with the events in $apply (indexes into events()); null
     * applies them all. Phases are loading at a stop, travel on a road, waiting at a quay, and a crossing.
     */
    public static function plan(array $pts, float $t0, ?array $apply = null): array
    {
        $events = self::events($pts);
        $on = $apply === null ? array_keys($events) : $apply;
        $has = function (int $leg, string $type) use ($events, $on): ?array {
            foreach ($on as $k) {
                if (isset($events[$k]) && $events[$k]['leg'] === $leg && $events[$k]['type'] === $type) {
                    return $events[$k];
                }
            }
            return null;
        };
        $t = 0.0;
        $phases = [];
        $legStart = [];
        for ($i = 1; $i < count($pts); $i++) {
            $a = $pts[$i - 1];
            $p = $pts[$i];
            $wx = $p['wx'] ?? null;
            $base = ['a' => $i - 1, 'b' => $i, 'sky' => $wx['sky'] ?? null, 'wind' => $wx['wind'] ?? null, 'cancelled' => false];
            if (($a['dwell'] ?? 0) > 0) {
                $phases[] = ['from' => $t, 'to' => $t + $a['dwell'], 'kind' => 'laster', 'b' => $i - 1] + $base;
                $t += $a['dwell'];
            }
            $legStart[$i] = $t;
            if ($p['mode'] === 'ferje') {
                $fx = $p['fx'] ?? [];
                $cross = (float) ($fx['cross'] ?? 1800);
                $storm = $has($i, 'storm') !== null;
                $at = $t0 + $t;
                $dep = self::nextDeparture($at, $fx);
                $cancelled = $has($i, 'cancel') !== null;
                if ($cancelled) {
                    $dep = self::nextDeparture($dep + 1, $fx);
                }
                if ($storm) {
                    $cross *= self::STORM_CROSSING;
                }
                if ($dep - $at > 0) {
                    $phases[] = ['from' => $t, 'to' => $dep - $t0, 'kind' => 'venter', 'cancelled' => $cancelled] + $base;
                    $t = $dep - $t0;
                }
                $phases[] = ['from' => $t, 'to' => $t + $cross, 'kind' => 'ferje'] + $base;
                $t += $cross;
            } else {
                $dur = Roads::roadSeconds(DemoDelivery::km($a['lat'], $a['lng'], $p['lat'], $p['lng']), (float) ($p['factor'] ?? Roads::FACTOR));
                if ($has($i, 'storm') !== null) {
                    $dur *= self::STORM_ROAD;
                }
                $works = $has($i, 'works');
                $phases[] = ['from' => $t, 'to' => $t + $dur + ($works ? $works['min'] * 60 : 0), 'kind' => 'vei'] + $base;
                $t += $dur + ($works ? $works['min'] * 60 : 0);
            }
        }
        if (!$phases) {
            $phases[] = ['from' => 0.0, 'to' => 0.0, 'kind' => 'vei', 'a' => 0, 'b' => 0, 'sky' => null, 'wind' => null, 'cancelled' => false];
        }
        return ['points' => $pts, 'phases' => $phases, 'total' => $t, 'legStart' => $legStart, 'events' => $events, 't0' => $t0];
    }

    /**
     * The next departure at or after unix time $at: one every $fx['every'] seconds, shifted by $fx['offset'] seconds.
     * The interval and its offset come from the Entur timetable when one is stored, else from Roads::FERRY.
     */
    public static function nextDeparture(float $at, array $fx): float
    {
        $every = max(60, (int) ($fx['every'] ?? 1800));
        $offset = (int) ($fx['offset'] ?? 0) % $every;
        return ceil(($at - $offset) / $every) * $every + $offset;
    }

    /** When each event becomes known, in simulated seconds after departure, on the plan with every event applied. */
    public static function known(array $full): array
    {
        $out = [];
        foreach ($full['events'] as $k => $e) {
            $start = $full['legStart'][$e['leg']] ?? 0.0;
            $out[$k] = max(0.0, $start - ($e['type'] === 'works' ? self::KNOW_WORKS : self::KNOW_STORM));
        }
        return $out;
    }

    /** The events known at $e simulated seconds after departure (indexes). */
    public static function knownBy(array $full, float $e): array
    {
        return array_keys(array_filter(self::known($full), fn($at) => $at <= $e));
    }

    /** Where the van is, how it travels and the weather around it at $e simulated seconds after departure. */
    public static function at(array $plan, float $e): array
    {
        $e = max(0.0, min($e, $plan['total']));
        $ph = end($plan['phases']);
        foreach ($plan['phases'] as $p) {
            if ($e <= $p['to']) {
                $ph = $p;
                break;
            }
        }
        $a = $plan['points'][$ph['a']];
        $b = $plan['points'][$ph['b']];
        $still = in_array($ph['kind'], ['venter', 'laster'], true);
        $f = $still ? 0.0 : ($ph['to'] > $ph['from'] ? ($e - $ph['from']) / ($ph['to'] - $ph['from']) : 1.0);
        $at = $ph['kind'] === 'laster' ? $b : $a;
        $to = $ph['kind'] === 'laster' ? $plan['points'][min($ph['b'] + 1, count($plan['points']) - 1)] : $b;
        $heading = (int) round(fmod(rad2deg(atan2(($to['lng'] - $at['lng']) * cos(deg2rad(($at['lat'] + $to['lat']) / 2)), $to['lat'] - $at['lat'])) + 360, 360));
        $leg = $plan['points'][$ph['kind'] === 'laster' ? min($ph['b'] + 1, count($plan['points']) - 1) : $ph['b']];
        return ['lat' => $at['lat'] + ($b['lat'] - $at['lat']) * $f, 'lng' => $at['lng'] + ($b['lng'] - $at['lng']) * $f, 'heading' => $heading,
            'kind' => $ph['kind'], 'from' => $at['name'], 'to' => $to['name'], 'road' => $leg['road'] ?? null,
            'sky' => $ph['sky'], 'wind' => $ph['wind'], 'temp' => $leg['wx']['temp'] ?? null, 'wxsrc' => $leg['wx']['src'] ?? null,
            'wxdemo' => (bool) ($leg['wx']['demo'] ?? false), 'cancelled' => $ph['cancelled'], 'done' => $e >= $plan['total']];
    }

    /**
     * The notifications of the journey, each at the simulated second it happens, with where it happened and the source
     * of what it says. Only the ones the clock has passed are shown, so they arrive one by one.
     */
    public static function notes(array $full, array $booked): array
    {
        $n = [];
        $pts = $full['points'];
        $add = function (float $at, string $key, array $vars, int $i, ?string $src = null) use (&$n, $pts): void {
            $n[] = ['at' => $at, 'key' => $key, 'vars' => $vars, 'lat' => $pts[$i]['lat'], 'lng' => $pts[$i]['lng'], 'src' => $src];
        };
        $add(0, 'avgang', ['from' => $pts[0]['name']], 0);
        $sky = null;
        foreach ($full['phases'] as $p) {
            $leg = $pts[$p['b']];
            if ($p['kind'] !== 'laster' && $p['sky'] !== null && $p['sky'] !== $sky) {
                $key = ['regn' => 'regn', 'storm' => 'storm', 'sno' => 'sno', 'sol' => 'klarner', 'natt' => 'klarner', 'sky' => 'opphold'][$p['sky']] ?? 'opphold';
                if ($sky !== null || $p['sky'] !== 'sol') {
                    $add($p['from'], $key, ['place' => $pts[$p['a']]['name'], 'wind' => $p['wind'] ?? '?'], $p['a'], $leg['wx']['src'] ?? null);
                }
                $sky = $p['sky'];
            }
            if ($p['kind'] === 'laster') {
                $add($p['from'], 'laster', ['place' => $pts[$p['a']]['name']], $p['a']);
                $add($p['to'], 'lastet', ['place' => $pts[$p['a']]['name']], $p['a']);
            }
            if ($p['kind'] === 'venter') {
                $add(max(0, $p['from'] - 600), 'mot_ferje', ['ferry' => self::ferryName($full, $p)], $p['a'], $leg['fx']['src'] ?? null);
                if ($p['cancelled']) {
                    $add($p['from'] + 1, $leg['fx']['cancel'] === 'storm' ? 'innstilt' : 'innstilt_entur', ['ferry' => self::ferryName($full, $p), 'wind' => $p['wind'] ?? '?'], $p['a'], $leg['fx']['src'] ?? null);
                }
                foreach ($leg['fx']['msg'] ?? [] as $m) {
                    $add($p['from'] + 2, 'ferjemelding', ['ferry' => self::ferryName($full, $p), 'text' => $m], $p['a'], $leg['fx']['msgsrc'] ?? 'Entur');
                }
            }
            if ($p['kind'] === 'ferje') {
                $prev = $n ? $n[count($n) - 1]['key'] : '';
                if (!in_array($prev, ['mot_ferje', 'innstilt', 'innstilt_entur', 'ferjemelding'], true)) {
                    $add(max(0, $p['from'] - 600), 'mot_ferje', ['ferry' => self::ferryName($full, $p)], $p['a'], $leg['fx']['src'] ?? null);
                }
                $add($p['from'], 'ombord', ['ferry' => self::ferryName($full, $p)], $p['a']);
                $add($p['to'], 'i_land', ['place' => $pts[$p['b']]['name']], $p['b']);
            }
        }
        // Each event when it becomes known, with the ETA it gives
        $known = self::known($full);
        asort($known);
        $applied = [];
        $said = 0;
        foreach ($known as $k => $at) {
            $e = $full['events'][$k];
            $applied[] = $k;
            $eta = self::plan($pts, $full['t0'], $applied)['total'];
            $min = (int) round(($eta - $booked['total']) / 60);
            $leg = $pts[$e['leg']];
            $a = $pts[$e['leg'] - 1];
            if ($e['type'] === 'works') {
                $kinds = array_column($leg['works'], 'kind');
                $w = $leg['works'][array_search(in_array('closure', $kinds, true) ? 'closure' : $kinds[0], $kinds, true)];
                $key = match (true) {
                    in_array('closure', $kinds, true) => 'stengt',
                    !array_diff($kinds, ['warning']) => 'farevarsel',
                    count($leg['works']) > 1 => 'veiarbeid_n',
                    default => 'veiarbeid',
                };
                $add($at, $key, ['road' => $leg['road'] ?? '', 'from' => $a['name'], 'to' => $leg['name'],
                    'text' => $w['text'], 'n' => count($leg['works']), 'min' => $e['min']], $e['leg'] - 1, $w['src']);
            } elseif ($e['type'] === 'storm' && ($pts[$e['leg'] - 1]['wx']['sky'] ?? null) !== 'storm') {
                // One warning per stretch of storm, at its first leg
                $add($at, 'stormvarsel', ['place' => $leg['name'], 'wind' => $leg['wx']['wind'] ?? '?'], $e['leg'], $leg['wx']['src'] ?? null);
            }
            if ($min > $said) {
                $add($at + 1, 'forsinket', ['min' => $min, 'eta' => $full['t0'] + $eta], $e['leg'] - 1);
                $said = $min;
            }
        }
        $last = count($pts) - 1;
        $add(max(0, $full['total'] - 900), 'naer', ['place' => $pts[$last]['name']], $last);
        $add($full['total'], 'levert', ['place' => $pts[$last]['name']], $last);
        usort($n, fn($a, $b) => $a['at'] <=> $b['at']);
        return $n;
    }

    /** "Halhjem til Sandvikvåg": the two quays without the word ferjekai. */
    public static function ferryName(array $plan, array $phase): string
    {
        $q = fn(int $i) => trim(str_replace('ferjekai', '', $plan['points'][$i]['name']));
        return $q($phase['a']) . ' til ' . $q($phase['b']);
    }

    /** Simulated time from the stored clock: frozen while paused, else running at the chosen rate. */
    public static function elapsed(float $atAnchor, float $anchor, int $rate, bool $paused, float $now, float $total = INF): float
    {
        return min($total, $paused ? $atAnchor : $atAnchor + max(0.0, $now - $anchor) * $rate);
    }

    /**
     * The storm scenario: the ferry legs and the road legs on either side get a storm, the legs around those rain.
     * A route without a ferry gets the storm on its longest road leg. Each changed leg says it is simulated.
     */
    public static function storm(array $pts): array
    {
        $n = count($pts);
        $storm = [];
        foreach ($pts as $i => $p) {
            if ($p['mode'] === 'ferje') {
                $storm[$i] = true;
                $storm[$i - 1] = $i - 1 > 0;
                if ($i + 1 < $n) {
                    $storm[$i + 1] = true;
                }
            }
        }
        if (!array_filter($storm)) {
            $long = 0;
            $best = -1.0;
            for ($i = 1; $i < $n; $i++) {
                $km = DemoDelivery::km($pts[$i - 1]['lat'], $pts[$i - 1]['lng'], $pts[$i]['lat'], $pts[$i]['lng']);
                if ($km > $best) {
                    [$best, $long] = [$km, $i];
                }
            }
            if ($long > 0) {
                $storm[$long] = true;
            }
        }
        foreach ($pts as $i => &$p) {
            if ($i === 0) {
                continue;
            }
            $near = !empty($storm[$i - 1]) || !empty($storm[$i + 1]);
            if (!empty($storm[$i])) {
                $p['wx'] = ['sky' => 'storm', 'wind' => $p['mode'] === 'ferje' ? 24 : 19, 'temp' => $p['wx']['temp'] ?? null, 'src' => 'Simulert storm', 'demo' => true, 'scenario' => true];
                if ($p['mode'] === 'ferje') {
                    $p['fx']['cancel'] = 'storm';
                }
            } elseif ($near) {
                $p['wx'] = ['sky' => 'regn', 'wind' => 12, 'temp' => $p['wx']['temp'] ?? null, 'src' => 'Simulert storm', 'demo' => true, 'scenario' => true];
            }
        }
        return $pts;
    }
}
