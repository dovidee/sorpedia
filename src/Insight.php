<?php
declare(strict_types=1);

/**
 * Data behind the Control Tower insight view: headline numbers for the last seven days against the seven before, daily series with a
 * linear trend forecast, the customer funnel, failed search terms, catalogue coverage, order values and days of stock.
 * Every figure is counted from stored rows; seeded demo orders are counted and labelled, never hidden.
 */
final class Insight
{
    private const TZ = 'Europe/Oslo';

    /** Headline figures cover the last seven days against the seven days before. */
    private const DAYS = 7;

    public static function build(int $now): array
    {
        $pdo = db();
        $since = $now - self::DAYS * 86400;
        $prev = $since - self::DAYS * 86400;
        $count = function (array $types, int $from, int $to) use ($pdo): int {
            $st = $pdo->prepare('SELECT COUNT(*) FROM analytics_events WHERE type IN (' . implode(',', array_fill(0, count($types), '?')) . ')
                AND created_at >= FROM_UNIXTIME(?) AND created_at < FROM_UNIXTIME(?)');
            $st->execute([...$types, $from, $to]);
            return (int) $st->fetchColumn();
        };
        $pair = fn(array $types) => [$count($types, $since, $now + 1), $count($types, $prev, $since)];
        $rate = fn(array $a, array $b) => [$b[0] ? $a[0] / $b[0] : null, $b[1] ? $a[1] / $b[1] : null];

        $searches = $pair(['search', 'search_zero']);
        $zero = $pair(['search_zero']);
        $chat = $pair(['chat']);
        $escalate = $pair(['escalate']);
        $cart = $pair(['add_to_cart', 'lab_cart']);
        $buy = $pair(['purchase']);

        // Daily series for the last 30 days (Oslo calendar days), events and orders
        $days = self::days($now, 30);
        $ev = $pdo->prepare('SELECT type, UNIX_TIMESTAMP(created_at) t FROM analytics_events WHERE created_at >= FROM_UNIXTIME(?)');
        $ev->execute([strtotime($days[0] . ' 00:00 ' . self::TZ)]);
        $series = array_fill_keys(['search', 'zero', 'chat', 'escalate'], array_fill_keys($days, 0));
        $map = ['search' => 'search', 'search_zero' => 'zero', 'chat' => 'chat', 'escalate' => 'escalate'];
        $firstEvent = null;
        foreach ($ev as $r) {
            $d = self::day((int) $r['t']);
            if (isset($map[$r['type']], $series[$map[$r['type']]][$d])) {
                $series[$map[$r['type']]][$d]++;
            }
            $firstEvent = min($firstEvent ?? $d, $d);
        }
        $series['search'] = array_map(fn($a, $b) => $a + $b, $series['search'], $series['zero']);
        $searchDays = $firstEvent ? array_slice($series['search'], (int) array_search($firstEvent, $days, true)) : [];

        $odays = self::days($now, 60);
        $orders = $pdo->query('SELECT UNIX_TIMESTAMP(created_at) t, total_ore, is_demo_seed FROM orders WHERE status = \'betalt\'')->fetchAll();
        $perDay = array_fill_keys($odays, 0);
        $firstOrder = null;
        foreach ($orders as $o) {
            $d = self::day((int) $o['t']);
            if (isset($perDay[$d])) {
                $perDay[$d]++;
                $firstOrder = min($firstOrder ?? $d, $d);
            }
        }
        $orderDays = $firstOrder ? array_slice($perDay, (int) array_search($firstOrder, $odays, true)) : [];
        $values = array_map(fn($o) => $o['total_ore'] / 100, $orders);

        // Search, cart, purchase: each step is a subset of the visits before it. Guides and manuals are counted on their own.
        $funnel = [
            ['k' => 'search', 'n' => $searches[0]],
            ['k' => 'cart', 'n' => $cart[0]],
            ['k' => 'purchase', 'n' => $buy[0]],
        ];
        $guides = $pair(['guide_done', 'manual_view', 'compare']);
        // Success goals: share of paid orders with a "goes well with" pair, and minutes from a session's first search to cart
        $acc = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(EXISTS (SELECT 1 FROM order_items a JOIN order_items b ON b.order_id = a.order_id AND b.product_id <> a.product_id
                JOIN relations r ON r.product_a = a.product_id AND r.product_b = b.product_id AND r.kind = 'passer_sammen' WHERE a.order_id = o.id)), 0) k
            FROM orders o WHERE o.status = 'betalt' AND o.created_at >= FROM_UNIXTIME(?) AND o.created_at < FROM_UNIXTIME(?)");
        $accRate = function (int $from, int $to) use ($acc): ?float {
            $acc->execute([$from, $to]);
            $r = $acc->fetch();
            return (int) $r['n'] ? (int) $r['k'] / (int) $r['n'] : null;
        };
        $accParts = function (int $from, int $to) use ($acc): array {
            $acc->execute([$from, $to]);
            $r = $acc->fetch();
            return [(int) $r['k'], (int) $r['n']];
        };
        $toCart = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, f.fs, (SELECT MIN(c.created_at) FROM analytics_events c
                WHERE c.session_id = f.session_id AND c.type IN ('add_to_cart', 'lab_cart') AND c.created_at >= f.fs)) / 60 m
            FROM (SELECT session_id, MIN(created_at) fs FROM analytics_events WHERE type IN ('search', 'search_zero')
                AND created_at >= FROM_UNIXTIME(?) AND created_at < FROM_UNIXTIME(?) GROUP BY session_id) f");
        $median = function (int $from, int $to) use ($toCart): ?float {
            $toCart->execute([$from, $to]);
            return Stats::describe(array_map('floatval', array_filter($toCart->fetchAll(\PDO::FETCH_COLUMN), fn($m) => $m !== null)))['median'];
        };

