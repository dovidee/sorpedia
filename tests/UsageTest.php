<?php
declare(strict_types=1);

// What a product is for and what else the customer needs (src/Usage.php), and the charging method (Extract::charging).
// Derived from the product type, so always marked as derived, never as something Sør-Tre has stated (CLAUDE.md rule 2).

$id = function (string $like): int {
    $st = db()->prepare('SELECT id FROM products WHERE name LIKE ? ORDER BY id LIMIT 1');
    $st->execute([$like]);
    $id = (int) $st->fetchColumn();
    check($id > 0, "no product like «{$like}», run scripts/import.php");
    return $id;
};

return [
    'a screw needs a screwdriver, which the store does not sell' => function () {
        [$use, $needs] = Usage::rule('Øyeskrue 6 x 16 x 2.8 MS P6');
        check(str_contains($use, 'Skrus'), "use: $use");
        check(str_contains($needs[0][0], 'skrutrekker') && $needs[0][1] === null, 'screwdriver missing');
    },
    'a bread toaster for gas needs a gas burner and a lighter' => function () {
        [, $needs] = Usage::rule('1852 Brødrister/Toaster for gassbluss');
        $all = implode(' ', array_column($needs, 0));
        check(str_contains($all, 'gassbluss') && str_contains($all, 'lighter'), "needs: $all");
    },
    'a headlamp is compared on charging first' => fn() => check((Usage::keys('Osean LED 450 – LED Hodelykt – 450 Lumen – Oppladbar')[0] ?? '') === 'lading', 'charging is not first'),
    'an accessory matches before its product' => fn() => check(str_contains(Usage::rule('Ledlenser Ladekabel USB/magnetisk lading – H/HF og P- Core')[0], 'Lader'), 'charging cable read as a lamp'),
    'an unknown type gives nothing' => fn() => check(Usage::rule('Ukjent ting') === null && Usage::lines(0, 'Ukjent ting') === [], 'guessed a type'),
    'needs are marked as derived and point to real products' => function () use ($id) {
        $f = implode("\n", Usage::lines($id('1852 Brødrister%'), '1852 Brødrister/Toaster for gassbluss'));
        check(str_contains($f, 'utledet'), 'not marked as derived');
        check(str_contains($f, 'Sør-Tre har det') && str_contains($f, '[[pris:'), 'no store product for the burner');
        check(str_contains($f, 'Sør-Tre selger det ikke her: lighter'), 'lighter not marked as missing from the range');
    },
    'Søren gets the main use in the facts' => fn() => check(str_contains(Chat::facts([$id('Øyeskrue 6 x 16%')]), 'Hovedbruk'), 'no main use in the facts'),
    'two charging methods are told apart' => function () {
        check(Extract::charging('Lykten er oppladbar via USB C, og har en brukstid') === 'USB-C-kabel', 'USB-C');
        check(Extract::charging('Lades via medfølgende magnetiske ladekabel med USB C') === 'Magnetisk ladekabel med USB-C', 'magnetic with USB-C');
        check(Extract::charging('Lades via magnetic charge system') === 'Magnetisk ladekabel', 'magnetic without USB');
    },
    'a USB output is not read as the charging method' => fn() => check(Extract::charging('Lykten har også USB 5v1A utgang for lading av mobiltelefon') === 'Oppladbar, med USB-utgang som lader mobilen', 'USB output read as charging'),
    'every way Sør-Tre writes an IP rating is read' => function () {
        foreach (['Beskyttelsesgrad: IPx-4/IK-07' => 'IPX4', 'Beskyttelsesklasse: iP67' => 'IP67', 'Vannresistens: IP 54' => 'IP54'] as $text => $want) {
            $ip = array_column(Extract::specs('Hodelykt', "<p>$text</p>", 'lys'), 'text', 'key')['ip_klasse'] ?? null;
            check($ip === $want, "«{$text}» gave " . ($ip ?? 'nothing'));
        }
    },
    'the product page gets the needs with store products' => function () use ($id) {
        $u = Usage::needs($id('1852 Brødrister%'), '1852 Brødrister/Toaster for gassbluss');
        check($u !== null && $u['has'] && $u['has'][0]['cards'] && in_array('lighter eller fyrstikker hvis brenneren ikke tenner selv', $u['not'], true), 'needs missing');
    },
    'only properties that decide fit link products across the store' => fn() => check(!preg_grep('/^Samme Lengde/u', Chat::storeContext($id('Øyeskrue 6 x 16%'))), 'a screw was linked to other products by length'),
];
