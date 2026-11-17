<?php
declare(strict_types=1);

// Runs every tests/*Test.php. Each file returns [name => function]. A test fails by throwing.
// Uses the database from src/bootstrap.php. Tests only read, or write inside a transaction that is rolled back.
// Run: php tests/run.php

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/helpers.php';
$_SESSION = [];

function check(bool $ok, string $what): void
{
    if (!$ok) {
        throw new RuntimeException($what);
    }
}

// Type check: load every class in src/. PHP checks signatures, inheritance and declarations when a class loads.
foreach (glob(__DIR__ . '/../src/*.php') as $f) {
    $class = basename($f, '.php');
    if (preg_match('/^[A-Z]/', $class) && !class_exists($class) && !interface_exists($class)) {
        echo "FAIL load: src/$class.php does not define class $class\n";
        exit(1);
    }
}

$n = 0;
$failed = 0;
foreach (glob(__DIR__ . '/*Test.php') as $file) {
    $suite = basename($file, '.php');
    foreach (require $file as $name => $fn) {
        $n++;
        try {
            $fn();
            echo "ok   $suite: $name\n";
        } catch (Throwable $e) {
            $failed++;
            echo "FAIL $suite: $name: {$e->getMessage()}\n";
        }
    }
}
echo "$n tests, $failed failed\n";
exit($failed ? 1 : 0);
