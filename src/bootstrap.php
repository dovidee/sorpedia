<?php
declare(strict_types=1);

// Felles oppstart for web og skript. Konfig fra miljøvariabler med lokale standardverdier.

const APP_ROOT = __DIR__ . '/..';

function env(string $key, string $default = ''): string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

function config(): array
{
    static $c = null;
    return $c ??= [
        'db_host' => env('DB_HOST', '127.0.0.1'),
        'db_port' => env('DB_PORT', '3306'),
        'db_name' => env('DB_NAME', 'sorpedia'),
        'db_user' => env('DB_USER', 'sorpedia'),
        'db_pass' => env('DB_PASS', 'sorpedia'),
        'ollama_url' => rtrim(env('OLLAMA_URL', 'http://127.0.0.1:11434'), '/'),
        // Liten modell som får plass i 6 GB grafikkminne. Søren skriver resonnementet sitt selv, på norsk.
        'ollama_model' => env('OLLAMA_MODEL', 'deepseek-r1:8b'),
        // Tidsfrister for demo: første tegn innen 8 s, hele svaret innen 25 s, ellers regelbasert svar
        // Tenker modellen selv? Tom = gjett fra navnet (r1, qwen3 ...)
        'native_think' => env('SOREN_NATIVE_THINK', '') === '' ? null : env('SOREN_NATIVE_THINK', '') === '1',
        'first_token_s' => (float) env('SOREN_FIRST_TOKEN_S', '20'),
        'max_answer_s' => (float) env('SOREN_MAX_ANSWER_S', '150'),
        // Modellens arbeidsminne i tokens. 8192 gir plass til lange tanker. Gå ned til 6144 hvis grafikkortet blir for lite.
        'num_ctx' => (int) env('SOREN_NUM_CTX', '8192'),
        'mascot' => 'Søren',
    ];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config();
        $pdo = new PDO(
            "mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4",
            $c['db_user'],
            $c['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
