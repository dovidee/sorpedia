<?php
declare(strict_types=1);

/**
 * The Demo Hub (feature 033): a presentation layer over the running Sørpedia.
 * Every figure is counted from the database on the request. Every feature names where it lives in the code and its
 * status: verifisert (a specs/NNN/verification.md records a run), implementert (the code exists and runs in the app),
 * demodata (works, on demo data) or gjenstår (needed before production). Nothing here is a projection unless it says so.
 */
final class Presentation
{
    /** Results of the last full run of the gates, written down when they were run (specs/032 verification). */
    public const GATES = ['date' => '2026-11-22', 'tests' => 230, 'evals' => 131, 'phpstan' => 3];

    /**
     * Søren before and after feature 034, from the saved run of evals/retrieval.php (both implementations, same questions,
     * same database, one run). Null when the file is missing. Each row: label, what it means, before, after, the bar scale,
     * the format and whether lower is better.
     */
    public static function soren(): ?array
    {
        $file = __DIR__ . '/../specs/034-product-retrieval/after.json';
        $d = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!isset($d['report']['legacy']['summary'], $d['report']['retriever']['summary'])) {
            return null;
        }
        [$b, $a] = [$d['report']['legacy'], $d['report']['retriever']];
        $frac = fn(string $x) => array_map('intval', explode('/', $x));
        // Questions written after the first tuning round, and never used to tune: passed when everything expected is found
        // with nothing irrelevant and the right product first
        $held = fn(array $r) => [count(array_filter($r['cases'], fn($c) => str_starts_with($c['id'], 'holdout') && $c['recall'] >= 1 && !$c['irrelevant'] && $c['first'] !== false)),
            count(array_filter($r['cases'], fn($c) => str_starts_with($c['id'], 'holdout')))];
        $row = fn(string $label, string $hint, float $before, float $after, float $max, string $fmt, bool $lower = false) =>
            compact('label', 'hint', 'before', 'after', 'max', 'fmt', 'lower');
        $f = fn(string $k) => [$frac($b['summary'][$k]), $frac($a['summary'][$k])];
        $rows = [
            $row('Treff blant de fem', 'Andel av riktige varer som kom med (Recall@5)', $b['summary']['recall_at_5'], $a['summary']['recall_at_5'], 1, 'pct'),
            $row('Presisjon', 'Andel av varene Søren fikk som hørte til spørsmålet (Precision@5)', $b['summary']['precision_at_5'], $a['summary']['precision_at_5'], 1, 'pct'),
            $row('Irrelevante varer', 'Andel varer uten noe med spørsmålet å gjøre', $b['summary']['irrelevant_hit_rate'], $a['summary']['irrelevant_hit_rate'], 1, 'pct', true),
        ];
        foreach ([['exact_match', 'Riktig vare først', 'Modellnavn og varenummer gir riktig vare øverst'], ['followup_resolution', 'Oppfølgingsspørsmål', '«Hva koster de to sammen?» gjelder de samme varene'],
            ['compatibility', 'Tilbehør som passer', 'Dokumentert tilbehør kommer med når kunden spør'], ['card_consistency', 'Kort og modell enige', 'Produktkortene er de samme varene modellen fikk']] as [$k, $label, $hint]) {
            [$x, $y] = $f($k);
            $rows[] = $row($label, $hint, $x[0], $y[0], max(1, $y[1]), 'of');
        }
        [$hb, $ha] = [$held($b), $held($a)];
        $rows[] = $row('Nye spørsmål', 'Skrevet etter første justering og aldri brukt til å justere', $hb[0], $ha[0], max(1, $ha[1]), 'of');
        $rows[] = $row('Tid per spørsmål', 'Henting av varer, uten språkmodellen', $b['summary']['ms_per_question'], $a['summary']['ms_per_question'],
            max($b['summary']['ms_per_question'], $a['summary']['ms_per_question']), 'ms', true);
        return ['date' => (string) $d['date'], 'cases' => (int) $a['summary']['cases'], 'held' => $ha[1], 'rows' => $rows];
    }

    /** Counted from the database now. */
    public static function stats(): array
    {
        $pdo = db();
        $one = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();
        $rel = $pdo->query('SELECT level, COUNT(*) n FROM relations GROUP BY level')->fetchAll(PDO::FETCH_KEY_PAIR);
        $src = $pdo->query("SELECT source, COUNT(*) n FROM spec_values WHERE version = 'verifisert' GROUP BY source")->fetchAll(PDO::FETCH_KEY_PAIR);
        $index = (string) file_get_contents(__DIR__ . '/../public/index.php');
        return [
            'products' => $one('SELECT COUNT(*) FROM products'),
            'themes' => $one('SELECT COUNT(DISTINCT theme) FROM products'),
            'categories' => $one('SELECT COUNT(*) FROM categories'),
            'images' => $one('SELECT COUNT(*) FROM product_images'),
            'specKeys' => $one('SELECT COUNT(*) FROM spec_keys'),
            'specValues' => array_sum($src),
            'specSources' => $src,
            'relations' => array_sum($rel),
            'relLevels' => $rel,
            'manuals' => $one('SELECT COUNT(*) FROM manuals'),
            'warehouses' => $one('SELECT COUNT(*) FROM warehouses'),
            'synonyms' => $one('SELECT COUNT(*) FROM search_synonyms'),
            'builders' => count(Byggelab::BUILDERS),
            'routes' => preg_match_all("/route\\('(GET|POST|PUT|PATCH|DELETE)'/", $index),
            'apiRoutes' => preg_match_all("/route\\('(GET|POST|PUT|PATCH|DELETE)', '\\/api\\//", $index),
            'classes' => count(glob(__DIR__ . '/*.php') ?: []),
            'specs' => count(glob(__DIR__ . '/../specs/[0-9][0-9][0-9]-*', GLOB_ONLYDIR) ?: []),
            'verified' => count(glob(__DIR__ . '/../specs/[0-9][0-9][0-9]-*/verification.md') ?: []),
            'english' => count(I18nEn::T),
            'tables' => $one('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'),
        ];
    }

    /**
     * The feature inventory by area. Each feature: name, what it does, where it is, status and whether it can be shown live.
     */
    public static function features(array $s): array
    {
        $b = implode(', ', array_map(fn($x) => $x[0], Byggelab::BUILDERS));
        return [
            'handel' => ['Netthandel', [
                ['Produktkatalog', "{$s['products']} produkter fra sor-tre.no i {$s['themes']} temaer og {$s['categories']} kategorier, {$s['images']} bilder lagret lokalt. Importen kan kjøres på nytt uten duplikater.", 'scripts/scrape.php, scripts/import.php, src/Catalog.php', 'implementert', true],
                ['Søk som forstår kunden', "Autoforslag, skrivefeil («hammmer»), bruksområder («henge opp bilde») og {$s['synonyms']} synonymer. Søk uten treff logges for de ansatte.", 'src/Search.php, tests/SearchTest.php', 'implementert', true],
                ['Filtrering', 'Pris, materiale, størrelse og temaets tekniske egenskaper. Utledede verdier er merket.', 'src/Catalog.php', 'implementert', true],
                ['Varehus og lager', "{$s['warehouses']} varehus. Fysisk lager, nettlager eller restordre med dato, for varehuset kunden har valgt.", 'stock, warehouses', 'demodata', true],
                ['Sammenligning', 'Opptil fire produkter side om side, forskjeller uthevet og kilde på hver verdi.', '/sammenlign', 'implementert', true],
                ['Kasse og ordre', 'Handlekurv, kasse med Vipps og Klarna simulert, ordreside med mva og angrerett.', '/kasse, orders', 'demodata', true],
                ['Temasider og bannere', 'Hvert tema har egen fargepalett og banner; tallene i banneret kommer fra databasen.', 'specs/018, specs/021', 'verifisert', true],
            ]],
            'innsikt' => ['Produktintelligens', [
                ['To versjoner av produktdata', 'Verifisert (Sør-Tre og godkjent av ansatt) og Åpen (verifiserte kjøpere) side om side. Historikk, fletting, avvisning og tilbakerulling, som på Wikipedia.', 'spec_values, spec_revisions', 'implementert', true],
                ['Kilde og nivå på hver verdi', 'Hver spesifikasjon har kilde: sortre, utledet, bruker eller ansatt. Ukjent er et gyldig svar.', "{$s['specValues']} verifiserte verdier, {$s['specKeys']} spesifikasjonstyper", 'implementert', true],
                ['Nye spesifikasjoner uten migrering', 'En ny spesifikasjonstype er en ny rad i spec_keys, aldri en ny kolonne.', 'spec_keys', 'implementert', false],
                ['Utledede spesifikasjoner', 'Regler leser produktteksten og lagrer verdien med bevis og kilden utledet, aldri som bekreftet.', 'src/Extract.php, tests/UsageTest.php', 'implementert', true],
                ['Passer sammen', "{$s['relations']} koblinger med nivå (dokumentert, regelbasert, antatt) og grunn. Antatt vises aldri som sikkert.", 'relations', 'implementert', true],
                ['Produktguide', 'Tre til fem spørsmål gir høyst tre forslag og et komplett sett, med grunner. Går videre til sammenligning, Byggelab eller Søren.', 'src/Guide.php', 'implementert', true],
                ['Anmeldelser og bilder fra kjøpere', 'Bare verifiserte kjøpere av akkurat det produktet. Bilder av ferdig resultat, EXIF fjernet.', 'reviews, product_images', 'demodata', true],
                ['Monteringsveiledninger', "{$s['manuals']} veiledninger som redaksjonelle sider med verktøyliste og nummererte steg, og PDF.", '/veiledning, specs/020', 'demodata', true],
                ['Feilmeldinger fra kunder', 'Alle kan melde feil i den åpne versjonen; kvittering i innboksen og status i Innstillinger.', 'specs/017', 'verifisert', true],
            ]],
            'lab' => ['Byggelab', [
                ['Sju byggere', "$b. Produktene sjekkes mot hverandre før kjøp.", 'src/Byggelab.php, /byggelab', 'verifisert', true],
                ['Fire kompatibilitetsstatuser', 'Dokumentert, regelbasert, ukjent eller passer ikke, alltid med grunn og grunnlag. Aldri gjettet som verifisert.', 'Byggelab::report', 'verifisert', true],
                ['3D-arbeidsbenk', 'Three.js-benk med produktene, angre og gjør om, hele oppsettet i kurven og CSV-eksport.', 'public/assets/byggelab.js', 'verifisert', true],
                ['Testkjøring', 'Rapport som bare leser katalogdata, for eksempel svakeste ledd i et løfteoppsett.', 'Byggelab::report', 'verifisert', true],
            ]],
            'ai' => ['Søren (KI)', [
                ['Lokal språkmodell', 'Qwen2.5 3B kjører i Ollama på egen maskin og leser bare spørsmålet inn i faste felt: hensikt, produkttype, krav og budsjett. Tolkningen sjekkes mot kundens egne ord. Ingen kundedata sendes til en skytjeneste.', 'OLLAMA_URL, OLLAMA_MODEL, src/Understand.php, specs/041', 'verifisert', true],
                ['Svar skrevet av serveren', 'Varene hentes fra katalogen og svaret skrives av serveren, med spesifikasjoner og kilde. Modellen skriver ikke produktsvaret.', 'src/ProductRetriever.php, src/ShopAnswer.php, specs/040', 'verifisert', true],
                ['Pris og lager fra databasen', 'Pris, lager, spesifikasjoner og sammenligninger kommer fra databasen, aldri fra modellen. Modellen kan ikke finne på en pris.', 'src/ShopAnswer.php, specs/039', 'verifisert', true],
                ['Faste svar uten modell', 'Hilsener, takk, hjelp og spørsmål om vær eller ferje får et fast svar fra serveren uten modellkall.', 'src/QuickReply.php, specs/042', 'verifisert', true],
                ['Sikkerhetsvern', 'Hver melding sjekkes før modellen: forsøk på å overstyre instruksene, trusler og personopplysninger. Serveren eier samtalehistorikken.', 'src/Guard.php, src/Moderation.php, src/Violence.php', 'verifisert', true],
                ['Virker uten modellen', 'Er Ollama nede eller treg, svarer Søren med produkter fra katalogsøket i stedet.', 'src/Chat.php', 'verifisert', true],
                ['Videre til et menneske', 'Kunden kan be om et menneske; saken får samtalen og produktet og dukker opp live i Kundeservice.', '/api/support, support_tickets', 'implementert', true],
                ['Søren for ansatte', 'I Kontrolltårnet svarer Søren på spørsmål om tallene og skriver utkast til kundesvar som en ansatt leser før sending.', 'src/StaffBrief.php, src/Drafts.php', 'implementert', true],
            ]],
            'tarn' => ['Kontrolltårnet', [
                ['Oversikt', 'Nøkkeltall, varehus og siste aktivitet, talt opp fra databasen. Hvert tall åpner postene bak det.', '/ansatt/tarn, src/Overview.php', 'verifisert', true],
                ['Gulv i 3D', 'Ekte produkter og sendinger på stasjoner etter tilstand, med Varemottak, bestillingslisten og forbedringsoppgaver ved siden av.', 'src/Gulv.php, public/assets/tower.js', 'verifisert', true],
                ['Bestilling og varemottak', 'Varer som mangler i butikken legges i en bestillingsliste. En bil henter dem fra butikker innen 60 km, én om gangen eller alle på én gang, og ved ankomst kvitteres de inn på lageret.', 'src/Supply.php, specs/043', 'verifisert', true],
                ['Forbedringsoppgaver', 'Seks tydelige tilstander. Fletting av et kjøperbidrag avslutter oppgavene for samme verdi.', 'improvement_tasks', 'verifisert', true],
                ['Tallene bak tallet', 'De nøyaktige søkeordene uten treff, de lagrede samtalene med Søren og Byggelab-oppsettene, som åpnes nøyaktig slik kunden satte dem opp.', 'src/Drill.php', 'verifisert', true],
                ['Vurder før oppgave', 'Et funn er ikke en oppgave. Den ansatte markerer det som vurdert eller ignorert, og først da kan en forbedringsoppgave opprettes.', 'src/EventDesk.php, specs/043', 'verifisert', true],
                ['Transport', 'Alle bilene på kartet. Hver værmelding, veimelding og ferjeavgang viser kilden og når den ble hentet: MET Norway, Statens vegvesen og Entur.', 'src/Transport.php, scripts/conditions.php', 'verifisert', true],
                ['Simulert levering', 'Velg hvilken som helst bil og spill av turen. Ruten følger veinettet og tar ferje bare der geografien krever det. Ekte forhold eller simulert storm, på én klokke med pause og fart.', 'src/Roads.php, src/DemoSim.php, tests/DemoSimTest.php', 'demodata', true],
                ['Kundeservice', 'Saker med samtalen fra Søren, ordre med bilder og refusjon per linje med bekreftelse.', 'src/Refund.php, tests/RefundTest.php', 'implementert', true],
                ['Innsikt', 'Seks hovedtall der hver andel viser teller og nevner, deretter det som trenger oppmerksomhet (varer som mangler i butikken, søk uten treff) og utviklingen over tid.', 'src/Insight.php, src/Stats.php', 'verifisert', true],
                ['Guidet omvisning', 'Første besøk viser en omvisning som går videre når den ansatte gjør handlingen.', 'public/assets/tour.js, specs/027', 'verifisert', true],
            ]],
            'integrasjon' => ['Integrasjoner', [
                ['Offentlig REST API', 'Read-only /api/v1 med produkter, spesifikasjoner med kilde, koblinger med nivå og anmeldelser. ETag, paginering og OpenAPI 3.1.', 'src/PublicApi.php, docs/api/openapi.json', 'verifisert', false],
                ['WooCommerce-kobling', 'Signerte webhooks holder katalogen i takt; en WordPress-utvidelse viser spesifikasjoner og Søren på butikkens egne produktsider.', 'integrations/woocommerce, docs/integration/woocommerce.md', 'implementert', false],
                ['Vær, vei og ferjer', 'Planlagt jobb henter MET Norway, Statens vegvesen og Entur og skriver til databasen. Faller tilbake til rader merket demo.', 'scripts/conditions.php', 'implementert', true],
            ]],
            'drift' => ['Sikkerhet og drift', [
                ['Innlogging', 'Argon2id-passord, låsing etter fem feil i 15 minutter, samme svar for ukjent e-post.', 'src/Auth.php, tests/AuthTest.php', 'verifisert', false],
                ['Roller og verifisering', 'Fire roller. Verifisert kjøper krever bekreftet e-post og minst ett betalt kjøp.', 'users, roles', 'implementert', true],
                ['Personvern', 'Alias i stedet for navn, data som JSON og sletting av konto.', 'src/Privacy.php', 'implementert', true],
                ['Tilbakestilling av passord', 'Engangslenke med hashet token som varer i 30 minutter.', 'specs/028', 'verifisert', true],
                ['Sikkerhetskopi', 'Konsistent gzip-dump hver natt i Docker, de 14 nyeste beholdes, synlig i Innsikt og på admin-siden.', 'scripts/backup.php', 'verifisert', false],
                ['Admin-side', 'Tegn på passordgjetting, sikkerhetslogg, ansattes handlinger, feillogg og sikkerhetskopier, med daglig hash i stedet for IP-adresse.', '/admin, audit_log', 'verifisert', false],
                ['Norsk og engelsk', "Bokmål som standard og engelsk med språkvelgeren: {$s['english']} oversatte tekster.", 'src/I18nEn.php, scripts/i18n_en.py', 'implementert', true],
                ['Kvalitetsporter', self::GATES['tests'] . ' enhetstester, ' . self::GATES['evals'] . ' eval-tilfeller for Søren, PHPStan nivå ' . self::GATES['phpstan'] . ' og kroker som stopper udokumenterte påstander.', 'tests/, evals/, .claude/hooks', 'verifisert', false],
            ]],
        ];
    }

    /**
     * The connections between the parts, each grounded in the code: what moves, how, and where. a and b are node keys
     * in the diagram.
     */
    public static function links(): array
    {
        return [
            ['kunde', 'butikk', 'Sider og søk', 'HTTP til public/index.php; maler i templates/, katalog og søk i src/Catalog.php og src/Search.php.'],
            ['kunde', 'soren', 'Spørsmål og strømmet svar', 'POST til /api/chat svarer med Server-Sent Events. Nettleseren sender bare den nye meldingen og sideadressen.'],
            ['soren', 'ollama', 'Prompt med fakta', 'Det eneste eksterne kallet under en sideforespørsel: HTTP-strøm til OLLAMA_URL (qwen2.5:7b-instruct).'],
            ['soren', 'db', 'Produktfakta og historikk', 'Chat::facts leser spesifikasjoner med kilde; chat_messages lagrer samtalen; ProductRef fyller inn pris og lager.'],
            ['butikk', 'db', 'Katalog, lager, ordre', 'products, spec_values, relations, stock og orders. Endringer skriver også en rad i events.'],
            ['lab', 'db', 'Kompatibilitet', 'Byggelab::report leser spec_values og relations; hver sjekk lagres som lab_check i analytics_events.'],
            ['lab', 'soren', 'Oppsettet som spørsmål', '«Spør Søren» fra Byggelab sender de valgte produktene som første melding.'],
            ['butikk', 'tarn', 'Kundeatferd', 'Søk, null treff, guide, kurv og kjøp lagres i analytics_events og vises i Oversikt, Innsikt og tallene bak tallet.'],
            ['soren', 'tarn', 'Saker til et menneske', 'POST /api/support lager support_tickets med samtalen og produktet; events gjør at Kundeservice ser saken live.'],
            ['kjoper', 'tarn', 'Kjøperbidrag', 'En verifisert kjøper endrer den åpne versjonen (spec_revisions). Produktet havner i Produktkontroll på Gulv.'],
            ['tarn', 'db', 'Fletting og oppgaver', 'Flett inn gjør verdien verifisert med kilden ansatt og avslutter oppgavene i improvement_tasks for samme verdi.'],
            ['tarn', 'butikk', 'Lager etter levering', 'En bestilling trekker antallet fra butikken bilen henter i; ved ankomst legges det til i mottakende butikk, én gang (supply_list, shipment_plans).'],
            ['cron', 'db', 'Vær, vei og ferjer', 'scripts/conditions.php henter MET Norway, Statens vegvesen og Entur etter plan og skriver til databasen; Transport leser bare databasen.'],
            ['woo', 'db', 'Produktendringer', 'Signert webhook til /api/integrations/woocommerce/webhook oppdaterer katalogen (src/WooSync.php).'],
            ['db', 'api', 'Åpne produktdata', '/api/v1 gir produkter, spesifikasjoner med kilde og koblinger med nivå til andre systemer (src/PublicApi.php).'],
            ['db', 'backup', 'Sikkerhetskopi', 'scripts/backup.php lager en konsistent dump hver natt til data/backups og beholder 14.'],
        ];
    }

    /** What still has to be in place before Sørpedia can run as a commercial service. */
    public const REMAINING = [
        ['Ekte betaling', 'Vipps og Klarna er simulert i kassen.'],
        ['Ekte lager per butikk', 'Lager per varehus er demodata; en kobling mot Sør-Tres lagersystem mangler.'],
        ['WooCommerce mot en ekte butikk', 'Koblingen og utvidelsen finnes, men er ikke testet mot Sør-Tres egen nettbutikk.'],
        ['Søren i drift', 'Lesingen av et spørsmål tok 0,9 til 3,5 sekunder med varm modell. Første lesing etter oppstart kan nå grensen på 10 sekunder; da leser reglene spørsmålet. Qwen2.5 3B har en lisens uten kommersiell bruk, så drift trenger en annen lisens eller en tilsvarende Apache-2.0-modell.'],
        ['E-post', 'E-post går til en demo-innboks i appen, ikke til en ekte e-posttjeneste.'],
        ['Drift og overvåking', 'Kjører lokalt i Docker. Hosting, overvåking og varsling er ikke satt opp.'],
        ['Ruter på kartet', 'Ruten følger hovedveiene mellom byer og ferjekaier, med rett linje mellom knutepunktene. Den er et anslag langs riktige veier, ikke veigeometri.'],
    ];

    /** Likely questions and where the answer is in the hub. */
    public const QA = [
        ['Hvordan holdes dataene i takt?', 'Importen fra sor-tre.no kan kjøres på nytt uten duplikater, og WooCommerce-webhooks oppdaterer katalogen. Live-endringer går via events-tabellen og Server-Sent Events.', 'arkitektur'],
        ['Hvordan virker KI-en?', 'En lokal Qwen2.5 3B i Ollama leser bare spørsmålet inn i faste felt. Serveren finner varene og skriver svaret med pris og lager fra databasen.', 'teknikk'],
        ['Hva skjer hvis KI-en går ned?', 'Søren svarer med produkter fra katalogsøket, og kunden kan alltid be om et menneske.', 'funksjoner'],
        ['Hvordan unngår dere tap av data?', 'Sikkerhetskopi hver natt, de 14 nyeste beholdes, og den nyeste vises i Innsikt og på admin-siden.', 'teknikk'],
        ['Hvordan er løsningen sikret?', 'Argon2id-passord, låsing ved gjetting, CSRF-vern, roller, revisjonslogg uten IP-adresser og vern mot instruksjonsangrep i chatten.', 'teknikk'],
        ['Hva er ferdig, og hva gjenstår?', 'Hver funksjon har status i oversikten; listen over hva som gjenstår før produksjon står under Veikart.', 'veikart'],
        ['Hvordan kan dette tjene penger?', 'Mer komplette produktsider gir tryggere kjøp, og kompatibilitetssjekk før betaling gir færre returer. Begge kan måles i tall Sør-Tre allerede har.', 'veikart'],
        ['Hvordan virker logistikken?', 'Transport leser sendinger fra databasen, med vær, vei og ferjer hentet etter plan og vist med kilde. Bilene og klokken er en simulering merket som demo; varene de henter trekkes fra og legges til lageret.', 'arkitektur'],
    ];
}
