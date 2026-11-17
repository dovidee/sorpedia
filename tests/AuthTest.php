<?php
declare(strict_types=1);

// README security: five wrong passwords in a row lock the account for 15 minutes. Runs in a transaction that is rolled back.

Auth::ensure();

$inTransaction = function (callable $fn) {
    db()->beginTransaction();
    try {
        $fn();
    } finally {
        db()->rollBack();
    }
};

return [
    'the right password logs in' => fn() => $inTransaction(fn() => check(Auth::attempt('kunde@sorpedia.test', 'admin') !== null, 'login failed')),
    'a wrong password does not log in' => fn() => $inTransaction(fn() => check(Auth::attempt('kunde@sorpedia.test', 'feil-passord') === null, 'wrong password logged in')),
    'the account locks after the maximum number of wrong passwords' => fn() => $inTransaction(function () {
        for ($i = 0; $i < Auth::MAX_FAILS; $i++) {
            Auth::attempt('kunde@sorpedia.test', 'feil-passord');
        }
        check(Auth::attempt('kunde@sorpedia.test', 'admin') === null, 'locked account logged in');
    }),
    'an unknown email does not log in' => fn() => check(Auth::attempt('finnes-ikke@sorpedia.test', 'admin') === null, 'unknown email logged in'),
];
