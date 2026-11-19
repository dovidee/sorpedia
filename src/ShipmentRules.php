<?php
declare(strict_types=1);

/**
 * Transport rules for the Control Tower map (DELIVERY 5.9). Pure functions over plain values: no database.
 * Positions are never interpolated: every figure here is derived from stored rows only.
 */
final class ShipmentRules
{
    /** Shipment statuses that are still on the way (not delivered, not cancelled). */
    public const UNDERWAY = ['ikke_startet', 'lastes', 'underveis', 'stoppet', 'losses', 'forsinket', 'ukjent'];

    /** A position older than this many minutes is stale. */
    public const STALE_MIN = 15;

    /** Item weights and the shipment total may differ by this much before it is flagged. */
    public const MISMATCH_KG = 0.5;

    /** Approximate latitude of the map, used to scale longitude for distances along the route. */
    private const LAT_SCALE = 63.0;

    public static function underway(string $status): bool
    {
        return in_array($status, self::UNDERWAY, true);
    }

    /** Minutes since the position was observed, or null without a position. */
    public static function ageMinutes(?int $observedAt, int $now): ?int
    {
        return $observedAt === null ? null : (int) round(max(0, $now - $observedAt) / 60);
    }

    public static function stale(?int $observedAt, int $now): bool
    {
        return $observedAt !== null && $now - $observedAt > self::STALE_MIN * 60;
    }

    /**
     * Weight block. Item weights are summed only when every item has one; the total is compared, never adjusted.
     * Remaining payload is calculated from the vehicle payload and the measured (else declared) load.
     * @param array<float|int|string|null> $itemKgs one entry per item, null when unknown
     */
    public static function weight(?float $declared, ?float $measured, array $itemKgs, ?float $payload): array
    {
        $known = array_filter($itemKgs, fn($k) => $k !== null);
        $items = $itemKgs && count($known) === count($itemKgs) ? (float) array_sum($known) : null;
        $total = $measured ?? $declared;
        return [
            'declared' => $declared, 'measured' => $measured, 'items' => $items, 'payload' => $payload,
            'mismatch' => $items !== null && $total !== null && abs($items - $total) > self::MISMATCH_KG,
            'remaining' => $payload !== null && $total !== null ? $payload - $total : null,
        ];
    }

    /**
     * Arrival variance in seconds: actual minus planned when delivered, else estimate minus planned. Null when unknown.
     */
    public static function variance(?int $planned, ?int $actual, ?int $eta): ?int
    {
        if ($planned === null) {
            return null;
        }
        return $actual !== null ? $actual - $planned : ($eta !== null ? $eta - $planned : null);
    }

    /**
     * Share of the route behind the latest position (0 to 1), from the nearest point on the route line.
     * 1 when delivered; null without a route or a position.
     * @param array<array{0: float, 1: float}> $route [lat, lng] points in order
     */
    public static function progress(array $route, ?float $lat, ?float $lng, bool $delivered): ?float
    {
        if ($delivered) {
            return 1.0;
        }
        if (count($route) < 2 || $lat === null || $lng === null) {
            return null;
        }
        $k = cos(deg2rad(self::LAT_SCALE));
        $p = array_map(fn($r) => [(float) $r[1] * $k, (float) $r[0]], $route);
        $px = $lng * $k;
        $total = $acc = $at = 0.0;
        $best = INF;
        for ($i = 1, $n = count($p); $i < $n; $i++) {
            [$ax, $ay] = $p[$i - 1];
            $dx = $p[$i][0] - $ax;
            $dy = $p[$i][1] - $ay;
            $len = hypot($dx, $dy);
            $t = $len > 0 ? max(0.0, min(1.0, (($px - $ax) * $dx + ($lat - $ay) * $dy) / ($dx * $dx + $dy * $dy))) : 0.0;
            $d = hypot($ax + $dx * $t - $px, $ay + $dy * $t - $lat);
            if ($d < $best) {
                $best = $d;
                $at = $acc + $len * $t;
            }
            $acc += $len;
            $total += $len;
        }
        return $total > 0 ? $at / $total : null;
    }

    /** True when any route point is an estimate rather than a source point. */
    public static function estimated(array $route): bool
    {
        foreach ($route as $r) {
            if (($r[2] ?? null) === 'estimert') {
                return true;
            }
        }
        return false;
    }

    /**
     * Registration and driver phone only for roles allowed to see them (admin, support).
     * @return array{0: ?array, 1: ?array}
     */
    public static function redact(?array $vehicle, ?array $driver, bool $restricted): array
    {
        if (!$restricted) {
            if ($vehicle !== null) {
                unset($vehicle['registration']);
            }
            if ($driver !== null) {
                unset($driver['phone']);
            }
        }
        return [$vehicle, $driver];
    }
}
