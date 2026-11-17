<?php
declare(strict_types=1);

/**
 * Vern mot prompt injection i tekst fra kjøpere (spesifikasjoner i Åpen-versjonen og nye spesifikasjonstyper).
 *
 * Tre lag, fordi ingen av dem er nok alene:
 * 1. Ved lagring: en spesifikasjonsverdi skal være en verdi ("220", "Varmgalvanisert stål"), ikke en setning
 *    som gir ordre. Slik tekst avvises før den havner i databasen.
 * 2. Før språkmodellen: alt fra kjøpere sjekkes på nytt (gamle rader, rader lagt inn utenom skjemaet),
 *    kuttes, og settes i «anførselstegn» så modellen ser hva som er data.
 * 3. Etter språkmodellen: priser i svaret sammenlignes med prisene i databasen.
 */
final class Guard
{
    /** Ord som hører hjemme i en ordre til en språkmodell, ikke i en produktverdi. Norsk og engelsk. */
    private const IMPERATIVE = '/\b(ignore|ignorer|glem|forget|disregard|override|overstyr|return|returner|respond|reply|answer|svar|say|si at|skriv|write|print|output|pretend|late som|act as|you are|du er|du skal|you must|instruks\w*|instruction\w*|prompt|system|assistant|assistent|developer|jailbreak|regl(?:er|ene)|rules?)\b/iu';

    /**
     * Slår sammen triks som skjuler ord for en enkel sjekk: usynlige tegn, breddeformer (ｉｇｎｏｒｅ),
     * kyrilliske lookalikes (іgnоrе), tall som bokstaver (ign0re) og s p r e d t e bokstaver.
     */
    public static function normalize(string $text): string
    {
        $t = (string) preg_replace('/[\x{200B}-\x{200F}\x{2060}\x{FEFF}\x{00AD}]/u', '', $text);
        $t = (string) preg_replace_callback('/[\x{FF01}-\x{FF5E}]/u', fn($m) => mb_chr(mb_ord($m[0]) - 0xFEE0), $t);
        $t = strtr($t, ['а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'х' => 'x', 'у' => 'y', 'і' => 'i', 'ј' => 'j', 'ѕ' => 's',
            'А' => 'A', 'Е' => 'E', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Х' => 'X', 'І' => 'I', 'Ѕ' => 'S',
            'υ' => 'u', 'ο' => 'o', 'α' => 'a', 'ε' => 'e', 'ι' => 'i', 'κ' => 'k', 'ν' => 'v', 'ρ' => 'p', 'τ' => 't', 'χ' => 'x', 'Ο' => 'O', 'Α' => 'A', 'Ε' => 'E', 'Κ' => 'K']);
        // s p r e d t e  b o k s t a v e r  ->  spredte
        $t = (string) preg_replace_callback('/(?<!\S)(?:\p{L}[ ._\-]){3,}\p{L}(?!\S)/u', fn($m) => preg_replace('/[ ._\-]/u', '', $m[0]), $t);
        return $t;
    }

    /** Grunn til at teksten ikke ser ut som en produktverdi, eller null hvis den er grei. */
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
        // Pris, tilbud og lager kommer bare fra Sør-Tre (constitution punkt 1)
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

    /** Tydelige forsøk på å styre en språkmodell. Snevrere enn IMPERATIVE, så vanlige anmeldelser ikke stoppes. */
    private const INJECTION = '/\b(?:ignor\w*|glem|forget|disregard|overstyr|override)\b.{0,40}\b(?:instruks\w*|instruction\w*|regl\w*|rules?|alt|all|alle|tidligere|previous|above|ovenfor)\b'
        . '|\b(?:du er nå|du er heretter|you are now|act as|late som|pretend)\b'
        . '|\b(?:system ?prompt\w*|systemmelding\w*|jailbreak|developer mode)\b'
        . '|\b(?:svar|reply|respond|answer|return|returner|skriv|write|say|output)\b.{0,15}\b(?:bare|kun|only|just|med ordet|with the word)\b'
        . '|\b(?:søren|assistent\w*|assistant|ai|ki|språkmodell\w*|model)\b\s*[,:]'
        . '|<\s*\/?\s*\w+\s*>|https?:|www\./iu';

    /** Forsøk i chatten på å overstyre Søren: «ignorer reglene», «du er nå», «systemprompt» og lignende. */
    public static function chatInjection(string $text): bool
    {
        $t = mb_strtolower(self::normalize($text));
        return (bool) preg_match('/\b(?:ignor\w*|glem|forget|disregard|overstyr|override)\b.{0,40}\b(?:instruks\w*|instruction\w*|regl\w*|rules?|tidligere|previous|above|ovenfor)\b'
            . '|\b(?:du er nå|du er heretter|fra nå av er du|you are now|act as|late som|pretend|roleplay|rollespill)\b'
            . '|\b(?:system ?prompt\w*|systemmelding\w*|jailbreak|developer mode|dan mode)\b/iu', $t);
    }

    /** For fritekst fra kjøpere (anmeldelser) ved lagring: grunn hvis teksten prøver å styre Søren. */
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
     * Fritekst fra kjøper klar for prompten: setninger som ligner ordre, lenker eller rop fjernes,
     * resten kuttes og settes i «anførselstegn».
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

    /** Ser verdien ut som et tall, eventuelt med enhet eller som mål (1,5 kg, 220, 70x80, 10-20)? */
    public static function isNumberish(string $v): bool
    {
        return (bool) preg_match('/^\s*[~≈ca.]*\s*\d[\d\s.,]*(?:\s*[-–x×\/]\s*\d[\d\s.,]*)*\s*\p{L}{0,4}\.?\s*$/u', $v);
    }

    /** Tekst fra kjøper klar for prompten: i anførselstegn, eller skjult hvis den ser mistenkelig ut. */
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
