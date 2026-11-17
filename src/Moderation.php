<?php
declare(strict_types=1);

/**
 * Familievennlig innhold. Sørpedia skal kunne brukes av alle aldre, så banning, skjellsord, hat og
 * seksuelt innhold stoppes overalt der folk kan skrive (Åpen-versjonen, anmeldelser, navn, chat) og
 * i alt Søren sier.
 *
 * Ordlisten er stammer. Et ord treffer hvis det starter med en stamme og resten er en kjent bøyning,
 * så «idiot» stoppes mens «idiotsikker» slipper gjennom. Før sjekken slås triks sammen med Guard::normalize
 * (spredte bokstaver, breddeformer, kyrilliske lookalikes) og tall som bokstaver (h1tler). Doble bokstaver
 * telles som én (faaaen).
 */
final class Moderation
{
    /** Stamme => tillatte endelser (regex). Lowercase, uten doble bokstaver der det er naturlig. */
    private const WORDS = [
        // Banning (norsk)
        'faen' => '|s', 'fanden' => '', 'fan' => 'ken', 'helvete' => '|s|t', 'jævla' => '', 'jævel' => '|en|er|ene', 'jævlig' => '|e|ere|st|ste',
        'satan' => '|s', 'dritt' => '\p{L}*', 'drit' => '\p{L}*', 'faens' => '', 'pokker' => '', 'fy faen' => '',
        'kuk' => '|en|er|ene|hue|huer|tryne', 'pikk' => '|en|er|ene|hue|huer', 'fitte' => '|n|r|ne|tryne|truten', 'pule' => '|r|t',
        'hore' => '|n|r|ne|bukk|unge|sønn', 'horunge' => '', 'ræv' => '\p{L}*', 'rasshøl' => '|et|a', 'rævhøl' => '|et|a', 'mammaknuller' => '',
        // Banning (engelsk)
        'fuck' => '\p{L}*', 'fck' => '\p{L}*', 'fuk' => '|ing|in', 'shit' => '\p{L}*', 'bitch' => '\p{L}*', 'cunt' => '\p{L}*', 'dick' => '|s|head|heads',
        'cock' => '|s|sucker', 'pussy' => '', 'bastard' => '|s|er|en', 'asshole' => '|s', 'arsehole' => '|s', 'wanker' => '|s', 'twat' => '|s', 'motherfuck' => '\p{L}*',
        'damn' => '|it', 'crap' => '|py',
        // Skjellsord og nedsettende ord
        'idiot' => '|en|er|ene|isk|iske', 'mongo' => '|er|ene', 'retard' => '|ed|s|ert|erte', 'homo' => '|en|er|ene|jævel', 'soper' => '|en|e|ne',
        'neger' => '|en|e|ne|re|ene', 'negre' => '|ne', 'nigger' => '|s|e|en|ne', 'nigga' => '|s|z', 'faggot' => '|s', 'fag' => '|s', 'tranny' => '',
        'pakkis' => '|en|er', 'svarting' => '|en|er|ene', 'sigøyner' => '|pakk', 'kjerring' => '|a|er|ene', 'tulling' => '|en|er|ene',
        'kødd' => '|en|er|ene', 'loser' => '|s', 'dumming' => '|en|er|ene', 'dumskalle' => '|n|r',
        // Hat og ekstremisme
        'hitler' => '\p{L}*', 'heil' => '', 'sieg' => '', 'nazi' => '\p{L}*', 'nazist' => '\p{L}*', 'nynazi' => '\p{L}*', 'hakekors' => '|et|ene', 'swastika' => '|s',
        'white power' => '', 'white pride' => '',
        // Seksuelt
        'sex' => '\p{L}*', 'porn' => '\p{L}*', 'porno' => '\p{L}*', 'naken' => '', 'nakenbilde' => '|r|t', 'pupp' => '|er|ene|ar', 'tits' => '', 'boobs' => '',
        'nude' => '|s', 'hentai' => '', 'onaner' => '\p{L}*', 'runk' => '\p{L}*', 'knull' => '\p{L}*', 'pul' => '|e|er|te', 'dildo' => '|en|er|s', 'orgasm' => '\p{L}*',
        'kåt' => '|e|ere', 'horny' => '', 'milf' => '', 'blowjob' => '|s',
        // Rus og vold
        'cocaine' => '', 'kokain' => '', 'hasj' => '', 'drep deg' => '', 'kill yourself' => '',
    ];

    /** Ord som ligner, men er greie i en jernvarebutikk. Sjekkes før listen over. */
    private const ALLOW = ['dusj', 'dusjer', 'duster', 'sexkant', 'sekskant', 'damning', 'dicks', 'fane', 'fanen', 'faner', 'pult', 'pulten', 'pulter',
        'kyst', 'kysten', 'hasjtag', 'stygt', 'dumper', 'dumpe', 'dumping', 'dumpeslange', 'teite', 'pakking', 'drift', 'heilt'];

    /** Treffes ordrett, uten å slå sammen doble bokstaver (kkk ville ellers blitt k). */
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

    /** Teksten i formene vi sjekker: vanlig, uten triks, og med tall som bokstaver. */
    private static function variants(string $text): array
    {
        $n = mb_strtolower(Guard::normalize($text));
        $leet = strtr($n, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '$' => 's', '@' => 'a', '!' => 'i', '€' => 'e']);
        $out = [];
        foreach ([$n, $leet] as $v) {
            $v = preg_replace_callback('/\p{L}+/u', fn($m) => in_array($m[0], self::ALLOW, true) ? '' : $m[0], $v);
            $out[] = self::squeeze((string) $v);
            // f.u.c.k og f*ck: fjern skilletegn inne i ord
            $out[] = self::squeeze((string) preg_replace('/(?<=\p{L})[.\-_*]+(?=\p{L})/u', '', (string) $v));
        }
        return array_unique($out);
    }

    /** True hvis teksten er familievennlig. */
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

    /** Begrunnelse til kunden, eller null. */
    public static function reason(?string $text): ?string
    {
        return self::clean($text) ? null : 'inneholder banning eller ord som ikke passer for alle aldre';
    }

    /**
     * Sensurerer teksten for visning: hvert stygt ord blir første bokstav og stjerner (f***).
     * Er triksene så kreative at vi ikke finner ordet igjen, skjules hele teksten.
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
        // Flerordsuttrykk ("white power") og spredte bokstaver fanges ikke ord for ord
        return self::clean($out) ? $out : '[skjult: upassende innhold]';
    }
}
