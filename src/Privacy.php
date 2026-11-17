<?php
declare(strict_types=1);

/**
 * Privacy (GDPR art. 25, data protection by design and by default).
 *
 * Full name, email and phone are never shown publicly. Contributions and reviews are shown with an alias:
 * the default is "Kjøper #a1b2c3" (a truncated SHA-256 of a random salt per user and the user ID),
 * or the first name if the user chooses that under Settings.
 * The alias is computed in SQL, so the full name never leaves the database for public views.
 */
final class Privacy
{
    public const DELETED = 'Slettet bruker';

    /** Adds the columns and the table in databases built before the privacy change. */
    public static function ensure(): void
    {
        $pdo = db();
        // The order matters: a database from before the privacy change lacks avatar, and theme is added after it
        if (!$pdo->query("SHOW TABLES LIKE 'spec_reports'")->fetch()) {
            self::privacyColumns($pdo);
        }
        // Light or dark mode is a personal setting (came after the privacy change)
        if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'theme'")->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS theme ENUM('system','lys','mork') NOT NULL DEFAULT 'system' AFTER avatar");
        }
    }

    /** The privacy change. spec_reports is created last, so it works as a marker that this is done. */
    private static function privacyColumns(PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE users
            ADD COLUMN IF NOT EXISTS phone VARCHAR(32) NULL AFTER name,
            ADD COLUMN IF NOT EXISTS display_name ENUM('anonym','fornavn') NOT NULL DEFAULT 'anonym' AFTER phone,
            ADD COLUMN IF NOT EXISTS alias_salt CHAR(16) NULL AFTER display_name,
            ADD COLUMN IF NOT EXISTS avatar VARCHAR(64) NULL AFTER alias_salt,
            ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL");
        $pdo->exec("UPDATE users SET alias_salt = LEFT(SHA2(CONCAT(RAND(), id, UUID()), 256), 16) WHERE alias_salt IS NULL");
        // Older approvals wrote the staff member's name into the source text, which is public
        $pdo->exec("UPDATE spec_values SET evidence = REGEXP_REPLACE(evidence, '^Godkjent av [^.]*\\\\.', 'Godkjent av en ansatt.')
            WHERE evidence LIKE 'Godkjent av %' AND evidence NOT LIKE 'Godkjent av en ansatt%'");
        $pdo->exec("UPDATE analytics_events SET user_id = NULL WHERE user_id IS NOT NULL");
        $pdo->exec(substr(file_get_contents(APP_ROOT . '/db/schema.sql'), strpos(file_get_contents(APP_ROOT . '/db/schema.sql'), 'CREATE TABLE spec_reports')));
    }

    /** SQL expression for the user's public alias in table alias $t. Gives NULL when there is no user. */
    public static function sql(string $t = 'u'): string
    {
        return "CASE WHEN $t.id IS NULL THEN NULL WHEN $t.deleted_at IS NOT NULL THEN '" . self::DELETED . "'"
            . " WHEN $t.display_name = 'fornavn' THEN SUBSTRING_INDEX(TRIM(REPLACE($t.name, '(demo)', '')), ' ', 1)"
            . " ELSE CONCAT('Kjøper #', LEFT(SHA2(CONCAT($t.alias_salt, $t.id), 256), 6)) END";
    }

    /** The same alias computed in PHP, for the user's own preview. */
    public static function alias(array $u, ?string $mode = null): string
    {
        if (!empty($u['deleted_at'])) {
            return self::DELETED;
        }
        if (($mode ?? $u['display_name'] ?? 'anonym') === 'fornavn') {
            return explode(' ', trim(str_replace('(demo)', '', (string) $u['name'])))[0];
        }
        return 'Kjøper #' . substr(hash('sha256', ($u['alias_salt'] ?? '') . $u['id']), 0, 6);
    }

    public static function newSalt(): string
    {
        return bin2hex(random_bytes(8));
    }

    // ---------- Profile picture ----------

    public const AVATAR_MAX = 3 * 1024 * 1024;

    public static function avatarDir(): string
    {
        $d = APP_ROOT . '/data/avatars';
        if (!is_dir($d)) {
            mkdir($d, 0775, true);
        }
        return $d;
    }

    /**
     * Saves an uploaded profile picture (JPEG or PNG) and returns the file name.
     * Metadata like EXIF (can contain GPS location and camera serial number) is removed before saving.
     * @throws RuntimeException with a message that can be shown to the user
     */
    public static function saveAvatar(string $tmp, int $size): string
    {
        if ($size <= 0 || $size > self::AVATAR_MAX) {
            throw new RuntimeException('Bildet må være mindre enn 3 MB.');
        }
        $info = @getimagesize($tmp);
        $type = $info[2] ?? 0;
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new RuntimeException('Bruk et JPEG- eller PNG-bilde.');
        }
        if ($info[0] > 6000 || $info[1] > 6000) {
            throw new RuntimeException('Bildet er for stort. Maks 6000 × 6000 piksler.');
        }
        $bytes = (string) file_get_contents($tmp);
        $clean = $type === IMAGETYPE_JPEG ? self::stripJpeg($bytes) : self::stripPng($bytes);
        if ($clean === null) {
            throw new RuntimeException('Klarte ikke å lese bildet. Prøv et annet.');
        }
        $name = bin2hex(random_bytes(12)) . ($type === IMAGETYPE_JPEG ? '.jpg' : '.png');
        file_put_contents(self::avatarDir() . '/' . $name, $clean);
        return $name;
    }

    public static function deleteAvatar(?string $name): void
    {
        if ($name && preg_match('/^[a-f0-9]{24}\.(jpg|png)$/', $name)) {
            @unlink(self::avatarDir() . '/' . $name);
        }
    }

    /** Removes APP1 (EXIF, XMP), APP13 (IPTC) and comments from a JPEG. Image data and color profile are kept. */
    public static function stripJpeg(string $b): ?string
    {
        if (substr($b, 0, 2) !== "\xFF\xD8") {
            return null;
        }
        $out = "\xFF\xD8";
        $i = 2;
        $n = strlen($b);
        while ($i + 4 <= $n) {
            if ($b[$i] !== "\xFF") {
                return null;
            }
            $m = ord($b[$i + 1]);
            if ($m === 0xFF) { // fill byte
                $i++;
                continue;
            }
            if ($m === 0xDA) { // start of image data: the rest is copied as is
                return $out . substr($b, $i);
            }
            $len = (ord($b[$i + 2]) << 8) | ord($b[$i + 3]);
            if ($len < 2 || $i + 2 + $len > $n) {
                return null;
            }
            if (!in_array($m, [0xE1, 0xED, 0xFE], true)) {
                $out .= substr($b, $i, 2 + $len);
            }
            $i += 2 + $len;
        }
        return null;
    }

    /** Removes text, time and EXIF chunks from a PNG. */
    public static function stripPng(string $b): ?string
    {
        if (substr($b, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }
        $out = substr($b, 0, 8);
        $i = 8;
        $n = strlen($b);
        while ($i + 12 <= $n) {
            $len = unpack('N', substr($b, $i, 4))[1];
            $type = substr($b, $i + 4, 4);
            if ($i + 12 + $len > $n) {
                return null;
            }
            if (!in_array($type, ['eXIf', 'tEXt', 'iTXt', 'zTXt', 'tIME'], true)) {
                $out .= substr($b, $i, 12 + $len);
            }
            $i += 12 + $len;
            if ($type === 'IEND') {
                return $out;
            }
        }
        return null;
    }
}
