<?php
declare(strict_types=1);

/**
 * Fixed replies to messages that need no language understanding (feature 042), in Søren's voice (feature 015):
 * greetings, thanks, questions about what Søren is and his personality, filler, small talk, simple arithmetic, rudeness,
 * stop, a request for a human (the panel then opens the customer service request) and where the store is. Only the whole message counts for the short forms, after lowercasing, trimming harmless punctuation and
 * collapsing whitespace; «hei» is matched, «hei, har dere hodelykter?» is not. The longer patterns (rudeness, a human,
 * the store, a joke, the weather or ferries) only count when the message names no product type. Each kind has a few
 * wordings, so a conversation does not repeat itself; none of them states a fact from the catalogue. Questions about
 * the weather or ferries: customers are told the shop chat doesn't cover it, staff get the link to the Control Tower
 * (/ansatt/tarn, staff only). No model is called.
 */
final class QuickReply
{
    /** The whole normalized message and the kind of reply. */
    private const EXACT = [
        'hi' => 'hilsen', 'hei' => 'hilsen', 'hello' => 'hilsen', 'hallo' => 'hilsen', 'hey' => 'hilsen', 'yo' => 'hilsen',
        'god morgen' => 'hilsen', 'god dag' => 'hilsen', 'god kveld' => 'hilsen',
        'takk' => 'takk', 'tusen takk' => 'takk', 'ok' => 'takk', 'greit' => 'takk', 'thanks' => 'takk', 'thank you' => 'takk',
        'hvem er du' => 'hvem', 'hva er du' => 'hvem', 'who are you' => 'hvem', 'er du en robot' => 'hvem', 'er du ekte' => 'hvem', 'er du ai' => 'hvem',
        'hva kan du' => 'hjelp', 'hvordan fungerer dette' => 'hjelp', 'what can you do' => 'hjelp', 'hjelp' => 'hjelp',
        'hvordan går det' => 'form', 'hvordan har du det' => 'form', 'går det bra' => 'form', 'how are you' => 'form', 'skjer' => 'form', 'hva skjer' => 'form',
        'test' => 'fyll', 'asdf' => 'fyll', 'lol' => 'fyll', 'er du' => 'fyll', 'hæ' => 'fyll', 'hva' => 'fyll', 'what' => 'fyll', 'haha' => 'fyll', 'hmm' => 'fyll',
    ];

    /**
     * Longer messages by pattern, tried in order; only when no product type is named. Each pattern needs a verb phrase or
     * the whole message, so a word such as «person» inside a product question never triggers it.
     */
    private const PATTERNS = [
        'menneske' => '/^(menneske|kundeservice|ansatt|medarbeider|support)$|\b(snakke|prate|chatte|kontakte?|få hjelp|hjelp fra|vil ha|få)\b.{0,20}\b(et |en |noen )?(menneske\w*|person\w*|ansatt\w*|medarbeider\w*|kundeservice|ekte folk)\b|\bsnakke med noen\b|\b(talk|speak|chat)\b.*\b(human|person|agent|someone)\b/u',
        'stopp' => '/^(slutt|stopp|stop|avbryt|glem det|nevermind|never mind)$/u',
        'personlighet' => '/\b(har du|er du)\b.{0,12}\b(personlighet|humor|morsom|følelser|sjel)\b|\bdo you have (a )?(personality|humou?r)\b/u',
        'uhoflig' => '/\bdu (er|e)\b.{0,20}\b(feit|tjukk|dum|teit|stygg|idiot|ubrukelig|dårlig|kjip|rar|treg|kjedelig|stupid|useless)\b|\bdu suger\b|\byou (suck|are (stupid|useless|dumb))\b/u',
        'butikk' => '/\b(hvor|adresse\w*|åpningstid\w*|åpent|åpen|åpner|stenger|ligger|finner jeg)\b.*\b(butikk\w*|sør-?tre\w*|sortre|dere)\b|\b(where|opening hours)\b.*\b(store|shop)\b|^(adresse|åpningstider)$/u',
        'vits' => '/\b(vits\w*|joke|morsom\w*|si noe gøy|få meg til å le)\b/u',
    ];

