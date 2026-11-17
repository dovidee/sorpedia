<?php
// Henter produkter fra Sør-Tre sitt offentlige WooCommerce Store API til data/raw/.
// Kjøres én gang på utviklingsmaskinen. Resultatet (JSON og bilder) følger med prosjektet,
// så demoen trenger ikke internett.
//
//   php scripts/scrape.php            # alle temaer, maks 40 per underkategori
//   php scripts/scrape.php --no-images

declare(strict_types=1);

const API = 'https://www.sor-tre.no/wp-json/wc/store/v1';
const PER_SUBCAT = 40;

// Underkategori-ID hos Sør-Tre => tema i vår guide (se docs/context/product-taxonomy.md)
const SUBCATS = [
    7111 => 'lys', 7129 => 'lys', 7117 => 'lys', 7160 => 'lys',
    7207 => 'bygg', 7137 => 'bygg', 7138 => 'bygg', 7663 => 'bygg', 7118 => 'bygg', 7128 => 'bygg',
    7364 => 'maling', 7179 => 'maling', 7193 => 'maling', 7206 => 'maling', 7253 => 'maling',
    7678 => 'balkong', 7680 => 'balkong', 7679 => 'balkong',
    7499 => 'kjetting', 7504 => 'kjetting', 7220 => 'kjetting',
    7070 => 'skog', 7079 => 'skog', 7088 => 'skog', 7087 => 'skog',
    7246 => 'tur', 7258 => 'tur',
];

// Enkeltprodukter fra andre kategorier som trengs for "passer sammen"-demoen:
// Ledlenser-batterier og ladekabler (en av dem sier eksplisitt "Passer ikke Core").
const EXTRA_IDS = [291489 => 'lys', 291485 => 'lys', 291480 => 'lys', 291385 => 'lys', 291431 => 'lys'];

$root = dirname(__DIR__);
$rawDir = "$root/data/raw";
$imgDir = "$root/public/media/products";
$withImages = !in_array('--no-images', $argv, true);
@mkdir($rawDir, 0775, true);
@mkdir($imgDir, 0775, true);

$ctx = stream_context_create(['http' => [
    'header' => "User-Agent: IS219-gruppe4-demo/1.0\r\nAccept: application/json\r\n",
    'timeout' => 30,
]]);

function fetch(string $url, $ctx): ?string
{
    for ($try = 1; $try <= 3; $try++) {
        $body = @file_get_contents($url, false, $ctx);
        if ($body !== false) {
            return $body;
        }
        fwrite(STDERR, "  feil ($try/3): $url\n");
        sleep(2 * $try);
    }
    return null;
}

$products = [];
foreach (SUBCATS as $catId => $theme) {
    $url = API . "/products?category=$catId&per_page=" . PER_SUBCAT . '&orderby=popularity';
    $json = fetch($url, $ctx);
    $list = $json ? json_decode($json, true) : [];
    printf("%-6d %-9s %3d produkter\n", $catId, $theme, count($list ?: []));
    foreach ($list ?: [] as $p) {
        $id = (int) $p['id'];
        if (!isset($products[$id])) {
            $p['_theme'] = $theme;
            $p['_subcat'] = $catId;
            $products[$id] = $p;
        }
    }
    usleep(400_000); // vær snill mot serveren
}

foreach (EXTRA_IDS as $id => $theme) {
    $json = fetch(API . "/products/$id", $ctx);
    $p = $json ? json_decode($json, true) : null;
    if ($p && !isset($products[$id])) {
        $p['_theme'] = $theme;
        $p['_subcat'] = (int) ($p['categories'][0]['id'] ?? 0);
        $products[$id] = $p;
        echo "ekstra  $theme     " . html_entity_decode($p['name']) . "\n";
    }
}

file_put_contents("$rawDir/products.json", json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$cats = [];
for ($page = 1; $page <= 6; $page++) {
    $json = fetch(API . "/products/categories?per_page=100&page=$page", $ctx);
    $chunk = $json ? json_decode($json, true) : [];
    if (!$chunk) {
        break;
    }
    $cats = array_merge($cats, $chunk);
}
file_put_contents("$rawDir/categories.json", json_encode($cats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
printf("\n%d unike produkter, %d kategorier\n", count($products), count($cats));

if ($withImages) {
    $n = 0;
    foreach ($products as $id => $p) {
        foreach (array_slice($p['images'] ?? [], 0, 6) as $i => $img) {
            // Mellomstor variant holder demoen lett. Faller tilbake til originalen.
            $src = $img['thumbnail'] ?? $img['src'];
            $big = $img['src'];
            foreach (['m' => $src, 'l' => $big] as $size => $u) {
                $ext = strtolower(pathinfo(parse_url($u, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
                $file = "$imgDir/{$id}-{$i}-{$size}.$ext";
                if (is_file($file)) {
                    continue;
                }
                $bin = fetch($u, $ctx);
                if ($bin !== null) {
                    file_put_contents($file, $bin);
                    $n++;
                }
                usleep(150_000);
            }
        }
    }
    echo "$n bilder lastet ned\n";
}
