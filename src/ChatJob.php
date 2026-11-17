<?php
declare(strict_types=1);

/**
 * Søren finishes his answer even if the customer changes page. Every question becomes a job: the events (thoughts, text, cards)
 * are written to a log as they stream, and a new page can keep reading from where the previous one left off (GET /api/chat/{id}).
 * The context (the page the question was asked on) is locked when the question is asked. Only the same session can read the job.
 */
final class ChatJob
{
    public static ?string $current = null;

    private static function dir(): string
    {
        $d = sys_get_temp_dir() . '/sorpedia-chat';
        if (!is_dir($d)) {
            @mkdir($d, 0700, true);
        }
        return $d;
    }

    private static function owner(): string
    {
        return hash('sha256', 'chatjob|' . session_id());
    }

    private static function file(string $id): string
    {
        return self::dir() . "/$id.jsonl";
    }

    public static function valid(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{24}$/', $id);
    }

    public static function start(): string
    {
        // Clean up jobs older than an hour
        foreach (glob(self::dir() . '/*.jsonl') ?: [] as $f) {
            if (@filemtime($f) < time() - 3600) {
                @unlink($f);
            }
        }
        $id = bin2hex(random_bytes(12));
        file_put_contents(self::file($id), json_encode(['owner' => self::owner()]) . "\n", LOCK_EX);
        self::$current = $id;
        return $id;
    }

    public static function append(string $event, mixed $data): void
    {
        if (self::$current !== null) {
            @file_put_contents(self::file(self::$current), json_encode([$event, $data], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Streams the events from number $from (0 = first after the owner line) and follows the log until "done".
     * Returns false if the job doesn't exist or belongs to another session.
     */
    public static function follow(string $id, int $from, callable $emit, int $maxSeconds = 300): bool
    {
        $f = self::file($id);
        if (!self::valid($id) || !is_file($f)) {
            return false;
        }
        $fh = fopen($f, 'r');
        $head = json_decode((string) fgets($fh), true);
        if (!hash_equals((string) ($head['owner'] ?? ''), self::owner())) {
            fclose($fh);
            return false;
        }
        $n = 0;
        $until = time() + $maxSeconds;
        $partial = '';
        while (time() < $until && !connection_aborted()) {
            $line = fgets($fh);
            if ($line === false) {
                usleep(150_000);
                fseek($fh, 0, SEEK_CUR); // reset EOF so new lines are read
                continue;
            }
            $partial .= $line;
            if (!str_ends_with($partial, "\n")) {
                continue; // the line is still being written
            }
            [$event, $data] = json_decode($partial, true) ?? [null, null];
            $partial = '';
            if ($event === null) {
                continue;
            }
            if ($n++ >= $from) {
                $emit($event, $data);
            }
            if ($event === 'done') {
                break;
            }
        }
        fclose($fh);
        return true;
    }
}
