<?php
declare(strict_types=1);

/** Draft sources from the database. Product facts come from Chat::facts, as in the chat. See DraftStore. */
final class PdoDraftStore implements DraftStore
{
    public function __construct(private PDO $pdo)
    {
    }

    public function ticket(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT id, product_id, summary, chat_log FROM support_tickets WHERE id = ? AND status IN ('apen','pagar')");
        $st->execute([$id]);
        $t = $st->fetch();
        if (!$t) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT sender, body FROM support_messages WHERE ticket_id = ? ORDER BY id DESC LIMIT ' . DraftPrompt::THREAD_LINES);
        $st->execute([$id]);
        return [
            'id' => (int) $t['id'], 'summary' => (string) $t['summary'],
            'chat' => json_decode((string) $t['chat_log'], true) ?: [], 'thread' => array_reverse($st->fetchAll()),
            'facts' => $t['product_id'] ? Chat::facts([(int) $t['product_id']]) : '',
        ];
    }

    public function report(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT r.product_id, r.`key`, k.label, k.unit FROM spec_reports r JOIN spec_keys k ON k.`key` = r.`key`
            WHERE r.id = ? AND r.status = 'apen'");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $st = $this->pdo->prepare("SELECT reason, value_text FROM spec_reports WHERE product_id = ? AND `key` = ? AND status = 'apen' ORDER BY id LIMIT 10");
        $st->execute([$r['product_id'], $r['key']]);
        return ['label' => (string) $r['label'], 'unit' => $r['unit'], 'reports' => $st->fetchAll(), 'facts' => Chat::facts([(int) $r['product_id']])];
    }
}
