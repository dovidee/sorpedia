<?php
declare(strict_types=1);

/** Floor rows held in memory, for checks without a database. Same results as PdoFloorStore for the same rows. */
final class InMemoryFloorStore implements FloorStore
{
    /**
     * @param list<array{id: int, type: string, data: mixed, at: int}> $events
     * @param list<array{id: int, name: string, theme: ?string, revisions: int, reports: int, tickets: int, inbound: ?string}> $products products with open work
     * @param array{kvalitet: int, kundeservice: int} $backlog
     */
    public function __construct(private array $events = [], private array $products = [], private array $backlog = ['kvalitet' => 0, 'kundeservice' => 0])
    {
    }

    public function knownTypes(): array
    {
        $types = array_values(array_unique(array_column($this->events, 'type')));
        sort($types);
        return $types;
    }

    /** @param string[] $types */
    private function matching(int $since, array $types, int $after): array
    {
        $rows = array_filter($this->events, fn($e) => $e['at'] >= $since && $e['id'] > $after && in_array($e['type'], $types, true));
        usort($rows, fn($a, $b) => $a['id'] <=> $b['id']);
        return $rows;
    }

    public function events(int $since, array $types, int $after, int $limit): array
    {
        $rows = $this->matching($since, $types, $after);
        $ids = array_column($this->events, 'id');
        return ['total' => count($rows), 'last' => $ids ? max($ids) : 0, 'rows' => $limit > 0 ? array_slice($rows, -$limit) : []];
    }

    public function counts(int $since, array $types, int $bucket): array
    {
        $bucket = max(1, $bucket);
        $out = [];
        foreach ($this->matching($since, $types, 0) as $e) {
            $b = intdiv($e['at'], $bucket) * $bucket;
            $out[$b] = ($out[$b] ?? 0) + 1;
        }
        ksort($out);
        return $out;
    }

    public function workProducts(int $limit): array
    {
        $rows = $this->products;
        usort($rows, fn($a, $b) => $a['id'] <=> $b['id']);
        return array_slice($rows, 0, max(0, $limit));
    }

    public function backlog(): array
    {
        return $this->backlog;
    }
}
