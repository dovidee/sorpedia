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

    /** A total the server worked out (feature 035): [[sum:ID+ID]], one of each product, filled in from the database. */
    private const SUM = '/\[\[sum:(\d{1,9}(?:\+\d{1,9}){1,9})\]\]/u';

    public static function price(int $id): string
    {
        return "[[pris:$id]]";
    }

    public static function stock(int $id): string
    {
        return "[[lager:$id]]";
    }

    /** @param int[] $ids */
    public static function sum(array $ids): string
    {
        return '[[sum:' . implode('+', array_values(array_unique(array_map('intval', $ids)))) . ']]';
    }

    /** The product IDs the model may reference: those with a reference in the facts it got. */
    public static function allowed(string $facts): array
    {
        preg_match_all(self::PATTERN, $facts, $m);
        return array_values(array_unique(array_map('intval', $m[2])));
    }

    /**
     * Removes links, and references to products that weren't in the facts. The rest are kept as references. Bare product IDs the
     * model copies from the facts («[[291448]]», «[291448]») mean nothing to the customer and are removed, and so are
     * markers without a product ID («[[pris]]», «[[id]]») and placeholders («[product]»), since there is nothing to fill in.
     */
    public static function clean(string $text, array $allowed): string
    {
        // A reference with single brackets («[pris:ID]») is read as the reference it was meant to be, so it is checked and filled
        // in like the others instead of reaching the customer as code
        $text = (string) preg_replace('/(?<!\[)\[(pris|lager):(\d{1,9})\](?!\])/u', '[[$1:$2]]', $text);
        // Links from the model are never shown: it has no source for addresses, and an address can carry a made-up price.
        // A Markdown link keeps its text; a bare address is removed
        $text = (string) preg_replace('/\[([^\[\]\n]{1,120})\]\(\s*(?:https?:\/\/|www\.)[^)\s]*\s*\)/iu', '$1', $text);
        $text = (string) preg_replace('/\s?\(?\b(?:https?:\/\/|www\.)[^\s)\]]+\)?/iu', '', $text);
        $text = (string) preg_replace('/\s?\[\[?\d{3,9}\]\]?/u', '', $text);
        $text = (string) preg_replace('/\s?\[\[(?!(?:pris|lager):\d{1,9}\]\]|sum:[\d+]{3,99}\]\])[^\[\]\n]{0,40}\]\]/u', '', $text);
        $text = (string) preg_replace('/\s?\[(?:product|produkt|produktnavn|product name|navn|name|pris|price|lager|stock)\](?!\])/iu', '', $text);
        $text = (string) preg_replace_callback(self::PATTERN, fn($m) => in_array((int) $m[2], $allowed, true) ? $m[0] : '', $text);
        $text = (string) preg_replace_callback(self::SUM, fn($m) => array_diff(array_map('intval', explode('+', $m[1])), $allowed) ? '' : $m[0], $text);
        // Bold marks left empty by a removed reference («**[[pris:999]]**» becomes «****») go too
        $text = (string) preg_replace('/(?<![*_])(\*\*|__)\s*\1(?![*_])/u', '', $text);
        // Brackets a removed marker left empty, or with only a lead-in such as «(som for eksempel )» or «(for example )»
        return (string) preg_replace('/\s?\(\s*(?:(?:som|such as|like)\s+)?(?:for eksempel|f\.\s?eks\.?|blant annet|for example|e\.g\.)?[\s,:]*\)/iu', '', $text);
    }

    /** Fills in the current price and stock from the database. $allowed = null accepts every product in the catalog. */
    public static function resolve(string $text, ?array $allowed = null): string
    {
        // The filled-in amount already ends in «kr», so a unit the model wrote after the reference is dropped («[[pris:1]] kr»)
        $text = (string) preg_replace('/(\[\[(?:pris|sum):[\d+]{1,99}\]\])(\*{0,2})\s*(?:kr|kroner|nok)(?!\p{L})/iu', '$1$2', $text);
        // Totals first: one of each product, summed in øre from the database, so no rounding and no model arithmetic
        $text = (string) preg_replace_callback(self::SUM, function ($m) use ($allowed) {
            $ids = array_values(array_unique(array_map('intval', explode('+', $m[1]))));
            if ($allowed !== null && array_diff($ids, $allowed)) {
                return '';
            }
            $total = AnswerGuard::total($ids);
            return $total['missing'] ? '' : kr($total['ore']);
        }, $text);
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
