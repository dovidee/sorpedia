<?php
// Builds the database from data/raw/*.json plus demo seed data. Can be rerun: always starts from an empty schema.
//
//   php scripts/import.php
//
// Everything synthetic (stock, users, orders, reviews, open contributions) is marked as demo.

declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$root = APP_ROOT;
$pdo = db();
mt_srand(219); // deterministic demo data

function step(string $msg): void { echo str_pad($msg, 44, '.') . ' '; }
function done(string $msg = 'ok'): void { echo "$msg\n"; }
function decode(string $s): string { return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function slugify(string $s): string
{
    $s = mb_strtolower(decode($s));
    $s = strtr($s, ['æ' => 'ae', 'ø' => 'o', 'å' => 'a', 'é' => 'e', '″' => 'tommer', '×' => 'x']);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $s) ?? '', '-');
}

// 1. Schema
step('Skjema');
$pdo->exec(file_get_contents("$root/db/schema.sql"));
done();

// 2. Categories
step('Kategorier');
$cats = json_decode(file_get_contents("$root/data/raw/categories.json"), true);
$st = $pdo->prepare('INSERT INTO categories (id, parent_id, name, slug) VALUES (?,?,?,?)');
foreach ($cats as $c) {
    $st->execute([$c['id'], $c['parent'] ?: null, decode($c['name']), $c['slug']]);
}
done(count($cats) . ' stk');

// 3. Specification types (rows, not columns)
step('Spesifikasjonstyper');
$st = $pdo->prepare('INSERT INTO spec_keys (`key`, label, unit, datatype, is_facet, themes, sort) VALUES (?,?,?,?,?,?,?)');
foreach (Extract::KEYS as $k => [$label, $unit, $type, $facet, $themes, $sort]) {
    $st->execute([$k, $label, $unit, $type, $facet, $themes, $sort]);
}
done(count(Extract::KEYS) . ' stk');

// 4. Products, categories, images, specifications
step('Produkter');
$raw = json_decode(file_get_contents("$root/data/raw/products.json"), true);
$insP = $pdo->prepare('INSERT INTO products (id, sku, name, slug, theme, brand, price_ore, regular_ore, short_html, long_html, plain_text, source_url)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
$insPC = $pdo->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?,?)');
$insI = $pdo->prepare('INSERT INTO product_images (product_id, path_m, path_l, kind, sort) VALUES (?,?,?,?,?)');
$insS = $pdo->prepare('INSERT INTO spec_values (product_id, `key`, version, value_text, value_num, source, evidence) VALUES (?,?,?,?,?,?,?)');
$products = [];
$slugs = [];
$nImg = 0;
$nSpec = ['sortre' => 0, 'utledet' => 0];
$newKey = $pdo->prepare('INSERT IGNORE INTO spec_keys (`key`, label, unit, datatype, is_facet, themes, sort) VALUES (?,?,?,?,?,?,?)');
$pdo->beginTransaction();
foreach ($raw as $p) {
    $id = (int) $p['id'];
    $name = trim(preg_replace('/\s+/u', ' ', decode($p['name'])) ?? '');
    $slug = slugify($p['slug'] ?: $name);
    if (isset($slugs[$slug])) {
        $slug .= "-$id";
    }
    $slugs[$slug] = true;
    $html = $p['description'] . ' ' . $p['short_description'];
    $specs = Extract::specs($name, $html, $p['_theme']);
    $brand = null;
    foreach ($specs as $s) {
        if ($s['key'] === 'merke') {
            $brand = $s['text'];
        }
    }
    $price = (int) $p['prices']['price'];
    $regular = (int) $p['prices']['regular_price'];
    $insP->execute([$id, $p['sku'] ?: null, $name, $slug, $p['_theme'], $brand, $price,
        $regular > $price ? $regular : null, $p['short_description'], $p['description'],
        Extract::plain($html), $p['permalink']]);
    foreach ($p['categories'] as $c) {
        $insPC->execute([$id, $c['id']]);
    }
    $insPC->execute([$id, $p['_subcat']]);
    foreach (array_slice($p['images'] ?? [], 0, 6) as $i => $img) {
        $m = glob("$root/public/media/products/$id-$i-m.*");
        $l = glob("$root/public/media/products/$id-$i-l.*");
        if ($m || $l) {
            $insI->execute([$id, 'media/products/' . basename(($m ?: $l)[0]), 'media/products/' . basename(($l ?: $m)[0]), 'sortre', $i]);
            $nImg++;
        }
    }
    foreach ($specs as $s) {
        if (!isset(Extract::KEYS[$s['key']])) { // new property from Sør-Tre's text: new row in spec_keys
            $newKey->execute([$s['key'], $s['label'] ?? $s['key'], $s['unit'] ?? null, $s['num'] !== null ? 'number' : 'text', 0, $p['_theme'], 90]);
        }
    }
    foreach ($specs as $s) {
        // Sør-Tre's own numbers go in Verified, our interpretations in Open (constitution point 2)
        $version = $s['source'] === 'sortre' ? 'verifisert' : 'apen';
        $insS->execute([$id, $s['key'], $version, $s['text'], $s['num'], $s['source'], $s['evidence']]);
        $nSpec[$s['source']]++;
    }
    $products[$id] = ['id' => $id, 'name' => $name, 'theme' => $p['_theme'], 'subcat' => (int) $p['_subcat'],
        'price' => $price, 'specs' => array_column($specs, null, 'key'), 'text' => Extract::plain($html)];
}
$pdo->commit();
done(count($products) . " stk, $nImg bilder, {$nSpec['sortre']} Sør-Tre-verdier, {$nSpec['utledet']} utledet");