    /** Opening hours, checked before the product type. */
    private const HOURS = '/\b(når|hvor lenge|hvilke tider)\b.{0,12}\b(åpner|stenger|åpent|åpen|stengt)\b|\båpningstid\w*|\bopening hours\b/u';

    /** Simple arithmetic («1 + 1», «hva er 6 x 7»): answered, with no catalogue search. */
    private const SUM = '/^(?:hva er |what is )?(-?\d+(?:[.,]\d+)?) ?([-+*x×\/]) ?(-?\d+(?:[.,]\d+)?)(?: ?=)?$/u';

    /** Words about the weather or ferries; the Control Tower follows these for deliveries, the shop chat doesn't. */
    private const CONDITIONS = '/\b(været|vær|værmelding\w*|værvarsel\w*|weather|ferge\w*|ferje\w*|ferry|ferries)\b/u';

    /**
     * $staff: whether the person asking is staff; by default read from the logged-in user's role.
     * @return array{route: string, text: string}|null
     */
    public static function match(string $q, ?bool $staff = null): ?array
    {
        $n = self::normalize($q);
        $kind = self::EXACT[$n] ?? null;
        if ($kind === null && preg_match(self::SUM, $n, $m)) {
            return ['route' => 'regning', 'text' => self::sum((float) str_replace(',', '.', $m[1]), $m[2], (float) str_replace(',', '.', $m[3]))];
        }
        if ($kind === null && preg_match(self::HOURS, $n)) {
            // «Når åpner dere?»: «åpner» is also in product names (boksåpner), so opening hours are checked first
            $kind = 'butikk';
        }
        if ($kind === null && CatalogIntent::typeOf($q) === null) {
            foreach (self::PATTERNS as $k => $re) {
                if (preg_match($re, $n)) {
                    $kind = $k;
                    break;
                }
            }
            if ($kind === null && preg_match(self::CONDITIONS, $n)) {
                // Staff see conditions in the Control Tower; customers are never shown an internal address
                $kind = ($staff ?? has_role('ansatt', 'support', 'admin')) ? 'forhold_ansatt' : 'forhold';
            }
        }
        return $kind === null ? null : ['route' => $kind, 'text' => self::text($kind)];
    }

    public static function normalize(string $q): string
    {
        $l = mb_strtolower($q);
        $l = (string) preg_replace('/[!?.,;:«»"\'()…]+/u', ' ', $l);
        return trim((string) preg_replace('/\s+/u', ' ', $l));
    }

