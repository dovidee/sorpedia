<?php
declare(strict_types=1);

// Answer guard (feature 035): prices and totals come from the database, and an answer never says that products fit when
// the catalogue documents that they don't. Read only: the catalogue and the relations are only read.

// Two lamps whose prices differ, and the documented «passer ikke» charging cable that names both in the facts
$lamps = fn() => db()->query("SELECT p.id, p.price_ore FROM products p WHERE p.name LIKE 'Ledlenser H5R Core%' OR p.name LIKE 'Ledlenser H7R Core%' ORDER BY p.price_ore")->fetchAll(PDO::FETCH_KEY_PAIR);
$guardFor = fn(array $ids) => AnswerGuard::context($ids, Chat::facts($ids));
$pair = fn(string $kind, string $level) => db()->query("SELECT product_a, product_b FROM relations WHERE kind = '$kind' AND level = '$level' ORDER BY product_a, product_b LIMIT 1")->fetch(PDO::FETCH_NUM);

return [
    'a total is the sum of the database prices in øre, one of each product' => function () use ($lamps) {
        $l = $lamps();
        check(count($l) === 2, 'the H5R Core and H7R Core lamps are in the catalogue');
        [$a, $b] = array_keys($l);
        $t = AnswerGuard::total([$a, $b, $b, $a]);
        check($t['ids'] === [$a, $b], 'duplicates are counted once');
        check($t['ore'] === array_sum($l) && $t['missing'] === [], 'sum in øre: ' . $t['ore']);
        check(ProductRef::resolve(ProductRef::sum([$a, $b])) === kr(array_sum($l)), 'the sum reference shows the total in Norwegian kroner');
        check(ProductRef::resolve(ProductRef::sum([$a, $b]) . ' kroner.') === kr(array_sum($l)) . '.', 'a unit written after the reference is not doubled');
    },

    'a total over three products adds all three' => function () {
        $rows = db()->query('SELECT id, price_ore FROM products WHERE price_ore > 0 ORDER BY id LIMIT 3')->fetchAll(PDO::FETCH_KEY_PAIR);
        check(AnswerGuard::total(array_keys($rows))['ore'] === array_sum($rows), 'three products');
    },

    'no total when a product has no price or is not in the catalogue' => function () use ($lamps) {
        $a = array_key_first($lamps());
        $t = AnswerGuard::total([$a, 999999999]);
        check($t['missing'] === [999999999] && $t['ore'] === 0, 'a missing price is not zero kroner');
        check(AnswerGuard::totalSentence([$a, 999999999]) === null, 'no total sentence');
        check(str_contains(AnswerGuard::totalFact([$a, 999999999]), 'kan ikke regnes ut'), 'the facts say the total can not be worked out');
        check(ProductRef::resolve(ProductRef::sum([$a, 999999999])) === '', 'the sum reference shows nothing');
    },

    'total questions are recognised, and only plain ones skip the model' => function () {
        foreach (['Hva koster de to sammen?', 'Hva blir totalen?', 'Hva koster hele pakken?', 'Hva blir summen?', 'Hvor mye må jeg betale totalt?'] as $q) {
            check(AnswerGuard::pureTotal($q), "plain total: $q");
        }
        check(AnswerGuard::isTotal('Hva koster de to sammen, og hvilken lyser lengst?') && !AnswerGuard::pureTotal('Hva koster de to sammen, og hvilken lyser lengst?'), 'a total with another question goes to the model');
        check(!AnswerGuard::isTotal('Hva koster H5R Core?'), 'one price is not a total');
        check(!AnswerGuard::isTotal('Jeg trenger 2 stk til 3 lamper'), 'quantities are not a total');
    },

    'the total is only offered to the model as a reference to products in the facts' => function () use ($lamps) {
        [$a, $b] = array_keys($lamps());
        check(ProductRef::clean(ProductRef::sum([$a, $b]), [$a]) === '', 'a sum with a product outside the facts is removed');
        check(ProductRef::clean(ProductRef::sum([$a, $b]), [$a, $b]) === ProductRef::sum([$a, $b]), 'a sum over products in the facts is kept');
    },

    'a wrong price the model wrote is replaced by the named product\'s own price' => function () use ($lamps, $guardFor) {
        $l = $lamps();
        [$a, $b] = array_keys($l);
        $ctx = $guardFor([$a, $b]);
        check(ProductRef::resolve(AnswerGuard::check('H5R Core koster 17 kr.', $ctx)) === 'H5R Core koster ' . kr($l[$a]) . '.', 'wrong amount for one product');
        check(AnswerGuard::check('H5R Core koster ' . kr($l[$a]) . '.', $ctx) === 'H5R Core koster ' . kr($l[$a]) . '.', 'the right amount is kept');
        check(AnswerGuard::check('Til sammen blir det ' . kr(array_sum($l)) . '.', $ctx) === 'Til sammen blir det ' . kr(array_sum($l)) . '.', 'the right total is kept');
        check(!str_contains(AnswerGuard::check('Til sammen blir det 3 000 kr.', $ctx), '3 000'), 'a wrong total is removed');
        check(AnswerGuard::check('Du trenger 2 stk.', $ctx) === 'Du trenger 2 stk.', 'quantities are not prices');
    },

    'a price is never moved between products with similar names' => function () use ($lamps, $guardFor) {
        $l = $lamps();
        [$a, $b] = array_keys($l);
        $ctx = $guardFor([$a, $b]);
        // The H7R sentence carries the H5R's price reference: it becomes the H7R's own
        check(AnswerGuard::check("H7R Core koster [[pris:$a]].", $ctx) === "H7R Core koster [[pris:$b]].", 'reference moved to the named product');
        // The H5R's price written next to the H7R is not accepted as the H7R's price
        check(ProductRef::resolve(AnswerGuard::check('H7R Core koster ' . kr($l[$a]) . '.', $ctx)) === 'H7R Core koster ' . kr($l[$b]) . '.', 'amount from another product');
    },

    'a fit claim against a documented «passer ikke» is replaced by the documented fact' => function () use ($lamps, $guardFor) {
        [$a, $b] = array_keys($lamps());
        $cable = (int) db()->query("SELECT r.product_b FROM relations r JOIN products p ON p.id = r.product_b WHERE r.kind = 'passer_ikke' AND r.product_a = $a AND p.name LIKE '%MH-Serie%'")->fetchColumn();
        check($cable > 0, 'the MH cable is documented as not fitting the H5R Core');
        $ctx = $guardFor([$a, $b]);
        check(isset($ctx['notFit']["$cable|$a"], $ctx['notFit']["$cable|$b"]), 'both lamps have the documented «passer ikke»');
        $out = AnswerGuard::check('Ladekabel USB/magnetisk lading MH-Serie passer begge modeller.', $ctx);
        check(substr_count($out, 'passer ikke') === 2 && !str_contains($out, 'passer begge'), 'claim replaced for both lamps: ' . $out);
        $neg = 'MH-ladekabelen passer ikke til lyktene.';
        check(AnswerGuard::check($neg, $ctx) === $neg, 'a correct «passer ikke» is left alone');
        check(AnswerGuard::check('Lykten lyser lenge. Den er vanntett.', $ctx) === 'Lykten lyser lenge. Den er vanntett.', 'sentences about something else are left alone');
    },

    'documented and rule-based fits are shown with their level, and never corrected' => function () use ($pair) {
        foreach (['dokumentert' => 'dokumentert av Sør-Tre', 'regelutledet' => 'regelbasert, ikke bekreftet av Sør-Tre'] as $level => $words) {
            [$a, $b] = $pair('passer_sammen', $level);
            $facts = Chat::facts([(int) $a]);
            if (str_contains($facts, "[$b]")) {
                check(str_contains($facts, $words), "$level relation shown as «{$words}»");
            }
            $ctx = AnswerGuard::context([(int) $a, (int) $b], $facts);
            check($ctx['notFit'] === [] || !isset($ctx['notFit']["$a|$b"]), "$level fit is not a «passer ikke»");
        }
    },

    'bought together is never a fit, and unknown fit stays unknown' => function () use ($pair) {
        [$a, $b] = $pair('kjopt_sammen', 'antatt');
        $facts = Chat::facts([(int) $a]);
        check(!preg_match('/Passer med: \[' . $b . '\]/', $facts), 'a bought-together product is not listed as a fit');
        $ctx = AnswerGuard::context([(int) $a, (int) $b], $facts);
        check(!isset($ctx['notFit']["$a|$b"]), 'and not as a misfit either: the guard leaves unknown fits to the model');
    },

    'recommending a documented misfit is replaced, describing the included charger is not (feature 039)' => function () {
        $lamp = (int) db()->query("SELECT id FROM products WHERE name LIKE 'Ledlenser H7R Core%'")->fetchColumn();
        $ctx = AnswerGuard::context([$lamp], Chat::facts([$lamp]));
        $out = AnswerGuard::check('For å lade Ledlenser H7R Core anbefaler vi Ledlenser Ladekabel USB/magnetisk lading fra MH-serien.', $ctx);
        check(str_contains($out, 'passer ikke til') && !str_contains($out, 'anbefaler'), $out);
        $kept = 'Ledlenser H7R Core lades med magnetisk ladekabel.';
        check(AnswerGuard::check($kept, $ctx) === $kept, 'a plain description stays');
    },
];