// 5. Stores (Sør-Tre has one store in Kristiansand, the rest are demo)
step('Varehus');
$warehouses = [
    1 => ['Kristiansand', 'Kristiansand', 1], 2 => ['Oslo', 'Oslo', 0], 3 => ['Bergen', 'Bergen', 0],
    4 => ['Stavanger', 'Stavanger', 0], 5 => ['Sandnes', 'Sandnes', 0], 6 => ['Trondheim', 'Trondheim', 0],
    7 => ['Arendal', 'Arendal', 0], 8 => ['Grimstad', 'Grimstad', 0], 9 => ['Mandal', 'Lindesnes', 0],
    10 => ['Lyngdal', 'Lyngdal', 0], 11 => ['Flekkefjord', 'Flekkefjord', 0], 12 => ['Drammen', 'Drammen', 0],
];
$st = $pdo->prepare('INSERT INTO warehouses (id, name, city, is_real) VALUES (?,?,?,?)');
foreach ($warehouses as $wid => [$n, $c, $real]) {
    $st->execute([$wid, $n, $c, $real]);
}
done(count($warehouses) . ' stk');

// 6. Stock per store: physically in store, online stock, or backorder with a date
step('Lager (demo)');
$st = $pdo->prepare('INSERT INTO stock (product_id, warehouse_id, in_store, online, preorder_eta) VALUES (?,?,?,?,?)');
$pdo->beginTransaction();
$n = 0;
foreach ($products as $id => $p) {
    $online = mt_rand(0, 100) < 85 ? mt_rand(3, 120) : 0; // online stock is shared, but stored per row for simplicity
    foreach ($warehouses as $wid => $w) {
        $r = mt_rand(0, 100);
        $inStore = $wid === 1 ? ($r < 80 ? mt_rand(1, 40) : 0) : ($r < 45 ? mt_rand(1, 15) : 0);
        $eta = ($inStore === 0 && $online === 0) ? date('Y-m-d', strtotime('+' . mt_rand(5, 30) . ' days')) : null;
        $st->execute([$id, $wid, $inStore, $online, $eta]);
        $n++;
    }
}
$pdo->commit();
done("$n rader");

// 6b. Transport (demo), see scripts/transport-demo.php
step('Transport (demo)');
require_once __DIR__ . '/transport-demo.php';
done(seed_transport($pdo, $root) . ' sendinger');

