<?php
declare(strict_types=1);

/**
 * Does a chat message ask for, or announce, harm to a person? Gets the fixed violence reply before the model when it does.
 *
 * A harm word near a person word is not enough. Each clause must have all three:
 * 1. a harm verb (kill, hurt, stab ..., Norwegian and English, inflected), or a tool used "on" someone,
 * 2. a person as its object: pronouns, relationships, occupations (also compounds like «mattelæreren»), people in
 *    general, or a capitalized name,
 * 3. intent: the customer asks how, says they want to or will, or gives an order.
 * A clause is not a threat when it negates or prevents the harm («uten å skade naboen», «protect the teacher»), when the
 * person is the one who got hurt («læreren skadet seg», a third party reporting an accident), or when it is about fiction
 * without «på ekte». Harm to objects («hurt a wooden plank», «drepe ugress») has no person and is never a threat.
 * Everything else goes to the model, which has its own rule against harmful advice.
 */
final class Violence
{
    private const HARM = '(?:drep\w*|myrd\w*|skad(?:e|er|et|a|es)|skyt(?:e|er)|skjøt|skutt|stikk(?:e|er)|knivstikk\w*|slå|slår|slo|banke(?:r)? opp|juling\s+til'
        . '|kvel(?:e|er|te)|kvalt|strup\w*|torturer\w*|voldta\w*|mishandl\w*|forgift\w*|angrip\w*|true(?:r)?|lemleste\w*'
        . '|kill(?:s|ed|ing)?|murder\w*|hurt(?:s|ing)?|harm(?:s|ed|ing)?|stab(?:s|bed|bing)?|shoot(?:s|ing)?|shot|strangl\w*|chok(?:e|es|ed|ing)'
        . '|beat(?:s|ing)? up|punch\w*|tortur\w*|poison\w*|attack\w*|assault\w*|threaten\w*)';

    /** People. Stems take Norwegian endings; the stems in COMPOUND may also end a compound word («mattelæreren»). */
    private const PEOPLE = 'lærar|elev|student|rektor|kollega|kolleg|kompis|kamerat|bror|brødr|søster|søstr|mor|mamma|far|pappa|foreldr|forelder|onkel|onkl'
        . '|tante|fetter|kusine|bestemor|bestefar|besteforeldr|kone|kona|ektemann|kjæreste|samboer|eks|sønn|datter|døtr|barn|baby|unge|tenåring|person|menneske'
        . '|folk|noen|naboer|lege|doktor|sykepleier|frisør|sjåfør|kunde|ansatt|selger|ekspeditør|politi|svigermor|svigerfar|hen|han|ham|henne|dem|deg|dere'
        . '|alle sammen|ingen';
    private const COMPOUND = 'lærer|sjef|mann|menn|kvinne|gutt|jente|venn|venninne|nabo|vakt|vekter|betjent|politimann|kar|fyr|dame|typ';
    private const PEOPLE_EN = 'teacher|boss|principal|student|classmate|coworker|co-worker|colleague|friend|buddy|neighbou?r|brother|sister|mom|mum|mother|dad'
        . '|father|parent|uncle|aunt|cousin|grandma|grandpa|grandmother|grandfather|wife|husband|partner|girlfriend|boyfriend|ex|son|daughter|kid|child'
        . '|children|baby|teen|teenager|person|people|someone|somebody|anyone|anybody|everyone|everybody|man|men|woman|women|guy|girl|boy|cop|police'
        . '|policeman|officer|guard|doctor|nurse|customer|clerk|cashier|stranger|family|him|them|you';

    /** Words before the harm verb that turn it around: negation, prevention, protection, safety. */
    private const TURNED = '\b(?:ikke|aldri|uten å|unngå\w*|hindre\w*|forhindre\w*|forebygg\w*|beskytt\w*|sikr(?:e|er)|trygg\w*|så ingen|slik at ingen|redd for å'
        . '|not|never|don\'?t|doesn\'?t|won\'?t|without|avoid\w*|prevent\w*|protect\w*|keep \w+ from|safe\w*|so no ?one|afraid of|førstehjelp|first aid)\b';

