<?php
declare(strict_types=1);

// Control Tower rules (feature 022). Every check here uses in-memory stores and a fixed clock: no database is opened.

$at = 1_800_000_000; // a fixed "now" for every time-based rule

$event = fn(int $id, string $type, int $t, array $data = []) => ['id' => $id, 'type' => $type, 'data' => $data, 'at' => $t];

$shipment = function (int $id, string $status, array $over = []) use ($at): array {
    return array_replace_recursive([
        'shipment' => [
            'id' => $id, 'ref' => "S-$id", 'direction' => 'inn', 'status' => $status, 'origin_city' => 'Oslo', 'origin_point' => null,
            'dest_city' => 'Kristiansand', 'dest_point' => null, 'container_ref' => null, 'container_type' => null, 'eta_source' => null,
            'weight_declared_kg' => 1000, 'weight_measured_kg' => null, 'delay_reason' => null, 'is_demo' => 1, 'warehouse' => null,
            'planned_departure' => null, 'actual_departure' => null, 'eta' => $at + 3600, 'eta_calculated_at' => null,
            'planned_arrival' => $at + 1800, 'actual_arrival' => null,
        ],
        'vehicle' => ['fleet_no' => "V-$id", 'type' => 'lastebil', 'make' => null, 'model' => null, 'model_year' => null,
            'capacity_kg' => 18000, 'payload_kg' => 9000, 'registration' => 'AB 12345', 'is_demo' => 1],
        'driver' => ['alias' => 'Sjåfør 1', 'driver_ref' => 'D-1', 'phone' => '+47 400 00 000', 'is_demo' => 1],
        'items' => [
            ['product_id' => 1, 'name' => 'Vare A', 'qty' => 2, 'weight_kg' => 600.0, 'weight_basis' => 'oppgitt', 'handling_unit' => null],
            ['product_id' => 2, 'name' => 'Vare B', 'qty' => 1, 'weight_kg' => 400.0, 'weight_basis' => 'oppgitt', 'handling_unit' => null],
        ],
        'positions' => [['lat' => 60.0, 'lng' => 10.0, 'heading' => 180, 'observed_at' => $at - 300, 'received_at' => $at - 290, 'source' => 'demo']],
        'route' => [[59.0, 10.0, 'kilde'], [61.0, 10.0, 'kilde']],
    ], $over);
};

// Draft sources: ticket 5 and report 3 are open; everything else is closed or missing
$draftStore = new class implements DraftStore {
    public function ticket(int $id): ?array
    {
        return $id === 5 ? ['id' => 5, 'summary' => 'Levering', 'chat' => [['role' => 'user', 'content' => 'Når kommer sagen?']],
            'thread' => [], 'facts' => 'Produkt: Sag'] : null;
    }

    public function report(int $id): ?array
    {
        return $id === 3 ? ['label' => 'Vekt', 'unit' => 'kg', 'reports' => [['reason' => 'Feil vekt', 'value_text' => '4']], 'facts' => 'Produkt: Sag'] : null;
    }
};

// A model that always gives the same answer and records what it was asked
$model = fn(string $text, bool $answered, bool $cutShort) => new class ($text, $answered, $cutShort) implements DraftModel {
    public string $asked = '';
    public int $calls = 0;

    public function __construct(private string $text, private bool $answered, private bool $cutShort)
    {
    }

    public function answer(string $system, string $user): array
    {
        $this->calls++;
        $this->asked = $user;
        return ['text' => $this->text, 'answered' => $this->answered, 'cutShort' => $this->cutShort];
    }
};