// 7. Users. All seeded users have the password "admin".
step('Brukere (passord: admin)');
$hash = password_hash('admin', PASSWORD_ARGON2ID);
$users = [
    ['admin@sorpedia.test', 'Admin Adminsen', 'admin', true],
    ['ansatt@sorpedia.test', 'Thomas Gregersen', 'ansatt', true],
    ['support@sorpedia.test', 'Siri Support', 'support', true],
    ['kunde@sorpedia.test', 'Ingrid Lunde', 'bruker', true],     // verified (email + purchase)
    ['arne@sorpedia.test', 'Arne Hansen', 'bruker', true],        // verified
    ['ny@sorpedia.test', 'Nora Ny', 'bruker', false],             // unconfirmed email, no purchases
    ['epost@sorpedia.test', 'Erik Epost', 'bruker', true],        // confirmed email, but no purchases
];
$demoBuyers = ['Kari', 'Ola', 'Lars', 'Mona', 'Per', 'Hanne', 'Jonas', 'Silje', 'Ahmed', 'Linnea', 'Tor', 'Maja'];
foreach ($demoBuyers as $i => $fn) {
    $users[] = [strtolower($fn) . '@sorpedia.test', "$fn (demo)", 'bruker', true];
}
$st = $pdo->prepare('INSERT INTO users (email, name, alias_salt, password_hash, role, email_verified_at, warehouse_id, is_demo_seed) VALUES (?,?,?,?,?,?,?,1)');
$userIds = [];
foreach ($users as [$email, $name, $role, $verified]) {
    $st->execute([$email, $name, Privacy::newSalt(), $hash, $role, $verified ? date('Y-m-d H:i:s', strtotime('-60 days')) : null, 1]);
    $userIds[$email] = (int) $pdo->lastInsertId();
}
done(count($users) . ' stk');

// 8. Demo orders: bought kits that give "others also bought" and make the buyers verified
step('Ordrer (demo)');
$byTheme = [];
foreach ($products as $id => $p) {
    $byTheme[$p['theme']][] = $id;
}
$insO = $pdo->prepare('INSERT INTO orders (user_id, warehouse_id, total_ore, payment, status, is_demo_seed, created_at) VALUES (?,?,?,?,?,1,?)');
$insOI = $pdo->prepare('INSERT IGNORE INTO order_items (order_id, product_id, qty, price_ore) VALUES (?,?,?,?)');
$buyers = array_filter($userIds, fn($e) => !in_array($e, ['ny@sorpedia.test', 'epost@sorpedia.test', 'admin@sorpedia.test', 'ansatt@sorpedia.test', 'support@sorpedia.test'], true), ARRAY_FILTER_USE_KEY);
$bought = []; // user_id => [product_id...]
$nO = 0;
$pdo->beginTransaction();
foreach ($buyers as $email => $uid) {
    $orders = $email === 'kunde@sorpedia.test' ? 2 : mt_rand(2, 6);
    for ($o = 0; $o < $orders; $o++) {
        $theme = array_rand($byTheme);
        $pool = $byTheme[$theme];
        shuffle($pool);
        $items = array_slice($pool, 0, mt_rand(1, 4));
        if ($email === 'kunde@sorpedia.test' && $o === 0) {
            // Ingrid has bought a Ledlenser headlamp, so she can contribute the beam range (demo of P1)
            $items = array_values(array_filter(array_keys($products), fn($i) => str_contains($products[$i]['name'], 'H7R Core')));
        }
        $total = array_sum(array_map(fn($i) => $products[$i]['price'], $items));
        $insO->execute([$uid, 1, $total, ['vipps', 'klarna', 'kort'][mt_rand(0, 2)], 'betalt', date('Y-m-d H:i:s', strtotime('-' . mt_rand(3, 50) . ' days'))]);
        $oid = (int) $pdo->lastInsertId();
        foreach ($items as $pid) {
            $insOI->execute([$oid, $pid, 1, $products[$pid]['price']]);
            $bought[$uid][] = $pid;
        }
        $nO++;
    }
}
$pdo->commit();
done("$nO ordrer");

