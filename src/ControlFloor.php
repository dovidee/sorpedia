<?php
declare(strict_types=1);

/**
 * The Control Tower floor (DELIVERY 5.4 to 5.7): which parcels are shown, where they go, the throughput chart and
 * congestion. Rows come from a FloorStore; the rules are in Floor. Nothing is generated to fill an empty period.
 */
final class ControlFloor
{
    /** Most parcels drawn at once. Totals always use full counts. */
    public const LIMIT = 400;

    public function __construct(private FloorStore $store, private Clock $clock)
    {
    }

    /** Every event type the panel can filter on: the mapped ones and any unknown type present. */
    public function knownTypes(): array
    {
        return array_values(array_unique(array_merge(array_keys(Floor::ROUTES), $this->store->knownTypes())));
    }

    /**
     * The event types a request selects. Both lists are explicit; a missing list selects nothing.
     * @return string[]
     */
    public function types(?string $stations, ?string $types): array
    {
        return Floor::select($stations, $types, $this->knownTypes());
    }

    /** Event parcels in the window, each with its stations and only the permitted payload fields. */
    public function events(string $window, int $after, array $types): array
    {
        $w = Floor::window($window);
        $r = $this->store->events(Floor::since($w, $this->clock->now()), $types, $after, self::LIMIT);
        $rows = array_map(fn($e) => ['id' => $e['id'], 'type' => $e['type'], 'route' => Floor::route($e['type']), 'at' => $e['at'],
            'data' => Floor::payload($e['data'])], $r['rows']);
        return ['window' => $w, 'total' => $r['total'], 'shown' => count($rows), 'last' => $r['last'], 'rows' => $rows];
    }

    /** Events per bucket from the start of the window to now, empty buckets included. */
    public function throughput(string $window, array $types): array
    {
        $now = $this->clock->now();
        $bucket = Floor::bucket($window);
        $since = Floor::since($window, $now);
        $counts = $this->store->counts($since, $types, $bucket);
        $points = [];
        for ($b = intdiv($since, $bucket) * $bucket; $b <= $now; $b += $bucket) {
            $points[] = [$b, $counts[$b] ?? 0];
        }
        return ['bucket' => $bucket, 'points' => $points];
    }

    /**
     * Product parcels at the named stations, each with the station it rests at.
     * @param string[] $stations
     */
    public function products(array $stations): array
    {
        $out = [];
        foreach ($this->store->workProducts(self::LIMIT) as $p) {
            $p['station'] = Floor::productStation($p['revisions'], $p['reports'], $p['tickets']);
            if (in_array($p['station'], $stations, true)) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** Open items per station and whether each is congested. */
    public function backlog(): array
    {
        $open = $this->store->backlog();
        return ['open' => $open, 'congested' => array_map(fn($n) => Floor::congested($n), $open)];
    }
}
