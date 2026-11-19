<?php
declare(strict_types=1);

/**
 * The logged in customer's own orders and error reports, for the settings page, the order page and the chat.
 * Every query is limited to the user's own rows.
 */
final class Account
{
    /** Right of withdrawal (angrerettloven § 20): 14 days from receipt. The demo has no delivery, so it counts from the order date. */
    public const WITHDRAW_DAYS = 14;

    /** Orders, newest first, with store name and number of items. */
    public static function orders(int $uid): array
    {
        $st = db()->prepare('SELECT o.*, w.name warehouse, (SELECT SUM(qty) FROM order_items i WHERE i.order_id = o.id) items,
                (SELECT p.name FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = o.id ORDER BY i.price_ore * i.qty DESC LIMIT 1) first_item
            FROM orders o LEFT JOIN warehouses w ON w.id = o.warehouse_id WHERE o.user_id = ? ORDER BY o.id DESC');
        $st->execute([$uid]);
        return $st->fetchAll();
    }

    /** One order with its lines, or null if it doesn't exist or belongs to someone else. */
    public static function order(int $uid, int $id): ?array
    {
        $st = db()->prepare('SELECT o.*, w.name warehouse FROM orders o LEFT JOIN warehouses w ON w.id = o.warehouse_id WHERE o.id = ? AND o.user_id = ?');
        $st->execute([$id, $uid]);
        $o = $st->fetch();
        if (!$o) {
            return null;
        }
        $st = db()->prepare('SELECT i.product_id, i.qty, i.price_ore, p.name, p.slug FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = ?');
        $st->execute([$id]);
        $o['lines'] = $st->fetchAll();
        return $o;
    }

    /** Last day of the withdrawal period. */
    public static function withdrawUntil(array $o): DateTimeImmutable
    {
        return (new DateTimeImmutable((string) $o['created_at']))->modify('+' . self::WITHDRAW_DAYS . ' days')->setTime(23, 59, 59);
    }

    public static function canWithdraw(array $o): bool
    {
        return $o['status'] === 'betalt' && new DateTimeImmutable() <= self::withdrawUntil($o);
    }

    /** Error reports the user has sent, newest first, with the product and the name of the value. */
    public static function reports(int $uid): array
    {
        $st = db()->prepare('SELECT r.id, r.`key`, r.value_text, r.reason, r.status, r.created_at, p.name product, p.slug, k.label, k.unit
            FROM spec_reports r JOIN products p ON p.id = r.product_id LEFT JOIN spec_keys k ON k.`key` = r.`key`
            WHERE r.user_id = ? ORDER BY r.id DESC');
        $st->execute([$uid]);
        return $st->fetchAll();
    }

    /** Takes back a report staff haven't handled yet. Returns false if it isn't the user's or is already handled. */
    public static function withdrawReport(int $uid, int $id): bool
    {
        $st = db()->prepare("DELETE FROM spec_reports WHERE id = ? AND user_id = ? AND status = 'apen'");
        $st->execute([$id, $uid]);
        return $st->rowCount() > 0;
    }
}