// 9. Demo reviews: only from users who bought the product (same rule as in the app)
step('Anmeldelser (demo)');
$templates = [
    5 => ['Akkurat som beskrevet', 'Gjør jobben og vel så det. Kjøper igjen.', 'Solid kvalitet til prisen. Anbefales.'],
    4 => ['Bra kjøp', 'Fungerer fint. Skulle gjerne hatt litt mer info om mål før jeg kjøpte.', 'God kvalitet, men bruksanvisningen var tynn.'],
    3 => ['Helt grei', 'Gjør jobben, men ikke noe mer. Sjekk målene nøye før du bestiller.', 'Ok, men jeg måtte kjøpe tilbehør i tillegg som jeg ikke visste om.'],
    2 => ['Litt skuffet', 'Passet ikke helt det jeg hadde. Burde stått tydeligere i beskrivelsen.'],
];
$insR = $pdo->prepare('INSERT IGNORE INTO reviews (product_id, user_id, rating, title, body, is_demo_seed, created_at) VALUES (?,?,?,?,?,1,?)');
$nR = 0;
foreach ($bought as $uid => $pids) {
    foreach (array_unique($pids) as $pid) {
        if (mt_rand(0, 100) > 60) {
            continue;
        }
        $rating = [5, 5, 5, 4, 4, 4, 3, 2][mt_rand(0, 7)];
        $t = $templates[$rating];
        $insR->execute([$pid, $uid, $rating, $t[0], $t[mt_rand(1, count($t) - 1)], date('Y-m-d H:i:s', strtotime('-' . mt_rand(1, 40) . ' days'))]);
        $nR += $insR->rowCount();
    }
}
done("$nR stk");

// 10. "Goes well with" (see docs/context/product-taxonomy.md)
step('Passer sammen');
$rel = [];
$addRel = function (int $a, int $b, string $kind, string $level, string $reason, float $score) use (&$rel): void {
    if ($a === $b) {
        return;
    }
    $k = "$a-$b-$kind";
    if (!isset($rel[$k]) || $rel[$k][4] < $score) {
        $rel[$k] = [$a, $b, $kind, $level, $reason, $score];
    }
};
$spec = fn(int $id, string $k): ?string => $products[$id]['specs'][$k]['text'] ?? null;
$num = fn(int $id, string $k): ?float => $products[$id]['specs'][$k]['num'] ?? null;
$inCat = fn(int $id, int ...$cats): bool => in_array($products[$id]['subcat'], $cats, true);
$nameHas = fn(int $id, string $re): bool => (bool) preg_match($re, $products[$id]['name']);

