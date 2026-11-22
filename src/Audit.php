<?php
declare(strict_types=1);

/**
 * Audit log for the admin page: security events (logins, lockouts, password resets), staff actions (merges, refunds,
 * mappings, closed cases) and operations (backups). One row per event, never changed afterwards.
 *
 * Data minimisation: no IP address is stored, only a short hash of it that changes every day. It is enough to see that
 * many failed logins come from one place today, and cannot be followed from one day to the next. A failed login on an
 * unknown address stores "ukjent e-post", never the address that was typed.
 */
final class Audit
{
    public const KINDS = ['sikkerhet', 'admin', 'drift'];

    public static function ensure(): void
    {
        db()->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            kind    ENUM('sikkerhet','admin','drift') NOT NULL,
            action  VARCHAR(40) NOT NULL,
            user_id INT UNSIGNED NULL,
            target  VARCHAR(120) NULL,
            ip_hash CHAR(12) NULL,
            detail  JSON NULL,
            KEY (kind, at),
            KEY (action, at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** The day's hash of the client address, or null outside a web request (scripts). */
    public static function ipHash(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return $ip === null ? null : substr(hash('sha256', $ip . '|' . date('Y-m-d') . '|' . env('APP_SECRET', 'sorpedia')), 0, 12);
    }

    /** $userId null: the logged in user, if any. 0: nobody (for example a failed login, whoever is logged in). */
    public static function log(string $kind, string $action, ?string $target = null, array $detail = [], ?int $userId = null): void
    {
        $userId ??= isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
        $userId = $userId === 0 ? null : $userId;
        db()->prepare('INSERT INTO audit_log (kind, action, user_id, target, ip_hash, detail) VALUES (?,?,?,?,?,?)')
            ->execute([$kind, $action, $userId, $target === null ? null : mb_substr($target, 0, 120), self::ipHash(),
                $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null]);
    }

    /** Newest rows of one kind, with the actor's alias (never the name). */
    public static function recent(string $kind, int $limit = 50): array
    {
        $st = db()->prepare('SELECT a.id, UNIX_TIMESTAMP(a.at) at, a.action, a.target, a.ip_hash, a.detail, a.user_id, u.role
            FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.kind = ? ORDER BY a.id DESC LIMIT ' . max(1, min(200, $limit)));
        $st->execute([$kind]);
        return array_map(fn($r) => $r + ['who' => $r['user_id'] ? '#' . $r['user_id'] . ($r['role'] ? " ({$r['role']})" : '') : null], $st->fetchAll());
    }

    /**
     * Signs of password guessing in the last 24 hours: failed logins and lockouts, and the places (day hashes) with
     * at least Auth::MAX_FAILS failures, most first.
     */
    public static function bruteForce(): array
    {
        $pdo = db();
        $count = fn(string $action) => (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = '$action' AND at >= NOW() - INTERVAL 1 DAY")->fetchColumn();
        $places = $pdo->query("SELECT ip_hash, COUNT(*) n, COUNT(DISTINCT target) accounts, UNIX_TIMESTAMP(MAX(at)) last FROM audit_log
            WHERE action = 'login_fail' AND at >= NOW() - INTERVAL 1 DAY AND ip_hash IS NOT NULL
            GROUP BY ip_hash HAVING n >= " . Auth::MAX_FAILS . ' ORDER BY n DESC LIMIT 10')->fetchAll();
        $locked = $pdo->query('SELECT id, role, UNIX_TIMESTAMP(locked_until) until FROM users WHERE locked_until > NOW() ORDER BY locked_until DESC')->fetchAll();
        return ['fails' => $count('login_fail'), 'lockouts' => $count('lockout'), 'resets' => $count('reset_request'), 'places' => $places, 'locked' => $locked];
    }
}
