<?php
declare(strict_types=1);

/**
 * A trace of one chat or API question (feature 039): what the server decided, the exact request sent to Ollama and what came
 * back, what the guard changed, and the final answer, under one correlation ID. One JSON object per line in
 * data/traces/YYYY-MM-DD.jsonl, on this machine only; files older than seven days are removed. Off with SOREN_TRACE=0.
 * Customer text is kept as written, like chat_messages; no session, cookie, IP address or account is written.
 */
final class Trace
{
    private static ?string $id = null;

    /** The correlation ID of this request. */
    public static function id(): string
    {
        return self::$id ??= bin2hex(random_bytes(8));
    }

    public static function add(string $stage, array $data): void
    {
        if (!config()['trace']) {
            return;
        }
        $dir = APP_ROOT . '/data/traces';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return;
        }
        $file = $dir . '/' . date('Y-m-d') . '.jsonl';
        if (!is_file($file)) {
            foreach (glob($dir . '/*.jsonl') ?: [] as $old) {
                if (filemtime($old) < time() - 7 * 86400) {
                    @unlink($old);
                }
            }
        }
        $line = json_encode(['trace' => self::id(), 'at' => date('c'), 'stage' => $stage] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /** The lines of one trace, for checks and tests. @return array<int, array> */
    public static function read(string $id, ?string $day = null): array
    {
        $file = APP_ROOT . '/data/traces/' . ($day ?? date('Y-m-d')) . '.jsonl';
        $out = [];
        foreach (is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES) ?: []) : [] as $l) {
            $j = json_decode($l, true);
            if (is_array($j) && ($j['trace'] ?? null) === $id) {
                $out[] = $j;
            }
        }
        return $out;
    }
}