// 10a. Documented: Sør-Tre's text says explicitly what the accessory fits.
$bySku = [];
foreach ($pdo->query('SELECT id, sku FROM products WHERE sku IS NOT NULL') as $r) {
    $bySku[$r['sku']] = (int) $r['id'];
}
foreach ($products as $acc) {
    $hay = $acc['name'] . ' ' . $acc['text'];
    // Item numbers: "Passer bla. varenr. 355190", "beregnet for vår arbeidslampe 718085"
    if (preg_match_all('/(?:varenr\.?|vår\s+\p{L}+)\s*(\d{6})\b/iu', $hay, $vm)) {
        foreach ($vm[1] as $sku) {
            if (isset($bySku[$sku]) && $bySku[$sku] !== $acc['id']) {
                $main = $bySku[$sku];
                $reason = "Sør-Tre skriver at {$acc['name']} passer til varenr. $sku ({$products[$main]['name']})";
                $addRel($main, $acc['id'], 'passer_sammen', 'dokumentert', $reason, 1.0);
                $addRel($acc['id'], $main, 'passer_sammen', 'dokumentert', $reason, 1.0);
            }
        }
    }
    // Accessories only. Otherwise codes like "IP54" or "P8 LED" in ordinary text would give false links.
    if (!preg_match('/batteri|lader|ladeboks|ladekabel|pannebånd|veke|reservedel|holder|feste/iu', $acc['name'])) {
        continue;
    }
    // Explicit "doesn't fit": "Ladekabel ... NB! Passer ikke Core" or "batteripakke for H7R2 (ikke Core)"
    if (preg_match('/(?:passer\s+ikke|\(ikke)\s+(?:til\s+|for\s+)?([\p{L}\d-]{3,})/iu', $hay, $nm)) {
        $word = $nm[1];
        $quote = trim($nm[0], '( ');
        foreach ($products as $main) {
            if ($main['id'] !== $acc['id'] && ($products[$acc['id']]['specs']['merke']['text'] ?? '') === ($main['specs']['merke']['text'] ?? '-')
                && preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $main['name'])
                && !preg_match('/batteri|lade/iu', $main['name'])) {
                $reason = "Sør-Tre skriver: «{$quote}»";
                $addRel($main['id'], $acc['id'], 'passer_ikke', 'dokumentert', $reason, 1.0);
                $addRel($acc['id'], $main['id'], 'passer_ikke', 'dokumentert', $reason, 1.0);
            }
        }
        $hay = (string) preg_replace(['/passer\s+ikke.*$/isu', '/\(ikke[^)]*\)/iu'], '', $hay); // the rest must not be read as "fits"
    }
    // Model codes in the title or after "passer/for/til": "Batteri 21700, H7R Core og P7R Core"
    $passage = $acc['name'];
    if (preg_match('/(?:passer|for|til)\s+(?:blant annet\s+|bl\.?a\.?\s+)?(.{3,120})/iu', $hay, $m)) {
        $passage .= ' ' . $m[1];
    }
    preg_match_all('/\b([A-Z]{1,3}\d{1,3}[A-Z]?(?:-\d)?)\b/u', $passage, $models);
    foreach (array_unique($models[1]) as $model) {
        if (preg_match('/^(IP|EN|CE|LED|AA|AAA)\d*/', $model)) {
            continue;
        }
        foreach ($products as $main) {
            if ($main['id'] !== $acc['id'] && preg_match('/\b' . preg_quote($model, '/') . '\b/u', $main['name'])
                && !preg_match('/batteri|lader|ladeboks|kabel/iu', $main['name'])) {
                $reason = "Sør-Tre skriver at {$acc['name']} passer til $model";
                $addRel($main['id'], $acc['id'], 'passer_sammen', 'dokumentert', $reason, 1.0);
                $addRel($acc['id'], $main['id'], 'passer_sammen', 'dokumentert', $reason, 1.0);
            }
        }
    }
}

