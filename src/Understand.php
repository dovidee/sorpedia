<?php
declare(strict_types=1);

/**
 * What the customer is asking for, read by the language model as structured data (feature 041). The model only fills in
 * a JSON schema: the intent, the product type word the customer used, requirements on specification keys, a budget,
 * what is missing and whether to ask back. It never names, picks or ranks a product, and never writes customer text.
 *
 * Every field is validated against the customer's own words and the catalogue: a product type must be a word the customer
 * wrote (or its English translation) that names products in the catalogue; a requirement must use a filterable
 * specification key from spec_keys, and its numbers must be in the message; a budget must be a number in the message.
 * What fails is dropped and recorded, never guessed. Schema compliance is not taken as proof of the right reading.
 *
 * When the model is not available (down, or over the public API's model allowance), the same fields are read by rules.
 */
final class Understand
{
    public const INTENTS = ['finn_produkt', 'om_produktet', 'handlekurv', 'ordre', 'smaprat', 'uklart'];
    public const MISSING = ['produkttype', 'bruk', 'budsjett', 'produkt'];

    private const CART = '/\b(handlekurv\w*|kurven|cart|basket)\b/u';
    private const ORDER = '/\b(ordre\w*|bestilling\w*|levering\w*|levert|retur\w*|angre\w*|refusjon|reklamasjon|order\w*|delivery|refund\w*)\b/u';
    private const GREETING = '/^\s*(hei|hallo|heisann|god (morgen|dag|kveld)|takk\w*|tusen takk|hi|hello|hey|thanks|thank you|hvordan går det|how are you)\b/u';
    private const BUDGET = '/\b(?:under|maks|max|inntil|opptil|høyst|below|up to|budsjett\w*|budget)\s*(?:på\s*|of\s*)?(\d[\d \x{00A0}.]*)\s*(?:kr|kroner|nok|,-)?/iu';

    /**
     * Reads the current message only: what an earlier message said is not carried into this one, so «hvordan lader jeg
     * den?» after a recommendation is not read as that recommendation again. Which product «den» means comes from the
     * retriever's memory of the conversation.
     * @return array{intent: string, type: ?array{0: int[], 1: string}, requirements: list<array{key: string, min: ?float, max: ?float, value: ?string}>,
     *   budget_ore: ?int, missing: list<string>, clarify: bool, source: string, dropped: list<string>}
     */
    public static function read(string $q, bool $useModel = true): array
    {
        $raw = $useModel ? self::model($q) : null;
        return $raw === null ? self::rules($q) : self::validate($raw, $q);
    }

    /** The catalogue's own reading of the product type and of unit requirements always counts, also when the model missed it. */
    private static function complete(array $out, string $q): array
    {
        if ($out['type'] === null && ($typed = self::typeIn($q)) !== null) {
            $out['type'] = $typed;
        }
        $have = array_column($out['requirements'], 'key');
        foreach (self::requirements($q) as $r) {
            if (!in_array($r['key'], $have, true)) {
                $out['requirements'][] = $r;
            }
        }
        return $out;
    }

