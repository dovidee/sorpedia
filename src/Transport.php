<?php
declare(strict_types=1);

/**
 * The Control Tower transport view (DELIVERY 5.9). Reads rows from a ShipmentStore and derives staleness, progress,
 * variance, weights and role-based redaction with ShipmentRules. Positions are never interpolated.
 */
final class Transport
{
    public function __construct(private ShipmentStore $store, private Clock $clock)
    {
    }

    /** Every shipment for the map, each with its staleness and the age of its latest position. */
    public function list(): array
    {
        $now = $this->clock->now();
        $rows = [];
        foreach ($this->store->all() as $r) {
            $at = $r['observed_at'] !== null ? (int) $r['observed_at'] : null;
            // Progress along the estimated route, the same figure the shipment inspector shows; null when there is no position
            $progress = ShipmentRules::progress($r['route'], $r['lat'] !== null ? (float) $r['lat'] : null, $r['lng'] !== null ? (float) $r['lng'] : null, $r['actual_arrival'] !== null);
            // A position from the simulation clock is the vehicle's place at the simulated time, never a feed that went quiet
            $sim = ($r['pos_source'] ?? null) === DemoDelivery::SOURCE;
            // A delivered shipment has stopped on purpose: its last position is its final place, never a stale one
            $rows[] = $r + ['stale' => !$sim && $r['actual_arrival'] === null && ShipmentRules::stale($at, $now), 'age_min' => ShipmentRules::ageMinutes($at, $now), 'progress' => $progress, 'sim' => $sim];
        }
        return ['rows' => $rows, 'now' => $now, 'stale_min' => ShipmentRules::STALE_MIN];
    }

    /** Shipments still on the way whose latest position is stale. */
    public function staleUnderway(): int
    {
        return count(array_filter($this->list()['rows'], fn($r) => $r['stale'] && ShipmentRules::underway($r['status'])));
    }

    /**
     * One shipment for the inspector with every figure the panel shows. Registration and driver phone only when
     * $restricted is true (admin, support).
     */
    public function one(int $id, bool $restricted): ?array
    {
        $f = $this->store->find($id);
        if ($f === null) {
            return null;
        }
        $s = $f['shipment'];
        [$vehicle, $driver] = ShipmentRules::redact($f['vehicle'], $f['driver'], $restricted);
        $last = $f['positions'] ? $f['positions'][count($f['positions']) - 1] : null;
        $lastAt = $last ? (int) $last['observed_at'] : null;
        $sim = $last !== null && $last['source'] === DemoDelivery::SOURCE;
        $num = fn($v) => $v === null ? null : (float) $v;
        $int = fn($v) => $v === null ? null : (int) $v;
        $now = $this->clock->now();
        return $s + [
            'vehicle' => $vehicle, 'driver' => $driver, 'items' => $f['items'], 'positions' => $f['positions'], 'route' => $f['route'],
            'weight' => ShipmentRules::weight($num($s['weight_declared_kg']), $num($s['weight_measured_kg']),
                array_column($f['items'], 'weight_kg'), $num($f['vehicle']['payload_kg'] ?? null)),
            'age_min' => ShipmentRules::ageMinutes($lastAt, $now),
            'stale' => !$sim && $s['actual_arrival'] === null && ShipmentRules::stale($lastAt, $now), 'sim' => $sim,
            'progress' => ShipmentRules::progress($f['route'], $last ? (float) $last['lat'] : null, $last ? (float) $last['lng'] : null, $s['actual_arrival'] !== null),
            'estimated' => ShipmentRules::estimated($f['route']),
            'variance' => ShipmentRules::variance($int($s['planned_arrival']), $int($s['actual_arrival']), $int($s['eta'])),
            'stale_min' => ShipmentRules::STALE_MIN,
        ];
    }
}
