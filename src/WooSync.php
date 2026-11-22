<?php
declare(strict_types=1);

/**
 * Keeps the catalogue in step with a WooCommerce shop (feature 025). The shop sends its standard webhooks
 * (WooCommerce > Settings > Advanced > Webhooks, topics product.created and product.updated) to
 * POST /api/integrations/woocommerce/webhook. Each delivery is checked against the shared secret
 * (X-WC-Webhook-Signature: base64 of HMAC-SHA256 over the raw body) and applied once (X-WC-Webhook-Delivery-ID).
 *
 * The shop stays the source of truth for ID, name, SKU and price (constitution rule 1). Specifications are read again
 * from the new text: the shop's own values replace the old verified values, derived values replace the old derived
 * ones, and values from buyers or staff are never touched. Nothing is fetched from the shop at runtime.
 */
final class WooSync
{
    /** WooCommerce category ID => theme in the guide (docs/context/product-taxonomy.md). Shared with scripts/scrape.php. */
    public const CATEGORY_THEME = [
        7111 => 'lys', 7129 => 'lys', 7117 => 'lys', 7160 => 'lys',
        7207 => 'bygg', 7137 => 'bygg', 7138 => 'bygg', 7663 => 'bygg', 7118 => 'bygg', 7128 => 'bygg',
        7364 => 'maling', 7179 => 'maling', 7193 => 'maling', 7206 => 'maling', 7253 => 'maling',
        7678 => 'balkong', 7680 => 'balkong', 7679 => 'balkong',
        7499 => 'kjetting', 7504 => 'kjetting', 7220 => 'kjetting',
        7070 => 'skog', 7079 => 'skog', 7088 => 'skog', 7087 => 'skog',
        7246 => 'tur', 7258 => 'tur',
    ];

