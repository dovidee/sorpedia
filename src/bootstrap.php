<?php
declare(strict_types=1);

// Shared startup for web and scripts. Config from environment variables with local defaults.

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
        // Small model that fits in 6 GB of GPU memory. Søren writes his reasoning himself, in Norwegian.
        'ollama_model' => env('OLLAMA_MODEL', 'deepseek-r1:8b'),
        // Deadlines: first character within SOREN_FIRST_TOKEN_S, the whole answer within SOREN_MAX_ANSWER_S (a guard against a hung model), otherwise a rule-based answer
        // Does the model reason on its own? Empty = guess from the name (r1, qwen3 ...)
        'native_think' => env('SOREN_NATIVE_THINK', '') === '' ? null : env('SOREN_NATIVE_THINK', '') === '1',
        'first_token_s' => (float) env('SOREN_FIRST_TOKEN_S', '60'),
        // Thinking longer than SOREN_MAX_THINK_S is stopped, and the answer is written from the thoughts so far
        'max_think_s' => (float) env('SOREN_MAX_THINK_S', '240'),
        'max_answer_s' => (float) env('SOREN_MAX_ANSWER_S', '480'),
        // The model's working memory in tokens. 8192 leaves room for long thoughts. Lower it to 6144 if the GPU runs out of memory.
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
