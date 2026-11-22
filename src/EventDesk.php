<?php
declare(strict_types=1);

/**
 * Staff actions on one analytics event in the Control Tower (specs/022-control-tower): related searches, mark as
 * reviewed, map a search term to a product, and raise a product improvement task. The event itself is never changed.
 */
final class EventDesk
{
    /** Related searches shown for one event. */
    public const RELATED = 20;

    /** Creates the tables on databases built before these actions existed. */
    public static function ensure(PDO $pdo): void
    {
        if ($pdo->query("SHOW TABLES LIKE 'improvement_tasks'")->fetch() && $pdo->query("SHOW TABLES LIKE 'event_reviews'")->fetch()) {
            // Review outcome (feature 043, CONTROLTOWER-TRIAGE.md): reviewed without a task, or ignored as noise
            if (!$pdo->query("SHOW COLUMNS FROM event_reviews LIKE 'outcome'")->fetch()) {
                $pdo->exec("ALTER TABLE event_reviews ADD COLUMN outcome ENUM('vurdert','ignorert') NOT NULL DEFAULT 'vurdert' AFTER user_id");
            }
            return;
        }
        $sql = (string) file_get_contents(APP_ROOT . '/db/schema.sql');
        foreach (['event_reviews', 'improvement_tasks'] as $t) {
            if (preg_match('/CREATE TABLE ' . $t . ' \(.*?\) ENGINE=InnoDB[^;]*;/s', $sql, $m)) {
                $pdo->exec(str_replace("CREATE TABLE $t", "CREATE TABLE IF NOT EXISTS $t", $m[0]));
            }
        }
    }

    /** The search term of an event, lower case, or null when the event has none. */
    public static function term(array $data): ?string
    {
        $q = mb_strtolower(trim((string) ($data['q'] ?? '')));
        return $q === '' ? null : mb_substr($q, 0, 80);
    }

    /** Review state, related searches, the current product mapping for the term and tasks raised from the event. */
    public static function detail(int $id): ?array
    {
        $st = db()->prepare('SELECT id, type, data FROM analytics_events WHERE id = ?');
        $st->execute([$id]);
        $e = $st->fetch();
        if (!$e) {
            return null;
        }
        $term = self::term(json_decode((string) $e['data'], true) ?: []);

        $st = db()->prepare('SELECT UNIX_TIMESTAMP(r.reviewed_at) at, ' . Privacy::sql() . ' who
            FROM event_reviews r LEFT JOIN users u ON u.id = r.user_id WHERE r.event_id = ?');
        $st->execute([$id]);
        $review = $st->fetch() ?: null;

        $related = [];
        $mapped = [];
        if ($term !== null) {
            // Same term, any search type, newest first. Only the counts and times leave the server, never the session.
            $st = db()->prepare("SELECT id, type, UNIX_TIMESTAMP(created_at) at, CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.n')) AS UNSIGNED) n
                FROM analytics_events WHERE type IN ('search', 'search_zero') AND id <> ?
                AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, '$.q'))) = ? ORDER BY id DESC LIMIT " . self::RELATED);
            $st->execute([$id, $term]);
            $related = $st->fetchAll();
            $mapped = self::mapped($term);
        }

        $st = db()->prepare('SELECT t.id, t.title, t.status, t.product_id, p.name product, UNIX_TIMESTAMP(t.created_at) at
            FROM improvement_tasks t LEFT JOIN products p ON p.id = t.product_id WHERE t.event_id = ? ORDER BY t.id');
        $st->execute([$id]);

        return ['id' => (int) $e['id'], 'term' => $term, 'review' => $review, 'related' => $related, 'mapped' => $mapped, 'tasks' => $st->fetchAll()];
    }

    /** Products a search term is mapped to (search_synonyms entries of the form produkt:ID). */
    public static function mapped(string $term): array
    {
        $st = db()->prepare("SELECT expands FROM search_synonyms WHERE term = ? AND kind = 'synonym'");
        $st->execute([$term]);
        preg_match_all('/(?:^|\s)produkt:(\d+)/', (string) $st->fetchColumn(), $m);
        if (!$m[1]) {
            return [];
        }
        $ids = array_map('intval', $m[1]);
        $st = db()->prepare('SELECT id, name FROM products WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY name');
        $st->execute($ids);
        return $st->fetchAll();
    }

