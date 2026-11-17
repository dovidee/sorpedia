<?php
declare(strict_types=1);

/**
 * Språkvakt for Søren. DeepSeek-R1 resonnerer gjerne på engelsk selv om vi ber om norsk,
 * så vi sjekker teksten i koden i stedet for å stole på instruksen. Enkel og forklarbar:
 * vi teller vanlige småord på engelsk og norsk.
 */
final class Lang
{
    private const EN = ['the', 'and', 'is', 'are', 'was', 'that', 'this', 'user', 'they', 'their', 'what', 'which', 'with',
        'about', 'should', 'would', 'could', 'maybe', 'need', 'but', 'let', 'okay', 'wait', 'so', 'it', 'its', "it's", 'of',
        'to', 'be', 'not', 'have', 'has', 'if', 'or', 'can', 'might', 'think', 'want', 'from', 'there', 'how', 'why', 'also', 'just',
        'i', "i'm", "i'll", "don't", 'you', 'your', 'he', 'she', 'them', 'do', 'does', 'any', 'some', 'first', 'right', 'product'];
    private const NO = ['og', 'det', 'er', 'som', 'jeg', 'ikke', 'på', 'til', 'kunden', 'han', 'hun', 'med', 'at', 'en', 'et', 'ei',
        'av', 'kan', 'skal', 'må', 'hva', 'vil', 'om', 'eller', 'også', 'så', 'nå', 'har', 'bør', 'produktet', 'den', 'de', 'vi',
        'hvis', 'fordi', 'derfor', 'kanskje', 'spør', 'spørsmålet', 'svare', 'svar', 'butikken', 'ikkje', 'dette', 'denne',
        'hvordan', 'hvorfor', 'mer', 'men', 'bare', 'ingen', 'noe', 'mitt', 'min', 'sin', 'sier', 'vet', 'gjelder', 'første'];

    /** 'no', 'en' eller null (for lite tekst til å avgjøre). */
    public static function detect(string $text, int $minWords = 12): ?string
    {
        $words = preg_split('/[^\p{L}\']+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) < $minWords) {
            return null;
        }
        $en = $no = 0;
        foreach ($words as $w) {
            $en += in_array($w, self::EN, true) ? 1 : 0;
            $no += in_array($w, self::NO, true) ? 1 : 0;
        }
        // Æ, ø og å finnes ikke i engelsk
        $no += preg_match_all('/[æøå]/u', mb_strtolower($text)) > 2 ? 2 : 0;
        if ($en === 0 && $no === 0) {
            return null;
        }
        return $en > $no * 1.3 ? 'en' : 'no';
    }

    /**
     * Ord som bare finnes på engelsk. Ord som også er norske («is», «and», «i», «to», «so», «do», «for», «hammer», «manual»)
     * er med vilje utelatt, så blandingsspråk ikke vippes over til engelsk.
     */
    private const Q_EN = ['the', 'what', 'which', 'does', 'have', 'has', 'how', 'why', 'where', 'need', 'want', 'show', 'find', 'any',
        'you', 'your', 'my', 'me', 'are', 'can', 'will', 'should', 'would', 'could', 'there', 'this', 'that', 'these', 'those', 'with',
        'about', 'cheapest', 'rated', 'buy', 'recommend', 'please', 'thanks', 'hello', 'hi', 'of', 'in', 'it', 'products', 'product',
        'good', 'better', 'best', 'worst', 'lowest', 'highest', 'expensive', 'cheap', 'instructions', 'manuals', 'reviews', 'stock',
        'tell', 'between', 'difference', 'compare', 'fit', 'fits', 'work', 'works', 'use', 'using', 'used', 'am', 'is it', "what's", "i'm"];
    private const Q_NO = ['hva', 'hvilke', 'hvilken', 'hvilket', 'hvordan', 'hvor', 'hvorfor', 'har', 'jeg', 'er', 'det', 'og', 'ikke',
        'med', 'til', 'på', 'en', 'et', 'ei', 'kan', 'skal', 'vil', 'noe', 'trenger', 'finnes', 'dere', 'du', 'meg', 'min', 'mitt', 'som',
        'av', 'om', 'billigst', 'dyrest', 'beste', 'veiledning', 'produkter', 'passer', 'hei', 'takk', 'bør', 'må', 'gjerne', 'sammen'];

    /**
     * Språket i en melding fra kunden: 'en' bare når det tydelig er engelsk, 'no' når det tydelig er norsk.
     * Ellers (ett ord, produktnavn, blandingsspråk) brukes $fallback, som er språket kunden har valgt med flagget, ellers norsk.
     * Æ, ø og å teller lite, fordi engelske spørsmål ofte nevner norske produktnavn («Does the Øyeskrue have …»).
     */
    public static function question(string $text, string $fallback = 'no'): string
    {
        $lower = mb_strtolower($text);
        $words = preg_split('/[^\p{L}\']+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $en = $no = 0;
        foreach ($words as $w) {
            $en += in_array($w, self::Q_EN, true) || isset(I18nEn::SEARCH[$w]) && !in_array($w, I18nEn::SEARCH, true) ? 1 : 0;
            $no += in_array($w, self::Q_NO, true) ? 1 : 0;
        }
        $no += preg_match('/[æøå]/u', $lower) ? 1 : 0;
        if ($en >= 2 && $en > 2 * $no) {
            return 'en';
        }
        if ($no >= 1 && $no >= $en) {
            return 'no';
        }
        return $fallback === 'en' ? 'en' : 'no';
    }

    public static function isEnglish(string $text, int $minWords = 12): bool
    {
        return self::detect($text, $minWords) === 'en';
    }
}
