<?php
declare(strict_types=1);

// The transport simulation (features 032 and 043): routes through the road network, one clock for movement, ferries,
// weather, delays and notifications, and who can fetch what. Pure: no database.

$t0 = (float) strtotime('2026-11-21 08:00:00 Europe/Oslo');
$kr = Roads::NODES['Kristiansand'];
$route = fn(string $from) => Roads::route([[$from, ...Roads::NODES[$from], ...['start']], ['Sør-Tre Kristiansand', $kr[0], $kr[1], 'mal']]);
// Fixed conditions instead of the stored ones: clear weather, a timetable every 30 minutes, no road works
$calm = function (array $pts): array {
    foreach ($pts as $i => &$p) {
        $p['dwell'] = $p['kind'] === 'hent' ? 1200 : 0;
        if ($i > 0) {
            $p['wx'] = ['sky' => 'sol', 'wind' => 3, 'temp' => 4, 'src' => 'test', 'demo' => false];
            $p['works'] = [];
            if ($p['mode'] === 'ferje') {
                $p['fx'] = ['cross' => 1800, 'every' => 1800, 'offset' => 0, 'cancel' => false, 'msg' => [], 'src' => 'test'];
            }
        }
    }
    return $pts;
};
$bergen = $calm($route('Bergen'));
$storm = DemoSim::storm($bergen);
$full = DemoSim::plan($storm, $t0);
$booked = DemoSim::plan($storm, $t0, []);

