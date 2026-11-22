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

    /**
     * Reasons on relations ("goes well with", "does not fit") are written in Norwegian by scripts/import.php. In English
     * the known sentence shapes are translated here; product names and Sør-Tre's own quoted words stay as they are.
     * A reason with no known shape is shown unchanged.
     */
    private const REASONS = [
        '/^Sør-Tre skriver at (.+) passer til varenr\. (\S+) \((.+)\)$/u' => 'Sør-Tre states that $1 fits item no. $2 ($3)',
        '/^Sør-Tre skriver at (.+) passer til (.+)$/u' => 'Sør-Tre states that $1 fits $2',
        '/^Sør-Tre skriver: «(.+)»$/u' => 'Sør-Tre writes: «$1»',
        '/^(.+) kjede files med (.+) rundfil$/u' => '$1 chain is sharpened with a $2 round file',
        '/^(.+) fil passer (.+) sagkjede$/u' => '$1 file fits $2 saw chain',
        '/^(.+ mm) passer kjetting på (.+ mm)$/u' => '$1 fits chain of $2',
        '/^Kjetting (.+ mm) passer (.+ mm) \(samme eller en størrelse opp\)$/u' => 'Chain $1 fits $2 (same size or one size up)',
        '/^Glass med høyde (.+) passer stender på (.+)$/u' => 'Glass $1 high fits posts of $2',
        '/^Stender på (.+) passer glass med høyde (.+)$/u' => 'Posts of $1 fit glass $2 high',
        '/^Lykten bruker (.+), batteriet er type (.+)$/u' => 'The lamp uses $1, the battery is type $2',
        '/^Bruk hansker og hørselvern ved motorsagarbeid$/u' => 'Wear gloves and hearing protection when using a chainsaw',
        '/^Dekklist dekker overkanten av glassfeltet$/u' => 'The cover strip covers the top edge of the glass panel',
        '/^Du trenger en hammer for å slå inn spiker$/u' => 'You need a hammer to drive in nails',
        '/^Etter sparkling slipes flaten, og kantene maskeres før maling$/u' => 'After filling, sand the surface and mask the edges before painting',
        '/^Komplett malesett til samme jobb$/u' => 'Complete painting set for the same job',
        '/^Masker lister og vinduer før du maler$/u' => 'Mask trims and windows before you paint',
        '/^Slegge og kile brukes sammen til vedkløyving$/u' => 'Sledgehammer and wedge are used together to split firewood',
        '/^Snekkerhammer brukes til spiker og stift$/u' => 'A claw hammer is used for nails and brads',
        '/^Andre kunder kjøpte disse sammen \(demo-ordrer\)$/u' => 'Other customers bought these together (demo orders)',
    ];

    public static function reason(string $reason): string
    {
        if (self::lang() !== 'en') {
            return $reason;
        }
        foreach (self::REASONS as $re => $en) {
            if (preg_match($re, $reason)) {
                $out = (string) preg_replace($re, $en, $reason);
                // Decimal comma in sizes ("9,5 mm") reads as a list in English; quotes and product names keep theirs
                return str_starts_with($reason, 'Sør-Tre') ? $out : (string) preg_replace('/(\d),(\d)/', '$1.$2', $out);
            }
        }
        return $reason;
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
