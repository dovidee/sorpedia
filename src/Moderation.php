<?php
declare(strict_types=1);

/**
 * Family friendly content. Sørpedia should be usable by all ages, so swearing, slurs, hate and
 * sexual content are stopped everywhere people can write (the open version, reviews, names, chat) and
 * in everything Søren says.
 *
 * The word list is stems. A word matches if it starts with a stem and the rest is a known inflection,
 * so "idiot" is stopped while "idiotsikker" (foolproof) gets through. Before the check, tricks are normalized with Guard::normalize
 * (spaced letters, fullwidth forms, Cyrillic lookalikes) and numbers as letters (h1tler). Double letters
 * count as one (faaaen).
 */
final class Moderation
{
    /** Stem => allowed endings (regex). Lowercase, without double letters where that is natural. */
    private const WORDS = [
        // Swearing (Norwegian)
        'faen' => '|s', 'fanden' => '', 'fan' => 'ken', 'helvete' => '|s|t', 'jævla' => '', 'jævel' => '|en|er|ene', 'jævlig' => '|e|ere|st|ste',
        'satan' => '|s', 'dritt' => '\p{L}*', 'drit' => '\p{L}*', 'faens' => '', 'pokker' => '', 'fy faen' => '',
        'kuk' => '|en|er|ene|hue|huer|tryne', 'pikk' => '|en|er|ene|hue|huer', 'fitte' => '|n|r|ne|tryne|truten', 'pule' => '|r|t',
        'hore' => '|n|r|ne|bukk|unge|sønn', 'horunge' => '', 'ræv' => '\p{L}*', 'rasshøl' => '|et|a', 'rævhøl' => '|et|a', 'mammaknuller' => '',
        // Swearing (English)
        'fuck' => '\p{L}*', 'fck' => '\p{L}*', 'fuk' => '|ing|in', 'shit' => '\p{L}*', 'bitch' => '\p{L}*', 'cunt' => '\p{L}*', 'dick' => '|s|head|heads',
        'cock' => '|s|sucker', 'pussy' => '', 'bastard' => '|s|er|en', 'asshole' => '|s', 'arsehole' => '|s', 'wanker' => '|s', 'twat' => '|s', 'motherfuck' => '\p{L}*',
        'damn' => '|it', 'crap' => '|py',
        // Insults and derogatory words
        'idiot' => '|en|er|ene|isk|iske', 'mongo' => '|er|ene', 'retard' => '|ed|s|ert|erte', 'homo' => '|en|er|ene|jævel', 'soper' => '|en|e|ne',
        'neger' => '|en|e|ne|re|ene', 'negre' => '|ne', 'nigger' => '|s|e|en|ne', 'nigga' => '|s|z', 'faggot' => '|s', 'fag' => '|s', 'tranny' => '',
        'pakkis' => '|en|er', 'svarting' => '|en|er|ene', 'sigøyner' => '|pakk', 'kjerring' => '|a|er|ene', 'tulling' => '|en|er|ene',
        'kødd' => '|en|er|ene', 'loser' => '|s', 'dumming' => '|en|er|ene', 'dumskalle' => '|n|r',
        // Hate and extremism
        'hitler' => '\p{L}*', 'heil' => '', 'sieg' => '', 'nazi' => '\p{L}*', 'nazist' => '\p{L}*', 'nynazi' => '\p{L}*', 'hakekors' => '|et|ene', 'swastika' => '|s',
        'white power' => '', 'white pride' => '',
        // Sexual
        'sex' => '\p{L}*', 'porn' => '\p{L}*', 'porno' => '\p{L}*', 'naken' => '', 'nakenbilde' => '|r|t', 'pupp' => '|er|ene|ar', 'tits' => '', 'boobs' => '',
        'nude' => '|s', 'hentai' => '', 'onaner' => '\p{L}*', 'runk' => '\p{L}*', 'knull' => '\p{L}*', 'pul' => '|e|er|te', 'dildo' => '|en|er|s', 'orgasm' => '\p{L}*',
        'kåt' => '|e|ere', 'horny' => '', 'milf' => '', 'blowjob' => '|s',
        // Drugs and violence
        'cocaine' => '', 'kokain' => '', 'hasj' => '', 'drep deg' => '', 'kill yourself' => '',
    ];

