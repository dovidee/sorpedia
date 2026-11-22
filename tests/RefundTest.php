<?php
declare(strict_types=1);

// Refund rules (feature 022). Refund::plan is pure: no database is opened.

$bought = [10 => ['qty' => 2, 'price_ore' => 5000], 20 => ['qty' => 1, 'price_ore' => 12900]];

return [
    'a whole-order refund covers every unit not yet refunded' => function () use ($bought) {
        $p = Refund::plan($bought, [10 => 1], null);
        check($p['error'] === null && $p['total_ore'] === 5000 + 12900, 'one unit of 10 and the line of 20 remain');
        check($p['complete'], 'nothing is left after a whole-order refund');
    },
    'a partial refund leaves the order open' => function () use ($bought) {
        $p = Refund::plan($bought, [], [10 => 1]);
        check($p['total_ore'] === 5000 && !$p['complete'], 'one of two units is refunded, the order is not complete');
    },
    'more units than are left are refused' => function () use ($bought) {
        check(Refund::plan($bought, [10 => 2], [10 => 1])['error'] !== null, 'a fully refunded line cannot be refunded again');
        check(Refund::plan($bought, [], [10 => 3])['error'] !== null, 'more than bought is refused');
    },
    'a product not on the order is refused' => function () use ($bought) {
        check(Refund::plan($bought, [], [99 => 1])['error'] !== null, 'unknown product must be refused');
    },
    'an empty request refunds nothing' => function () use ($bought) {
        check(Refund::plan($bought, [], [10 => 0])['error'] !== null, 'zero units is not a refund');
        check(Refund::plan($bought, [10 => 2, 20 => 1], null)['error'] !== null, 'a fully refunded order has nothing left');
    },
];