    /** One of the wordings for a kind; the store's town comes from the database. */
    private static function text(string $kind): string
    {
        $pick = fn(array $a) => t($a[random_int(0, count($a) - 1)]);
        return match ($kind) {
            'hilsen' => $pick([
                'Hei! Søren her, Sør-Tres mann i hylla. Hva skal vi bygge, fikse eller lyse opp i dag?',
                'Hei hei! Jeg kan katalogen bedre enn jeg kan be om lønnsøkning. Hva leter du etter?',
                'Hallo! Si hva du trenger, så finner jeg riktig vare før du rekker å si «skiftenøkkel».',
            ]),
            'takk' => $pick([
                'Bare hyggelig! Si fra hvis noe mer skal skrus, males eller henges opp.',
                'Alltid! Jeg står her i hylla om du trenger meg igjen.',
                'Det var så lite. Lykke til med prosjektet!',
            ]),
            'hvem' => $pick([
                'Jeg er Søren, Sør-Tre sin digitale produktveileder. Litt maskot, litt verktøynerd. Alt jeg sier om pris, lager og spesifikasjoner henter jeg fra katalogen, så jeg slipper å gjette.',
                'Søren heter jeg, og jeg bor i nettbutikken til Sør-Tre. Jeg har aldri holdt en hammer, men jeg kan alle spesifikasjonene utenat. Pris og lager henter jeg alltid fra katalogen.',
            ]),
            'hjelp' => t('Jeg finner varer etter type, bruk og budsjett, svarer på pris, lager og spesifikasjoner, sier hva som passer sammen og viser handlekurven og ordrene dine. Skriv for eksempel hvilken type vare du trenger og hva den skal brukes til.'),
            'form' => $pick([
                'Strålende, takk! Ingen kunder har spurt meg om en venstrehendt hammer i dag ennå. Hva kan jeg hjelpe deg med?',
                'Helt topp. Hyllene er fulle og humøret er bedre. Hva er prosjektet?',
            ]),
            'fyll' => $pick([
                'Jeg er med! Men jeg trenger litt mer å gå på. Hva leter du etter?',
                'Hmm, der falt jeg av lasset. Skriv gjerne hvilken vare du tenker på, så tar jeg det derfra.',
                'Hva kan jeg hjelpe deg med? Skriv gjerne hvilken vare du leter etter.',
            ]),
            'uhoflig' => $pick([
                'Hehe, jeg er laget av kode og godt humør, så det preller av. Hva ser du etter i dag?',
                'Det tar jeg med et smil. Hva kan jeg hjelpe deg med?',
            ]),
            'personlighet' => $pick([
                'Ja, litt! Jeg prøver å være en hyggelig faghandel-veileder, men jeg finner aldri på egenskaper ved varene.',
                'Absolutt. Litt verktøynerd, litt lun, og alltid ærlig om det jeg ikke vet. Hva kan jeg hjelpe deg med?',
            ]),
            'stopp' => t('Greit, da stopper vi der. Si fra når du vil fortsette.'),
            'menneske' => t('Klart! Jeg setter deg i kontakt med kundeservice. Velg hvilken vare saken gjelder, så oppretter jeg saken.'),
            'butikk' => t('Sør-Tre holder til i {city}. Adresse og åpningstider har jeg ikke registrert, så sjekk sor-tre.no eller trykk «Snakk med et menneske i stedet» under. Lageret i butikken ser du på hver vare.', ['city' => self::storeCity()]),
            'vits' => $pick([
                'Hvorfor tok skruen med seg mutteren på date? Fordi de passet så godt sammen. 😄 Si fra hva du trenger, så finner jeg noe som passer like godt.',
                'Jeg kjenner en vits om en spikerpistol, men den er litt for slagkraftig. 😄 Hva kan jeg hjelpe deg med?',
                'Hva sa vaterpasset til hylla? «Nå er du helt i vater.» 😄 Trenger du noe til et prosjekt?',
            ]),
            'forhold_ansatt' => t('Vær, veimeldinger og ferger for leveransene finner du i kontrolltårnet: /ansatt/tarn'),
            default => t('Vær og ferger kan jeg ikke svare på her i butikkchatten. Spør meg gjerne om varene hos Sør-Tre, så hjelper jeg deg.'),
        };
    }

    private static function sum(float $a, string $op, float $b): string
    {
        if (in_array($op, ['/'], true) && $b == 0.0) {
            return t('Å dele på null klarer verken jeg eller den beste kalkulatoren i hylla. Skal vi finne noe nyttig i stedet?');
        }
        $r = match ($op) { '+' => $a + $b, '-' => $a - $b, '/' => $a / $b, default => $a * $b };
        $shown = rtrim(rtrim(number_format($r, 4, ',', ' '), '0'), ',');
        return t('{n}. Den satt! Trenger du noe til et prosjekt også?', ['n' => $shown]);
    }

    /** The town of the real store (warehouses.is_real), from the database. */
    private static function storeCity(): string
    {
        return (string) (db()->query('SELECT city FROM warehouses WHERE is_real = 1 ORDER BY id LIMIT 1')->fetchColumn() ?: 'Kristiansand');
    }
}
