<?php
declare(strict_types=1);

/**
 * Feilhåndtering for nettsidene. Kunden ser aldri PHP-meldinger, filstier eller stakkspor:
 * advarsler blir unntak, unntak blir en vennlig side (eller JSON for /api), og alt logges med en kort
 * referanse som også vises til kunden, så en ansatt kan finne feilen i loggen (docker logs).
 *
 * APP_DEBUG=1 viser meldingen på siden, bare for utvikling.
 */
final class BadRequest extends RuntimeException
{
}

final class Errors
{
    /**
     * Parametere som skal være lister, og bare der de brukes: filtrene på kategorisidene (min[k], max[k], v[k][])
     * og skjemaet for Åpen-versjonen (spec[k]). Andre steder er v= vanlig tekst, for eksempel /profilbilde?v=abc123.
     */
    private const GET_LISTS = ['#^/tema/#' => ['min' => 1, 'max' => 1, 'v' => 2]];
    private const POST_LISTS = ['spec' => 1, 'a' => 1];

    private static bool $handling = false;

    public static function install(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        set_error_handler([self::class, 'onError']);
        set_exception_handler([self::class, 'onException']);
        register_shutdown_function([self::class, 'onShutdown']);
    }

    public static function debug(): bool
    {
        return env('APP_DEBUG', '0') === '1';
    }

    /** Advarsler og notiser blir unntak. Utdaterte funksjoner logges bare. «@» respekteres. */
    public static function onError(int $no, string $msg, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $no)) {
            return false;
        }
        if (in_array($no, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            error_log("[Sørpedia] Utdatert: $msg i $file:$line");
            return true;
        }
        throw new ErrorException($msg, 0, $no, $file, $line);
    }

    public static function onException(Throwable $e): void
    {
        if (self::$handling) { // feil under feilhåndteringen: ikke gå i ring
            echo 'Noe gikk galt.';
            return;
        }
        self::$handling = true;
        $bad = $e instanceof BadRequest;
        $code = $bad ? 400 : 500;
        $ref = bin2hex(random_bytes(4));
        if (!$bad) {
            self::log(sprintf("[Sørpedia %s] %s: %s i %s:%d\n%s", $ref, $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));
        }
        self::respond($code, $ref, $bad ? null : (self::debug() ? $e::class . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : null));
    }

    /** Fatale feil (minne, syntaks) går ikke gjennom unntakshåndteringen. */
    public static function onShutdown(): void
    {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) && !self::$handling) {
            self::$handling = true;
            $ref = bin2hex(random_bytes(4));
            self::log("[Sørpedia $ref] Fatal: {$err['message']} i {$err['file']}:{$err['line']}");
            self::respond(500, $ref, self::debug() ? $err['message'] : null);
        }
    }

    /**
     * Til serverloggen (docker logs) og til data/logs/errors.log i prosjektmappen, som Docker deler med maskinen,
     * så feilen kan leses uten Docker-kommandoer. Filen roteres ved 1 MB.
     */
    public static function log(string $line): void
    {
        error_log($line);
        $dir = APP_ROOT . '/data/logs';
        $file = "$dir/errors.log";
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (@filesize($file) > 1_000_000) {
            @rename($file, "$file.1");
        }
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function respond(int $code, string $ref, ?string $detail): void
    {
        $text = $code === 400 ? t('Adressen eller skjemaet inneholdt noe vi ikke forstår.') : t('Noe gikk galt hos oss. Prøv igjen om litt.');
        // Søren strømmer (SSE): hodene er sendt, så feilen må sendes som en hendelse i strømmen
        if (headers_sent()) {
            $sse = in_array('Content-Type: text/event-stream; charset=utf-8', headers_list(), true);
            if ($sse && function_exists('sse')) { // også i jobbloggen, så en side som leser videre får vite det
                sse('info', t('Noe gikk galt. Prøv igjen, eller snakk med et menneske.') . " (ref. $ref)");
                sse('done', true);
            } else {
                echo $sse ? 'event: info' . "\ndata: " . json_encode(t('Noe gikk galt. Prøv igjen, eller snakk med et menneske.') . " (ref. $ref)", JSON_UNESCAPED_UNICODE) . "\n\nevent: done\ndata: true\n\n"
                    : '<p class="flash">' . h($text) . ' (ref. ' . h($ref) . ')</p>';
            }
            return;
        }
        while (ob_get_level()) {
            ob_end_clean(); // kast halvferdig side
        }
        $_GET = $_POST = []; // feilsiden bruker ikke input, og ødelagt input skal ikke kunne felle den også
        http_response_code($code);
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (str_starts_with($path, '/api/') || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $text, 'ref' => $ref] + ($detail ? ['detail' => $detail] : []), JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            ob_start();
            render('error', ['code' => $code, 'text' => $text, 'ref' => $ref, 'detail' => $detail], $code === 400 ? t('Ugyldig forespørsel') : t('Noe gikk galt'));
            ob_end_flush();
        } catch (Throwable) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8"><title>Sørpedia</title><p>' . h($text) . ' (ref. ' . h($ref) . ')</p><p><a href="/">Forsiden</a></p>';
        }
    }

    /**
     * Rydder innkommende parametere før koden ser dem. «?q[]=x» gjør q til en liste i PHP,
     * og kode som venter tekst ville krasjet. Bare parametere i LISTS får være lister, med fast dybde.
     */
    public static function cleanInput(): void
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $getLists = [];
        foreach (self::GET_LISTS as $re => $keys) {
            if (preg_match($re, $path)) {
                $getLists += $keys;
            }
        }
        foreach ([[$_GET, $getLists], [$_POST, self::POST_LISTS], [$_COOKIE, []]] as [$arr, $lists]) {
            foreach ($arr as $k => $v) {
                $depth = $lists[$k] ?? 0;
                if (!self::shape($v, $depth)) {
                    throw new BadRequest(t('Adressen eller skjemaet inneholdt noe vi ikke forstår.'));
                }
            }
        }
    }

    /** Nøyaktig $depth nivåer med lister og tekst på bunnen. min[k]=1 er riktig, min=1 og min[k][]=1 er ikke. */
    private static function shape(mixed $v, int $depth): bool
    {
        if ($depth === 0) {
            return is_string($v);
        }
        if (!is_array($v)) {
            return false;
        }
        foreach ($v as $x) {
            if (!self::shape($x, $depth - 1)) {
                return false;
            }
        }
        return true;
    }
}