        $zt = $pdo->prepare("SELECT JSON_UNQUOTE(JSON_EXTRACT(data, '$.q')) q, COUNT(*) n FROM analytics_events
            WHERE type = 'search_zero' AND created_at >= FROM_UNIXTIME(?) GROUP BY q ORDER BY n DESC LIMIT 8");
        $zt->execute([$since]);

        // Days of stock: online stock over average daily units sold in the last 30 days (paid orders), lowest first
        $cover = $pdo->query("SELECT p.id, p.name, COALESCE(s.online, 0) stock, x.sold, x.demo
            FROM (SELECT i.product_id, SUM(i.qty) sold, MAX(o.is_demo_seed) demo FROM order_items i JOIN orders o ON o.id = i.order_id
                  WHERE o.status = 'betalt' AND o.created_at >= NOW() - INTERVAL 30 DAY GROUP BY i.product_id) x
            JOIN products p ON p.id = x.product_id
            LEFT JOIN (SELECT product_id, SUM(online) online FROM stock GROUP BY product_id) s ON s.product_id = p.id")->fetchAll();
        foreach ($cover as &$c) {
            $c['days'] = $c['sold'] > 0 ? $c['stock'] / ($c['sold'] / 30) : null;
        }
        unset($c);
        usort($cover, fn($a, $b) => $a['days'] <=> $b['days']);

        return [
            'tiles' => [
                'searches' => $searches, 'zeroRate' => $rate($zero, $searches), 'chat' => $chat, 'escalateRate' => $rate($escalate, $chat),
                'cart' => $cart, 'purchases' => $buy, 'guides' => $guides,
                'accessoryRate' => [$accRate($since, $now + 1), $accRate($prev, $since)], 'toCartMin' => [$median($since, $now + 1), $median($prev, $since)],
            ],
            // Numerator and denominator of each rate this week, shown next to the percentage (CONTROLTOWER-REDESIGN.md 6)
            'parts' => ['zeroRate' => [$zero[0], $searches[0]], 'escalateRate' => [$escalate[0], $chat[0]], 'accessoryRate' => $accParts($since, $now + 1)],
            'days' => $days,
            'series' => array_map('array_values', $series),
            'searchForecast' => Stats::forecast($searchDays),
            'orderDays' => $odays,
            'orders' => array_values($perDay),
            'orderForecast' => Stats::forecast($orderDays),
            'ordersDemo' => count(array_filter($orders, fn($o) => (int) $o['is_demo_seed'] === 1)),
            'orderValues' => Stats::describe($values) + ['bins' => Stats::histogram($values)],
            'funnel' => $funnel,
            'zeroTerms' => $zt->fetchAll(),
            'coverage' => $pdo->query("SELECT source, version, COUNT(*) n FROM spec_values GROUP BY source, version")->fetchAll(),
            'rel' => $pdo->query('SELECT level, COUNT(*) n FROM relations GROUP BY level ORDER BY n DESC')->fetchAll(),
            'cover' => array_slice($cover, 0, 8),
            'quality' => Tower::quality($since, array_keys(Floor::ROUTES), 0),
        ] + self::journeys($now) + ['backup' => self::lastBackup()];
    }

    /** The newest database backup from scripts/backup.php: when and how large. Only the file's metadata is read. */
    public static function lastBackup(): ?array
    {
        $all = glob(APP_ROOT . '/data/backups/sorpedia-*.sql.gz') ?: [];
        rsort($all);
        return $all ? ['at' => (int) filemtime($all[0]), 'bytes' => (int) filesize($all[0]), 'count' => count($all)] : null;
    }

    /** Opportunities, configuration insights and guide abandonment look back this far. */
    private const LOOKBACK_DAYS = 30;

    /**
     * Product opportunities (needs the catalogue does not meet yet), Byggelab configuration insights and where customers leave the guide.
     * Counted from analytics_events of the last LOOKBACK_DAYS. Sessions are only counted, never shown. Nothing here changes the catalogue:
     * each opportunity points at its newest event, where staff mark it as reviewed or raise a task (EventDesk).
     */
    public static function journeys(int $now): array
    {
        $pdo = db();
        $st = $pdo->prepare("SELECT id, session_id s, type, data, UNIX_TIMESTAMP(created_at) t FROM analytics_events
            WHERE type IN ('search_zero', 'guide_feedback', 'guide_step', 'guide_done', 'lab_check', 'lab_test', 'lab_save', 'lab_list', 'lab_cart')
            AND created_at >= FROM_UNIXTIME(?) ORDER BY id");
        $st->execute([$now - self::LOOKBACK_DAYS * 86400]);
        $opp = [];
        $add = function (string $kind, string $key, string $text, array $e) use (&$opp): void {
            $k = "$kind|$key";
            $opp[$k] ??= ['kind' => $kind, 'text' => $text, 'n' => 0, 'sessions' => [], 'events' => [], 'last' => 0];
            $opp[$k]['n']++;
            $opp[$k]['sessions'][$e['s']] = true;
            $opp[$k]['events'][] = (int) $e['id'];
            $opp[$k]['last'] = max($opp[$k]['last'], (int) $e['t']);
        };
        $lab = ['products' => [], 'pairs' => [], 'builders' => [], 'verdicts' => [], 'sessions' => [], 'done' => []];
        $guide = ['last' => [], 'done' => []];
        foreach ($st as $e) {
            $d = json_decode((string) $e['data'], true) ?: [];
            switch ($e['type']) {
                case 'search_zero':
                    if (($q = EventDesk::term($d)) !== null) {
                        $add('search', $q, $q, $e);
                    }
                    break;
                case 'guide_feedback':
                    $w = mb_strtolower(trim((string) ($d['wanted'] ?? '')));
                    if ($w !== '') {
                        $add('guide', $w, $w, $e);
                    }
                    break;
                case 'guide_step':
                    $guide['last'][$e['s']] = (string) ($d['question'] ?? '');
                    break;
                case 'guide_done':
                    $guide['done'][$e['s']] = true;
                    break;
                default: // lab_*
                    $b = (string) ($d['builder'] ?? '');
                    $key = $e['s'] . '|' . $b;
                    $ids = array_map('intval', $d['ids'] ?? []);
                    if (in_array($e['type'], ['lab_check', 'lab_test'], true)) {
                        $lab['sessions'][$key] = ['ids' => $ids, 'verdict' => (string) ($d['verdict'] ?? ''), 'builder' => $b, 'missing' => $d['missing'] ?? []];
                    } else {
                        $lab['done'][$key] = true;
                    }
                    if ($e['type'] === 'lab_check' || $e['type'] === 'lab_test') {
                        foreach ($d['missing'] ?? [] as $m) {
                            $add('lab', $b . '|' . $m, (string) $m, $e);
                        }
                    }
            }
        }
        // A configuration counts once per session and builder, in its last checked state
        foreach ($lab['sessions'] as $c) {
            $lab['builders'][$c['builder']] = ($lab['builders'][$c['builder']] ?? 0) + 1;
            $lab['verdicts'][$c['verdict']] = ($lab['verdicts'][$c['verdict']] ?? 0) + 1;
            $ids = array_values(array_unique($c['ids']));
            sort($ids);
            foreach ($ids as $i => $a) {
                $lab['products'][$a] = ($lab['products'][$a] ?? 0) + 1;
                foreach (array_slice($ids, $i + 1) as $b) {
                    $lab['pairs']["$a-$b"] = ($lab['pairs']["$a-$b"] ?? 0) + 1;
                }
            }
        }
        $names = function (array $ids) use ($pdo): array {
            if (!$ids) {
                return [];
            }
            return $pdo->query('SELECT id, name FROM products WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')')->fetchAll(\PDO::FETCH_KEY_PAIR);
        };
        arsort($lab['products']);
        arsort($lab['pairs']);
        $topP = array_slice($lab['products'], 0, 8, true);
        $topPairs = array_slice($lab['pairs'], 0, 6, true);
        $pairIds = array_merge(...array_map(fn($k) => array_map('intval', explode('-', (string) $k)), array_keys($topPairs) ?: ['0-0']));
        $nm = $names(array_unique([...array_keys($topP), ...$pairIds]));

        // Opportunities: newest events decide the review state; recurring needs (more sessions) first
        $opp = array_values($opp);
        usort($opp, fn($a, $b) => [count($b['sessions']), $b['last']] <=> [count($a['sessions']), $a['last']]);
        $opp = array_slice($opp, 0, 12);
        $all = array_merge([0], ...array_map(fn($o) => $o['events'], $opp));
        $in = implode(',', $all);
        $reviews = EventDesk::reviews($all);
        $tasks = $pdo->query("SELECT event_id, id, status FROM improvement_tasks WHERE event_id IN ($in) ORDER BY id")->fetchAll();
        $out = [];
        foreach ($opp as $o) {
            $ev = array_flip($o['events']);
            $own = array_values(array_filter($tasks, fn($t) => isset($ev[(int) $t['event_id']])));
            $out[] = ['kind' => $o['kind'], 'text' => $o['text'], 'n' => $o['n'], 'sessions' => count($o['sessions']), 'last' => $o['last'],
                'event' => max($o['events']), 'reviewed' => EventDesk::state($o['events'], $reviews, false) !== 'ikke',
                'review' => EventDesk::state($o['events'], $reviews, (bool) $own),
                'tasks' => array_map(fn($t) => ['id' => (int) $t['id'], 'status' => $t['status']], $own), 'events' => $o['events']];
        }
        $out = Drill::opportunityProducts($out);

        // Guide: for each question, sessions whose last shown question it was and that never finished
        $drop = [];
        foreach ($guide['last'] as $s => $q) {
            if (!isset($guide['done'][$s]) && $q !== '') {
                $drop[$q] = ($drop[$q] ?? 0) + 1;
            }
        }
        arsort($drop);
        $started = count($guide['last']) + count(array_diff_key($guide['done'], $guide['last']));

        return [
            'opportunities' => $out,
            'lab' => [
                'configs' => count($lab['sessions']),
                'abandoned' => count(array_diff_key($lab['sessions'], $lab['done'])),
                'builders' => $lab['builders'],
                'verdicts' => $lab['verdicts'],
                'products' => array_map(fn($id, $n) => ['id' => $id, 'name' => $nm[$id] ?? "#$id", 'n' => $n], array_keys($topP), $topP),
                'pairs' => array_map(function ($k, $n) use ($nm) {
                    [$a, $b] = array_map('intval', explode('-', (string) $k));
                    return ['a' => $nm[$a] ?? "#$a", 'b' => $nm[$b] ?? "#$b", 'n' => $n];
                }, array_keys($topPairs), $topPairs),
            ],
            'guideDrop' => ['started' => $started, 'done' => count($guide['done']),
                'at' => array_map(fn($q, $n) => ['q' => t($q === 'type' ? Guide::TYPE_Q : (Guide::QUESTIONS[$q][0] ?? $q)), 'n' => $n], array_keys($drop), $drop)],
        ];
    }

    /**
     * Stock flow per Oslo calendar day: units in from inbound shipments that arrived (shipment_items) and from refunds, units out on paid
     * orders (order_items), the net change, and the units still on the way in. A restock is a shipment arrival, so it is
     * counted once through the shipment.
     */
    public static function stockFlow(int $now, int $n = 14): array
    {
        $pdo = db();
        $days = self::days($now, $n);
        $from = date('Y-m-d H:i:s', (int) strtotime($days[0] . ' 00:00 ' . self::TZ));
        $in = array_fill_keys($days, 0);
        $out = array_fill_keys($days, 0);
        $st = $pdo->prepare("SELECT UNIX_TIMESTAMP(s.actual_arrival) t, SUM(i.qty) q, MAX(s.is_demo) demo FROM shipments s JOIN shipment_items i ON i.shipment_id = s.id
            WHERE s.direction = 'inn' AND s.actual_arrival >= ? GROUP BY s.id");
        $st->execute([$from]);
        $demo = false;
        foreach ($st as $r) {
            $d = self::day((int) $r['t']);
            if (isset($in[$d])) {
                $in[$d] += (int) $r['q'];
                $demo = $demo || (int) $r['demo'] === 1;
            }
        }
        $st = $pdo->prepare("SELECT UNIX_TIMESTAMP(o.created_at) t, SUM(i.qty) q, MAX(o.is_demo_seed) demo FROM orders o JOIN order_items i ON i.order_id = o.id
            WHERE o.status = 'betalt' AND o.created_at >= ? GROUP BY o.id");
        $st->execute([$from]);
        foreach ($st as $r) {
            $d = self::day((int) $r['t']);
            if (isset($out[$d])) {
                $out[$d] += (int) $r['q'];
                $demo = $demo || (int) $r['demo'] === 1;
            }
        }
        // Refunded units go back on the shelf
        $st = $pdo->prepare('SELECT UNIX_TIMESTAMP(created_at) t, qty FROM order_refunds WHERE created_at >= ?');
        $st->execute([$from]);
        foreach ($st as $r) {
            $d = self::day((int) $r['t']);
            if (isset($in[$d])) {
                $in[$d] += (int) $r['qty'];
            }
        }
        $coming = (int) $pdo->query("SELECT COALESCE(SUM(i.qty), 0) FROM shipments s JOIN shipment_items i ON i.shipment_id = s.id
            WHERE s.direction = 'inn' AND s.actual_arrival IS NULL AND s.status NOT IN ('kansellert', 'levert')")->fetchColumn();
        return ['days' => $days, 'in' => array_values($in), 'out' => array_values($out),
            'net' => array_map(fn($a, $b) => $a - $b, array_values($in), array_values($out)), 'coming' => $coming, 'demo' => $demo];
    }

    /** The last $n Oslo calendar days up to and including today, as Y-m-d. */
    private static function days(int $now, int $n): array
    {
        $out = [];
        $d = new DateTimeImmutable('@' . $now);
        $d = $d->setTimezone(new DateTimeZone(self::TZ))->setTime(0, 0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $out[] = $d->modify("-$i day")->format('Y-m-d');
        }
        return $out;
    }

    private static function day(int $t): string
    {
        return (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone(self::TZ))->format('Y-m-d');
    }
}
