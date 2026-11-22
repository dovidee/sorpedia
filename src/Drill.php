<?php
declare(strict_types=1);

/**
 * KPI drill-downs for the Control Tower (feature 032). Every row comes from a stored record: the exact search
 * term, the conversation as it was saved, the products a Byggelab session held. Nothing is filled in when a
 * record lacks it; the interface shows "unknown" instead.
 */
final class Drill
{
    public const PERIODS = [7, 30];

    /** Links an escalation to the conversation it came from, and lets staff mark a conversation as read. */
    public static function ensure(PDO $pdo): void
    {
        if (!$pdo->query("SHOW COLUMNS FROM support_tickets LIKE 'conversation_id'")->fetch()) {
            $pdo->exec('ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS conversation_id INT UNSIGNED NULL AFTER product_id');
        }
        if (!$pdo->query("SHOW COLUMNS FROM chat_conversations LIKE 'reviewed_at'")->fetch()) {
            $pdo->exec('ALTER TABLE chat_conversations ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP NULL, ADD COLUMN IF NOT EXISTS reviewed_by INT UNSIGNED NULL');
        }
    }

    /** The period in days from the query string: 7 or 30, nothing else. */
    public static function days(mixed $v): int
    {
        return in_array((int) $v, self::PERIODS, true) ? (int) $v : 30;
    }

    /**
     * Product name, first image and price for a set of ids, read from the catalog. Ids that no longer exist are left out.
     * @param int[] $ids
     */
    public static function products(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $st = db()->prepare("SELECT p.id, p.name, p.slug, p.price_ore,
                (SELECT path_m FROM product_images i WHERE i.product_id = p.id ORDER BY kind = 'sortre' DESC, sort LIMIT 1) img
            FROM products p WHERE p.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')');
        $st->execute($ids);
        $out = [];
        foreach ($st as $r) {
            $out[(int) $r['id']] = ['id' => (int) $r['id'], 'name' => $r['name'], 'slug' => $r['slug'], 'price_ore' => (int) $r['price_ore'], 'img' => $r['img']];
        }
        return $out;
    }

