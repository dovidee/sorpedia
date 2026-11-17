<?php
declare(strict_types=1);

// CLAUDE.md rule 5: only verified users (confirmed email AND at least one paid purchase) edit or review.
// README security: safe_back() blocks open redirects, h() escapes output.

$user = function (string $email): array {
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    check((bool) $u, "test user $email is missing, run scripts/import.php");
    return $u;
};

return [
    'nobody logged in is not verified' => fn() => check(!is_verified(null), 'null counted as verified'),
    'confirmed email and a paid order is verified' => fn() => check(is_verified($user('kunde@sorpedia.test')), 'kunde@sorpedia.test not verified'),
    'confirmed email without a paid order is not verified' => fn() => check(!is_verified($user('epost@sorpedia.test')), 'epost@sorpedia.test counted as verified'),
    'unconfirmed email is not verified' => fn() => check(!is_verified($user('ny@sorpedia.test')), 'ny@sorpedia.test counted as verified'),
    'a local path is kept' => fn() => check(safe_back('/produkt/x') === '/produkt/x', 'local path changed'),
    'another site is blocked' => fn() => check(safe_back('https://evil.example') === '/' && safe_back('//evil.example') === '/' && safe_back('/\\evil.example') === '/', 'open redirect'),
    'html is escaped' => fn() => check(h('<script>') === '&lt;script&gt;', 'not escaped'),
    'price is shown in kroner from øre' => fn() => check(kr(12900) === '129 kr', 'got: ' . kr(12900)),
];