    public static function review(int $event, int $user, string $outcome = 'vurdert'): bool
    {
        if (!self::exists($event) || !in_array($outcome, ['vurdert', 'ignorert'], true)) {
            return false;
        }
        db()->prepare('INSERT INTO event_reviews (event_id, user_id, outcome) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), outcome = VALUES(outcome), reviewed_at = CURRENT_TIMESTAMP')->execute([$event, $user, $outcome]);
        return true;
    }

    /**
     * The review state of an observation made of these events (CONTROLTOWER-TRIAGE.md 3), kept apart from task status:
     * oppgave when a task was deliberately created from one of them, else the latest review outcome, else ikke (not reviewed).
     * @param list<int> $events
     * @param array<int, array{0: string, 1: int}> $reviews event id => [outcome, unix time], from reviews()
     */
    public static function state(array $events, array $reviews, bool $hasTask): string
    {
        if ($hasTask) {
            return 'oppgave';
        }
        $last = null;
        foreach ($events as $e) {
            if (isset($reviews[$e]) && ($last === null || $reviews[$e][1] >= $last[1])) {
                $last = $reviews[$e];
            }
        }
        return $last[0] ?? 'ikke';
    }

    /** Review outcomes for these events: event id => [outcome, unix time]. */
    public static function reviews(array $events): array
    {
        $in = implode(',', array_merge([0], array_map('intval', $events)));
        $out = [];
        foreach (db()->query("SELECT event_id, outcome, UNIX_TIMESTAMP(reviewed_at) t FROM event_reviews WHERE event_id IN ($in)") as $r) {
            $out[(int) $r['event_id']] = [$r['outcome'], (int) $r['t']];
        }
        return $out;
    }

    /**
     * Maps the event's search term to a product, so the next search for it shows that product first. An existing
     * synonym for the term keeps its expansions; the product is added to them once.
     */
    public static function map(int $event, int $product): bool
    {
        $st = db()->prepare('SELECT data FROM analytics_events WHERE id = ?');
        $st->execute([$event]);
        $data = $st->fetchColumn();
        $term = $data === false ? null : self::term(json_decode((string) $data, true) ?: []);
        $p = db()->prepare('SELECT 1 FROM products WHERE id = ?');
        $p->execute([$product]);
        if ($term === null || !$p->fetchColumn()) {
            return false;
        }
        $add = "produkt:$product";
        db()->prepare("INSERT INTO search_synonyms (term, expands, kind) VALUES (?, ?, 'synonym')
            ON DUPLICATE KEY UPDATE expands = IF(CONCAT(' ', expands, ' ') LIKE CONCAT('% ', VALUES(expands), ' %'), expands, CONCAT(expands, ' ', VALUES(expands)))")
            ->execute([$term, $add]);
        return true;
    }

    /** Raises a task. The title is required; the product is optional and must exist when given. */
    public static function task(int $event, ?int $product, string $title, int $user): ?int
    {
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '' || !self::exists($event)) {
            return null;
        }
        if ($product !== null) {
            $p = db()->prepare('SELECT 1 FROM products WHERE id = ?');
            $p->execute([$product]);
            if (!$p->fetchColumn()) {
                return null;
            }
        }
        // A failed search is a search task; a guide or Byggelab need is an opportunity. The origin stops a second task for the same event.
        $st = db()->prepare('SELECT type FROM analytics_events WHERE id = ?');
        $st->execute([$event]);
        $source = $st->fetchColumn() === 'search_zero' ? 'sok' : 'mulighet';
        $r = Gulv::create(['event' => $event, 'product' => $product, 'title' => $title, 'source' => $source, 'origin' => 'hendelse:' . $event], $user);
        return $r['id'] ?? null;
    }

    /** Marks an active task as done (Fullført). Kept for the first version's "Lukk" action. */
    public static function close(int $task, int $user): bool
    {
        $st = db()->prepare("UPDATE improvement_tasks SET status = 'fullfort', closed_by = ? WHERE id = ? AND status IN ('apen','under_behandling','klar_for_kontroll')");
        $st->execute([$user, $task]);
        return $st->rowCount() > 0;
    }

    private static function exists(int $event): bool
    {
        $st = db()->prepare('SELECT 1 FROM analytics_events WHERE id = ?');
        $st->execute([$event]);
        return (bool) $st->fetchColumn();
    }
}
