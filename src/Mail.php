<?php
declare(strict_types=1);

/**
 * The demo inbox. The prototype sends no real email: everything that would be sent lands in mail_outbox and is shown
 * on /innboks. The inbox icon in the top bar counts the messages the user hasn't opened yet.
 */
final class Mail
{
    /** Adds read_at in databases built before the unread counter. */
    public static function ensure(): void
    {
        if (!db()->query("SHOW COLUMNS FROM mail_outbox LIKE 'read_at'")->fetch()) {
            db()->exec('ALTER TABLE mail_outbox ADD COLUMN IF NOT EXISTS read_at TIMESTAMP NULL');
        }
    }

    /** @return int the message ID */
    public static function send(string $to, string $subject, string $html): int
    {
        db()->prepare('INSERT INTO mail_outbox (to_email, subject, body_html) VALUES (?,?,?)')->execute([$to, $subject, $html]);
        return (int) db()->lastInsertId();
    }

    /** Given messages by ID, for a visitor who is not logged in (the reset mail they just asked for in this session). */
    public static function byIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        return db()->query('SELECT * FROM mail_outbox WHERE id IN (' . implode(',', $ids) . ') ORDER BY id DESC')->fetchAll();
    }

    public static function unread(string $to): int
    {
        $st = db()->prepare('SELECT COUNT(*) FROM mail_outbox WHERE to_email = ? AND read_at IS NULL');
        $st->execute([$to]);
        return (int) $st->fetchColumn();
    }

    /** All messages, newest first. Opening the inbox marks them as read; the list still shows which ones were new. */
    public static function open(string $to): array
    {
        $st = db()->prepare('SELECT * FROM mail_outbox WHERE to_email = ? ORDER BY id DESC');
        $st->execute([$to]);
        $mails = $st->fetchAll();
        db()->prepare('UPDATE mail_outbox SET read_at = NOW() WHERE to_email = ? AND read_at IS NULL')->execute([$to]);
        return $mails;
    }
}