// 10b. Rule based
$ids = array_keys($products);
foreach ($ids as $a) {
    foreach ($ids as $b) {
        if ($a === $b) {
            continue;
        }
        // Hammer and sledgehammer -> nails and staples
        if ($inCat($a, 7207) && $nameHas($a, '/hammer/iu') && $inCat($b, 7137)) {
            $addRel($a, $b, 'passer_sammen', 'regelutledet', 'Snekkerhammer brukes til spiker og stift', 0.8);
        }
        if ($inCat($a, 7137) && $inCat($b, 7207) && $nameHas($b, '/hammer/iu')) {
            $addRel($a, $b, 'passer_sammen', 'regelutledet', 'Du trenger en hammer for å slå inn spiker', 0.8);
        }
        // Sledgehammer -> steel wedge (splitting firewood)
        if ($nameHas($a, '/slegge/iu') && !$nameHas($a, '/kile/iu') && $nameHas($b, '/kile/iu')) {
            $addRel($a, $b, 'passer_sammen', 'regelutledet', 'Slegge og kile brukes sammen til vedkløyving', 0.7);
        }
        // Painting: filler -> sandpaper, masking, roller/brush. Roller/brush -> masking
        if ($inCat($a, 7179) && $inCat($b, 7253, 7193, 7206)) {
            $addRel($a, $b, 'passer_sammen', 'regelutledet', 'Etter sparkling slipes flaten, og kantene maskeres før maling', 0.7);
        }
        if ($inCat($a, 7364) && $inCat($b, 7193)) {
            $addRel($a, $b, 'passer_sammen', 'regelutledet', 'Masker lister og vinduer før du maler', 0.7);
        }
        if ($inCat($a, 7193, 7206, 7253) && $inCat($b, 7364) && $nameHas($b, '/sett/iu')) {
            $addRel($a, $b, 'passer_sammen', 'regelutledet', 'Komplett malesett til samme jobb', 0.6);
        }
        // Balcony: glass -> post with the right height, and cover strip
        if ($inCat($a, 7678) && $inCat($b, 7680)) {
            $hg = $num($a, 'hoyde_cm');
            $hs = $num($b, 'hoyde_cm');
            if ($hg && $hs && abs($hs - $hg) <= 0.5) {
                $addRel($a, $b, 'passer_sammen', 'regelutledet', "Stender på {$spec($b, 'hoyde_cm')} cm passer glass med høyde {$spec($a, 'hoyde_cm')} cm", 0.95);
                $addRel($b, $a, 'passer_sammen', 'regelutledet', "Glass med høyde {$spec($a, 'hoyde_cm')} cm passer stender på {$spec($b, 'hoyde_cm')} cm", 0.95);
            }
        }
        if ($inCat($a, 7678) && $inCat($b, 7679)) {
            $addRel($a, $b, 'passer_sammen', 'regelutledet', 'Dekklist dekker overkanten av glassfeltet', 0.7);
        }
        // Chain: shackle and hook with the same dimension as the chain
        if ($inCat($a, 7504) && $inCat($b, 7499, 7220)) {
            $da = $num($a, 'dimensjon_mm');
            $db = $num($b, 'dimensjon_mm');
            if ($da && $db && $db >= $da && $db <= $da + 2) {
                $addRel($a, $b, 'passer_sammen', 'regelutledet', "Kjetting {$spec($a, 'dimensjon_mm')} mm passer {$spec($b, 'dimensjon_mm')} mm (samme eller én størrelse opp)", 0.85);
                $addRel($b, $a, 'passer_sammen', 'regelutledet', "{$spec($b, 'dimensjon_mm')} mm passer kjetting på {$spec($a, 'dimensjon_mm')} mm", 0.85);
            }
        }
        // Chainsaw chain: 3/8″ LP uses a 4.0 mm round file, .325″ uses 4.8 mm
        if ($nameHas($a, '/sagkjede/iu') && $nameHas($b, '/rundfil|filsett/iu')) {
            $lp = $nameHas($a, '/3\/8″\s*LP/u');
            $want = $lp ? '4,0' : '4,8';
            if ($nameHas($b, '/' . preg_quote($want, '/') . '/u') || $nameHas($b, '/filsett/iu')) {
                $addRel($a, $b, 'passer_sammen', 'regelutledet', ($lp ? '3/8″ LP' : '.325″') . " kjede files med $want mm rundfil", 0.9);
                $addRel($b, $a, 'passer_sammen', 'regelutledet', "$want mm fil passer " . ($lp ? '3/8″ LP' : '.325″') . ' sagkjede', 0.9);
            }
        }
        // Forest: saw chain and forestry accessories -> gloves and hearing protection
        if ($inCat($a, 7070, 7079) && $inCat($b, 7088, 7087)) {
            $addRel($a, $b, 'passer_sammen', 'regelutledet', 'Bruk hansker og hørselvern ved motorsagarbeid', 0.6);
        }
        // Light: lamp -> batteries of the same type
        if ($products[$a]['theme'] === 'lys' && !$nameHas($a, '/batteri/iu') && $nameHas($b, '/batteri/iu')) {
            $ba = $spec($a, 'batteri');
            if ($ba && $ba !== 'Oppladbart' && preg_match('/x(AAA|AA|D|C)$/', $ba, $t) && preg_match('/\b' . $t[1] . '\b/u', $products[$b]['name'])) {
                $addRel($a, $b, 'passer_sammen', 'regelutledet', "Lykten bruker $ba, batteriet er type {$t[1]}", 0.85);
            }
        }
    }
}
// 10c. Assumed: bought together in demo orders
$co = $pdo->query('SELECT a.product_id pa, b.product_id pb, COUNT(*) n FROM order_items a JOIN order_items b ON a.order_id = b.order_id AND a.product_id <> b.product_id GROUP BY pa, pb')->fetchAll();
foreach ($co as $r) {
    $addRel((int) $r['pa'], (int) $r['pb'], 'kjopt_sammen', 'antatt', 'Andre kunder kjøpte disse sammen (demo-ordrer)', 0.3 + 0.1 * (int) $r['n']);
}
// A documented "doesn't fit" wins over every rule that says the pair fits
foreach ($rel as $k => $r) {
    if ($r[2] === 'passer_ikke') {
        unset($rel["{$r[0]}-{$r[1]}-passer_sammen"]);
    }
}
$st = $pdo->prepare('INSERT INTO relations (product_a, product_b, kind, level, reason, score) VALUES (?,?,?,?,?,?)');
$pdo->beginTransaction();
$count = [];
foreach ($rel as $r) {
    $st->execute($r);
    $count[$r[3]] = ($count[$r[3]] ?? 0) + 1;
}
$pdo->commit();
done(implode(', ', array_map(fn($k, $v) => "$v $k", array_keys($count), $count)));

