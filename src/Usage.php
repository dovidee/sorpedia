<?php
declare(strict_types=1);

/**
 * What a product is mainly for, what else the customer needs to use it, and which properties decide between
 * similar products (a headlamp: how it charges; a screw: diameter and length). Rule based on the product type in the
 * name, so it is derived knowledge (source 'utledet'), never shown as something Sør-Tre has confirmed.
 *
 * Needs point to real products in the catalog when the store has them (by name pattern), otherwise they say the
 * item is not in the range. Price and stock for those products come from the database through ProductRef.
 */
final class Usage
{
    /**
     * Product type (regex on the lowercased name) => [main use, needs, deciding keys]. First match wins, so
     * accessories come before the product they belong to. A need is [text, name pattern for LIKE or null].
     */
    private const RULES = [
        // Light
        '/reserveblad.*tapetkniv/u' => ['Reserveblad til tapetkniv.', [['tapetkniv som bladene passer i', null]], []],
        '/ladekabel|ladeboks/u' => ['Lader en Ledlenser-lykt med magnetisk kontakt. Sjekk at modellen står i navnet.', [['USB-lader eller USB-port', null]], ['lading']],
        '/batteripakke|ledlenser batteri/u' => ['Reservebatteri til bestemte Ledlenser-lykter. Modellen i navnet må stemme.', [], ['batteri']],
        '/\bbatteri\b/u' => ['Engangsbatterier til lykter og småelektronikk. Størrelsen (AA, AAA, CR123A) må passe apparatet.', [], ['batteri']],
        '/veke/u' => ['Reserveveke til fjøslykt (parafinlampe). Bredden må passe brenneren.', [['lampeolje eller parafin', null], ['fyrstikker eller lighter', null]], ['bredde_mm']],
        '/tripod|stativ.*arbeidslampe/u' => ['Stativ som holder en arbeidslampe i høyden.', [['arbeidslampe som passer stativet', '%Arbeidslampe%']], []],
        '/hodelykt|stavlykt|campinglykt|lommelykt|dykkerlykt|arbeidslampe|arbeidslys|\blykt\b|\bp\d+r\b|\bml\d\b/u' => ['Lys du bærer med deg eller setter fra deg. Det som skiller lyktene mest er hvordan de lades (USB-C, magnetisk kabel, solcelle eller batterier), lysstyrken og om de tåler vann.', [], ['lading', 'batteri', 'lysstyrke_lm', 'ip_klasse']],
        '/lyspære|pære/u' => ['Lyspære. Sokkelen (for eksempel E27, GU10 eller BA15S) må passe lampen, og spenningen (12 V eller 230 V) må stemme.', [['lampe eller armatur med samme sokkel og spenning', null]], ['sokkel', 'x_spenning', 'effekt_w']],
        '/bryggelys|vegglys|solcelle/u' => ['Lys til brygge, vegg eller båt som lades av solcellen. Trenger ikke strøm.', [['skruer til å feste lyset', '%skrue%']], ['ip_klasse', 'batteri']],

        // Outdoor cooking
        '/brødrister|toaster/u' => ['Rister brød rett over et gassbluss på tur eller i båt.', [['gassbluss eller kokeapparat å sette den på', '%kokeapparat%'], ['lighter eller fyrstikker hvis brenneren ikke tenner selv', null]], []],
        '/omnia/u' => ['Omnia er en ovn som står på et gassbluss, for baking og matlaging på tur og i båt.', [['gassbluss eller kokeapparat', '%kokeapparat%']], []],
        '/overtrekk|rekkemontering|l-brakett/u' => ['Tilbehør til Magma-grill.', [['Magma-grill som tilbehøret passer til', '%Magma%grill%']], []],
        '/kullgrill/u' => ['Grill som bruker kull, for brygge, båt eller hage.', [['grillkull eller briketter', null], ['tennbrikett og lighter', null]], []],
        '/kokeapparat|minikjøkken|gassgrill|krabbekoker|grill.*gass/u' => ['Lager mat på gass utendørs, på tur eller i båt.', [['gass (butangassboks eller propan med regulator, se produktteksten). Følger ofte ikke med', null], ['lighter hvis den ikke har piezotenning', null], ['stabilt, brannsikkert underlag', null]], ['effekt_w', 'folger_ikke_med', 'x_gassforbruk', 'vekt_g']],
        '/\bgrill\b/u' => ['Grill for utendørs bruk.', [['kull eller gass, se produktteksten', null]], []],
        '/grytesett|gryte|kaffekjele/u' => ['Kokekar for tur, båt eller hjem.', [['varmekilde: gassbluss, kokeapparat eller induksjon, se produktteksten', '%kokeapparat%']], ['volum_l', 'materiale']],
        '/kikkert/u' => ['Ser på avstand, på sjøen eller i naturen. 7x50 betyr 7 ganger forstørrelse og 50 mm objektiv.', [], ['forstorrelse', 'objektiv_mm', 'vekt_g']],

        // Forest and protection
        '/håndvarmer/u' => ['Engangsvarmer for hender i kulde.', [], []],
        '/hanske/u' => ['Beskytter hendene. Nitril for vått og oljete arbeid, fôret for kulde, skinn for grovt arbeid. Størrelsen må passe.', [], ['storrelse', 'materiale']],
        '/hørsel|øreklokke/u' => ['Beskytter hørselen mot støy fra motorsag, vedkløyver og verktøy.', [], ['standard']],
        '/sagkjede/u' => ['Kjede til motorsag. Deling, drivlenkebredde og antall drivlenker må passe sverdet og sagen.', [['motorsag og sverd med samme deling, drivlenkebredde og antall drivlenker', null], ['rundfil for å holde kjedet skarpt', '%Rundfil%'], ['kjedeolje', null], ['hørselvern og hansker', '%hørselsvern%']], ['drivlenker', 'x_kjededeling', 'x_drivlenkebredde', 'sverdlengde_tomme']],
        '/rundfil|filsett/u' => ['Filer motorsagkjedet skarpt igjen. Fildiameteren må passe kjedet.', [['sagkjede med samme anbefalte fil', '%Sagkjede%']], ['x_anbefalt_rundfil', 'diameter_mm']],
        '/stativ.*vedkløyv/u' => ['Stativ som hever vedkløyveren til arbeidshøyde.', [['vedkløyveren stativet er laget for', '%Vedkløyver%']], []],
        '/vedkløyver/u' => ['Kløyver ved med hydraulisk kraft.', [['stikkontakt eller skjøteledning', null], ['arbeidshansker', '%Hanske%']], ['effekt_w']],
        '/vedsekk|sekkekanal/u' => ['Til lagring og transport av ved.', [], ['volum_l']],
        '/barkespade/u' => ['Fjerner bark fra stokker.', [['skaft (selges uten skaft)', null]], []],

        // Building
        '/hammer/u' => ['Slår inn spiker, og kloa river dem ut igjen.', [['spiker', '%Spiker%']], ['vekt_g']],
        '/slegge/u' => ['Tunge slag: riving, stolper og kiler.', [['vernebriller og hansker', '%Hanske%']], ['vekt_g']],
        '/stålkile|\bkile\b/u' => ['Kløyver ved sammen med en slegge.', [['slegge', '%Slegge%']], []],
        '/stolpespyd|stolpejern/u' => ['Fester stolper i bakken uten å støpe.', [['slegge', '%Slegge%'], ['skruer eller bolter til stolpen', null]], []],
        '/kappeskive|diamantblad|lamellslipeskive|sliperondell|slipekopp/u' => ['Skive til vinkelsliper, for kapping eller sliping. Diameter og senterhull må passe maskinen.', [['vinkelsliper med samme skivediameter', null], ['vernebriller, hansker og hørselvern', '%hørselsvern%']], ['diameter_mm', 'x_senterhull']],
        '/sandpapir/u' => ['Sliper tre, sparkel og maling. Lavt tall er grovt, høyt tall er fint.', [['slipekloss eller slipemaskin', null]], ['kornstorrelse']],
        '/x-krok|bildehempe/u' => ['Henger opp bilder på vegg, festes med stift.', [['hammer', '%HAMMER%']], []],
        '/murstift/u' => ['Stift som slås inn i mur og betong.', [['hammer', '%HAMMER%']], ['lengde_mm']],
        '/spiker|\bstift\b/u' => ['Spikres inn med hammer, i tre.', [['hammer', '%HAMMER%']], ['dimensjon_mm', 'lengde_mm', 'materiale']],
        '/øyeskrue|øyesk|skruekrok|vinkelkrok|vinkelskrue|spiralkrok|flenskrok|lampekrok|skrue/u' => ['Skrus inn i tre for å henge opp eller feste noe.', [['skrutrekker eller drill', null], ['forbor i hardt treverk', null], ['plugg hvis veggen er mur eller gips', '%plugg%']], ['diameter_mm', 'lengde_mm', 'materiale']],
        '/hullplate|bjelkesko|^vinkel/u' => ['Beslag som binder sammen trevirke i bygg.', [['beslagspiker eller beslagskruer', null], ['hammer eller drill', '%HAMMER%']], ['dimensjon_mm', 'bredde_mm', 'materiale']],
        '/tommestokk|meterstokk|metermål|skyvelær/u' => ['Måler lengder.', [], ['lengde_mm']],

        // Chain and fittings
        '/sjakkel/u' => ['Kobler sammen kjetting, tau og fester. Arbeidslasten må tåle lasten, og bolten må passe lenken.', [['tang eller fastnøkkel til bolten', null]], ['arbeidslast_kg', 'bruddstyrke_kg', 'dimensjon_mm']],
        '/kjetting|ankersikring/u' => ['Kjetting for fortøyning, løft, sikring eller pynt. Lenketype og tykkelse avgjør bruken.', [['sjakler som passer lenken', '%Sjakkel%']], ['dimensjon_mm', 'arbeidslast_kg', 'bruddstyrke_kg']],
        '/krok|skjøteledd|hurtigkopling|deck hook/u' => ['Kobler eller fester kjetting og tau.', [], ['arbeidslast_kg', 'bruddstyrke_kg', 'dimensjon_mm']],

        // Paint
        '/blandevisp/u' => ['Blander maling med en drill.', [['drill', null]], []],
        '/forlengerskaft/u' => ['Forlenger malerullen for tak og høye vegger.', [['malerulle med gjenge til skaftet', '%RULLE%']], []],
        '/(stål|japan|bred|tann|bakelitt)sparkel|sparkelspade/u' => ['Verktøy for å legge på og trekke av sparkelmasse.', [['sparkelmasse', '%LETTSPARKEL%']], ['bredde_mm']],
        '/sparkel|formtre|reparasjonsvoks/u' => ['Fyller hull og sprekker før maling eller behandling.', [['sparkelspade', '%SPARKELSPADE%'], ['sandpapir til å slipe etterpå', '%Sandpapir%']], ['volum_l']],
        '/pensel|rulle|malerpute|fordriver/u' => ['Påfører maling, beis eller lakk.', [['maling, beis eller lakk som passer underlaget', null], ['rullebrett eller malingsspann', '%RULLEKAR%'], ['maskeringstape', '%MASKERINGSTAPE%']], ['bredde_mm']],
        '/maskeringstape|dekkfolie|maskeringsblad|malerfilt|malerfug|dispenser/u' => ['Dekker til og beskytter flater mens du maler.', [], ['bredde_mm', 'lengde_mm']],
        '/reserveblad|skrapeskjær/u' => ['Reserveblad til malingsskrape. Bredden må passe skrapen.', [['skrape med samme bladbredde', '%SKRAPE%']], ['bredde_mm']],
        '/skrape|stålull|børste/u' => ['Fjerner gammel maling, rust eller skitt før ny behandling.', [], ['bredde_mm']],
        '/spannåpner/u' => ['Åpner og lukker malingsspann.', [], []],
        '/fugemasse/u' => ['Tetter fuger og sprekker.', [['fugepistol', null]], []],

        // Balcony
        '/glass/u' => ['Glassfelt til VindFix levegg for balkong eller terrasse.', [['VindFix stendere og dekklist med samme høyde', '%VindFix stender%']], ['hoyde_cm', 'bredde_cm']],
        '/stender/u' => ['Stolpe i VindFix-systemet som holder glassene.', [['glass og dekklist med samme høyde', '%balkongglass%']], ['hoyde_cm']],
        '/dekklist/u' => ['Dekklist på toppen av VindFix-stenderne.', [['stendere med samme høyde', '%VindFix stender%']], ['hoyde_cm']],
    ];

