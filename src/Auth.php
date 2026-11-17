<?php
declare(strict_types=1);

/**
 * Protection against password guessing: five failures in a row lock the account for 15 minutes.
 * A locked account gives the same message as a wrong password, so nobody can tell whether the email exists or is locked.
 */
final class Auth
{
    public const MAX_FAILS = 5;
    public const LOCK_MINUTES = 15;

    /** Older databases lack the columns. Added on the first request, no separate migration. */
    public static function ensure(): void
    {
        if (!db()->query("SHOW COLUMNS FROM users LIKE 'locked_until'")->fetch()) {
            db()->exec('ALTER TABLE users
                ADD COLUMN IF NOT EXISTS failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
                ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL');
        }
    }

    /** The user if email and password match and the account isn't locked, otherwise null. */
    public static function attempt(string $email, string $password): ?array
    {
        $st = db()->prepare('SELECT *, (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked FROM users WHERE email = ? AND deleted_at IS NULL');
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u || $u['is_locked']) {
            return null;
        }
        if (!password_verify($password, $u['password_hash'])) {
            db()->prepare('UPDATE users SET failed_logins = failed_logins + 1 WHERE id = ?')->execute([$u['id']]);
            db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NOW() + INTERVAL ' . self::LOCK_MINUTES . ' MINUTE
                WHERE id = ? AND failed_logins >= ' . self::MAX_FAILS)->execute([$u['id']]);
            return null;
        }
        db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([$u['id']]);
        return $u;
    }
}