// 11. Search synonyms and use cases ("henge opp", "male vegg")
step('Søkesynonymer');
$syn = [
    ['lommelykt', 'lykt', 'synonym'], ['lykt', 'lommelykt hodelykt stavlykt', 'synonym'], ['hodelampe', 'hodelykt', 'synonym'],
    ['pannelampe', 'hodelykt', 'synonym'], ['lyspære', 'lyspære LED', 'synonym'], ['pære', 'lyspære', 'synonym'],
    ['hammar', 'hammer', 'synonym'], ['spikre', 'spiker', 'synonym'], ['nagle', 'spiker stift', 'synonym'],
    ['skruer', 'skrue', 'synonym'], ['malerull', 'rulle', 'synonym'], ['malerulle', 'rulle', 'synonym'],
    ['kost', 'pensel', 'synonym'], ['tape', 'maskering', 'synonym'], ['malertape', 'maskering', 'synonym'],
    ['sparkelmasse', 'sparkel', 'synonym'], ['slipepapir', 'sandpapir', 'synonym'], ['kjede', 'kjetting', 'synonym'],
    ['lenke', 'kjetting', 'synonym'], ['sjakel', 'sjakkel', 'synonym'], ['sakkel', 'sjakkel', 'synonym'],
    ['vindskjerm', 'balkongglass', 'synonym'], ['glassrekkverk', 'balkongglass stender', 'synonym'], ['levegg', 'balkongglass', 'synonym'],
    ['hansker', 'hanske', 'synonym'], ['øreklokker', 'øreklokke hørselvern', 'synonym'], ['hørselsvern', 'hørselvern øreklokke', 'synonym'],
    ['motorsagkjede', 'sagkjede', 'synonym'], ['fil', 'rundfil filsett', 'synonym'], ['kikert', 'kikkert', 'synonym'],
    ['drill', 'tema:bygg', 'synonym'], ['bormaskin', 'tema:bygg', 'synonym'], ['skrutrekker', 'tema:bygg', 'synonym'],
    ['se i mørket', 'tema:lys', 'bruksomrade'], ['mørkt', 'tema:lys', 'bruksomrade'], ['strømbrudd', 'lykt batteri', 'bruksomrade'],
    ['nattfiske', 'hodelykt', 'bruksomrade'], ['jogging', 'hodelykt', 'bruksomrade'], ['male vegg', 'tema:maling', 'bruksomrade'],
    ['male tak', 'rulle maskering', 'bruksomrade'], ['male terrasse', 'terrasse', 'bruksomrade'], ['beise', 'pensel terrasse', 'bruksomrade'],
    ['fikse hull i vegg', 'sparkel sandpapir', 'bruksomrade'], ['tette sprekker', 'sparkel', 'bruksomrade'], ['bygge terrasse', 'skrue spiker beslag', 'bruksomrade'],
    ['snekre', 'tema:bygg', 'bruksomrade'], ['henge opp', 'krok øyeskrue', 'bruksomrade'], ['fortøye båt', 'sjakkel kjetting syrefast', 'bruksomrade'],
    ['løfte', 'tema:kjetting', 'bruksomrade'], ['sikre last', 'sjakkel kjetting krok', 'bruksomrade'], ['le på balkongen', 'tema:balkong', 'bruksomrade'],
    ['skjerme for vind', 'tema:balkong', 'bruksomrade'], ['hogge ved', 'slegge kile vedsekk', 'bruksomrade'], ['felle trær', 'tema:skog', 'bruksomrade'],
    ['slipe motorsag', 'rundfil filsett', 'bruksomrade'], ['fuglekikking', 'kikkert', 'bruksomrade'], ['båttur', 'kikkert syrefast', 'bruksomrade'],
    ['grille', 'grill', 'bruksomrade'], ['camping', 'tema:tur', 'bruksomrade'], ['hytta', 'lykt grill vedsekk', 'bruksomrade'],
];
$st = $pdo->prepare('INSERT INTO search_synonyms (term, expands, kind) VALUES (?,?,?)');
foreach ($syn as $s) {
    $st->execute($s);
}
done(count($syn) . ' stk');

