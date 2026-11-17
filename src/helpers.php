<?php
declare(strict_types=1);

// Små hjelpefunksjoner for web-delen.

function h(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function kr(int $ore): string
{
    return number_format($ore / 100, $ore % 100 ? 2 : 0, ',', ' ') . ' kr';
}

function url(string $path, array $q = []): string
{
    $q = array_filter($q, fn($v) => $v !== null && $v !== '' && $v !== []);
    return $path . ($q ? '?' . http_build_query($q) : '');
}

function redirect(string $to): never
{
    header("Location: $to", true, 303);
    exit;
}

/** Bare stier på dette nettstedet. «//evil.no» og «/\\evil.no» er lenker til andre nettsteder og blir $default. */
function safe_back(mixed $to, string $default = '/'): string
{
    $to = is_string($to) ? $to : '';
    return preg_match('#^/(?![/\\\\])#', $to) ? $to : $default;
}

function json_out(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sid(): string
{
    return session_id();
}

function csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf() . '">';
}

function check_csrf(): void
{
    $t = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!hash_equals(csrf(), (string) $t)) {
        http_response_code(400);
        exit('Ugyldig skjema (CSRF). Last siden på nytt.');
    }
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL');
            $st->execute([$_SESSION['uid']]);
            $u = $st->fetch() ?: null;
        }
    }
    return $u;
}

/** Verifisert bruker = bekreftet e-post OG minst én betalt ordre (constitution punkt 4). */
function is_verified(?array $u): bool
{
    if (!$u || !$u['email_verified_at']) {
        return false;
    }
    $st = db()->prepare("SELECT 1 FROM orders WHERE user_id = ? AND status = 'betalt' LIMIT 1");
    $st->execute([$u['id']]);
    return (bool) $st->fetchColumn();
}

function has_role(string ...$roles): bool
{
    $u = current_user();
    return $u && in_array($u['role'], $roles, true);
}

function warehouse_id(): int
{
    return (int) ($_SESSION['warehouse'] ?? 1);
}

function warehouses(): array
{
    static $w = null;
    if ($w === null) {
        // Eldre databaser kalte butikken «Sør-Tre Kristiansand». Det heter bare Kristiansand.
        db()->exec("UPDATE warehouses SET name = 'Kristiansand' WHERE name = 'Sør-Tre Kristiansand'");
        $w = db()->query('SELECT * FROM warehouses ORDER BY is_real DESC, name')->fetchAll(PDO::FETCH_UNIQUE);
    }
    return $w;
}

function track(string $type, array $data = []): void
{
    // Dataminimering: måltallene trenger ikke vite hvem som gjorde hva, så bruker-ID lagres ikke
    db()->prepare('INSERT INTO analytics_events (session_id, user_id, type, data) VALUES (?,?,?,?)')
        ->execute([sid(), null, $type, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

/** Skriver til hendelsestabellen (ADR-004). Leses av /api/stream. */
function emit(string $channel, string $type, array $payload): void
{
    db()->prepare('INSERT INTO events (channel, type, payload) VALUES (?,?,?)')
        ->execute([$channel, $type, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
}

function cart_count(): int
{
    $st = db()->prepare('SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE session_id = ?');
    $st->execute([sid()]);
    return (int) $st->fetchColumn();
}

function render(string $template, array $vars = [], string $title = ''): void
{
    extract($vars);
    ob_start();
    require APP_ROOT . "/templates/$template.php";
    $content = ob_get_clean();
    require APP_ROOT . '/templates/layout.php';
}

function stars(float $avg): string
{
    $full = (int) round($avg);
    return '<span class="stars" aria-label="' . h(t('{n} av 5', ['n' => number_format($avg, 1, lang() === 'en' ? '.' : ',', '')])) . '">'
        . str_repeat('★', $full) . '<span class="off">' . str_repeat('★', 5 - $full) . '</span></span>';
}

const SOURCE_LABEL = [
    'sortre' => ['Sør-Tre', 'Står i Sør-Tre sin produkttekst'],
    'ansatt' => ['Ansatt', 'Kontrollert og godkjent av en ansatt'],
    'utledet' => ['Utledet', 'Tolket automatisk fra produktteksten, ikke kontrollert'],
    'bruker' => ['Kjøper', 'Lagt inn av en verifisert kjøper, ikke kontrollert av Sør-Tre'],
];

function source_badge(string $source, ?string $evidence = null): string
{
    [$label, $tip] = SOURCE_LABEL[$source];
    [$label, $tip] = [t($label), t($tip)];
    $title = $tip . ($evidence ? '. ' . t('Kilde') . ": $evidence" : '');
    return '<span class="src src-' . h($source) . '" title="' . h($title) . '">' . h($label) . '</span>';
}

const LEVEL_LABEL = [
    'dokumentert' => 'Dokumentert',
    'regelutledet' => 'Regel',
    'antatt' => 'Kjøpt sammen',
];

/** Escaper og sensurerer tekst fra brukere (Åpen-verdier, anmeldelser, navn). Se src/Moderation.php. */
function hc(mixed $s): string
{
    return h(Moderation::censor((string) $s));
}

/** Tekst på kundens språk (src/I18n.php). Returnerer rå tekst: oversettelsene er våre egne, men variabler må escapes av den som kaller. */
function t(string $no, array $vars = []): string
{
    return I18n::t($no, $vars);
}

function lang(): string
{
    return I18n::lang();
}

function theme_name(string $k): string
{
    return t(Catalog::THEMES[$k][0] ?? $k);
}

function theme_desc(string $k): string
{
    return t(Catalog::THEMES[$k][1] ?? '');
}

/** Ikon fra Icons (Lucide), alltid sammen med tekst. */
function icon(string $name, string $class = ''): string
{
    return Icons::svg($name, $class);
}

/** Navn på en person til visning. Testbrukerne heter «Kari (demo)» i databasen; merkingen vises ikke. */
function person(mixed $s): string
{
    return hc(preg_replace('/\s*\(demo\)\s*$/iu', '', (string) $s));
}
