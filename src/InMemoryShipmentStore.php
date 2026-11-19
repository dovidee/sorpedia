<?php
declare(strict_types=1);

/**
 * Transport rows held in memory, for checks without a database. Each entry is a find() result (see ShipmentStore),
 * keyed by shipment ID; all() is derived from them the same way the database query derives it.
 */
final class InMemoryShipmentStore implements ShipmentStore
{
    /** @param array<int, array> $shipments */
    public function __construct(private array $shipments)
    {
    }

    public function all(): array
    {
        $rows = [];
        foreach ($this->shipments as $f) {
            $s = $f['shipment'];
            $last = $f['positions'] ? $f['positions'][count($f['positions']) - 1] : null;
            $rows[] = [
                'id' => $s['id'], 'ref' => $s['ref'], 'direction' => $s['direction'], 'status' => $s['status'],
                'origin_city' => $s['origin_city'], 'dest_city' => $s['dest_city'], 'is_demo' => $s['is_demo'],
                'eta' => $s['eta'], 'planned_arrival' => $s['planned_arrival'], 'actual_arrival' => $s['actual_arrival'],
                'fleet_no' => $f['vehicle']['fleet_no'] ?? null, 'vehicle_type' => $f['vehicle']['type'] ?? null,
                'lat' => $last['lat'] ?? null, 'lng' => $last['lng'] ?? null, 'heading' => $last['heading'] ?? null,
                'observed_at' => $last['observed_at'] ?? null, 'route' => $f['route'],
            ];
        }
        usort($rows, fn($a, $b) => [$a['status'] === 'levert', $a['eta']] <=> [$b['status'] === 'levert', $b['eta']]);
        return $rows;
    }

    public function find(int $id): ?array
    {
        return $this->shipments[$id] ?? null;
    }
}