    /** The JSON schema the model must fill in. The specification keys come from the database, so a new key needs no code. */
    public static function schema(): array
    {
        $num = ['type' => ['number', 'null']];
        return ['type' => 'object', 'additionalProperties' => false,
            'required' => ['intent', 'produkttype', 'krav', 'budsjett_kr', 'mangler', 'trenger_avklaring'],
            'properties' => [
                'intent' => ['type' => 'string', 'enum' => self::INTENTS],
                'produkttype' => ['type' => ['string', 'null']],
                'krav' => ['type' => 'array', 'maxItems' => 4, 'items' => ['type' => 'object', 'additionalProperties' => false,
                    'required' => ['egenskap', 'min', 'max', 'verdi'],
                    'properties' => ['egenskap' => ['type' => 'string', 'enum' => array_keys(self::keys())], 'min' => $num, 'max' => $num, 'verdi' => ['type' => ['string', 'null']]]]],
                'budsjett_kr' => $num,
                'mangler' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::MISSING]],
                'trenger_avklaring' => ['type' => 'boolean'],
            ]];
    }

    /** The filterable specification keys and their labels, from spec_keys. @return array<string, string> */
    public static function keys(): array
    {
        static $keys = null;
        return $keys ??= db()->query('SELECT `key`, label FROM spec_keys WHERE is_facet = 1 ORDER BY sort')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** The model's reading, or null when it gave none. */
    private static function model(string $q): ?array
    {
        $keys = implode('; ', array_map(fn($k, $l) => "$k = $l", array_keys(self::keys()), self::keys()));
        $system = 'Du leser hva en kunde i en jernvarebutikk spør om, og svarer bare med JSON etter skjemaet. Du skriver ikke svar til kunden, '
            . 'og du nevner aldri produktnavn, priser eller egenskaper som kunden ikke skrev selv.';
        $user = "Hensikter: finn_produkt (kunden vil ha forslag eller leter etter en type vare), om_produktet (spør om en bestemt vare, også hvordan den brukes eller lades), "
            . "handlekurv, ordre (bestilling, levering, retur, angring), smaprat (hilsen eller prat uten varer), uklart.\n"
            . "produkttype: ordet kunden brukte for typen vare («hodelykt», «sagkjede»), ellers null.\n"
            . "krav: bare egenskaper kunden selv nevnte, med nøkkel fra denne lista: $keys. Tall bare hvis kunden skrev tallet.\n"
            . "budsjett_kr: bare hvis kunden skrev et beløp. mangler: det du trenger å vite for å hjelpe. trenger_avklaring: true hvis spørsmålet ikke kan besvares uten mer informasjon.\n\n"
            . "Kunden skriver nå: $q";
        return Chat::structured([['role' => 'user', 'content' => $user]], $system, self::schema());
    }

    /**
     * The model's fields that hold up against the message and the catalogue.
     * @return array{intent: string, type: ?array, requirements: list<array>, budget_ore: ?int, missing: list<string>, clarify: bool, source: string, dropped: list<string>}
     */
    public static function validate(array $raw, string $q): array
    {
        $said = mb_strtolower($q);
        $saidEn = mb_strtolower((string) Search::fromEnglish($said, [])[0]);
        $dropped = [];
        $intent = in_array($raw['intent'] ?? null, self::INTENTS, true) ? (string) $raw['intent'] : 'uklart';
        // Cart and order are only the reading when the customer used such a word; otherwise the model's label is not trusted
        $l = mb_strtolower($q);
        if (($intent === 'handlekurv' && !preg_match(self::CART, $l)) || ($intent === 'ordre' && !preg_match(self::ORDER, $l))) {
            $dropped[] = "intent:$intent";
            $intent = 'uklart';
        }
        // ... and when the customer did use a cart or order word, that is the reading, also if the model said otherwise
        $byWords = preg_match(self::CART, $l) ? 'handlekurv' : (preg_match(self::ORDER, $l) ? 'ordre' : null);
        if ($byWords !== null && $intent !== $byWords) {
            $dropped[] = "intent:$intent";
            $intent = $byWords;
        }

        $type = null;
        $word = is_string($raw['produkttype'] ?? null) ? mb_strtolower(trim($raw['produkttype'])) : '';
        if ($word !== '') {
            $stem = mb_substr($word, 0, max(4, mb_strlen($word) - 3));
            $written = str_contains($said, $stem) || str_contains($saidEn, $stem);
            $type = $written ? (CatalogIntent::typeOf($word) ?? CatalogIntent::typeOf((string) Search::fromEnglish($word, [])[0])) : null;
            if ($type === null) {
                $dropped[] = "produkttype:$word";
            }
        }

        $reqs = [];
        // Keys with a unit, and the catalogue's own reading of each from the message (null: no number with that unit)
        $units = [];
        foreach (db()->query("SELECT `key` FROM spec_keys WHERE is_facet = 1 AND datatype = 'number' AND unit IS NOT NULL AND unit <> ''")->fetchAll(PDO::FETCH_COLUMN) as $k) {
            $units[$k] = null;
        }
        foreach (self::requirements($q) as $r) {
            if (array_key_exists($r['key'], $units) && ($r['min'] !== null || $r['max'] !== null)) {
                $units[$r['key']] = $r;
            }
        }
        foreach (is_array($raw['krav'] ?? null) ? $raw['krav'] : [] as $r) {
            $key = is_array($r) ? ($r['egenskap'] ?? null) : null;
            if (!is_string($key) || !isset(self::keys()[$key])) {
                $dropped[] = 'krav:ukjent_nokkel';
                continue;
            }
            $min = self::number($r['min'] ?? null);
            $max = self::number($r['max'] ?? null);
            $value = is_string($r['verdi'] ?? null) && trim($r['verdi']) !== '' ? mb_strtolower(trim($r['verdi'])) : null;
            // A number or a value the customer didn't write is not the customer's requirement
            foreach (['min' => $min, 'max' => $max] as $side => $n) {
                if ($n !== null && (!self::inText($n, $said) || self::direction($n, $said) === ($side === 'min' ? 'max' : 'min'))) {
                    $dropped[] = "krav:$key:tall";
                    continue 2;
                }
            }
            // A property with a unit: the number counts only next to its unit («500 lumen», not «1000 kr»), as the catalogue reads it
            if (($min !== null || $max !== null) && isset($units[$key])) {
                $own = $units[$key];
                if ($own === null || $own['min'] !== $min || $own['max'] !== $max) {
                    $dropped[] = "krav:$key:enhet";
                    continue;
                }
                $value = null;
            }
            if ($value !== null && !str_contains($said, $value) && !str_contains($saidEn, $value)) {
                $dropped[] = "krav:$key:verdi";
                continue;
            }
            if ($min === null && $max === null && $value === null) {
                $dropped[] = "krav:$key:tom";
                continue;
            }
            $reqs[$key] = isset($reqs[$key]) && $reqs[$key] !== ['key' => $key, 'min' => $min, 'max' => $max, 'value' => $value]
                ? false // two different readings of the same property: neither is trusted
                : ['key' => $key, 'min' => $min, 'max' => $max, 'value' => $value];
        }
        if (in_array(false, $reqs, true)) {
            $dropped[] = 'krav:motstridende';
        }
        $reqs = array_values(array_filter($reqs));

        $budget = self::number($raw['budsjett_kr'] ?? null);
        if ($budget !== null && !self::inText($budget, $said)) {
            $dropped[] = 'budsjett';
            $budget = null;
        }
        $budget ??= self::budget($q);
        $missing = array_values(array_intersect(is_array($raw['mangler'] ?? null) ? $raw['mangler'] : [], self::MISSING));
        return self::complete(['intent' => $intent, 'type' => $type, 'requirements' => $reqs, 'budget_ore' => $budget === null ? null : (int) round($budget * 100),
            'missing' => $missing, 'clarify' => ($raw['trenger_avklaring'] ?? false) === true, 'source' => 'modell', 'dropped' => $dropped], $q);
    }

    /** A greeting or thanks on its own («hei», «takk»), the only text the small talk reply answers. */
    public static function greeting(string $q): bool
    {
        $l = mb_strtolower(trim($q));
        return (bool) preg_match(self::GREETING, $l) && mb_strlen($l) < 40;
    }

    /** The same fields read by rules, when the model is not used. */
    public static function rules(string $q): array
    {
        $l = mb_strtolower($q);
        $intent = match (true) {
            (bool) preg_match(self::CART, $l) => 'handlekurv',
            (bool) preg_match(self::ORDER, $l) => 'ordre',
            (bool) preg_match(self::GREETING, $l) && mb_strlen($l) < 40 => 'smaprat',
            default => 'uklart',
        };
        $budget = self::budget($q);
        return self::complete(['intent' => $intent, 'type' => null, 'requirements' => [], 'budget_ore' => $budget === null ? null : (int) round($budget * 100),
            'missing' => [], 'clarify' => false, 'source' => 'regler', 'dropped' => []], $q);
    }

    /**
     * Requirements the question states in a form the catalogue defines: a number with a specification key's unit
     * («minst 500 lumen», «under 200 g»), and a text property named by its label («vanntett» for «Vanntetthet (IP)»),
     * which then needs a verified value. Units and labels come from spec_keys.
     * @return list<array{key: string, min: ?float, max: ?float, value: ?string}>
     */
    public static function requirements(string $q): array
    {
        $l = mb_strtolower($q);
        $out = [];
        $rows = db()->query('SELECT `key`, label, unit, datatype FROM spec_keys WHERE is_facet = 1 ORDER BY sort')->fetchAll();
        foreach ($rows as $k) {
            $unit = mb_strtolower((string) $k['unit']);
            if ($k['datatype'] === 'number' && $unit !== '') {
                $units = $unit === 'lm' ? 'lm|lumen' : preg_quote($unit, '/');
                if (preg_match('/(\d[\d \x{00A0}]*(?:[.,]\d+)?)\s?(?:' . $units . ')(?![\p{L}\d])/u', $l, $m)) {
                    $n = (float) str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $m[1]);
                    $dir = self::direction($n, $l);
                    if ($dir !== null) {
                        $out[] = ['key' => (string) $k['key'], 'min' => $dir === 'min' ? $n : null, 'max' => $dir === 'max' ? $n : null, 'value' => null];
                    }
                }
                continue;
            }
            if ($k['datatype'] === 'text') {
                foreach (preg_split('/[^\p{L}]+/u', mb_strtolower((string) $k['label'])) ?: [] as $w) {
                    $stem = mb_substr($w, 0, 7);
                    if (mb_strlen($w) >= 6 && preg_match('/\b' . preg_quote($stem, '/') . '/u', $l)) {
                        $out[] = ['key' => (string) $k['key'], 'min' => null, 'max' => null, 'value' => '*'];
                        break;
                    }
                }
            }
        }
        return $out;
    }

    /** The product type the question names, read from the catalogue's product names (CatalogIntent). */
    private static function typeIn(string $q): ?array
    {
        $l = mb_strtolower($q);
        return CatalogIntent::typeOf($l) ?? CatalogIntent::typeOf((string) Search::fromEnglish($l, [])[0]);
    }

    /** «under 500 kr», «budsjett på 1000»: kroner, or null. */
    private static function budget(string $q): ?float
    {
        return preg_match(self::BUDGET, $q, $m) ? (float) str_replace([' ', "\u{00A0}", '.'], '', $m[1]) : null;
    }

    private static function number(mixed $v): ?float
    {
        return is_int($v) || is_float($v) ? (float) $v : null;
    }

    /** «minst 500» is a minimum, «under 500» a maximum, else null. */
    private static function direction(float $n, string $text): ?string
    {
        $want = preg_quote(rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'), '/');
        if (preg_match('/\b(minst|over|mer enn|fra|at least|more than|over)\s+' . $want . '\b/u', $text)) {
            return 'min';
        }
        return preg_match('/\b(under|maks|max|høyst|opptil|inntil|mindre enn|below|less than|up to)\s+' . $want . '\b/u', $text) ? 'max' : null;
    }

    /** Is the number written in the text («1000», «1 000», «1.000»)? */
    private static function inText(float $n, string $text): bool
    {
        $digits = preg_replace('/(?<=\d)[ \x{00A0}.](?=\d{3}\b)/u', '', $text) ?? $text;
        $want = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        return (bool) preg_match('/(?<![\d,.])' . preg_quote($want, '/') . '(?![\d])|(?<![\d,.])' . preg_quote(str_replace('.', ',', $want), '/') . '(?![\d])/u', $digits);
    }
}
