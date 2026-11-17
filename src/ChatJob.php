<?php
declare(strict_types=1);

/**
 * Søren svarer ferdig selv om kunden bytter side. Hvert spørsmål blir en jobb: hendelsene (tanker, tekst, kort)
 * skrives til en logg mens de strømmes, og en ny side kan lese videre fra der den forrige slapp (GET /api/chat/{id}).
 * Konteksten (siden spørsmålet ble stilt på) er låst da spørsmålet ble stilt. Bare samme økt kan lese jobben.
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
        // Rydd bort jobber eldre enn en time
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
     * Strømmer hendelsene fra nummer $from (0 = første etter eierlinjen) og følger loggen til «done».
     * Gir false hvis jobben ikke finnes eller tilhører en annen økt.
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
                fseek($fh, 0, SEEK_CUR); // nullstill EOF så nye linjer leses
                continue;
            }
            $partial .= $line;
            if (!str_ends_with($partial, "\n")) {
                continue; // linjen skrives fortsatt
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