return [
    // Floor: pure rules
    'an unknown window falls back to today, and today starts at midnight' => function () use ($at) {
        check(Floor::window('aar') === 'dag', 'unknown window is not today');
        check(Floor::since('dag', $at) === (int) strtotime('today', $at), 'today does not start at midnight');
        check(Floor::since('time', $at) === $at - 3600, 'last hour is wrong');
        check(Floor::bucket('7d') === 86400 && Floor::bucket('time') === 300, 'bucket sizes are wrong');
    },
    'unknown event types go to Ukjent and are never dropped' => function () {
        check(Floor::route('ny_type') === ['ukjent'], 'unknown type has a route');
        $known = [...array_keys(Floor::ROUTES), 'ny_type'];
        check(Floor::select('ukjent', 'ny_type', $known) === ['ny_type'], 'unknown type is not selectable at Ukjent');
    },
    'a missing station or type list selects nothing' => function () {
        $known = array_keys(Floor::ROUTES);
        check(Floor::select(null, 'search', $known) === [], 'no stations still selected types');
        check(Floor::select('sok', null, $known) === [], 'no types still selected types');
        check(Floor::select('sok', 'search,purchase', $known) === ['search'], 'types outside the station were selected');
    },
    'a parcel counts at the last station on its route' => function () {
        check(Floor::select('avvik', 'search_zero', array_keys(Floor::ROUTES)) === ['search_zero'], 'zero search does not end at Avvik');
        check(Floor::select('sok', 'search_zero', array_keys(Floor::ROUTES)) === [], 'zero search ends at Søk');
    },
    'only permitted payload fields reach staff' => function () {
        check(Floor::payload(['q' => 'sag', 'session' => 'x', 'user' => 9]) === ['q' => 'sag'], 'session or user leaked');
        check(Floor::payload('not an array') === [], 'non-array payload was passed on');
    },
    'a product rests at Kvalitet, then Kundeservice, then Mottak' => function () {
        check(Floor::productStation(1, 0, 3) === 'kvalitet', 'open revision is not Kvalitet');
        check(Floor::productStation(0, 0, 1) === 'kundeservice', 'open ticket is not Kundeservice');
        check(Floor::productStation(0, 0, 0) === 'mottak', 'inbound only is not Mottak');
    },
    'a station is congested only above the threshold' => function () {
        check(!Floor::congested(Floor::CONGESTED), 'threshold itself counts as congested');
        check(Floor::congested(Floor::CONGESTED + 1), 'above threshold is not congested');
    },

    // ControlFloor over an in-memory store
    'events follow the window and the types, and newest rows are kept under the limit' => function () use ($at, $event) {
        $old = (int) strtotime('today', $at) - 60;
        $floor = new ControlFloor(new InMemoryFloorStore([
            $event(1, 'search', $old, ['q' => 'i går']),
            $event(2, 'search', $at - 10, ['q' => 'sag', 'session' => 'abc']),
            $event(3, 'purchase', $at - 5),
        ]), new FixedClock($at));
        $r = $floor->events('dag', 0, ['search']);
        check($r['total'] === 1 && $r['rows'][0]['id'] === 2, 'window or type filter is wrong');
        check($r['rows'][0]['data'] === ['q' => 'sag'], 'payload was not filtered');
        check($r['rows'][0]['route'] === ['sok'], 'route missing');
        check($r['last'] === 3, 'last ID is not the highest in the store');
        check($floor->events('dag', 2, ['search'])['total'] === 0, 'polling after an ID returned old rows');
    },
    'throughput has a point for every bucket, empty ones as zero' => function () use ($at, $event) {
        $floor = new ControlFloor(new InMemoryFloorStore([$event(1, 'chat', $at - 30), $event(2, 'chat', $at - 20)]), new FixedClock($at));
        $t = $floor->throughput('time', ['chat']);
        check($t['bucket'] === 300 && count($t['points']) >= 12, 'not one point per five minutes');
        check(array_sum(array_column($t['points'], 1)) === 2, 'counts are lost or invented');
        check($floor->throughput('time', [])['points'][0][1] === 0, 'an empty type list counted events');
    },
    'products are placed by the rule and filtered by station' => function () use ($at) {
        $p = fn(int $id, int $rev, int $rep, int $tk) => ['id' => $id, 'name' => "P$id", 'theme' => null, 'revisions' => $rev, 'reports' => $rep, 'tickets' => $tk, 'inbound' => null];
        $floor = new ControlFloor(new InMemoryFloorStore([], [$p(3, 0, 0, 0), $p(1, 0, 1, 0), $p(2, 0, 0, 2)]), new FixedClock($at));
        $all = $floor->products(['kvalitet', 'kundeservice', 'mottak']);
        check(array_column($all, 'station', 'id') === [1 => 'kvalitet', 2 => 'kundeservice', 3 => 'mottak'], 'stations are wrong');
        check(array_column($floor->products(['mottak']), 'id') === [3], 'station filter is wrong');
    },
    'congestion flags come from the backlog' => function () use ($at) {
        $floor = new ControlFloor(new InMemoryFloorStore([], [], ['kvalitet' => 9, 'kundeservice' => 2]), new FixedClock($at));
        check($floor->backlog()['congested'] === ['kvalitet' => true, 'kundeservice' => false], 'congestion flags are wrong');
    },

    // Transport: pure rules
    'a position is stale only after the limit' => function () use ($at) {
        check(!ShipmentRules::stale($at - ShipmentRules::STALE_MIN * 60, $at), 'exactly at the limit is stale');
        check(ShipmentRules::stale($at - ShipmentRules::STALE_MIN * 60 - 1, $at), 'past the limit is not stale');
        check(!ShipmentRules::stale(null, $at) && ShipmentRules::ageMinutes(null, $at) === null, 'no position has an age');
    },
    'item weights are summed only when all are known, and never adjusted' => function () {
        $w = ShipmentRules::weight(1000.0, null, [600, 400], 9000.0);
        check($w['items'] === 1000.0 && !$w['mismatch'] && $w['remaining'] === 8000.0, 'known weights are wrong');
        check(ShipmentRules::weight(1000.0, null, [600, null], 9000.0)['items'] === null, 'a partial sum was shown');
        check(ShipmentRules::weight(1000.0, 1200.0, [600, 400], 9000.0)['mismatch'], 'measured weight does not win');
        check(ShipmentRules::weight(null, null, [], null)['remaining'] === null, 'remaining payload was invented');
    },
    'variance uses the actual arrival, else the estimate' => function () use ($at) {
        check(ShipmentRules::variance($at, $at + 600, $at + 60) === 600, 'actual arrival not used');
        check(ShipmentRules::variance($at, null, $at - 120) === -120, 'estimate not used');
        check(ShipmentRules::variance(null, $at, $at) === null && ShipmentRules::variance($at, null, null) === null, 'unknown variance was invented');
    },
    'progress is measured along the route, never guessed' => function () {
        $route = [[59.0, 10.0], [61.0, 10.0]];
        check(abs(ShipmentRules::progress($route, 60.0, 10.0, false) - 0.5) < 1e-9, 'midpoint is not half');
        check(ShipmentRules::progress($route, null, null, false) === null, 'progress without a position');
        check(ShipmentRules::progress([[59.0, 10.0]], 60.0, 10.0, false) === null, 'progress without a route');
        check(ShipmentRules::progress([], null, null, true) === 1.0, 'delivered is not complete');
    },
    'registration and phone are hidden unless the role may see them' => function () {
        [$v, $d] = ShipmentRules::redact(['registration' => 'AB 1', 'fleet_no' => 'V'], ['phone' => '1', 'alias' => 'A'], false);
        check(!isset($v['registration']) && !isset($d['phone']) && $v['fleet_no'] === 'V', 'redaction is wrong');
        [$v] = ShipmentRules::redact(['registration' => 'AB 1'], null, true);
        check($v['registration'] === 'AB 1', 'allowed role lost the registration');
    },

    // Transport over an in-memory store
    'the inspector gets every derived figure from the server' => function () use ($at, $shipment) {
        $t = new Transport(new InMemoryShipmentStore([7 => $shipment(7, 'underveis')]), new FixedClock($at));
        $s = $t->one(7, false);
        check($s['age_min'] === 5 && !$s['stale'], 'age or staleness is wrong');
        check(abs($s['progress'] - 0.5) < 1e-9 && $s['variance'] === 3600 - 1800, 'progress or variance is wrong');
        check($s['weight']['remaining'] === 8000.0 && !$s['weight']['mismatch'], 'weight block is wrong');
        check(!isset($s['vehicle']['registration']) && !isset($s['driver']['phone']), 'restricted fields leaked');
        check($t->one(7, true)['vehicle']['registration'] === 'AB 12345', 'allowed role lost the registration');
        check($t->one(99, true) === null, 'a missing shipment was found');
    },
    'stale shipments are counted only while still on the way' => function () use ($at, $shipment) {
        $old = ['positions' => [['lat' => 60.0, 'lng' => 10.0, 'heading' => 0, 'observed_at' => $at - 3600, 'received_at' => $at - 3600, 'source' => 'demo']]];
        $t = new Transport(new InMemoryShipmentStore([
            1 => $shipment(1, 'underveis', $old),
            2 => $shipment(2, 'levert', $old + ['shipment' => ['actual_arrival' => $at - 60]]),
            3 => $shipment(3, 'underveis'),
        ]), new FixedClock($at));
        $rows = array_column($t->list()['rows'], 'stale', 'id');
        check($rows === [1 => true, 3 => false, 2 => true], 'list staleness or order is wrong');
        check($t->staleUnderway() === 1, 'a delivered shipment counted as stale');
    },

    // Draft prompts
    'a cut-off or empty model answer is never given to staff' => function () {
        check(DraftPrompt::accept('Hei', true, true) === null, 'cut-off answer accepted');
        check(DraftPrompt::accept('  ', true, false) === null && DraftPrompt::accept('Hei', false, false) === null, 'empty or failed answer accepted');
        check(DraftPrompt::accept('Ja — nei – kanskje', true, false) === 'Ja , nei , kanskje', 'dashes were kept');
    },
    'the ticket prompt keeps the latest lines and says when there are no facts' => function () {
        $chat = array_map(fn($i) => ['role' => 'user', 'content' => "linje $i"], range(1, 12));
        [, $user] = DraftPrompt::ticket(4, 'Spørsmål', $chat, [['sender' => 'kunde', 'body' => 'Hallo']], '');
        check(!str_contains($user, 'linje 4') && str_contains($user, 'linje 5') && str_contains($user, 'linje 12'), 'chat lines not cut to the latest');
        check(str_contains($user, 'Kunde: Hallo') && str_contains($user, 'Ingen produktfakta.'), 'thread or empty facts missing');
    },

    // Drafts over an in-memory store and a fixed model answer
    'a draft is written from the stored ticket and its facts' => function () use ($draftStore, $model) {
        $m = $model('Vi sjekker lageret.', true, false);
        $d = new Drafts($draftStore, $m);
        check($d->ticket(5) === 'Vi sjekker lageret.', 'draft not returned');
        check(str_contains($m->asked, 'Sak #5: Levering') && str_contains($m->asked, 'Produkt: Sag'), 'prompt lacks ticket or facts');
        check($d->ticket(6) === null && $m->calls === 1, 'a closed ticket reached the model');
    },
    'a cut-off draft or assessment is dropped' => function () use ($draftStore, $model) {
        $d = new Drafts($draftStore, $model('Halv tek', true, true));
        check($d->ticket(5) === null && $d->report(3) === null, 'cut-off text given to staff');
        check((new Drafts($draftStore, $model('Ser riktig ut.', true, false)))->report(3) === 'Ser riktig ut.', 'assessment not returned');
    },
];