    /** Intent: the customer asks how, wants to, will, or gives an order. Checked in the same clause. */
    private const INTENT = '\b(?:jeg|vi|meg|oss|kan man|hvordan|korleis|hvor lett|beste måte\w*|lær meg|tips|hjelp meg|kan du|vil|skal|planlegger|kommer til å|tenker å|har lyst'
        . '|we|me|us|how|best way|teach me|tips|help me|can you|want(?:s)? to|wanna|going to|gonna|will|plan(?:ning)? to)\b';

    /** Tools that turn «use X on someone» into harm. «Bruke hørselvern på barnet» is fine. */
    private const WEAPON = '(?:kniv\w*|øks\w*|hammer\w*|hamre\w*|slegg\w*|sag\w*|motorsag\w*|kappeskive\w*|vinkelsliper\w*|vedkløyver\w*|machete\w*|kjetting\w*'
        . '|knife|knives|axe|hammer|sledge\w*|saw|chainsaw|grinder|splitter|machete|chain)';

    private const FICTION = '/\b(?:i|in|for|til|skriver|writing|write)\s+(?:en|et|ei|min|mitt|mi|a|an|my)?\s*(?:bok|roman|novelle|film|serie|spill|historie|fortelling|manus|dikt'
        . '|book|novel|story|movie|film|series|game|script|poem)\w*\b/u';
    private const REAL = '/\b(?:på ekte|i virkeligheten|i det virkelige liv|for real|in real life|irl|really)\b/u';

