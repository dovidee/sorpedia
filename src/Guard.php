<?php
declare(strict_types=1);

/**
 * Protection against prompt injection in text from buyers (specifications in the open version and new specification types).
 *
 * Layers, because none of them is enough alone:
 * 1. On save: a specification value must be a value ("220", "Varmgalvanisert stål"), not a sentence
 *    giving orders. Such text is rejected before it reaches the database.
 * 2. Before the language model: everything from buyers is checked again (old rows, rows added outside the form),
 *    shortened, and put in quotes so the model sees what is data.
 * 3. After the language model: product cards with name, price and stock are built from the database, not from the answer text.
 */
final class Guard
{
    /** Words that belong in an order to a language model, not in a product value. Norwegian and English. */
    private const IMPERATIVE = '/\b(ignore|ignorer|glem|forget|disregard|override|overstyr|return|returner|respond|reply|answer|svar|say|si at|skriv|write|print|output|pretend|late som|act as|you are|du er|du skal|you must|instruks\w*|instruction\w*|prompt|system|assistant|assistent|developer|jailbreak|regl(?:er|ene)|rules?)\b/iu';

    /**
     * Normalizes tricks that hide words from a simple check: invisible characters, fullwidth forms (ｉｇｎｏｒｅ),
     * Cyrillic lookalikes (іgnоrе), numbers as letters (ign0re) and s p a c e d letters.
     */
    public static function normalize(string $text): string
    {
        $t = (string) preg_replace('/[\x{200B}-\x{200F}\x{2060}\x{FEFF}\x{00AD}]/u', '', $text);
        $t = (string) preg_replace_callback('/[\x{FF01}-\x{FF5E}]/u', fn($m) => mb_chr(mb_ord($m[0]) - 0xFEE0), $t);
        $t = strtr($t, ['а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'х' => 'x', 'у' => 'y', 'і' => 'i', 'ј' => 'j', 'ѕ' => 's',
            'А' => 'A', 'Е' => 'E', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Х' => 'X', 'І' => 'I', 'Ѕ' => 'S',
            'υ' => 'u', 'ο' => 'o', 'α' => 'a', 'ε' => 'e', 'ι' => 'i', 'κ' => 'k', 'ν' => 'v', 'ρ' => 'p', 'τ' => 't', 'χ' => 'x', 'Ο' => 'O', 'Α' => 'A', 'Ε' => 'E', 'Κ' => 'K']);
        // s p a c e d  l e t t e r s  ->  spaced
        $t = (string) preg_replace_callback('/(?<!\S)(?:\p{L}[ ._\-]){3,}\p{L}(?!\S)/u', fn($m) => preg_replace('/[ ._\-]/u', '', $m[0]), $t);
        return $t;
    }

