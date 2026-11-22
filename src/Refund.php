<?php
declare(strict_types=1);

/**
 * Refunds from a customer service ticket (feature 022). Staff refund a whole order or chosen lines of an order that
 * belongs to the ticket's customer. Each refunded line is a row in order_refunds; the units go back to the online stock
 * of the order's warehouse; the order becomes 'refundert' once every unit is refunded; the customer gets a message in
 * the ticket. Payment is a demo: no money moves, and the message says so.
 */
final class Refund
{
    /** Table and status added by this feature on databases built before it. */
    public static function ensure(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS order_refunds (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id   INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            qty        SMALLINT UNSIGNED NOT NULL,
            amount_ore INT UNSIGNED NOT NULL,
            ticket_id  INT UNSIGNED NULL,
            user_id    INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY (order_id),
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB');
        $col = $pdo->query("SHOW COLUMNS FROM orders LIKE 'status'")->fetch();
        if ($col && !str_contains((string) $col['Type'], 'refundert')) {
            $pdo->exec("ALTER TABLE orders MODIFY status ENUM('betalt','kansellert','refundert') NOT NULL DEFAULT 'betalt'");
        }
    }

    /**
     * Checks a request against what was bought and what is already refunded. Pure: no database.
     * @param array<int, array{qty: int, price_ore: int}> $bought   product_id => line
     * @param array<int, int>                             $refunded product_id => units already refunded
     * @param array<int, int>|null                        $want     product_id => units, or null for everything left
     * @return array{lines: list<array{product_id: int, qty: int, amount_ore: int}>, total_ore: int, complete: bool, error: ?string}
     */
    public static function plan(array $bought, array $refunded, ?array $want): array
    {
        $left = [];
        foreach ($bought as $pid => $l) {
            $left[$pid] = max(0, (int) $l['qty'] - (int) ($refunded[$pid] ?? 0));
        }
        $want ??= array_filter($left);
        $lines = [];
        foreach ($want as $pid => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) {
                continue;
            }
            if (!isset($bought[$pid])) {
                return ['lines' => [], 'total_ore' => 0, 'complete' => false, 'error' => 'Varen er ikke på ordren'];
            }
            if ($qty > $left[$pid]) {
                return ['lines' => [], 'total_ore' => 0, 'complete' => false, 'error' => 'Flere enheter enn det som kan refunderes'];
            }
            $lines[] = ['product_id' => (int) $pid, 'qty' => $qty, 'amount_ore' => $qty * (int) $bought[$pid]['price_ore']];
            $left[$pid] -= $qty;
        }
        if (!$lines) {
            return ['lines' => [], 'total_ore' => 0, 'complete' => false, 'error' => 'Ingenting å refundere'];
        }
        return ['lines' => $lines, 'total_ore' => array_sum(array_column($lines, 'amount_ore')), 'complete' => !array_filter($left), 'error' => null];
    }

