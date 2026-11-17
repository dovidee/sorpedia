<?php
declare(strict_types=1);

/**
 * Norwegian and English. Norwegian is the default. English is chosen with the flag in the top bar (cookie "lang"),
 * or per message in the chat when the customer clearly writes English (see Lang::question).
 *
 * The keys are the Norwegian text, so the templates read naturally and fall back to Norwegian if a translation is missing.
 * Product names and descriptions come from Sør-Tre and are not translated.
 */
final class I18n
{
    /** Overrides the language temporarily, for example for Søren's answer to an English message. */
    public static ?string $force = null;

    public static function lang(): string
    {
        return self::$force ?? (($_COOKIE['lang'] ?? '') === 'en' ? 'en' : 'no');
    }

    /** Has the customer chosen a language with the flag themselves? */
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

    /** Values from the database that have a known translation (use case, material, color). Otherwise as they are. */
    public static function value(string $v): string
    {
        return self::lang() === 'en' ? (I18nEn::VALUES[$v] ?? $v) : $v;
    }

    /** Name of a specification type. Types made by buyers stay as they are. */
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
