<?php
declare(strict_types=1);

/**
 * Norsk og engelsk. Norsk er standard. Engelsk velges med flagget i toppstripen (informasjonskapsel «lang»),
 * eller per melding i chatten når kunden tydelig skriver engelsk (se Lang::question).
 *
 * Nøklene er den norske teksten, så malene leses naturlig og faller tilbake til norsk hvis en oversettelse mangler.
 * Produktnavn og beskrivelser kommer fra Sør-Tre og oversettes ikke.
 */
final class I18n
{
    /** Overstyrer språket midlertidig, for eksempel for Sørens svar på en engelsk melding. */
    public static ?string $force = null;

    public static function lang(): string
    {
        return self::$force ?? (($_COOKIE['lang'] ?? '') === 'en' ? 'en' : 'no');
    }

    /** Har kunden selv valgt språk med flagget? */
    public static function chosen(): bool
    {
        return in_array($_COOKIE['lang'] ?? '', ['no', 'en'], true);
    }

    public static function t(string $no, array $vars = []): string
    {
        $s = self::lang() === 'en' ? (I18nEn::T[$no] ?? $no) : $no;
        if ($vars) {
            $s = strtr($s, array_combine(array_map(fn($k) => '{' . $k . '}', array_keys($vars)), array_map('strval', array_values($vars))));
        }
        return $s;
    }

    /** Verdier fra databasen som har en kjent oversettelse (bruksområde, materiale, farge). Ellers som de er. */
    public static function value(string $v): string
    {
        return self::lang() === 'en' ? (I18nEn::VALUES[$v] ?? $v) : $v;
    }

    /** Navn på en spesifikasjonstype. Typer kjøpere har laget selv står som de er. */
    public static function spec(string $key, string $label): string
    {
        return self::lang() === 'en' ? (I18nEn::SPECS[$key] ?? $label) : $label;
    }

    public static function with(string $lang, callable $fn): mixed
    {
        $old = self::$force;
        self::$force = $lang;
        try {
            return $fn();
        } finally {
            self::$force = $old;
        }
    }
}