return [
    'the west coast route takes both E39 ferries, the east and the inland routes none' => function () use ($route) {
        $ferries = fn(array $pts) => array_values(array_column(array_filter($pts, fn($p) => $p['mode'] === 'ferje'), 'ferry'));
        check($ferries($route('Bergen')) === ['halhjem-sandvikvag', 'mortavika-arsvagen'], 'Bergen to Kristiansand crosses Bjørnafjorden and Boknafjorden');
        check($ferries($route('Oslo')) === [] && $ferries($route('Trondheim')) === [] && $ferries($route('Stavanger')) === [], 'Oslo, Trondheim and Stavanger drive without a ferry');
        $last = $route('Oslo')[count($route('Oslo')) - 1];
        check($last['name'] === 'Sør-Tre Kristiansand' && $last['kind'] === 'mal', 'the route ends at the receiving store');
    },
    'a point away from the network gets its own local road leg' => function () {
        $pts = Roads::route([['Hytta', 58.5, 7.4, 'start'], ['Mandal', ...Roads::NODES['Mandal'], ...['mal']]]);
        check($pts[0]['name'] === 'Hytta' && $pts[1]['road'] === 'Lokal vei', 'start, then the local road to the nearest node');
    },
    'the phases are continuous in time' => function () use ($full) {
        $t = 0.0;
        foreach ($full['phases'] as $p) {
            check(abs($p['from'] - $t) < 1e-6 && $p['to'] >= $p['from'], 'each phase starts where the last ended');
            $t = $p['to'];
        }
        check(abs($t - $full['total']) < 1e-6, 'the last phase ends at the total');
    },
    'the storm delays the journey and only the storm' => function () use ($full, $booked, $bergen, $t0) {
        check($full['total'] > $booked['total'], 'the stormy plan arrives later than the booked one');
        check(!array_filter($booked['phases'], fn($p) => $p['cancelled']), 'the booked plan cancels nothing');
        check((bool) array_filter($full['phases'], fn($p) => $p['cancelled']), 'the storm cancels a departure');
        $calmPlan = DemoSim::plan($bergen, $t0);
        check(abs($calmPlan['total'] - DemoSim::plan($bergen, $t0, [])['total']) < 1e-6, 'without events the plan is the booked plan');
        check(count(array_filter(array_column($full['points'], 'wx'), fn($w) => ($w['src'] ?? '') === 'Simulert storm')) > 0, 'the storm says it is simulated');
    },
    'the ETA changes only when news arrives' => function () use ($full, $booked, $storm, $t0) {
        check(DemoSim::knownBy($full, -1) === [], 'nothing is known before departure');
        $first = min(DemoSim::known($full));
        $before = DemoSim::plan($storm, $t0, DemoSim::knownBy($full, $first - 1));
        check(abs($before['total'] - $booked['total']) < 1e-6, 'before the first warning the ETA is the booked one');
        $end = DemoSim::plan($storm, $t0, DemoSim::knownBy($full, $full['total']));
        check(abs($end['total'] - $full['total']) < 1e-6, 'by arrival every event is known and the ETA is the real one');
    },
    'a crossing moves the van over the water, waiting keeps it at the quay' => function () use ($full) {
        $ferry = array_values(array_filter($full['phases'], fn($p) => $p['kind'] === 'ferje'))[0];
        $mid = DemoSim::at($full, ($ferry['from'] + $ferry['to']) / 2);
        check($mid['kind'] === 'ferje', 'halfway through the crossing the van is on the ferry');
        check(abs($mid['lat'] - $full['points'][$ferry['a']]['lat']) > 0.01, 'the van has left the quay');
        $wait = array_values(array_filter($full['phases'], fn($p) => $p['kind'] === 'venter'))[0];
        $w = DemoSim::at($full, ($wait['from'] + $wait['to']) / 2);
        check($w['kind'] === 'venter' && abs($w['lat'] - $full['points'][$wait['a']]['lat']) < 1e-9, 'waiting stands still at the quay');
    },
    'a pick-up stop holds the van while it loads' => function () use ($calm, $kr, $t0) {
        $pts = $calm(Roads::route([['Bergen', ...Roads::NODES['Bergen'], ...['start']], ['Sør-Tre Stavanger', ...Roads::NODES['Stavanger'], ...['hent']], ['Sør-Tre Kristiansand', $kr[0], $kr[1], 'mal']]));
        $plan = DemoSim::plan($pts, $t0);
        $load = array_values(array_filter($plan['phases'], fn($p) => $p['kind'] === 'laster'));
        check(count($load) === 1 && abs($load[0]['to'] - $load[0]['from'] - 1200) < 1e-6, 'twenty minutes loading at the store');
        $at = DemoSim::at($plan, ($load[0]['from'] + $load[0]['to']) / 2);
        check(abs($at['lat'] - Roads::NODES['Stavanger'][0]) < 1e-9, 'the van stands at the store while loading');
    },
    'notifications come in time order and end with the delivery' => function () use ($full, $booked) {
        $n = DemoSim::notes($full, $booked);
        $at = array_column($n, 'at');
        $sorted = $at;
        sort($sorted);
        check($at === $sorted, 'sorted by simulated time');
        check($n[0]['key'] === 'avgang' && end($n)['key'] === 'levert', 'first the departure, last the delivery');
        check(in_array('forsinket', array_column($n, 'key'), true) && in_array('innstilt', array_column($n, 'key'), true), 'the cancellation and the delay are notified');
    },
    'departures follow the timetable interval' => function () {
        check(DemoSim::nextDeparture(1000.0, ['every' => 600, 'offset' => 0]) === 1200.0, 'the next one on the interval');
        check(DemoSim::nextDeparture(1000.0, ['every' => 600, 'offset' => 300]) === 1500.0, 'shifted by the timetable offset');
        check(DemoSim::nextDeparture(1200.0, ['every' => 600, 'offset' => 0]) === 1200.0, 'a departure right now is taken');
    },
    'the clock pauses and runs at the chosen rate' => function () {
        check(DemoSim::elapsed(100.0, 1000.0, 240, false, 1010.0) === 2500.0, '10 real seconds at x240 are 2400 simulated seconds');
        check(DemoSim::elapsed(100.0, 1000.0, 240, true, 1010.0) === 100.0, 'paused time stands still');
    },
    'a road message counts only for the road it names' => function () {
        check(DemoDelivery::names('E39 Håtong - Bukkatøkje, Bokn: Vegarbeid', 'E39'), 'E39 on E39');
        check(!DemoDelivery::names('E390 Somewhere: Vegarbeid', 'E39') && !DemoDelivery::names('Fv. 456 Kjos: stengt', 'E39'), 'not another road');
        check(DemoDelivery::names('Fv. 44 Hellevik - Sannernes', 'Fv. 44') && !DemoDelivery::names('Fv. 4534 Lagerveien', 'Fv. 44'), 'county roads by their full number');
    },
    'each pick goes to the vehicle with the nearest store that has it' => function () {
        $pick = fn(int $id, array $stock) => ['id' => $id, 'product' => 100 + $id, 'name' => "P$id", 'img' => null, 'qty' => 2, 'stock' => $stock];
        $picks = [$pick(1, [['id' => 3, 'name' => 'Bergen', 'qty' => 5]]), $pick(2, [['id' => 7, 'name' => 'Arendal', 'qty' => 1], ['id' => 4, 'name' => 'Stavanger', 'qty' => 9]]),
            $pick(3, [['id' => 6, 'name' => 'Trondheim', 'qty' => 4]])];
        $veh = fn(int $id, ?int $busy, array $near) => ['id' => $id, 'busy' => $busy, 'near' => $near];
        $vehicles = [$veh(2, null, [['id' => 3, 'km' => 0.0]]), $veh(3, null, [['id' => 4, 'km' => 0.0]]), $veh(4, null, [['id' => 7, 'km' => 0.0]]), $veh(5, 9, [['id' => 6, 'km' => 0.0]])];
        $a = Supply::assign($picks, $vehicles);
        check(array_column($a['vehicles'][2], 'pick') === [1], 'Bergen goes with the Bergen vehicle');
        check(count($a['options'][2]) === 2, 'two vehicles could fetch the second pick');
        check(($a['vehicles'][3][0]['pick'] ?? null) === 2 || ($a['vehicles'][4][0]['qty'] ?? null) === 1, 'the second pick goes to one vehicle only');
        check(array_column($a['uncovered'], 'pick') === [3], 'a busy vehicle fetches nothing, so Trondheim is uncovered');
        $only = Supply::assign($picks, $vehicles, [2]);
        check(array_keys($only['vehicles']) === [2] && count($only['uncovered']) === 2, 'a deselected vehicle takes nothing');
    },
];
