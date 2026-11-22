<?php
// Fetches products from Sør-Tre's public WooCommerce Store API into data/raw/.
// Run once on the development machine. The result (JSON and images) ships with the project,
// so the demo doesn't need internet.
//
//   php scripts/scrape.php            # all themes, at most 40 per subcategory
//   php scripts/scrape.php --no-images

declare(strict_types=1);

const API = 'https://www.sor-tre.no/wp-json/wc/store/v1';
const PER_SUBCAT = 40;

// Subcategory ID at Sør-Tre => theme in our guide. One list, shared with the webhook sync (src/WooSync.php)
require __DIR__ . '/../src/WooSync.php';
const SUBCATS = WooSync::CATEGORY_THEME;

// Single products from other categories needed for the "goes well with" demo:
// Ledlenser batteries and charging cables (one of them says explicitly "Passer ikke Core").
const EXTRA_IDS = [291489 => 'lys', 291485 => 'lys', 291480 => 'lys', 291385 => 'lys', 291431 => 'lys'];

$root = dirname(__DIR__);
$rawDir = "$root/data/raw";
$imgDir = "$root/public/media/products";
$withImages = !in_array('--no-images', $argv, true);
@mkdir($rawDir, 0775, true);
@mkdir($imgDir, 0775, true);

$ctx = stream_context_create(['http' => [
    'header' => "User-Agent: Sorpedia-demo/1.0\r\nAccept: application/json\r\n",
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
    usleep(400_000); // be gentle with the server
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
            // The medium size keeps the demo light. Falls back to the original.
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