    /** Reason the text doesn't look like a product value, or null if it is fine. */
    public static function suspicious(string $text): ?string
    {
        $t = trim(self::normalize($text));
        if ($t === '') {
            return null;
        }
        $leet = strtr(mb_strtolower($t), ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '$' => 's', '@' => 'a']);
        if (preg_match('/[\r\n<>{}\[\]`]|https?:|www\.|@/u', $t)) {
            return 'inneholder tegn eller lenker som ikke hører hjemme i en produktverdi';
        }
        if (preg_match(self::IMPERATIVE, $t) || preg_match(self::IMPERATIVE, $leet)) {
            return 'ser ut som en instruks, ikke en verdi';
        }
        // Price, offers and stock only come from Sør-Tre (constitution point 1)
        if (preg_match('/\b(kr|kroner|nok|pris\w*|gratis|tilbud\w*|kampanje\w*|rabatt\w*|salg|utsalg|lager\w*|price|free|discount|sale)\b|,-/iu', $t)) {
            return 'handler om pris eller lager, som bare Sør-Tre kan oppgi';
        }
        if (preg_match('/["“”«»]/u', $t) && str_word_count($t) >= 3) {
            return 'inneholder sitat, ikke en verdi';
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $t);
        $upper = preg_replace('/[^\p{Lu}]/u', '', $t);
        if (mb_strlen($letters) >= 12 && mb_strlen($upper) / mb_strlen($letters) > 0.6 && preg_match_all('/\p{L}+/u', $t) >= 3) {
            return 'er skrevet med store bokstaver som en ordre';
        }
        if (preg_match_all('/\p{L}+/u', $t) > 10) {
            return 'er for lang til å være en verdi';
        }
        return null;
    }

    /** Clear attempts to steer a language model. Narrower than IMPERATIVE, so ordinary reviews aren't stopped. */
    private const INJECTION = '/\b(?:ignor\w*|glem|forget|disregard|overstyr|override)\b.{0,40}\b(?:instruks\w*|instruction\w*|regl\w*|rules?|alt|all|alle|tidligere|previous|above|ovenfor)\b'
        . '|\b(?:du er nå|du er heretter|you are now|act as|late som|pretend)\b'
        . '|\b(?:system ?prompt\w*|systemmelding\w*|jailbreak|developer mode)\b'
        . '|\b(?:svar|reply|respond|answer|return|returner|skriv|write|say|output)\b.{0,15}\b(?:bare|kun|only|just|med ordet|with the word)\b'
        . '|\b(?:søren|assistent\w*|assistant|ai|ki|språkmodell\w*|model)\b\s*[,:]'
        . '|<\s*\/?\s*\w+\s*>|https?:|www\./iu';

    /** Attempts in the chat to override Søren: "ignore the rules", "you are now", "system prompt" and the like. */
    public static function chatInjection(string $text): bool
    {
        $t = mb_strtolower(self::normalize($text));
        return (bool) preg_match('/\b(?:ignor\w*|glem|forget|disregard|overstyr|override)\b.{0,40}\b(?:instruks\w*|instruction\w*|regl\w*|rules?|tidligere|previous|above|ovenfor)\b'
            . '|\b(?:du er nå|du er heretter|fra nå av er du|you are now|act as|late som|pretend|roleplay|rollespill)\b'
            . '|\b(?:system ?prompt\w*|systemmelding\w*|jailbreak|developer mode|dan mode)\b/iu', $t);
    }

    /** For free text from buyers (reviews) on save: a reason if the text tries to steer Søren. */
    public static function injectionInProse(string $text): ?string
    {
        $t = self::normalize($text);
        $leet = strtr(mb_strtolower($t), ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '$' => 's', '@' => 'a']);
        if (preg_match(self::INJECTION, $t) || preg_match(self::INJECTION, $leet)) {
            return 'inneholder noe som ser ut som en instruks til Søren eller en lenke';
        }
        return null;
    }

    /**
     * Free text from a buyer, ready for the prompt: sentences that look like orders, links or shouting are removed,
     * the rest is shortened and put in quotes.
     */
    public static function forProse(string $text, int $max = 220): string
    {
        $t = trim((string) preg_replace('/\s+/u', ' ', self::normalize(Moderation::censor($text))));
        if (str_starts_with($t, '[skjult')) {
            return '[skjult: upassende innhold]';
        }
        $keep = [];
        $dropped = 0;
        foreach (preg_split('/(?<=[.!?])\s+/u', $t) ?: [] as $snt) {
            $leet = strtr(mb_strtolower($snt), ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '$' => 's', '@' => 'a']);
            $letters = preg_replace('/[^\p{L}]/u', '', $snt);
            $caps = mb_strlen($letters) >= 12 && mb_strlen(preg_replace('/[^\p{Lu}]/u', '', $snt)) / mb_strlen($letters) > 0.6;
            if (preg_match(self::INJECTION, $snt) || preg_match(self::INJECTION, $leet) || preg_match('/\b(?:ignore|ignorer|disregard|override|overstyr|jailbreak|prompt\w*|you are|you must)\b/iu', $leet)
                || preg_match('/[<>{}\[\]`]|@/u', $snt) || $caps) {
                $dropped++;
                continue;
            }
            $keep[] = $snt;
        }
        $body = mb_substr(str_replace(['«', '»'], '', implode(' ', $keep)), 0, $max);
        if ($body === '') {
            return '[skjult: hele teksten så ut som instrukser]';
        }
        return "«{$body}»" . ($dropped ? " [{$dropped} setning(er) fjernet fordi de lignet instrukser]" : '');
    }

    /** Does the value look like a number, possibly with a unit or as dimensions (1,5 kg, 220, 70x80, 10-20)? */
    public static function isNumberish(string $v): bool
    {
        return (bool) preg_match('/^\s*[~≈ca.]*\s*\d[\d\s.,]*(?:\s*[-–x×\/]\s*\d[\d\s.,]*)*\s*\p{L}{0,4}\.?\s*$/u', $v);
    }

    /** Text from a buyer, ready for the prompt: in quotes, or hidden if it looks suspicious. */
    public static function forPrompt(string $text, int $max = 60): string
    {
        if (!Moderation::clean($text)) {
            return '[skjult: upassende innhold]';
        }
        if (self::suspicious($text) !== null) {
            return '[skjult: teksten fra kjøperen så ut som en instruks og er fjernet]';
        }
        $t = mb_substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, $max);
        return '«' . str_replace(['«', '»'], '', $t) . '»';
    }

}