// 12. Demo of the two-version system: open contributions from verified buyers
step('Åpne bidrag (demo)');
$ingrid = $userIds['kunde@sorpedia.test'];
$arne = $userIds['arne@sorpedia.test'];
$contrib = [];
foreach ($products as $p) {
    if (str_contains($p['name'], 'H7R Core')) {
        // The value is a demo contribution. The point is that it shows as "from a verified buyer", not as Sør-Tre's.
        $contrib[] = [$p['id'], $ingrid, 'rekkevidde_m', '220', 220.0, 'Målte selv på en skogsvei i mørket, omtrent 220 meter før lyset ble for svakt. (demo-bidrag, ikke kontrollert)'];
    }
}
foreach ($products as $p) {
    if ($p['subcat'] === 7504 && count($contrib) < 3) {
        $contrib[] = [$p['id'], $arne, 'materiale', 'Varmgalvanisert stål', null, 'Står varmforsinket på emballasjen. (demo-bidrag)'];
    }
}
$insRev = $pdo->prepare('INSERT INTO spec_revisions (product_id, user_id, diff, comment, status, created_at) VALUES (?,?,?,?,?,?)');
$upS = $pdo->prepare('INSERT INTO spec_values (product_id, `key`, version, value_text, value_num, source, evidence, revision_id)
                      VALUES (?,?,"apen",?,?,"bruker",?,?) ON DUPLICATE KEY UPDATE value_text=VALUES(value_text), value_num=VALUES(value_num),
                      source="bruker", evidence=VALUES(evidence), revision_id=VALUES(revision_id)');
foreach ($contrib as [$pid, $uid, $key, $txt, $numv, $why]) {
    $old = $products[$pid]['specs'][$key]['text'] ?? null;
    $insRev->execute([$pid, $uid, json_encode([['key' => $key, 'from' => $old, 'to' => $txt]], JSON_UNESCAPED_UNICODE), $why, 'aktiv', date('Y-m-d H:i:s', strtotime('-2 days'))]);
    $upS->execute([$pid, $key, $txt, $numv, $why, (int) $pdo->lastInsertId()]);
}
done(count($contrib) . ' stk');

// 13. Assembly guides (demo) with PDF
step('Veiledninger (demo)');
require_once APP_ROOT . '/src/helpers.php';
$n = require __DIR__ . '/manuals.php';
done("$n stk, " . count(glob(APP_ROOT . '/public/media/manuals/*.pdf')) . ' PDF');

echo "\nFerdig. Logg inn med f.eks. kunde@sorpedia.test / admin\n";
