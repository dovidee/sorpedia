<?php
declare(strict_types=1);

/**
 * Product references in the model's text (feature 015). The model never gets exact prices or stock counts. The facts
 * carry references instead, [[pris:ID]] and [[lager:ID]], and the model copies them. The server checks each reference
 * against the IDs in the facts it sent, and fills in the price and stock from the database when the text is shown.
 *
 * Answers are stored with the references, not the numbers, so a later answer or the support log shows the price and
 * stock as they are then, never as they were when the answer was written.
 */
final class ProductRef
{
    private const PATTERN = '/\[\[(pris|lager):(\d{1,9})\]\]/u';

    public static function price(int $id): string
    {
        return "[[pris:$id]]";
    }

    public static function stock(int $id): string
    {
        return "[[lager:$id]]";
    }

    /** The product IDs the model may reference: those with a reference in the facts it got. */
    public static function allowed(string $facts): array
    {
        preg_match_all(self::PATTERN, $facts, $m);
        return array_values(array_unique(array_map('intval', $m[2])));
    }

    /**
     * Removes references to products that weren't in the facts. The rest are kept as references. Bare product IDs the
     * model copies from the facts («[[291448]]», «[291448]») mean nothing to the customer and are removed, and so are
     * markers without a product ID («[[pris]]», «[[id]]») and placeholders («[product]»), since there is nothing to fill in.
     */
    public static function clean(string $text, array $allowed): string
    {
        $text = (string) preg_replace('/\s?\[\[?\d{3,9}\]\]?/u', '', $text);
        $text = (string) preg_replace('/\s?\[\[(?!(?:pris|lager):\d{1,9}\]\])[^\[\]\n]{0,40}\]\]/u', '', $text);
        $text = (string) preg_replace('/\s?\[(?:product|produkt|produktnavn|product name|navn|name|pris|price|lager|stock)\](?!\])/iu', '', $text);
        $text = (string) preg_replace_callback(self::PATTERN, fn($m) => in_array((int) $m[2], $allowed, true) ? $m[0] : '', $text);
        // Brackets a removed marker left empty, or with only a lead-in such as «(som for eksempel )» or «(for example )»
        return (string) preg_replace('/\s?\(\s*(?:(?:som|such as|like)\s+)?(?:for eksempel|f\.\s?eks\.?|blant annet|for example|e\.g\.)?[\s,:]*\)/iu', '', $text);
    }

    /** Fills in the current price and stock from the database. $allowed = null accepts every product in the catalog. */
    public static function resolve(string $text, ?array $allowed = null): string
    {
        if (!preg_match_all(self::PATTERN, $text, $m)) {
            return $text;
        }
        $ids = array_values(array_unique(array_map('intval', $m[2])));
        if ($allowed !== null) {
            $ids = array_values(array_intersect($ids, $allowed));
        }
        $cards = [];
        foreach (Catalog::cards($ids) as $c) {
            $cards[(int) $c['id']] = $c;
        }
        return preg_replace_callback(self::PATTERN, function ($m) use ($cards) {
            $c = $cards[(int) $m[2]] ?? null;
            if ($c === null) {
                return '';
            }
            if ($m[1] === 'pris') {
                return kr((int) $c['price_ore']);
            }
            return $c['in_store'] > 0 ? t('{n} i butikk', ['n' => $c['in_store']]) : t($c['online'] > 0 ? 'på nettlager' : 'ikke på lager');
        }, $text);
    }
}
