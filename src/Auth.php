<?php
declare(strict_types=1);

/**
 * Vern mot gjetting av passord: fem feil på rad låser kontoen i 15 minutter.
 * Låst konto gir samme melding som feil passord, så ingen kan se om e-posten finnes eller er låst.
 */
final class Auth
{
    public const MAX_FAILS = 5;
    public const LOCK_MINUTES = 15;

    /** Eldre databaser mangler kolonnene. Legges til ved første forespørsel, ingen egen migrering. */
    public static function ensure(): void
    {
        if (!db()->query("SHOW COLUMNS FROM users LIKE 'locked_until'")->fetch()) {
            db()->exec('ALTER TABLE users
                ADD COLUMN IF NOT EXISTS failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
                ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL');
        }
    }

    /** Brukeren hvis e-post og passord stemmer og kontoen ikke er låst, ellers null. */
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
