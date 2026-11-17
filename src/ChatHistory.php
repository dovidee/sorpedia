<?php
declare(strict_types=1);

/**
 * Server-owned chat history (feature 015).
 *
 * The browser sends only the new message and the page address. Earlier messages, and their roles, come from
 * these tables, so a browser can't add assistant messages or change earlier answers.
 *
 * A conversation belongs to an owner: the SHA-256 hash of a random token in the PHP session. The token survives
 * session_regenerate_id() at login and is removed at logout. The conversation id is kept in the session too, never
 * taken from the browser. Every read and write checks the owner.
 *
 * A question and its answer are saved together, in one transaction, after the answer is finished. If no answer
 * came, nothing is saved. Messages that got a fixed safety reply are saved with for_model = 0: customer service
 * sees them, the model never does.
 */
final class ChatHistory
{
    public const USER_MAX = 1500;        // characters per customer message
    public const ASSISTANT_MAX = 4000;   // characters per stored answer
    public const PROMPT_MESSAGES = 8;    // messages sent to the model, the new one included
    public const MAX_MESSAGES = 40;      // stored per conversation, the oldest are deleted
    public const TTL_HOURS = 24;         // a conversation expires this long after its last message

    /** Creates the tables in databases built before feature 015. */
    public static function ensure(): void
    {
        if (db()->query("SHOW TABLES LIKE 'chat_messages'")->fetch()) {
            return;
        }
        $sql = (string) file_get_contents(__DIR__ . '/../db/schema.sql');
        foreach (['chat_conversations', 'chat_messages'] as $t) {
            if (preg_match('/CREATE TABLE ' . $t . ' \(.*?\) ENGINE=InnoDB[^;]*;/s', $sql, $m)) {
                db()->exec(str_replace("CREATE TABLE $t", "CREATE TABLE IF NOT EXISTS $t", $m[0]));
            }
        }
    }

    /** The owner of this session's conversations. */
    public static function owner(): string
    {
        $_SESSION['chat_owner'] ??= bin2hex(random_bytes(16));
        return hash('sha256', (string) $_SESSION['chat_owner']);
    }

    /** Forgets this session's conversation (logout, account deletion). */
    public static function forget(): void
    {
        unset($_SESSION['chat_owner'], $_SESSION['chat_conv']);
    }

    /** Only the new message from the request body. Any history the browser sends is ignored. */
    public static function input(array $in): string
    {
        $m = $in['message'] ?? '';
        return is_string($m) ? mb_substr(trim($m), 0, self::USER_MAX) : '';
    }

    /** The conversation $id if it belongs to $owner and hasn't expired, otherwise a new one. */
    public static function open(string $owner, ?int $id, bool $fresh = false): int
    {
        if ($id && !$fresh) {
            $st = db()->prepare('SELECT id FROM chat_conversations WHERE id = ? AND owner = ? AND expires_at > NOW()');
            $st->execute([$id, $owner]);
            if ($st->fetchColumn()) {
                return $id;
            }
        }
        db()->exec('DELETE FROM chat_conversations WHERE expires_at <= NOW()');
        db()->prepare('INSERT INTO chat_conversations (owner, expires_at) VALUES (?, NOW() + INTERVAL ' . self::TTL_HOURS . ' HOUR)')
            ->execute([$owner]);
        return (int) db()->lastInsertId();
    }

    /** This session's conversation, kept in the session. $fresh starts a new one. */
    public static function current(bool $fresh = false): int
    {
        if ($fresh) {
            $_SESSION['chat_fresh_at'] = time();
        }
        $id = self::open(self::owner(), isset($_SESSION['chat_conv']) ? (int) $_SESSION['chat_conv'] : null, $fresh);
        $_SESSION['chat_conv'] = $id;
        return $id;
    }

    /**
     * Does the product guide the customer took follow into the chat on other pages? For three hours, and only until the
     * customer starts a new conversation. A new conversation is the customer's own way of changing the subject.
     */
    public static function carriesGuide(mixed $g, ?int $now = null): bool
    {
        $now ??= time();
        return is_array($g) && $now - (int) ($g['at'] ?? 0) < 3 * 3600 && (int) ($g['at'] ?? 0) > (int) ($_SESSION['chat_fresh_at'] ?? 0);
    }

    /** The last messages for the model, oldest first, as [role, content]. Only the owner's, only for_model = 1. */
    public static function forModel(int $conv, string $owner, int $limit = self::PROMPT_MESSAGES - 1): array
    {
        $st = db()->prepare('SELECT m.role, m.content FROM chat_messages m JOIN chat_conversations c ON c.id = m.conversation_id
            WHERE m.conversation_id = ? AND c.owner = ? AND c.expires_at > NOW() AND m.for_model = 1 ORDER BY m.seq DESC LIMIT ' . max(0, $limit));
        $st->execute([$conv, $owner]);
        return array_reverse($st->fetchAll());
    }

    /** The whole stored conversation, for customer service. Only the owner's. */
    public static function log(int $conv, string $owner): array
    {
        $st = db()->prepare('SELECT m.role, m.content FROM chat_messages m JOIN chat_conversations c ON c.id = m.conversation_id
            WHERE m.conversation_id = ? AND c.owner = ? ORDER BY m.seq');
        $st->execute([$conv, $owner]);
        return $st->fetchAll();
    }

    /**
     * Saves a question and its answer as the next two messages. The conversation row is locked, so two requests at the
     * same time get separate, ordered sequence numbers. Returns false if the conversation isn't the owner's.
     */
    public static function record(int $conv, string $owner, string $question, string $answer, bool $forModel = true): bool
    {
        $question = mb_substr($question, 0, self::USER_MAX);
        $answer = mb_substr($answer, 0, self::ASSISTANT_MAX);
        if ($question === '' || $answer === '') {
            return false;
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT id FROM chat_conversations WHERE id = ? AND owner = ? FOR UPDATE');
            $st->execute([$conv, $owner]);
            if (!$st->fetchColumn()) {
                $pdo->rollBack();
                return false;
            }
            $st = $pdo->prepare('SELECT COALESCE(MAX(seq), 0) FROM chat_messages WHERE conversation_id = ?');
            $st->execute([$conv]);
            $seq = (int) $st->fetchColumn();
            $ins = $pdo->prepare('INSERT INTO chat_messages (conversation_id, seq, role, content, for_model) VALUES (?,?,?,?,?)');
            $ins->execute([$conv, $seq + 1, 'user', $question, (int) $forModel]);
            $ins->execute([$conv, $seq + 2, 'assistant', $answer, (int) $forModel]);
            $pdo->prepare('DELETE FROM chat_messages WHERE conversation_id = ? AND seq <= ?')->execute([$conv, $seq + 2 - self::MAX_MESSAGES]);
            $pdo->prepare('UPDATE chat_conversations SET expires_at = NOW() + INTERVAL ' . self::TTL_HOURS . ' HOUR WHERE id = ?')->execute([$conv]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
