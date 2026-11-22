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
    /** A password reset link works this long, and once. */
    public const RESET_MINUTES = 30;

    /** Older databases lack the columns. Added on the first request, no separate migration. */
    public static function ensure(): void
    {
        if (!db()->query("SHOW COLUMNS FROM users LIKE 'reset_hash'")->fetch()) {
            db()->exec('ALTER TABLE users
                ADD COLUMN IF NOT EXISTS failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
                ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL,
                ADD COLUMN IF NOT EXISTS reset_hash CHAR(64) NULL,
                ADD COLUMN IF NOT EXISTS reset_until TIMESTAMP NULL');
        }
        if (!db()->query("SHOW COLUMNS FROM users LIKE 'terms_at'")->fetch()) {
            // When the account holder accepted the terms (feature 046); null for accounts made before
            db()->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS terms_at TIMESTAMP NULL');
        }
    }

    /** The user if email and password match and the account isn't locked, otherwise null. */
    public static function attempt(string $email, string $password): ?array
    {
        $st = db()->prepare('SELECT *, (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked FROM users WHERE email = ? AND deleted_at IS NULL');
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u) {
            Audit::log('sikkerhet', 'login_fail', 'ukjent e-post', [], 0);
            return null;
        }
        $who = 'konto #' . $u['id'];
        if ($u['is_locked']) {
            Audit::log('sikkerhet', 'login_blocked', $who, [], 0);
            return null;
        }
        if (!password_verify($password, $u['password_hash'])) {
            db()->prepare('UPDATE users SET failed_logins = failed_logins + 1 WHERE id = ?')->execute([$u['id']]);
            $lock = db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NOW() + INTERVAL ' . self::LOCK_MINUTES . ' MINUTE
                WHERE id = ? AND failed_logins >= ' . self::MAX_FAILS);
            $lock->execute([$u['id']]);
            Audit::log('sikkerhet', 'login_fail', $who, [], 0);
            if ($lock->rowCount()) {
                Audit::log('sikkerhet', 'lockout', $who, ['minutes' => self::LOCK_MINUTES], 0);
            }
            return null;
        }
        db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([$u['id']]);
        Audit::log('sikkerhet', 'login_ok', $who, [], (int) $u['id']);
        return $u;
    }

    // ---------- Password reset by email ----------
    // Only a SHA-256 hash of the token is stored, so a copy of the database cannot be used to reset a password.
    // The token is 32 random bytes, works for RESET_MINUTES and only once; a new request replaces the old one.

    /** A reset token for an active account, or null when there is none. The caller answers the same in both cases. */
    public static function startReset(string $email): ?array
    {
        $st = db()->prepare('SELECT id, email, name FROM users WHERE email = ? AND deleted_at IS NULL');
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u) {
            Audit::log('sikkerhet', 'reset_request', 'ukjent e-post', [], 0);
            return null;
        }
        Audit::log('sikkerhet', 'reset_request', 'konto #' . $u['id'], [], 0);
        $token = bin2hex(random_bytes(32));
        db()->prepare('UPDATE users SET reset_hash = ?, reset_until = NOW() + INTERVAL ' . self::RESET_MINUTES . ' MINUTE WHERE id = ?')
            ->execute([hash('sha256', $token), $u['id']]);
        return ['token' => $token, 'email' => $u['email'], 'name' => $u['name']];
    }

    /** The user ID a token belongs to while it is valid. */
    public static function resetUser(string $token): ?int
    {
        $st = db()->prepare('SELECT id FROM users WHERE reset_hash = ? AND reset_until > NOW() AND deleted_at IS NULL');
        $st->execute([hash('sha256', $token)]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Sets the new password, uses up the token and lifts a lock. False when the token is not valid (any more). */
    public static function finishReset(string $token, string $password): bool
    {
        $id = self::resetUser($token);
        if ($id === null) {
            return false;
        }
        db()->prepare('UPDATE users SET password_hash = ?, reset_hash = NULL, reset_until = NULL, failed_logins = 0, locked_until = NULL WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_ARGON2ID), $id]);
        Audit::log('sikkerhet', 'reset_done', 'konto #' . $id, [], $id);
        return true;
    }
}
