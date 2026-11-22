<?php
declare(strict_types=1);

// Ferry status on the transport map (feature 022): live data decides, the timetable is never shown as live. Pure: no database.

$t = 1_800_000_000;
$call = fn(int $late = 0, bool $live = true, bool $cancelled = false) => ['aimed' => $t, 'expected' => $t + $late * 60, 'live' => $live, 'cancelled' => $cancelled];

return [
    'nothing known gives unknown, not normal' => fn() => check(Ferries::status([], []) === ['unknown', null], 'empty gave ' . json_encode(Ferries::status([], []))),
    'timetable only is never called normal' => fn() => check(Ferries::status([$call(0, false), $call(0, false)], [])[0] === 'scheduled', 'timetable shown as live'),
    'live and on time is normal' => fn() => check(Ferries::status([$call(), $call(2)], []) === ['normal', null], 'live on time not normal'),
    'a live delay from five minutes makes the crossing delayed' => function () use ($call) {
        check(Ferries::status([$call(), $call(7)], []) === ['delayed', 7], 'seven minutes late not delayed');
        check(Ferries::status([$call(4)], [])[0] === 'normal', 'four minutes counted as delayed');
    },
    'an operator message wins over on time' => fn() => check(Ferries::status([$call()], ['Avgangen er innstilt.'])[0] === 'disrupted', 'message ignored'),
    'a live cancellation wins over everything' => fn() => check(Ferries::status([$call(9), $call(0, true, true)], ['Melding'])[0] === 'cancelled', 'cancellation not shown'),
    'a cancellation without live data is not trusted' => fn() => check(Ferries::status([$call(0, false, true)], [])[0] === 'scheduled', 'timetable cancellation shown as live'),
    'only routes past both quays get the crossing' => function () {
        $c = Ferries::CROSSINGS['mortavika-arsvagen'];
        $past = ['id' => 1, 'route' => [[59.20, 5.40], [59.10, 5.65]]];
        $near = ['id' => 2, 'route' => [[59.20, 5.40], [59.30, 5.30]]];
        check(Ferries::ships($c, [$past, $near]) === [1], 'got ' . json_encode(Ferries::ships($c, [$past, $near])));
    },
];
