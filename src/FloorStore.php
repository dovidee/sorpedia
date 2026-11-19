<?php
declare(strict_types=1);

/**
 * Where the Control Tower floor rows come from (DELIVERY 5.4 to 5.7). PdoFloorStore reads the database;
 * InMemoryFloorStore holds arrays, so ControlFloor can be checked without a database.
 * Every type filter is an explicit list; an empty list matches nothing. Timestamps are Unix seconds.
 */
interface FloorStore
{
    /** @return string[] distinct event types present */
    public function knownTypes(): array;

    /**
     * Events at or after $since with one of $types and an ID above $after, newest $limit of them, oldest first.
     * @param string[] $types
     * @return array{total: int, last: int, rows: list<array{id: int, type: string, data: mixed, at: int}>}
     *         total counts every match, last is the highest event ID in the store
     */
    public function events(int $since, array $types, int $after, int $limit): array;

    /**
     * Event counts per bucket at or after $since.
     * @param string[] $types
     * @return array<int, int> bucket start => count
     */
    public function counts(int $since, array $types, int $bucket): array;

    /**
     * Products with open work: active revisions, open reports, open tickets, or an inbound shipment on the way.
     * @return list<array{id: int, name: string, theme: ?string, revisions: int, reports: int, tickets: int, inbound: ?string}>
     */
    public function workProducts(int $limit): array;

    /** @return array{kvalitet: int, kundeservice: int} open items per station */
    public function backlog(): array;
}
