<?php
declare(strict_types=1);

/**
 * Control Tower floor rules (specs/022-control-tower, DELIVERY 5.4 to 5.7). Pure functions over plain values:
 * no database, no request, no session. Tower reads the rows and asks Floor where they belong.
 */
final class Floor
{
    /** Stations in floor order. The client positions them; the server only says where each parcel goes. */
    public const STATIONS = [
        'sok' => 'Søk',
        'avvik' => 'Avvik',
        'guide' => 'Guide',
        'byggelab' => 'Byggelab',
        'soren' => 'Søren',
        'kundeservice' => 'Kundeservice',
        'kvalitet' => 'Kvalitet',
        'handel' => 'Handel',
        'ukjent' => 'Ukjent',
        'mottak' => 'Mottak',
    ];

    /** Event type => stations the parcel passes, in order. Producers: track() calls in public/index.php. */
    public const ROUTES = [
        'search' => ['sok'],
        'compare' => ['sok'],
        'search_zero' => ['sok', 'avvik'],
        'injection_blocked' => ['avvik'],
        'guide_done' => ['guide'],
        'guide_feedback' => ['guide'],
        'manual_view' => ['guide'],
        'lab_test' => ['byggelab'],
        'lab_cart' => ['handel'],
        'chat' => ['soren'],
        'escalate' => ['soren', 'kundeservice'],
        'spec_report' => ['kvalitet'],
        'add_to_cart' => ['handel'],
        'purchase' => ['handel'],
    ];

    /** Time windows the panel offers. since() gives the lower bound of each. */
    public const WINDOWS = ['time', 'dag', '7d', '30d'];

    /** Payload fields shown to staff. Session and user are never sent (track() stores no user ID). */
    public const FIELDS = ['q', 'n', 'product', 'ticket', 'ids', 'key', 'via', 'manual', 'page', 'found', 'llm', 'intent', 'lang', 'field', 'order', 'lines', 'wanted', 'builder', 'verdict'];

    /** Open items at one station before it is flagged as congested. */
    public const CONGESTED = 5;

    /** A window name from a request; anything else is today. */
    public static function window(?string $w): string
    {
        return in_array($w, self::WINDOWS, true) ? $w : 'dag';
    }

    /** Lower bound of a window as a Unix timestamp: the last hour, today since midnight, 7 or 30 days back. */
    public static function since(string $window, int $now): int
    {
        return match (self::window($window)) {
            'time' => $now - 3600,
            '7d' => $now - 7 * 86400,
            '30d' => $now - 30 * 86400,
            default => (int) strtotime('today', $now),
        };
    }

    /** Chart bucket in seconds for a window. */
    public static function bucket(string $window): int
    {
        return match (self::window($window)) { 'time' => 300, 'dag' => 3600, default => 86400 };
    }

    /** Stations an event type passes. Unknown types go to Ukjent and are never dropped. */
    public static function route(string $type): array
    {
        return self::ROUTES[$type] ?? ['ukjent'];
    }

    /**
     * Station keys from a request ("sok,avvik"). Only names in STATIONS are kept.
     * @return string[]
     */
    public static function stations(?string $csv): array
    {
        return array_values(array_intersect(explode(',', (string) $csv), array_keys(self::STATIONS)));
    }

    /**
     * The event types that end at the given stations, as an explicit list. Unknown types (in $known but not in
     * ROUTES) end at Ukjent.
     * @param string[] $stations
     * @param string[] $known every type present, mapped or not
     * @return string[]
     */
    public static function typesEndingAt(array $stations, array $known): array
    {
        $types = [];
        foreach (array_unique(array_merge(array_keys(self::ROUTES), $known)) as $type) {
            $route = self::route($type);
            if (in_array(end($route), $stations, true)) {
                $types[] = $type;
            }
        }
        return $types;
    }

    /**
     * The event types a request asks for: ending at the named stations AND named in the type list.
     * Both lists are sent explicitly by the client; a missing list selects nothing.
     * @param string[] $known
     * @return string[]
     */
    public static function select(?string $stations, ?string $types, array $known): array
    {
        $named = array_intersect(explode(',', (string) $types), $known);
        return array_values(array_intersect(self::typesEndingAt(self::stations($stations), $known), $named));
    }

    /** Only the permitted payload fields of an event. */
    public static function payload(mixed $data): array
    {
        return is_array($data) ? array_intersect_key($data, array_flip(self::FIELDS)) : [];
    }

    /** Where a product parcel rests (DELIVERY 5.5): Kvalitet with open revisions or reports, Kundeservice with an open ticket, else Mottak. */
    public static function productStation(int $revisions, int $reports, int $tickets): string
    {
        return $revisions + $reports > 0 ? 'kvalitet' : ($tickets > 0 ? 'kundeservice' : 'mottak');
    }

    /** Whether a station's open items exceed the congestion threshold. */
    public static function congested(int $open): bool
    {
        return $open > self::CONGESTED;
    }
}