    /** [use, needs, keys] for the product name, or null when no rule knows the type. */
    public static function rule(string $name): ?array
    {
        $n = mb_strtolower($name);
        foreach (self::RULES as $re => $r) {
            if (preg_match($re, $n)) {
                return $r;
            }
        }
        return null;
    }

    /** The properties that decide between similar products, most important first. */
    public static function keys(string $name): array
    {
        return self::rule($name)[2] ?? [];
    }

    /**
     * Main use and needs for one product: use (text), has (needs the store sells, with up to two product cards each)
     * and not (needs the store doesn't sell). Null when no rule knows the type. Shared by the product page and Søren.
     * @return array{use:string, has:list<array{text:string, cards:array}>, not:list<string>}|null
     */
    public static function needs(int $id, string $name): ?array
    {
        $r = self::rule($name);
        if ($r === null) {
            return null;
        }
        $has = $not = [];
        $st = db()->prepare('SELECT id FROM products WHERE name LIKE ? AND id <> ? ORDER BY price_ore LIMIT 2');
        foreach ($r[1] as [$text, $like]) {
            $ids = [];
            if ($like !== null) {
                $st->execute([$like, $id]);
                $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
            }
            if ($ids) {
                $has[] = ['text' => $text, 'cards' => Catalog::cards($ids)];
            } else {
                $not[] = $text;
            }
        }
        return ['use' => $r[0], 'has' => $has, 'not' => $not];
    }

    /** Lines for Søren's facts: main use and needs, with products from the store where they exist. */
    public static function lines(int $id, string $name): array
    {
        $u = self::needs($id, $name);
        if ($u === null) {
            return [];
        }
        $out = ["Hovedbruk (utledet fra produkttypen, ikke oppgitt av Sør-Tre): {$u['use']}"];
        if ($u['has']) {
            $out[] = 'Trenger i tillegg, og Sør-Tre har det: ' . implode('; ', array_map(fn($h) => "{$h['text']}: "
                . implode(' eller ', array_map(fn($c) => "{$c['name']} " . ProductRef::price((int) $c['id']) . ' ' . Chat::availability($c), $h['cards'])), $u['has']));
        }
        if ($u['not']) {
            $out[] = 'Trenger i tillegg, men Sør-Tre selger det ikke her: ' . implode('; ', $u['not']);
        }
        return $out;
    }
}
