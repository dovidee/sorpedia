<?php
declare(strict_types=1);

// Statistics behind the Control Tower insight view (feature 022). Pure functions: no database, no clock.

$near = fn(float $a, float $b, float $eps = 1e-9) => abs($a - $b) < $eps;

return [
    'describe gives mean, median, sample deviation and quartiles' => function () use ($near) {
        $d = Stats::describe([4, 1, 3, 2, 5]);
        check($d['n'] === 5 && $near($d['mean'], 3.0) && $near($d['median'], 3.0), 'mean or median wrong');
        check($near($d['sd'], sqrt(2.5)), 'sample standard deviation divides by n - 1');
        check($near($d['q1'], 2.0) && $near($d['q3'], 4.0), 'quartiles interpolate between ranks');
        check(Stats::describe([])['mean'] === null, 'empty input gives null, not zero');
    },
    'histogram uses Sturges bins and counts every value once' => function () {
        $xs = range(1, 16);
        $bins = Stats::histogram($xs);
        check(count($bins) === 5, 'Sturges: ceil(log2 16) + 1 = 5 bins');
        check(array_sum(array_column($bins, 'n')) === 16, 'every value lands in one bin, the maximum included');
        check(count(Stats::histogram([7, 7, 7])) >= 1, 'equal values do not divide by zero');
    },
    'a straight line is fitted exactly' => function () use ($near) {
        $f = Stats::linear([1, 3, 5, 7, 9]);
        check($near($f['a'], 1.0) && $near($f['b'], 2.0), 'intercept or slope wrong');
        check($near($f['r2'], 1.0) && $near($f['se'], 0.0), 'a perfect fit has R² 1 and no residual error');
    },
    'the forecast is withheld below the minimum number of days' => function () {
        $f = Stats::forecast(array_fill(0, Stats::MIN_POINTS - 1, 3));
        check($f['ok'] === false && $f['have'] === Stats::MIN_POINTS - 1, 'too few points must not give a forecast');
    },
    'the forecast continues the trend, never below zero, and reports the holdout error' => function () use ($near) {
        $ys = range(0, 20); // y = x
        $f = Stats::forecast($ys, 7);
        check($f['ok'] && count($f['points']) === 7, 'seven days ahead');
        check($near($f['points'][0]['y'], 21.0, 1e-6), 'next value of y = x is 21');
        check($near($f['mae'], 0.0, 1e-6), 'a linear series has no holdout error');
        $down = Stats::forecast(range(20, 0, -1), 7);
        foreach ($down['points'] as $p) {
            check($p['y'] >= 0 && $p['lo'] >= 0, 'counts are never forecast below zero');
        }
    },
];