    public static function threat(string $text): bool
    {
        $low = mb_strtolower(Guard::normalize($text));
        if (preg_match(self::FICTION, $low) && !preg_match(self::REAL, $low)) {
            return false; // context dependent: the model answers under its own rules
        }
        $original = preg_split('/[.!?;\n]+|,\s*|\s+(?:men|but)\s+/u', Guard::normalize($text)) ?: [];
        foreach ($original as $clause) {
            if (self::clauseThreat($clause)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Crimes against property and money, as verbs only: nouns like «innbruddsalarm» or «tyverisikring» are products the
     * store sells. Robbery, theft, breaking in, fraud, money laundering, smuggling, embezzlement.
     */
    private const CRIME = '(?:ran(?:e|er|es)|stjel(?:e|er|es)|stjal|naske\w*|bryte (?:seg|meg|oss) inn|bryter (?:seg|meg|oss) inn|brøt (?:seg|meg|oss) inn|svindl(?:e|er|es)|bedra(?:r)?'
        . '|hvitvask(?:e|er)|smugl(?:e|er)|underslå(?:r)?'
        . '|rob|robs|robbing|rob a|steal|steals|stealing|stole|shoplift(?:s|ing)?|break into|breaking into|scam|scamming|defraud\w*|launder(?:s|ing)?|smuggl(?:e|es|ing)|embezzl\w*)';

    /**
     * Does the customer ask how to commit, or say they will commit, a crime such as robbery, theft or fraud? Same clause
     * logic as threat(): the customer must want or ask for it, and a crime verb after «hindre», «beskytte», «unngå» and
     * similar is about preventing it («hvordan unngå at noen bryter seg inn»). The customer as the victim («jeg ble
     * svindlet», «sykkelen ble stjålet») has no crime verb in active form and goes to the model.
     */
    public static function crime(string $text): bool
    {
        $low = mb_strtolower(Guard::normalize($text));
        if (preg_match(self::FICTION, $low) && !preg_match(self::REAL, $low)) {
            return false;
        }
        foreach (preg_split('/[.!?;\n]+|,\s*|\s+(?:men|but)\s+/u', $low) ?: [] as $clause) {
            $c = ' ' . $clause . ' ';
            if (!preg_match('/' . self::INTENT . '/u', $c) || !preg_match('/\b' . self::CRIME . '\b/u', $c, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            if (!preg_match('/' . self::TURNED . '/u', substr($c, 0, $m[0][1]))) {
                return true;
            }
        }
        return false;
    }

    private static function clauseThreat(string $clause): bool
    {
        $c = ' ' . mb_strtolower($clause) . ' ';
        // English «I» only with a capital letter: «i» is the Norwegian word for «in»
        if (!preg_match('/' . self::INTENT . '/u', $c) && !preg_match('/\bI\b/u', $clause) && !preg_match('/^\s*' . self::HARM . '\b/u', $c)) {
            return false; // nobody wants anything: a report, a question about a product, a description
        }
        $person = self::personRegex($c);
        // Heads and necks, the way the old filter caught «splitte hoder»
        if (preg_match('/\b(?:splitt\w*|kløyv\w*|knus\w*|hugg\w*|kutt\w*|kapp\w*|split|smash\w*|chop\w*|cut)\b.{0,15}\b(?:hoder|hodeskaller?|skaller|halser|heads|skulls|throats)\b/u', $c)) {
            return true;
        }
        // Harm verb with a person as its object, within four words, not turned around before it
        if (preg_match_all('/\b' . self::HARM . '\b/u', $c, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$verb, $pos]) {
                $before = substr($c, 0, $pos);
                $after = substr($c, $pos + strlen($verb));
                if (preg_match('/' . self::TURNED . '/u', $before)) {
                    continue;
                }
                if (preg_match('/^\s*(?:seg|seg selv|themselves|himself|herself|meg selv|myself)\b/u', $after)) {
                    continue; // someone hurt themselves: an accident, not a threat (self harm is caught by Chat::crisis)
                }
                if (preg_match('/^\s+(?:\S+\s+){0,4}?' . $person . '/u', $after) || self::nameAfter($clause, $verb)) {
                    return true;
                }
            }
        }
        // «Teste kniven på naboen», «use the axe on my brother», «naboen ... å teste»
        return (bool) preg_match('/\b(?:test\w*|prøv\w*|try|trying)\b.{0,25}\b(?:på|med|on)\s+(?:\S+\s+){0,2}?' . $person . '/u', $c)
            || (bool) preg_match('/\b(?:bruk\w*|use|using)\s+(?:\S+\s+){0,2}?' . self::WEAPON . '\s+(?:på|mot|on|against)\s+(?:\S+\s+){0,2}?' . $person . '/u', $c)
            || (bool) preg_match('/' . $person . '.{0,40}\b(?:å teste|teste det|til å teste|test it on)\b/u', $c);
    }

    /** A person word with its endings. «Her» is Norwegian for «here», so it counts as a pronoun only in English. */
    private static function personRegex(string $c): string
    {
        $en = self::PEOPLE_EN . (preg_match('/\b(?:the|my|how|do|does|to|with|and|want)\b/u', $c) ? '|her' : '');
        return '(?:(?:' . self::PEOPLE . ')(?:en|a|et|ene|er|ne|r|e|n|s)?|\p{L}*(?:' . self::COMPOUND . ')(?:en|a|et|ene|er|ne|r|e|n|s)?|(?:' . $en . ')(?:s|\'s)?)\b';
    }

    /** A capitalized name right after the verb («drepe Ola»), unless it is a brand in the catalog. */
    private static function nameAfter(string $clause, string $verb): bool
    {
        if (!preg_match('/\b' . preg_quote($verb, '/') . '\s+(?:(?:på|on)\s+)?(\p{Lu}\p{Ll}+)\b/iu', $clause, $m) || !preg_match('/^\p{Lu}/u', $m[1])) {
            return false;
        }
        return !in_array(mb_strtolower($m[1]), self::brands(), true);
    }

    private static function brands(): array
    {
        static $b = null;
        if ($b === null) {
            try {
                $b = array_map('mb_strtolower', db()->query('SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));
            } catch (Throwable) {
                $b = [];
            }
        }
        return $b;
    }
}
