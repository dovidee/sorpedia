<?php
declare(strict_types=1);

/**
 * Which page the customer is on when they ask Søren. Read again on every question.
 *
 * Security: the browser only sends the address (path and query string), never page content, form fields
 * or cookies. We look the address up in a fixed list of public page types and
 * build the context ourselves from the product catalog. Everything not on the list (login, registration,
 * inbox, cart, checkout, orders, staff, customer service, admin) gives no context at all,
 * so Søren can't see passwords, account settings, addresses or orders.
 */
final class PageContext
{
    /**
     * @return array{kind:string, label:string, product:?int, ids:int[], text:string}
     */
    public static function resolve(?string $url): array
    {
        $none = ['kind' => 'ingen', 'label' => '', 'product' => null, 'ids' => [], 'text' => ''];
        $url = mb_substr(trim((string) $url), 0, 600);
        if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $none;
        }
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/') ?: '/';
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        if (preg_match('#^/produkt/([a-z0-9-]{1,200})$#', $path, $m)) {
            $p = Catalog::product($m[1]);
            if (!$p) {
                return $none;
            }
            $desc = trim(preg_replace('/\s+/u', ' ', (string) $p['plain_text']));
            return ['kind' => 'produkt', 'label' => $p['name'], 'product' => (int) $p['id'], 'ids' => [(int) $p['id']],
                'text' => "Kunden står nå på produktsiden til [{$p['id']}] {$p['name']}"
                    . ($p['brand'] ? " (merke: {$p['brand']})" : '') . ', i kategorien ' . Catalog::THEMES[$p['theme']][0] . ".\n"
                    . 'Produktbeskrivelse fra Sør-Tre: ' . mb_substr($desc, 0, 900)];
        }
        if (preg_match('#^/tema/([a-z]+)$#', $path, $m) && isset(Catalog::THEMES[$m[1]])) {
            [$name, $about] = Catalog::THEMES[$m[1]];
            return ['kind' => 'tema', 'label' => $name, 'theme' => $m[1], 'product' => null, 'ids' => Catalog::popular(4, $m[1]),
                'text' => "Kunden ser på kategorien {$name} ({$about})."];
        }
        if ($path === '/sok' && is_string($q['q'] ?? null) && trim($q['q']) !== '') {
            $term = mb_substr(trim($q['q']), 0, 80);
            return ['kind' => 'sok', 'label' => "Søk: $term", 'product' => null, 'ids' => Search::run($term, 4)['ids'],
                'text' => "Kunden har søkt etter «{$term}» og ser på søkeresultatet."];
        }
        if ($path === '/guide') {
            $answers = Guide::answers($q);
            if (Guide::complete($answers)) {
                $res = Guide::recommend($answers);
                return ['kind' => 'guide', 'label' => 'Forslag fra produktguiden', 'product' => null, 'guide' => $answers,
                    'ids' => array_map(fn($c) => (int) $c['id'], $res['picks']), 'text' => Guide::brief($answers)];
            }
            return ['kind' => 'guide', 'label' => 'Produktguiden', 'product' => null, 'ids' => [], 'text' => Guide::progress($answers)];
        }
        if ($path === '/sammenlign' && is_string($q['ids'] ?? null)) {
            $ids = array_slice(array_values(array_filter(array_map('intval', explode(',', $q['ids'])))), 0, 4);
            $cards = Catalog::cards($ids);
            if (!$cards) {
                return $none;
            }
            return ['kind' => 'sammenlign', 'label' => 'Sammenligning', 'product' => null, 'ids' => array_column($cards, 'id'),
                'text' => 'Kunden sammenligner: ' . implode(', ', array_map(fn($c) => "[{$c['id']}] {$c['name']}", $cards)) . '.'];
        }
        if (preg_match('#^/veiledning/([a-z0-9-]{1,120})$#', $path, $m)) {
            $st = db()->prepare('SELECT title, intro FROM manuals WHERE slug = ?');
            $st->execute([$m[1]]);
            if ($man = $st->fetch()) {
                return ['kind' => 'veiledning', 'label' => $man['title'], 'product' => null, 'ids' => [],
                    'text' => "Kunden leser monteringsveiledningen «{$man['title']}». " . mb_substr((string) $man['intro'], 0, 400)];
            }
        }
        return $none;
    }
}
