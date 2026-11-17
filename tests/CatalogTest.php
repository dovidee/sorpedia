<?php
declare(strict_types=1);

// CLAUDE.md rules 1 to 4: data from the database, source and level on specifications, level on "goes well with",
// new specification types as rows, not columns.

$one = fn(string $sql) => db()->query($sql)->fetchColumn();

return [
    'cards show the price from the database' => function () {
        $rows = db()->query('SELECT id, price_ore FROM products ORDER BY id LIMIT 5')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach (Catalog::cards(array_keys($rows)) as $c) {
            check((int) $c['price_ore'] === (int) $rows[$c['id']], "price differs for product {$c['id']}");
        }
    },
    'specifications only have known sources' => fn() => check((int) $one("SELECT COUNT(*) FROM spec_values WHERE source NOT IN ('sortre','utledet','bruker','ansatt')") === 0, 'unknown source'),
    'derived and user values are never in the verified version' => fn() => check((int) $one("SELECT COUNT(*) FROM spec_values WHERE version = 'verifisert' AND source IN ('utledet','bruker')") === 0, 'derived or user value shown as verified'),
    'the verified value wins over the open one' => fn() => check(Catalog::effective(['verifisert' => ['v' => 'a'], 'apen' => ['v' => 'b']]) === ['v' => 'a'], 'open value won'),
    'the open value is used when nothing is verified' => fn() => check(Catalog::effective(['verifisert' => null, 'apen' => ['v' => 'b']]) === ['v' => 'b'], 'open value ignored'),
    'unknown is a valid answer' => fn() => check(Catalog::effective(['verifisert' => null, 'apen' => null]) === null, 'unknown not null'),
    '"goes well with" is never assumed' => fn() => check((int) $one("SELECT COUNT(*) FROM relations WHERE kind = 'passer_sammen' AND level NOT IN ('dokumentert','regelutledet')") === 0, 'assumed relation shown as goes well with'),
    '"goes well with" always has a reason' => fn() => check((int) $one("SELECT COUNT(*) FROM relations WHERE kind = 'passer_sammen' AND (reason IS NULL OR reason = '')") === 0, 'relation without reason'),
    'specifications are rows, not product columns' => function () {
        $cols = db()->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_COLUMN);
        $known = ['id', 'sku', 'name', 'slug', 'theme', 'brand', 'price_ore', 'regular_ore', 'short_html', 'long_html', 'plain_text', 'source_url', 'created_at'];
        check(!array_diff($cols, $known), 'new product column: ' . implode(', ', array_diff($cols, $known)) . '. Add a row in spec_keys instead');
    },
];
