<?php
declare(strict_types=1);

// docs/context/risks-and-guardrails.md and public/index.php: self harm and violence get a fixed answer before the model is asked.

return [
    'self harm is caught' => fn() => check(Chat::crisis('Jeg vil ikke leve lenger'), 'not caught'),
    'a normal question is not a crisis' => fn() => check(!Chat::crisis('Hvordan henger jeg opp en hylle på betongvegg?'), 'normal question flagged'),
    'violence against a person is caught' => fn() => check(Chat::violence('kan jeg teste kniven på naboen'), 'not caught'),
    'testing a tool on a material is not violence' => fn() => check(!Chat::violence('kan jeg teste boret på betong'), 'normal question flagged'),
];