    /** The ticket customer's orders with each line's refunded units. Guests have no orders. */
    public static function orders(int $ticketId): array
    {
        $pdo = db();
        $st = $pdo->prepare("SELECT o.id, o.status, o.payment, o.total_ore, o.is_demo_seed demo, UNIX_TIMESTAMP(o.created_at) at
            FROM orders o JOIN support_tickets t ON t.user_id = o.user_id WHERE t.id = ? ORDER BY o.id DESC LIMIT 10");
        $st->execute([$ticketId]);
        $orders = $st->fetchAll();
        $items = $pdo->prepare('SELECT i.product_id, p.name, i.qty, i.price_ore,
                (SELECT COALESCE(SUM(r.qty), 0) FROM order_refunds r WHERE r.order_id = i.order_id AND r.product_id = i.product_id) refunded,
                (SELECT path_m FROM product_images m WHERE m.product_id = i.product_id ORDER BY m.sort, m.id LIMIT 1) img
            FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = ?');
        foreach ($orders as &$o) {
            $items->execute([$o['id']]);
            $o['items'] = $items->fetchAll();
        }
        return $orders;
    }

    /**
     * Refunds lines of an order from a ticket. $want null refunds everything not yet refunded.
     * @param array<int, int>|null $want product_id => units
     * @return array{ok: bool, error?: string, total_ore?: int, complete?: bool}
     */
    public static function apply(int $ticketId, int $orderId, ?array $want, int $staffId): array
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // The order must belong to the ticket's customer; the row lock keeps two refunds from passing the same check
            $st = $pdo->prepare("SELECT o.id, o.status, o.payment, o.warehouse_id FROM orders o JOIN support_tickets t ON t.user_id = o.user_id
                WHERE o.id = ? AND t.id = ? FOR UPDATE");
            $st->execute([$orderId, $ticketId]);
            $o = $st->fetch();
            if (!$o || $o['status'] !== 'betalt') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => $o ? 'Ordren kan ikke refunderes' : 'Ordren hører ikke til denne kunden'];
            }
            $st = $pdo->prepare('SELECT i.product_id, i.qty, i.price_ore, p.name FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = ?');
            $st->execute([$orderId]);
            $bought = [];
            $names = [];
            foreach ($st as $r) {
                $bought[(int) $r['product_id']] = ['qty' => (int) $r['qty'], 'price_ore' => (int) $r['price_ore']];
                $names[(int) $r['product_id']] = $r['name'];
            }
            $st = $pdo->prepare('SELECT product_id, SUM(qty) FROM order_refunds WHERE order_id = ? GROUP BY product_id');
            $st->execute([$orderId]);
            $refunded = array_map('intval', $st->fetchAll(\PDO::FETCH_KEY_PAIR));
            $plan = self::plan($bought, $refunded, $want);
            if ($plan['error'] !== null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => $plan['error']];
            }
            $ins = $pdo->prepare('INSERT INTO order_refunds (order_id, product_id, qty, amount_ore, ticket_id, user_id) VALUES (?,?,?,?,?,?)');
            $stock = $pdo->prepare('INSERT INTO stock (product_id, warehouse_id, online) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE online = online + VALUES(online)');
            foreach ($plan['lines'] as $l) {
                $ins->execute([$orderId, $l['product_id'], $l['qty'], $l['amount_ore'], $ticketId, $staffId]);
                if ($o['warehouse_id'] !== null) {
                    $stock->execute([$l['product_id'], $o['warehouse_id'], $l['qty']]);
                }
            }
            if ($plan['complete']) {
                $pdo->prepare("UPDATE orders SET status = 'refundert' WHERE id = ?")->execute([$orderId]);
            }
            $kr = fn(int $ore) => number_format($ore / 100, 2, ',', ' ') . ' kr';
            $list = implode(', ', array_map(fn($l) => "{$l['qty']} × {$names[$l['product_id']]}", $plan['lines']));
            $body = "Vi har refundert {$kr($plan['total_ore'])} for ordre $orderId: $list. Beløpet går tilbake med samme betalingsmåte ({$o['payment']}). "
                . 'Dette er en demobutikk, så ingen penger er trukket eller sendt.';
            $pdo->prepare("INSERT INTO support_messages (ticket_id, sender, user_id, body) VALUES (?, 'support', ?, ?)")->execute([$ticketId, $staffId, $body]);
            $pdo->prepare("UPDATE support_tickets SET status = 'pagar', assigned_to = COALESCE(assigned_to, ?) WHERE id = ? AND status = 'apen'")->execute([$staffId, $ticketId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        emit("ticket:$ticketId", 'message', ['from' => 'support', 'name' => 'Kundeservice', 'body' => $body]);
        emit('stock', 'refund', ['order' => $orderId, 'ticket' => $ticketId, 'units' => array_sum(array_column($plan['lines'], 'qty'))]);
        return ['ok' => true, 'total_ore' => $plan['total_ore'], 'complete' => $plan['complete']];
    }
}