    /** Words that look similar but are fine in a hardware store. Checked before the list above. */
    private const ALLOW = ['dusj', 'dusjer', 'duster', 'sexkant', 'sekskant', 'damning', 'dicks', 'fane', 'fanen', 'faner', 'pult', 'pulten', 'pulter',
        'kyst', 'kysten', 'hasjtag', 'stygt', 'dumper', 'dumpe', 'dumping', 'dumpeslange', 'teite', 'pakking', 'drift', 'heilt'];

    /** Matched literally, without collapsing double letters (kkk would otherwise become k). */
    private const RAW = '/(?<![\p{L}\d])(?:kkk|1488|14\/88)(?![\p{L}\d])/iu';

    private static ?string $re = null;

    private static function squeeze(string $s): string
    {
        return (string) preg_replace('/(\p{L})\1+/u', '$1', $s);
    }

    private static function regex(): string
    {
        if (self::$re === null) {
            $alts = [];
            foreach (self::WORDS as $stem => $end) {
                $alts[] = preg_quote(self::squeeze((string) $stem), "/") . ($end === '' ? '' : '(?:' . self::squeeze($end) . ')');
            }
            usort($alts, fn($a, $b) => strlen($b) <=> strlen($a));
            self::$re = '/(?<![\p{L}\d])(?:' . implode('|', $alts) . ')(?![\p{L}\d])/iu';
        }
        return self::$re;
    }

    /** The text in the forms we check: plain, without tricks, and with numbers as letters. */
    private static function variants(string $text): array
    {
        $n = mb_strtolower(Guard::normalize($text));
        $leet = strtr($n, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '$' => 's', '@' => 'a', '!' => 'i', '€' => 'e']);
        $out = [];
        foreach ([$n, $leet] as $v) {
            $v = preg_replace_callback('/\p{L}+/u', fn($m) => in_array($m[0], self::ALLOW, true) ? '' : $m[0], $v);
            $out[] = self::squeeze((string) $v);
            // f.u.c.k and f*ck: remove punctuation inside words
            $out[] = self::squeeze((string) preg_replace('/(?<=\p{L})[.\-_*]+(?=\p{L})/u', '', (string) $v));
        }
        return array_unique($out);
    }

    /** True if the text is family friendly. */
    public static function clean(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return true;
        }
        if (preg_match(self::RAW, mb_strtolower(Guard::normalize($text)))) {
            return false;
        }
        foreach (self::variants($text) as $v) {
            if (preg_match(self::regex(), $v)) {
                return false;
            }
        }
        return true;
    }

    /** Reason for the customer, or null. */
    public static function reason(?string $text): ?string
    {
        return self::clean($text) ? null : 'inneholder banning eller ord som ikke passer for alle aldre';
    }

    /**
     * Censors the text for display: every bad word becomes its first letter and asterisks (f***).
     * If the tricks are so creative that we can't find the word again, the whole text is hidden.
     */
    public static function censor(?string $text): string
    {
        $text = (string) $text;
        if (self::clean($text)) {
            return $text;
        }
        $out = preg_replace_callback('/[\p{L}\d$@!€*._\-]+/u', function ($m) {
            $w = $m[0];
            if (self::clean($w)) {
                return $w;
            }
            $first = mb_substr($w, 0, 1);
            return $first . str_repeat('*', max(2, mb_strlen($w) - 1));
        }, $text);
        // Multi word phrases ("white power") and spaced letters aren't caught word by word
        return self::clean($out) ? $out : '[skjult: upassende innhold]';
    }
}
