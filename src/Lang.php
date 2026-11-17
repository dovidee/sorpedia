<?php
declare(strict_types=1);

/**
 * Language guard for Søren. DeepSeek-R1 likes to reason in English even when we ask for Norwegian,
 * so we check the text in code instead of trusting the instruction. Simple and explainable:
 * we count common small words in English and Norwegian.
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

    /** 'no', 'en' or null (too little text to decide). */
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
        // Æ, ø and å don't exist in English
        $no += preg_match_all('/[æøå]/u', mb_strtolower($text)) > 2 ? 2 : 0;
        if ($en === 0 && $no === 0) {
            return null;
        }
        return $en > $no * 1.3 ? 'en' : 'no';
    }

    /**
     * Words that only exist in English. Words that are also Norwegian ("is", "and", "i", "to", "so", "do", "for", "hammer", "manual")
     * are left out on purpose, so mixed language isn't tipped over to English.
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
     * The language of a message from the customer: 'en' only when it is clearly English, 'no' when it is clearly Norwegian.
     * Otherwise (one word, product names, mixed language) $fallback is used, which is the language the customer chose with the flag, otherwise Norwegian.
     * Æ, ø and å count little, because English questions often mention Norwegian product names ("Does the Øyeskrue have ...").
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