    /**
     * Search terms in the period, the ones without a hit first. Per term: how often, how many sessions, first and last
     * time, whether it was seen before the period, the product mapping, review state and tasks raised from its events.
     */
    public static function searches(int $since): array
    {
        $pdo = db();
        $st = $pdo->prepare("SELECT id, session_id s, type, data, UNIX_TIMESTAMP(created_at) t FROM analytics_events
            WHERE type IN ('search', 'search_zero') AND created_at >= FROM_UNIXTIME(?) ORDER BY id");
        $st->execute([$since]);
        $terms = [];
        foreach ($st as $e) {
            $d = json_decode((string) $e['data'], true) ?: [];
            if (($q = EventDesk::term($d)) === null) {
                continue;
            }
            $zero = $e['type'] === 'search_zero';
            $k = ($zero ? '0|' : '1|') . $q;
            $terms[$k] ??= ['q' => $q, 'zero' => $zero, 'n' => 0, 'sessions' => [], 'events' => [], 'first' => (int) $e['t'], 'last' => 0, 'hits' => 0];
            $terms[$k]['n']++;
            $terms[$k]['sessions'][$e['s']] = true;
            $terms[$k]['events'][] = (int) $e['id'];
            $terms[$k]['last'] = max($terms[$k]['last'], (int) $e['t']);
            $terms[$k]['hits'] = max($terms[$k]['hits'], (int) ($d['n'] ?? 0));
        }
        $terms = array_values($terms);
        usort($terms, fn($a, $b) => [$b['zero'], $b['n'], $b['last']] <=> [$a['zero'], $a['n'], $a['last']]);
        $terms = array_slice($terms, 0, 60);
        $all = array_merge([0], ...array_map(fn($t) => $t['events'], $terms));
        $in = implode(',', $all);
        $reviews = EventDesk::reviews($all);
        $tasks = $pdo->query("SELECT event_id, id, status, title FROM improvement_tasks WHERE event_id IN ($in) ORDER BY id")->fetchAll();
        $before = $pdo->prepare("SELECT 1 FROM analytics_events WHERE type IN ('search', 'search_zero') AND created_at < FROM_UNIXTIME(?)
            AND LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(data, '$.q')))) = ? LIMIT 1");
        $out = [];
        foreach ($terms as $t) {
            $ev = array_flip($t['events']);
            $before->execute([$since, $t['q']]);
            $out[] = ['q' => $t['q'], 'zero' => $t['zero'], 'n' => $t['n'], 'customers' => count($t['sessions']), 'first' => $t['first'], 'last' => $t['last'],
                'hits' => $t['hits'], 'event' => max($t['events']), 'before' => (bool) $before->fetchColumn(),
                'reviewed' => EventDesk::state($t['events'], $reviews, false) !== 'ikke',
                'review' => EventDesk::state($t['events'], $reviews, (bool) array_filter($tasks, fn($x) => isset($ev[(int) $x['event_id']]))),
                'mapped' => $t['zero'] ? EventDesk::mapped($t['q']) : [],
                'tasks' => array_values(array_map(fn($x) => ['id' => (int) $x['id'], 'status' => $x['status'], 'title' => $x['title']],
                    array_filter($tasks, fn($x) => isset($ev[(int) $x['event_id']]))))];
        }
        return $out;
    }

    /**
     * Søren conversations with a message in the period, newest first: the customer's first question, Søren's last answer,
     * how many turns, and the escalation if one was made. Escalations saved before conversations were linked are listed
     * from the copy of the chat the ticket kept.
     */
    public static function conversations(int $since): array
    {
        $pdo = db();
        $st = $pdo->prepare("SELECT c.id, UNIX_TIMESTAMP(MIN(m.created_at)) started, UNIX_TIMESTAMP(MAX(m.created_at)) last, COUNT(*) n,
                SUM(m.role = 'user') asks, UNIX_TIMESTAMP(c.reviewed_at) reviewed,
                (SELECT content FROM chat_messages x WHERE x.conversation_id = c.id AND x.role = 'user' ORDER BY seq LIMIT 1) question,
                (SELECT content FROM chat_messages x WHERE x.conversation_id = c.id AND x.role = 'assistant' ORDER BY seq DESC LIMIT 1) answer,
                (SELECT t.id FROM support_tickets t WHERE t.conversation_id = c.id ORDER BY t.id DESC LIMIT 1) ticket
            FROM chat_conversations c JOIN chat_messages m ON m.conversation_id = c.id
            GROUP BY c.id HAVING MAX(m.created_at) >= FROM_UNIXTIME(?) ORDER BY last DESC LIMIT 60");
        $st->execute([$since]);
        $rows = [];
        foreach ($st as $r) {
            $rows[] = ['kind' => 'chat', 'id' => (int) $r['id'], 'started' => (int) $r['started'], 'last' => (int) $r['last'], 'n' => (int) $r['n'],
                'asks' => (int) $r['asks'], 'reviewed' => $r['reviewed'] !== null, 'question' => self::preview($r['question']),
                'answer' => self::preview($r['answer']), 'ticket' => $r['ticket'] !== null ? (int) $r['ticket'] : null];
        }
        // Tickets without a linked conversation still carry the conversation they were raised from
        $st = $pdo->prepare('SELECT id, status, summary, chat_log, product_id, UNIX_TIMESTAMP(created_at) t FROM support_tickets
            WHERE conversation_id IS NULL AND created_at >= FROM_UNIXTIME(?) ORDER BY id DESC');
        $st->execute([$since]);
        foreach ($st as $t) {
            $log = json_decode((string) $t['chat_log'], true) ?: [];
            $user = array_values(array_filter($log, fn($m) => ($m['role'] ?? '') === 'user'));
            $bot = array_values(array_filter($log, fn($m) => ($m['role'] ?? '') === 'assistant'));
            $rows[] = ['kind' => 'ticket', 'id' => (int) $t['id'], 'started' => (int) $t['t'], 'last' => (int) $t['t'], 'n' => count($log), 'asks' => count($user),
                'reviewed' => $t['status'] === 'lukket', 'question' => self::preview($user[0]['content'] ?? null) ?? self::preview($t['summary']),
                'answer' => self::preview(end($bot)['content'] ?? null), 'ticket' => (int) $t['id']];
        }
        usort($rows, fn($a, $b) => $b['last'] <=> $a['last']);
        return $rows;
    }

    /** One conversation in full, as stored, with its escalation and the product the escalation named. */
    public static function conversation(string $kind, int $id): ?array
    {
        $pdo = db();
        if ($kind === 'ticket') {
            $st = $pdo->prepare('SELECT id, status, summary, chat_log, product_id, UNIX_TIMESTAMP(created_at) t FROM support_tickets WHERE id = ?');
            $st->execute([$id]);
            $t = $st->fetch();
            if (!$t) {
                return null;
            }
            $msgs = array_map(fn($m) => ['role' => $m['role'] ?? '', 'content' => (string) ($m['content'] ?? ''), 'at' => null], json_decode((string) $t['chat_log'], true) ?: []);
            return ['kind' => 'ticket', 'id' => $id, 'messages' => $msgs, 'reviewed' => $t['status'] === 'lukket',
                'ticket' => ['id' => $id, 'status' => $t['status'], 'summary' => $t['summary'], 'at' => (int) $t['t']],
                'product' => self::products([(int) $t['product_id']])[(int) $t['product_id']] ?? null];
        }
        $st = $pdo->prepare('SELECT UNIX_TIMESTAMP(reviewed_at) FROM chat_conversations WHERE id = ?');
        $st->execute([$id]);
        $reviewed = $st->fetch(PDO::FETCH_NUM);
        if (!$reviewed) {
            return null;
        }
        $st = $pdo->prepare('SELECT role, content, UNIX_TIMESTAMP(created_at) at FROM chat_messages WHERE conversation_id = ? ORDER BY seq');
        $st->execute([$id]);
        $msgs = array_map(fn($m) => ['role' => $m['role'], 'content' => ProductRef::resolve((string) $m['content']), 'at' => (int) $m['at']], $st->fetchAll());
        $st = $pdo->prepare('SELECT id, status, summary, product_id, UNIX_TIMESTAMP(created_at) at FROM support_tickets WHERE conversation_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$id]);
        $t = $st->fetch() ?: null;
        return ['kind' => 'chat', 'id' => $id, 'messages' => $msgs, 'reviewed' => $reviewed[0] !== null,
            'ticket' => $t ? ['id' => (int) $t['id'], 'status' => $t['status'], 'summary' => $t['summary'], 'at' => (int) $t['at']] : null,
            'product' => $t && $t['product_id'] ? (self::products([(int) $t['product_id']])[(int) $t['product_id']] ?? null) : null];
    }

    /** Marks a conversation as read by staff. A ticket is handled in Kundeservice instead. */
    public static function review(int $id, int $user): bool
    {
        $st = db()->prepare('UPDATE chat_conversations SET reviewed_at = NOW(), reviewed_by = ? WHERE id = ? AND reviewed_at IS NULL');
        $st->execute([$user, $id]);
        return $st->rowCount() > 0;
    }

    /**
     * Byggelab sessions in the period, newest first: one per browser session and builder, in its last checked state, with
     * the products, their prices and images, what was missing, and whether it was saved, exported or put in the cart.
     */
    public static function lab(int $since): array
    {
        $st = db()->prepare("SELECT session_id s, type, data, UNIX_TIMESTAMP(created_at) t FROM analytics_events
            WHERE type IN ('lab_check', 'lab_test', 'lab_save', 'lab_list', 'lab_cart') AND created_at >= FROM_UNIXTIME(?) ORDER BY id");
        $st->execute([$since]);
        $sessions = [];
        foreach ($st as $e) {
            $d = json_decode((string) $e['data'], true) ?: [];
            $b = (string) ($d['builder'] ?? '');
            $k = $e['s'] . '|' . $b;
            $sessions[$k] ??= ['builder' => $b, 'ids' => [], 'verdict' => null, 'missing' => [], 'conflicts' => 0, 'unknown' => 0,
                'first' => (int) $e['t'], 'last' => 0, 'checks' => 0, 'saved' => false, 'listed' => false, 'cart' => false];
            $x = &$sessions[$k];
            $x['last'] = (int) $e['t'];
            if (in_array($e['type'], ['lab_check', 'lab_test'], true)) {
                $x['ids'] = array_map('intval', $d['ids'] ?? []);
                $x['verdict'] = (string) ($d['verdict'] ?? '');
                $x['missing'] = array_map('strval', $d['missing'] ?? []);
                $x['conflicts'] = (int) ($d['conflicts'] ?? 0);
                $x['unknown'] = (int) ($d['unknown'] ?? 0);
                $x['checks']++;
            } else {
                $x[['lab_save' => 'saved', 'lab_list' => 'listed', 'lab_cart' => 'cart'][$e['type']]] = true;
                $x['ids'] = $x['ids'] ?: array_map('intval', $d['ids'] ?? []);
            }
            unset($x);
        }
        $sessions = array_slice(array_reverse(array_values($sessions)), 0, 40);
        $p = self::products(array_merge([], ...array_map(fn($s) => $s['ids'], $sessions)));
        foreach ($sessions as &$s) {
            $s['products'] = array_values(array_filter(array_map(fn($id) => $p[$id] ?? null, $s['ids'])));
            $s['price_ore'] = array_sum(array_column($s['products'], 'price_ore'));
            $s['name'] = t(Byggelab::BUILDERS[$s['builder']][0] ?? $s['builder']);
            // The exact setup of this session, opened in Byggelab through its address (?c=id:qty), CONTROLTOWER-TRIAGE.md 7
            $s['url'] = isset(Byggelab::BUILDERS[$s['builder']]) && $s['ids']
                ? '/byggelab/' . rawurlencode($s['builder']) . '?c=' . implode(',', array_map(fn($id, $n) => "$id:$n", array_keys(array_count_values($s['ids'])), array_count_values($s['ids'])))
                : null;
            unset($s['ids']);
        }
        unset($s);
        return $sessions;
    }

    /** Opportunities from Insight with the product photos they concern: the products in the Byggelab sessions or the mapped search. */
    public static function opportunityProducts(array $opps): array
    {
        $events = array_merge([0], ...array_map(fn($o) => $o['events'] ?? [$o['event']], $opps));
        $st = db()->query('SELECT id, type, data FROM analytics_events WHERE id IN (' . implode(',', array_map('intval', $events)) . ')');
        $ids = [];
        foreach ($st as $e) {
            $d = json_decode((string) $e['data'], true) ?: [];
            $ids[(int) $e['id']] = array_map('intval', $d['ids'] ?? (isset($d['product']) ? [$d['product']] : []));
        }
        $p = self::products(array_merge([], ...array_values($ids)));
        foreach ($opps as &$o) {
            $own = $o['kind'] === 'search' ? array_column(EventDesk::mapped($o['text']), 'id')
                : array_merge([], ...array_map(fn($ev) => $ids[$ev] ?? [], $o['events'] ?? [$o['event']]));
            $count = array_count_values(array_map('intval', $own));
            arsort($count);
            $p += self::products(array_keys($count));
            $o['products'] = array_values(array_filter(array_map(fn($id) => $p[$id] ?? null, array_keys($count))));
            // The product the issue names comes first and becomes the task's product: the first two words of its name in the text
            $named = array_filter($o['products'], fn($x) => mb_stripos($o['text'], implode(' ', array_slice(preg_split('/\s+/u', $x['name']) ?: [], 0, 2))) !== false);
            $o['products'] = array_slice([...$named, ...array_filter($o['products'], fn($x) => !in_array($x, $named, true))], 0, 4);
            $o['product'] = $o['kind'] === 'search' ? ($o['products'][0]['id'] ?? null) : (array_values($named)[0]['id'] ?? null);
            unset($o['events']);
        }
        unset($o);
        return $opps;
    }

    private static function preview(?string $s): ?string
    {
        if ($s === null || trim($s) === '') {
            return null;
        }
        $s = trim((string) preg_replace('/\s+/u', ' ', ProductRef::resolve($s)));
        return mb_strlen($s) > 220 ? mb_substr($s, 0, 219) . '…' : $s;
    }
}