    public static function ensure(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS integration_deliveries (
            delivery_id VARCHAR(64) PRIMARY KEY,
            topic       VARCHAR(64) NOT NULL,
            product_id  INT UNSIGNED NULL,
            outcome     VARCHAR(32) NOT NULL,
            received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB');
    }

    /** Is the signature right for this body and secret? Constant-time comparison. Pure. */
    public static function signed(string $raw, string $signature, string $secret): bool
    {
        return $secret !== '' && $signature !== '' && hash_equals(base64_encode(hash_hmac('sha256', $raw, $secret, true)), trim($signature));
    }

    /** Theme for a WooCommerce product: the first category that maps, else null. Pure. */
    public static function theme(array $categories): ?string
    {
        foreach ($categories as $c) {
            if (isset(self::CATEGORY_THEME[(int) ($c['id'] ?? 0)])) {
                return self::CATEGORY_THEME[(int) $c['id']];
            }
        }
        return null;
    }

    /** Price in øre from WooCommerce's decimal string ("199.00", "199,5", ""). Null when not a price. Pure. */
    public static function ore(mixed $price): ?int
    {
        $s = str_replace(',', '.', trim((string) $price));
        return preg_match('/^\d{1,7}(\.\d{1,2})?$/', $s) ? (int) round((float) $s * 100) : null;
    }

    /**
     * Handles one webhook request.
     * @param array<string, string> $headers lower-case names
     * @return array{0: int, 1: array<string, mixed>} status and JSON body
     */
    public static function receive(string $raw, array $headers, string $secret): array
    {
        // WooCommerce pings a new webhook with a form body "webhook_id=N" and expects 200
        if (preg_match('/^webhook_id=\d+$/', trim($raw))) {
            return [200, ['ok' => true, 'ping' => true]];
        }
        if ($secret === '') {
            return [503, ['ok' => false, 'error' => 'The webhook secret (WC_WEBHOOK_SECRET) is not set on this server.']];
        }
        if (!self::signed($raw, $headers['x-wc-webhook-signature'] ?? '', $secret)) {
            return [401, ['ok' => false, 'error' => 'Signature does not match.']];
        }
        $topic = $headers['x-wc-webhook-topic'] ?? '';
        $delivery = substr($headers['x-wc-webhook-delivery-id'] ?? '', 0, 64);
        $pdo = db();
        self::ensure($pdo);
        if ($delivery !== '') {
            $st = $pdo->prepare('SELECT outcome FROM integration_deliveries WHERE delivery_id = ?');
            $st->execute([$delivery]);
            if ($seen = $st->fetchColumn()) {
                return [200, ['ok' => true, 'duplicate' => true, 'outcome' => $seen]];
            }
        }
        $p = json_decode($raw, true);
        if (!is_array($p)) {
            return [400, ['ok' => false, 'error' => 'Body is not JSON.']];
        }
        $outcome = in_array($topic, ['product.created', 'product.updated', 'product.restored'], true) ? self::upsert($p) : 'ignored';
        if ($delivery !== '') {
            $pdo->prepare('INSERT IGNORE INTO integration_deliveries (delivery_id, topic, product_id, outcome) VALUES (?, ?, ?, ?)')
                ->execute([$delivery, $topic, isset($p['id']) ? (int) $p['id'] : null, $outcome]);
        }
        return [$outcome === 'invalid' ? 422 : 200, ['ok' => $outcome !== 'invalid', 'topic' => $topic, 'outcome' => $outcome]];
    }

    /**
     * Writes one product from a WooCommerce REST v3 product payload. Returns created, updated, skipped (no theme for a
     * new product) or invalid.
     */
    public static function upsert(array $p): string
    {
        $id = (int) ($p['id'] ?? 0);
        $name = trim(html_entity_decode(strip_tags((string) ($p['name'] ?? '')), ENT_QUOTES | ENT_HTML5));
        $price = self::ore($p['price'] ?? null);
        if ($id < 1 || $name === '' || $price === null) {
            return 'invalid';
        }
        $pdo = db();
        $st = $pdo->prepare('SELECT theme, slug FROM products WHERE id = ?');
        $st->execute([$id]);
        $old = $st->fetch();
        $theme = $old['theme'] ?? self::theme($p['categories'] ?? []);
        if ($theme === null) {
            return 'skipped'; // outside the themes the guide covers
        }
        $regular = self::ore($p['regular_price'] ?? null);
        $html = (string) ($p['description'] ?? '') . ' ' . (string) ($p['short_description'] ?? '');
        $specs = Extract::specs($name, $html, $theme);
        $brand = null;
        foreach ($specs as $s) {
            $brand = $s['key'] === 'merke' ? $s['text'] : $brand;
        }
        $slug = $old['slug'] ?? self::slug((string) ($p['slug'] ?? $name), $id);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO products (id, sku, name, slug, theme, brand, price_ore, regular_ore, short_html, long_html, plain_text, source_url)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE sku = VALUES(sku), name = VALUES(name), brand = VALUES(brand), price_ore = VALUES(price_ore),
                  regular_ore = VALUES(regular_ore), short_html = VALUES(short_html), long_html = VALUES(long_html),
                  plain_text = VALUES(plain_text), source_url = VALUES(source_url)')
                ->execute([$id, ($p['sku'] ?? '') !== '' ? (string) $p['sku'] : null, $name, $slug, $theme, $brand, $price,
                    $regular !== null && $regular > $price ? $regular : null, $p['short_description'] ?? null, $p['description'] ?? null,
                    Extract::plain($html), $p['permalink'] ?? null]);
            $cat = $pdo->prepare('INSERT IGNORE INTO product_categories (product_id, category_id)
                SELECT ?, id FROM categories WHERE id = ?');
            foreach ($p['categories'] ?? [] as $c) {
                $cat->execute([$id, (int) ($c['id'] ?? 0)]);
            }
            // Machine values are rebuilt; buyer and staff values (and anything from a revision) stay
            $pdo->prepare("DELETE FROM spec_values WHERE product_id = ? AND source IN ('sortre', 'utledet') AND revision_id IS NULL")->execute([$id]);
            $key = $pdo->prepare('INSERT IGNORE INTO spec_keys (`key`, label, unit, datatype, is_facet, themes, sort) VALUES (?,?,?,?,0,?,90)');
            $val = $pdo->prepare('INSERT IGNORE INTO spec_values (product_id, `key`, version, value_text, value_num, source, evidence) VALUES (?,?,?,?,?,?,?)');
            foreach ($specs as $s) {
                $key->execute([$s['key'], $s['label'] ?? $s['key'], $s['unit'] ?? null, $s['num'] !== null ? 'number' : 'text', $theme]);
                $val->execute([$id, $s['key'], $s['source'] === 'sortre' ? 'verifisert' : 'apen', $s['text'], $s['num'], $s['source'], $s['evidence']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        emit('catalog', $old ? 'updated' : 'created', ['product' => $id]);
        return $old ? 'updated' : 'created';
    }

    private static function slug(string $s, int $id): string
    {
        $s = strtr(mb_strtolower($s), ['æ' => 'ae', 'ø' => 'o', 'å' => 'a']);
        $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-') ?: "produkt-$id";
        $st = db()->prepare('SELECT 1 FROM products WHERE slug = ? AND id <> ?');
        $st->execute([$s, $id]);
        return $st->fetchColumn() ? "$s-$id" : $s;
    }
}
