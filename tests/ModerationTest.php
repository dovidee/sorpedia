<?php
declare(strict_types=1);

// docs/context/risks-and-guardrails.md: trolling and spam in the open version. Profanity is hidden with asterisks.

return [
    'normal text is clean' => fn() => check(Moderation::clean('Fin drill til hytta'), 'normal text flagged'),
    'swearing is not clean' => fn() => check(!Moderation::clean('Dette er dritt'), 'swearing passed'),
    'swearing is censored to its first letter' => fn() => check(Moderation::censor('Dette er dritt') === 'Dette er d****', 'got: ' . Moderation::censor('Dette er dritt')),
    'normal text is not censored' => fn() => check(Moderation::censor('Fin drill til hytta') === 'Fin drill til hytta', 'normal text changed'),
    'swearing gives a reason for the customer' => fn() => check(Moderation::reason('Dette er dritt') !== null, 'no reason'),
];
