<?php
declare(strict_types=1);

/**
 * Descriptive statistics and a linear trend forecast for the Control Tower insight view.
 * Pure functions over plain numbers: no database, no clock.
 */
final class Stats
{
    /** Fewest observations a trend is fitted on. Below this the forecast is withheld, not guessed. */
    public const MIN_POINTS = 14;

    /**
     * Mean, median, standard deviation (sample), quartiles, min and max. Empty input gives nulls.
     * @param array<int|float> $xs
     * @return array{n: int, mean: ?float, median: ?float, sd: ?float, q1: ?float, q3: ?float, min: ?float, max: ?float}
     */
    public static function describe(array $xs): array
    {
        $n = count($xs);
        if (!$n) {
            return ['n' => 0, 'mean' => null, 'median' => null, 'sd' => null, 'q1' => null, 'q3' => null, 'min' => null, 'max' => null];
        }
        sort($xs);
        $mean = array_sum($xs) / $n;
        $sd = $n > 1 ? sqrt(array_sum(array_map(fn($x) => ($x - $mean) ** 2, $xs)) / ($n - 1)) : 0.0;
        return ['n' => $n, 'mean' => $mean, 'median' => self::quantile($xs, .5), 'sd' => $sd,
            'q1' => self::quantile($xs, .25), 'q3' => self::quantile($xs, .75), 'min' => (float) $xs[0], 'max' => (float) $xs[$n - 1]];
    }

    /** Quantile of a sorted list by linear interpolation between closest ranks. */
    public static function quantile(array $sorted, float $p): float
    {
        $h = (count($sorted) - 1) * $p;
        $lo = (int) floor($h);
        $hi = min($lo + 1, count($sorted) - 1);
        return $sorted[$lo] + ($h - $lo) * ($sorted[$hi] - $sorted[$lo]);
    }

    /**
     * Histogram with Sturges' rule for the number of bins (ceil(log2 n) + 1), equal widths from min to max.
     * @param array<int|float> $xs
     * @return list<array{from: float, to: float, n: int}>
     */
    public static function histogram(array $xs): array
    {
        $n = count($xs);
        if (!$n) {
            return [];
        }
        $lo = min($xs);
        $hi = max($xs);
        $k = max(1, (int) ceil(log($n, 2)) + 1);
        $w = $hi > $lo ? ($hi - $lo) / $k : 1;
        $bins = [];
        for ($i = 0; $i < $k; $i++) {
            $bins[] = ['from' => $lo + $i * $w, 'to' => $lo + ($i + 1) * $w, 'n' => 0];
        }
        foreach ($xs as $x) {
            $bins[min($k - 1, (int) floor(($x - $lo) / $w))]['n']++;
        }
        return $bins;
    }

    /**
     * Ordinary least squares fit y = a + b * x over x = 0..n-1.
     * @param array<int|float> $ys
     * @return array{a: float, b: float, r2: float, se: float}
     */
    public static function linear(array $ys): array
    {
        $ys = array_values($ys);
        $n = count($ys);
        $mx = ($n - 1) / 2;
        $my = $n ? array_sum($ys) / $n : 0.0;
        $sxx = 0.0;
        $sxy = 0.0;
        foreach ($ys as $x => $y) {
            $sxx += ($x - $mx) ** 2;
            $sxy += ($x - $mx) * ($y - $my);
        }
        $b = $sxx > 0 ? $sxy / $sxx : 0.0;
        $a = $my - $b * $mx;
        $sse = 0.0;
        $sst = 0.0;
        foreach ($ys as $x => $y) {
            $sse += ($y - ($a + $b * $x)) ** 2;
            $sst += ($y - $my) ** 2;
        }
        return ['a' => $a, 'b' => $b, 'r2' => $sst > 0 ? 1 - $sse / $sst : 0.0, 'se' => $n > 2 ? sqrt($sse / ($n - 2)) : 0.0];
    }

    /**
     * Linear trend forecast for the next $ahead points with a 95 % band (plus and minus 1.96 residual standard errors,
     * never below zero), and a holdout check: the trend is refitted without the last $ahead points and its mean absolute
     * error on those points is reported. With fewer than MIN_POINTS observations nothing is forecast.
     * @param array<int|float> $ys
     * @return array{ok: bool, need: int, have: int, fit?: array, points?: list<array{y: float, lo: float, hi: float}>, mae?: ?float}
     */
    public static function forecast(array $ys, int $ahead = 7): array
    {
        $ys = array_values($ys);
        $n = count($ys);
        if ($n < self::MIN_POINTS) {
            return ['ok' => false, 'need' => self::MIN_POINTS, 'have' => $n];
        }
        $fit = self::linear($ys);
        $points = [];
        for ($i = 0; $i < $ahead; $i++) {
            $y = $fit['a'] + $fit['b'] * ($n + $i);
            $points[] = ['y' => max(0.0, $y), 'lo' => max(0.0, $y - 1.96 * $fit['se']), 'hi' => max(0.0, $y + 1.96 * $fit['se'])];
        }
        $mae = null;
        if ($n - $ahead >= self::MIN_POINTS) {
            $train = self::linear(array_slice($ys, 0, $n - $ahead));
            $err = 0.0;
            for ($i = $n - $ahead; $i < $n; $i++) {
                $err += abs($ys[$i] - max(0.0, $train['a'] + $train['b'] * $i));
            }
            $mae = $err / $ahead;
        }
        return ['ok' => true, 'need' => self::MIN_POINTS, 'have' => $n, 'fit' => $fit, 'points' => $points, 'mae' => $mae];
    }
}
