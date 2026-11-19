<?php
declare(strict_types=1);

// Product references (feature 015): the model never gets exact prices or stock, and every price or stock number in
// the answer is filled in from the database, for products that were in the facts only.

$one = fn(string $where = '1') => db()->query("SELECT id, price_ore FROM products WHERE $where ORDER BY id LIMIT 1")->fetch();
$inTransaction = function (callable $fn) {
    db()->beginTransaction();
    try {
        $fn();
    } finally {
        db()->rollBack();
    }
};
$money = '/\d[\d  ]*(?:,\d+)?\s?(?:kr|,-)(?!\p{L})/u';

return [
    'the facts for the model have no prices or stock counts, only references' => function () use ($money) {
        $ids = array_map('intval', db()->query('SELECT id FROM products ORDER BY id LIMIT 5')->fetchAll(PDO::FETCH_COLUMN));
        $facts = Chat::facts($ids);
        check(!preg_match($money, $facts, $m), 'price in the facts: ' . ($m[0] ?? ''));
        check(!preg_match('/\d+ i butikk/u', $facts, $m), 'stock count in the facts: ' . ($m[0] ?? ''));
        foreach ($ids as $id) {
            check(str_contains($facts, ProductRef::price($id)), "no price reference for $id");
        }
    },

    'only products in the facts can be referenced' => function () use ($one) {
        $p = $one();
        $other = (int) db()->query('SELECT id FROM products WHERE id <> ' . (int) $p['id'] . ' ORDER BY id LIMIT 1')->fetchColumn();
        $allowed = ProductRef::allowed(Chat::facts([(int) $p['id']]));
        check(in_array((int) $p['id'], $allowed, true), 'product in the facts not allowed');
        $out = ProductRef::clean('Den koster [[pris:' . $other . ']] og [[pris:' . $p['id'] . ']].', [(int) $p['id']]);
        check($out === 'Den koster  og [[pris:' . $p['id'] . ']].', 'clean: ' . $out);
        check(ProductRef::resolve('[[pris:999999999]]', null) === '', 'unknown product resolved');
        check(ProductRef::clean('Lykten [[291448]] og [291449] lades', []) === 'Lykten og lades', 'bare product IDs were kept');
    },

    'a price reference becomes the price in the database' => function () use ($one) {
        $p = $one();
        check(ProductRef::resolve('Pris [[pris:' . $p['id'] . ']]', [(int) $p['id']]) === 'Pris ' . kr((int) $p['price_ore']), 'wrong price');
    },

    'a stock reference becomes the stock in the database' => function () use ($one) {
        $p = $one();
        $c = Catalog::cards([(int) $p['id']])[0];
        $want = $c['in_store'] > 0 ? t('{n} i butikk', ['n' => $c['in_store']]) : t($c['online'] > 0 ? 'på nettlager' : 'ikke på lager');
        check(ProductRef::resolve('[[lager:' . $p['id'] . ']]') === $want, 'wrong stock');
    },

    'a stored answer shows the price as it is now, not when it was written' => fn() => $inTransaction(function () use ($one) {
        $p = $one();
        $stored = 'Den koster ' . ProductRef::price((int) $p['id']) . '.';
        db()->prepare('UPDATE products SET price_ore = price_ore + 10000 WHERE id = ?')->execute([$p['id']]);
        check(ProductRef::resolve($stored) === 'Den koster ' . kr((int) $p['price_ore'] + 10000) . '.', 'old price shown');
    }),

    'the guide summary and catalog overview carry references, not prices' => function () use ($money) {
        $intent = CatalogIntent::build('billigst', 'hva er billigst', ['kind' => 'ingen', 'text' => '', 'label' => '', 'product' => null, 'ids' => []]);
        check(!preg_match($money, $intent['facts'], $m), 'price in the overview: ' . ($m[0] ?? ''));
        check(ProductRef::allowed($intent['facts']) !== [], 'no references in the overview');
        check(!preg_match($money, $intent['answer'], $m), 'price in the overview answer: ' . ($m[0] ?? ''));
        $brief = Guide::brief(['oppgave' => 'lys']);
        check(!preg_match($money, $brief, $m), 'price in the guide summary: ' . ($m[0] ?? ''));
    },
];
