<?php
/**
 * Control Tower (feature 022). Own full-screen shell: no shop header, navigation, banners or chat.
 * All data is loaded from /api/tarn/* by public/assets/tower.js.
 */
$u = current_user();
$en = lang() === 'en';
// The account's theme setting (Settings): mork gives dark; lys, system and no account give light (CONTROLTOWER-REDESIGN.md)
$dark = ($u['theme'] ?? '') === 'mork';
$v = fn(string $f) => filemtime(APP_ROOT . "/public/assets/$f");
$labels = [
    // Gulv stations (feature 032): products and shipments only
    'stations' => ['inntak' => t('Varemottak'), 'mottak' => t('Sendinger inn'), 'kontroll' => t('Produktkontroll'), 'kvalitet' => t('Datakvalitet'),
        'kunnskap' => t('Varekunnskap'), 'kobling' => t('Kompatibilitet'), 'klar' => t('Klargjøring')],
    // One icon and one line per station, so the floor reads without training
    'icons' => array_map('icon', ['inntak' => 'warehouse', 'mottak' => 'truck', 'kontroll' => 'clipboard-check', 'kvalitet' => 'triangle-alert',
        'kunnskap' => 'book-open', 'kobling' => 'link', 'klar' => 'circle-check']),
    'g' => [
        'floor' => t('Gulv'), 'close' => t('Lukk'), 'incoming' => t('Varemottak'), 'tasks' => t('Forbedringsoppgaver'), 'task' => t('Oppgave'),
        'active' => t('Aktive'), 'done' => t('Ferdige'), 'none' => t('Ingen aktive oppgaver'), 'noneDone' => t('Ingen ferdige oppgaver'),
        'noIncoming' => t('Ingenting på vei inn'),
        'arrived' => t('Levert, venter på mottak'), 'arrivedAt' => t('levert'), 'fromCity' => t('fra'), 'checkIn' => t('Kvitter mottak'),
        'orderList' => t('Bestillingsliste'), 'pickStatus' => ['valgt' => t('Venter på bil'), 'bestilt' => t('Bestilt'), 'mottatt' => t('Mottatt'), 'fjernet' => t('Fjernet')],
        'unpick' => t('Fjern'), 'toTransport' => t('Velg biler i Transport'), 'noPicks' => t('Ingen varer på listen. Legg til fra Innsikt, Mangler i butikken.'), 'eta' => t('Ankomst'), 'awaiting' => t('Venter på kontroll'), 'noProduct' => t('Uten produkt'),
        'src' => ['sending' => t('Sending'), 'bidrag' => t('Kjøperbidrag')], 'ship' => t('Sending'), 'onMap' => t('Vis på kartet'),
        'status' => ['apen' => t('Åpen'), 'under_behandling' => t('Under behandling'), 'klar_for_kontroll' => t('Klar for kontroll'),
            'fullfort' => t('Fullført'), 'avvist' => t('Avvist'), 'arkivert' => t('Arkivert')],
        'move' => ['under_behandling' => t('Start'), 'klar_for_kontroll' => t('Klar for kontroll'), 'fullfort' => t('Fullført'),
            'avvist' => t('Avvis'), 'arkivert' => t('Arkiver'), 'apen' => t('Åpne igjen')],
        'hint' => ['apen' => t('Ingen har startet. Trykk Start når du tar den.'), 'under_behandling' => t('Noen jobber med den. Klar for kontroll når endringen er gjort.'),
            'klar_for_kontroll' => t('Endringen er gjort og venter på at en annen ser over den.'), 'fullfort' => t('Forbedringen er gjort.'),
            'avvist' => t('Forslaget ble avvist.'), 'arkivert' => t('Ikke lenger aktiv.')],
        'sources' => ['sok' => t('Søk'), 'datakvalitet' => t('Datakvalitet'), 'produkt' => t('Produkt'), 'kompatibilitet' => t('Kompatibilitet'),
            'mulighet' => t('Mulighet'), 'revisjon' => t('Kjøperbidrag')],
        'why' => ['inntak' => t('På vei inn med en sending'), 'kontroll' => t('Kjøperbidrag eller oppgave venter'), 'kvalitet' => t('Har en åpen feilmelding'),
            'kobling' => t('Har en dokumentert kobling'), 'klar' => t('Ferdig behandlet siste 30 dager')],
        'created' => t('Opprettet'), 'product' => t('Produkt'), 'taskKey' => t('Gjelder'), 'origin' => t('Kilde'), 'outcome' => t('Resultat'),
        'newTask' => t('Ny forbedringsoppgave'), 'taskTitle' => t('Hva skal forbedres?'), 'taskNote' => t('Beskrivelse (valgfritt)'),
        'wholeProduct' => t('Hele produktet'), 'taskMake' => t('Opprett oppgave'), 'made' => t('Oppgave #{id} ligger nå i Forbedringsoppgaver.'),
        'dup' => t('Det finnes allerede en aktiv oppgave for dette (#{id}).'), 'fixDefault' => t('Rett {key}'), 'fillDefault' => t('Fyll inn {key}'),
        'verif' => t('Spesifikasjoner'), 'verifN' => t('{v} verifisert av {n}, {m} mangler'), 'reportsN' => t('{n} feilmeldinger'),
        'current' => t('Nå'), 'proposed' => t('Foreslått'), 'empty' => t('tom'),
        'mergeAsk' => t('Flette inn i verifisert?'), 'mergeWhat' => t('Verdien flyttes til Verifisert med kilden Ansatt, og kundene ser den som kontrollert.'),
        'rejectAsk' => t('Avvise endringen?'), 'rejectWhat' => t('Verdien fjernes fra den åpne versjonen.'),
        'willComplete' => t('Oppgaver for denne verdien som blir Fullført: {n}.'), 'willReject' => t('Oppgaver for denne verdien som blir Avvist: {n}.'),
        'noLinked' => t('Ingen oppgaver er knyttet til denne verdien.'), 'confirm' => t('Bekreft'), 'cancel' => t('Avbryt'),
        'mergedOk' => t('Flettet inn'), 'rejectedOk' => t('Avvist'), 'settled' => t('Oppgaver avsluttet'), 'stillOpen' => t('Fortsatt åpne på produktet'),
        'noTasks' => t('Ingen oppgaver var knyttet til endringen.'), 'relations' => t('Koblinger'), 'fits' => t('Passer'), 'nofit' => t('Passer ikke'),
    ],
    'what' => [
        'inntak' => t('Hver hendelse i butikken kommer inn her som en pakke.'),
        'sok' => t('Kunden søkte og fikk treff.'),
        'avvik' => t('Kunden søkte og fikk ingen treff. Dra pakken til Data å rette eller Søk med treff.'),
        'guide' => t('Kunden fullførte en guide eller åpnet en manual.'),
        'lab' => t('Kunden sjekket, testkjørte, lagret eller eksporterte et oppsett i Byggelab.'),
        'soren' => t('Kunden spurte Søren.'),
        'kundeservice' => t('Søren sendte kunden videre til et menneske.'),
        'kvalitet' => t('Produkter med feilmelding eller forslag til endring.'),
        'handel' => t('Lagt i handlekurv eller kjøpt.'),
        'ukjent' => t('Hendelser systemet ikke kjenner igjen.'),
        'mottak' => t('Varer på vei inn med bil. Klikk for innhold.'),
    ],
    'legend' => t('Forklaring'),
    // Ask Søren panel
    'soren' => [
        'title' => t('Spør Søren'), 'close' => t('Lukk'), 'ask' => t('Spørsmål til Søren'), 'send' => t('Spør'),
        'note' => t('Søren svarer bare ut fra tall serveren har talt opp, og endrer ingenting. Du bestemmer.'),
        'placeholder' => t('For eksempel: hva bør vi rette først?'), 'thinking' => t('Søren leser tallene …'), 'facts' => t('Tallene Søren brukte'),
        'suggest' => [t('Hva bør vi rette først?'), t('Hvilke saker venter på svar?'), t('Hvilke sendinger er forsinket?'), t('Hvilke varer går tom snart?')],
    ],
    // Customer service view
    'cs' => [
        'filters' => ['apen' => t('Åpne'), 'alle' => t('Alle')], 'none' => t('Ingen saker.'), 'guest' => t('Gjest'), 'waiting' => t('Venter på svar'),
        'searches' => t('Søk uten treff, 7 dager'), 'searchesHint' => t('Ord kundene ikke fant noe på. Lag en oppgave så katalogen kan rettes.'),
        'noSearches' => t('Ingen søk uten treff denne uka.'), 'task' => t('Lag oppgave'), 'taskTitle' => t('Søk uten treff: «{q}»'), 'taskMade' => t('Oppgave #{id} opprettet'),
        'customer' => t('Kunde'), 'staff' => t('Kundeservice'), 'withSoren' => t('Samtalen med Søren'), 'handover' => t('Sendt videre til kundeservice'),
        'noReplies' => t('Ingen svar ennå.'), 'reply' => t('Svar til kunden'), 'placeholder' => t('Skriv et svar. Ctrl + Enter sender.'),
        'draft' => t('Utkast fra Søren'), 'send' => t('Send'), 'close' => t('Lukk sak'), 'closed' => t('Saken er lukket.'),
        'customerTitle' => t('Kunde'), 'product' => t('Gjelder'), 'orders' => t('Ordre'), 'noOrders' => t('Ingen ordre på denne kunden.'),
        'guestOrders' => t('Gjester har ingen ordre å refundere.'), 'order' => t('Ordre {id}'),
        'ostatus' => ['betalt' => t('Betalt'), 'kansellert' => t('Kansellert'), 'refundert' => t('Refundert')], 'refunded' => t('{n} refundert'),
        'less' => t('Ett mindre'), 'more' => t('Ett til'), 'refundSel' => t('Refunder valgte, {sum}'), 'refundAll' => t('Refunder hele, {sum}'),
        'confirm' => t('Refundere {sum} for ordre {id}? Varene legges tilbake på lager, og kunden får en melding i saken.'),
        'confirmBtn' => t('Refunder'), 'cancel' => t('Avbryt'), 'refundDone' => t('Refundert {sum}. Kunden har fått melding.'),
    ],
    // Insight view
    'ins' => [
        'noData' => t('Ingen data'),
        'title' => t('Innsikt'), 'week' => t('Siste 7 dager mot de 7 før'), 'vs' => t('siste 7 dager, uka før {b}'), 'showAll' => t('Vis {n} til'),
        'backup' => t('Siste sikkerhetskopi av databasen: {t}, {mb} MB, {n} lagret.'), 'noBackup' => t('Ingen sikkerhetskopi av databasen ennå (php scripts/backup.php).'), 'prev' => t('forrige 7 d'), 'new' => t('ny'),
        'tiles' => ['searches' => t('Søk'), 'zeroRate' => t('Søk uten treff'), 'chat' => t('Spørsmål til Søren'), 'escalateRate' => t('Sendt til menneske'),
            'guides' => t('Guider og manualer'), 'cart' => t('Lagt i kurv'), 'purchases' => t('Kjøp'),
            'accessoryRate' => t('Ordre med tilbehør som passer'), 'toCartMin' => t('Fra søk til kurv, median min')],
        'searchTrend' => t('Søk per dag, 30 dager'), 'orders' => t('Ordre per dag, 60 dager'), 'forecast' => t('Prognose 7 dager'),
        'band' => t('95 % intervall'), 'actual' => t('Faktisk'), 'trend' => t('Trend'),
        'needData' => t('Prognose vises fra {need} dager med data. Nå: {have}.'),
        'fitLine' => t('Lineær trend, R² {r2}. Snittfeil på de 7 siste dagene når de holdes utenfor (MAE): {mae} per dag.'),
        'fitOnly' => t('Lineær trend, R² {r2}.'), 'to' => t('til'), 'valuesWord' => t('verdier'), 'loading' => t('Henter tall'),
        'slope' => t('{b} ordre per dag per uke'),
        'funnel' => t('Fra søk til kjøp, 7 dager'), 'steps' => ['search' => t('Søk'), 'cart' => t('Lagt i kurv'), 'purchase' => t('Kjøp')], 'ofPrev' => t('{p} av forrige steg'),
        'values' => t('Ordreverdi'), 'mean' => t('Snitt'), 'median' => t('Median'), 'sd' => t('Standardavvik'), 'iqr' => t('Midtre halvdel'), 'orderN' => t('{n} ordre'),
        'coverage' => t('Spesifikasjoner etter kilde'),
        'rel' => t('Koblinger etter nivå'), 'levels' => ['dokumentert' => t('Dokumentert'), 'regelutledet' => t('Regelbasert'), 'antatt' => t('Antatt')],
        'quality' => t('Datakvalitet'), 'q' => ['missingProducts' => t('Produkter med hull'), 'unverified' => t('Ukontrollerte verdier'), 'noPrice' => t('Uten pris')],
        'zero' => t('Søk uten treff, 7 dager'), 'zeroNone' => t('Ingen søk uten treff denne uka.'),
        'cover' => t('Dager med lager igjen'), 'coverNote' => t('Nettlager delt på snittsalg per dag siste 30 dager. Lavest først.'), 'days' => t('{n} d'), 'empty' => t('Tomt'),
        'opp' => t('Muligheter, 30 dager'), 'oppTab' => t('Muligheter'), 'oppNote' => t('Behov katalogen ikke dekker ennå, flest kunder først. Forslag til vurdering: ingenting endres i katalogen før en ansatt gjør det.'),
        'oppNone' => t('Ingen udekte behov registrert de siste 30 dagene.'),
        'oppKind' => ['search' => t('Søk uten treff'), 'guide' => t('Guiden fant det ikke'), 'lab' => t('Mangler i Byggelab')],
        'oppAct' => ['search' => t('Undersøk om et produkt bør kobles til søkeordet, eller om katalogen mangler det.'),
            'guide' => t('Undersøk om produktet finnes under et annet navn, eller om det mangler i katalogen.'),
            'lab' => t('Undersøk om et tilbehør bør kobles, eller om spesifikasjoner mangler.')],
        'oppCount' => t('Økter: {s}, ganger: {n}'), 'oppSeen' => t('Vurdert'), 'oppNoImg' => t('Mangler produktbilde'), 'oppNoProduct' => t('Ikke knyttet til et produkt'), 'oppView' => t('Vis produktet'),
        'labOpen' => t('Se oppsettene'), 'zeroOpen' => t('Se alle søkeord'), 'oppTask' => t('Oppgave #{id}'),
        'oppDoSeen' => t('Marker vurdert'), 'oppDoTask' => t('Opprett forbedringsoppgave'), 'ignore' => t('Ignorer'),
        'states' => ['ikke' => t('Ikke vurdert'), 'vurdert' => t('Vurdert'), 'ignorert' => t('Ignorert'), 'oppgave' => t('Oppgave opprettet')],
        'taskState' => t('Oppgave #{id} · {s}'),
        'of' => ['zeroRate' => t('{a} av {b} søk'), 'escalateRate' => t('{a} av {b} samtaler'), 'accessoryRate' => t('{a} av {b} betalte ordre')], 'prevZero' => t('forrige 7 dager: 0'), 'ofAny' => t('{a} av {b}'),
        'lvAttention' => t('Trenger oppmerksomhet'), 'lvTrends' => t('Utvikling'), 'lvUsage' => t('Byggelab og produktguide'), 'lvData' => t('Datakvalitet og kilder'),
        'missTitle' => t('Mangler i butikken'), 'missNote' => t('{n} produkter er tomme i {home}. Mest solgte siste 60 dager først. Lager per butikk er demodata.'),
        'missMeta' => t('Solgt {sold} siste 60 dager · {n} på lager i {s} andre butikker'), 'missNone' => t('Ingen tomme hyller i mottakerbutikken.'),
        'missFail' => t('Kunne ikke hente lagerstatus.'), 'addToOrder' => t('Legg i bestilling'), 'added' => t('Lagt til'), 'qty' => t('Antall'),
        'listed' => ['valgt' => t('På bestillingslisten'), 'bestilt' => t('Bestilt')], 'nowhere' => t('Ingen butikk har den'),
        'zeroTriage' => t('Et søk uten treff er et spor, ikke en feil. Vurder eller ignorer søkeordet; lag en oppgave bare når du har funnet hva som skal rettes.'),
        'meanMedian' => t('Snitt påvirkes av store ordre; medianen er den midterste ordren.'), 'oppTaskTitle' => t('{kind}: «{text}»'),
        'lab' => t('Byggelab, 30 dager'), 'labConfigs' => t('Oppsett'), 'labAbandoned' => t('Forlatt uten lagring eller kurv'),
        'labVerdict' => ['klar' => t('Klar'), 'mangler' => t('Mangler noe'), 'konflikt' => t('Passer ikke'), 'tom' => t('Tomt')],
        'labTop' => t('Mest brukte produkter'), 'labPairs' => t('Brukes oftest sammen'), 'labNone' => t('Ingen oppsett i Byggelab de siste 30 dagene.'),
        'gd' => t('Produktguiden, 30 dager'), 'gdStarted' => t('Startet'), 'gdDone' => t('Fullført'), 'gdDrop' => t('Her gikk kundene fra guiden'),
        'gdNone' => t('Ingen har forlatt guiden de siste 30 dagene.'),
        'demo' => t('Inkluderer {n} demoordre'), 'table' => t('Vis som tabell'), 'day' => t('Dag'), 'count' => t('Antall'),
    ],
    'sync' => ['title' => t('Synkroniserer med lageret'), 'steps' => [t('Rutenett og koordinater'), t('Bygger lagerbygg og transportbånd'),
        t('Materialer, lys og detaljer'), t('Henter sanntidsdata'), t('Aktiverer gulvet')]],
    'ov' => [
        'title' => t('Oversikt'), 'updated' => t('Oppdatert {t}'), 'week' => t('Siste 7 dager'), 'unknown' => t('Ukjent'), 'demo' => t('demo'),
        'open' => t('Åpne'), 'failed' => t('Kunne ikke hente oversikten. Prøver igjen om et minutt.'),
        'grade' => ['good' => t('God'), 'mid' => t('Middels'), 'low' => t('Svak')],
        'data' => ['title' => t('Produktdata'), 'sub' => t('{n} av {m} filterverdier finnes'), 'more' => t('Ukontrollerte verdier: {u}. Åpne feilmeldinger: {r}.')],
        'delivery' => ['title' => t('Levert i tide'), 'sub' => t('{n} av {m} leveranser siste 30 dager'), 'none' => t('Ingen leveranser siste 30 dager'),
            'road' => t('{n} på veien, {l} forsinket')],
        'cases' => ['title' => t('Kundesaker'), 'waiting' => t('venter på svar'), 'handling' => t('{n} påbegynt'), 'oldest' => t('Eldste har ventet {t}'), 'none' => t('Ingen saker venter')],
        'orders' => ['title' => t('Omsetning'), 'titleN' => t('Ordre'), 'label' => t('Betalte ordre siste 7 dager'), 'n' => t('Antall ordre'), 'avg' => t('Snitt per ordre'),
            'refunds' => t('Refundert'), 'refundN' => t('Refunderte ordre'), 'demo' => t('{n} av {m} er demo')],
        'search' => ['title' => t('Søk med treff'), 'zero' => t('uten treff'), 'of' => t('{n} søk siste 7 dager')],
        'soren' => ['title' => t('Søren løste selv'), 'esc' => t('sendt videre'), 'of' => t('{n} spørsmål siste 7 dager')],
        'top' => ['title' => t('Viktigst nå'), 'case' => t('Kundesak #{id}'), 'waited' => t('Har ventet {t}'), 'openCase' => t('Åpne saken'),
            'late' => t('Forsinkede sendinger: {n}'), 'lateSub' => t('Se ruten og årsaken på kartet'), 'openMap' => t('Åpne kartet'),
            'miss' => t('Søk uten treff: «{q}»'), 'missSub' => t('{n} ganger siste 7 dager'), 'openIns' => t('Se søkene'), 'calm' => t('Ingenting haster nå'),
            'urgent' => t('Haster'), 'watch' => t('Følg med')],
        'route' => ['title' => t('Ruten i dag'), 'ships' => t('sendinger på veien'), 'closed' => t('Stengte veier'), 'warn' => t('Farevarsler'),
            'ferry' => ['normal' => t('I rute'), 'scheduled' => t('Rutetid'), 'delayed' => t('Forsinket'), 'disrupted' => t('Avvik'), 'cancelled' => t('Innstilt'), 'unknown' => t('Ukjent')]],
        'activity' => ['title' => t('Siste aktivitet'), 'none' => t('Ingenting har skjedd ennå.'),
            'order' => t('Ordre betalt'), 'orderSub' => t('{n} varer, {w}'), 'case' => t('Ny kundesak'), 'edit' => t('Forslag til endring'),
            'arrived' => t('Sending levert'), 'arrivedSub' => t('{ref} til {w}'),
            'audit' => ['refund' => t('Refusjon'), 'supply_order' => t('Varer bestilt fra annen butikk'), 'search_mapping' => t('Søkeord koblet til produkt'), 'revision_merge' => t('Endring flettet inn'),
                'revision_reject' => t('Endring avvist'), 'report_remove' => t('Verdi fjernet etter feilmelding'), 'report_close' => t('Feilmelding lukket'),
                'case_close' => t('Sak lukket'), 'backup_ok' => t('Sikkerhetskopi tatt'), 'backup_fail' => t('Sikkerhetskopi feilet')]],
        'icons' => array_map('icon', ['order' => 'receipt', 'case' => 'headset', 'edit' => 'git-merge', 'arrived' => 'package', 'audit' => 'clipboard-check']),
        'wh' => ['title' => t('Varehus'), 'demo' => t('Lager per varehus er demodata'), 'name' => t('Varehus'), 'skus' => t('På hylla'), 'units' => t('Enheter'),
            'orders' => t('Ordre 30 d'), 'road' => t('På veien'), 'cover' => t('Dekning'), 'total' => t('Enheter i alle varehus'),
            'grade' => ['good' => t('God'), 'mid' => t('Middels'), 'low' => t('Lav')]],
        'tick' => ['live' => t('Tallene er hentet {t}'), 'waiting' => t('Kundesaker som venter: {n}'), 'late' => t('Forsinkede sendinger: {n}'),
            'stale' => t('Sendinger uten fersk posisjon: {n}'), 'ferry' => t('{name}: {s}'), 'closed' => t('Stengte veier på rutene: {n}'),
            'backup' => t('Siste sikkerhetskopi for {t} siden'), 'noBackup' => t('Ingen sikkerhetskopi ennå'), 'zero' => t('Søk uten treff siste 7 dager: {n}'),
            'calm' => t('Ingen avvik akkurat nå')],
        'greet' => ['morning' => t('God morgen.'), 'day' => t('Hei.'), 'evening' => t('God kveld.'),
            'cases' => ['one' => t('1 kundesak venter på svar.'), 'other' => t('{n} kundesaker venter på svar.')],
            'late' => ['one' => t('1 sending er forsinket.'), 'other' => t('{n} sendinger er forsinket.')], 'calm' => t('Ingen saker venter, og ingen sendinger er forsinket.'),
            'ask' => t('Spør meg om tallene, så viser jeg hva jeg bygger svaret på.')],
    ],
    'tour' => [
        'ui' => ['of' => t('{i} av {n}'), 'next' => t('Neste'), 'back' => t('Tilbake'), 'close' => t('Lukk omvisningen'), 'finish' => t('Ferdig'),
            'doIt' => t('Gjør det, så går omvisningen videre.')],
        'steps' => [
            ['title' => t('Fem visninger'), 'text' => t('Oversikt viser tilstanden i butikken, Gulv varene og oppgavene, Transport sendingene, Kundeservice sakene og Innsikt tallene over tid.')],
            ['title' => t('Gulvet'), 'text' => t('Hver kasse er et ekte produkt, og hver pall en ekte sending. Stasjonen sier hva produktet venter på: kontroll av et kjøperbidrag, en feilmelding, en kobling eller klargjøring.')],
            ['title' => t('Et bidrag fra en verifisert kjøper'), 'text' => t('En kjøper har foreslått en ny verdi. Den vises som ukontrollert til en ansatt fletter den inn i den verifiserte versjonen eller avviser den. Trykk Flett eller Avvis.')],
            ['title' => t('Oversikten'), 'text' => t('Nøkkeltallene, varehusene og siste aktivitet, talt opp fra databasen. Klikk Søk eller Søren for å se de faktiske søkeordene og samtalene bak tallet.')],
            ['title' => t('Varemottak og forbedringsoppgaver'), 'text' => t('Det som er på vei inn, og oppgavene som forbedrer produktdataene. En oppgave går fra Åpen til Fullført, og fletting av et kjøperbidrag avslutter oppgavene som gjelder samme verdi.')],
            ['title' => t('Klokken og forholdene'), 'text' => t('Én simulert klokke styrer alle demobilene. Spill av, pause og velg fart. Ekte forhold bruker lagret vær fra MET Norway, veimeldinger fra Statens vegvesen og ferjeruter fra Entur; Simulert storm legger en storm over Vestlandet. Bestill alt sender hele bestillingslisten med de ledige bilene.')],
            ['title' => t('Velg en bil'), 'text' => t('Klikk en bil hvor som helst på kartet. På tur ser du hvor den er, været, ny ankomst og hvorfor, med kilde for hvert varsel. Ledig bil viser varene den kan hente fra butikker innen 60 km, og en demotur til et punkt du klikker.')],
            ['title' => t('Kartet'), 'text' => t('Sendingene på veien, med vær, veimeldinger og ferjer i sanntid. Kameraet er ditt: zoom og dra fritt, eller trykk Følg kjøretøy. Tilbakestill visning viser alt igjen.')],
            ['title' => t('Sendingene'), 'text' => t('Klikk en sending for rute, beregnet ankomst, kilde og forholdene på veien.')],
            ['title' => t('Sakene'), 'text' => t('Saker Søren har sendt videre til et menneske. Saker som venter på svar er merket. Klikk en sak for å åpne den.')],
            ['title' => t('Samtalen med Søren'), 'text' => t('Det kunden skrev til Søren før saken ble sendt videre, og deretter tråden mellom kunden og kundeservice. Svarene kommer inn mens du ser på.')],
            ['title' => t('Svar kunden'), 'text' => t('Skriv svaret selv, eller trykk Utkast fra Søren. Utkastet havner i feltet og sendes ikke før du har lest det og trykket Send.')],
            ['title' => t('Kunden og ordrene'), 'text' => t('Produktet saken gjelder og kundens betalte ordre med bilder. Velg antall med pluss og minus, og refunder linjene eller hele ordren. Du bekrefter beløpet før noe skjer.')],
            ['title' => t('Lukk saken'), 'text' => t('Når saken er løst, lukker du den. Den ligger fortsatt under Alle.')],
            ['title' => t('Søk uten treff'), 'text' => t('Denne ukas søk som ikke ga treff. Lag en oppgave til katalogen rett herfra.')],
            ['title' => t('Innsikt'), 'text' => t('Tallene over tid, Mangler i butikken med Legg i bestilling, og Observasjoner til vurdering av en ansatt.')],
            ['title' => t('Spør Søren'), 'text' => t('Åpne Søren når du trenger ham. Han svarer på spørsmål om tallene og sakene, med de samme reglene som i butikken.')],
        ],
    ],
    'pick' => t('Velg en pakke på gulvet'),
    'event' => t('Hendelse'), 'type' => t('Type'), 'station' => t('Stasjon'), 'time' => t('Tidspunkt'), 'id' => 'ID',
    'product' => t('Produkt'), 'price' => t('Pris'), 'stock' => t('Lager'), 'demo' => 'DEMO',
    'specs' => t('Spesifikasjoner'), 'missing' => t('Mangler'), 'unknown' => t('ukjent'),
    'revisions' => t('Endringer'), 'reports' => t('Feilmeldinger'), 'merge' => t('Flett'), 'reject' => t('Avvis'),
    'remove' => t('Fjern verdi'), 'correct' => t('Riktig'), 'open' => t('Åpne'), 'selected' => t('valgt'),
    'empty' => t('Ingen varer eller sendinger i arbeid'), 'loading' => t('Henter …'), 'sampled' => t('viser'), 'updated' => t('Oppdatert'),
    'queue' => t('Kø'), 'failed' => t('Handlingen feilet'), 'noaccess' => t('Ingen tilgang'), 'nowebgl' => t('3D er ikke tilgjengelig. Viser tabell.'),
    'sources' => ['sortre' => 'Sør-Tre', 'utledet' => t('Utledet'), 'bruker' => t('Kjøper'), 'ansatt' => t('Ansatt')],
    'versions' => ['verifisert' => t('Verifisert'), 'apen' => t('Åpen')],
    'throughput' => t('Lagerflyt'), 'flow' => ['in' => t('Inn'), 'out' => t('Solgt'), 'net' => t('Netto'), 'coming' => t('På vei inn'), 'today' => t('I dag'), 'next' => t('Neste')], 'log' => t('Logg'),
    'play' => t('Spill av'), 'pause' => t('Pause'), 'replay' => t('Avspilling'),
    // KPI drill-downs (feature 032): the records behind each number
    'dr' => [
        'back' => t('Tilbake'), 'period' => t('Periode'), 'days' => t('{n} dager'), 'loading' => t('Henter …'), 'failed' => t('Kunne ikke hente detaljene'),
        'unknown' => t('Ukjent'), 'noImg' => t('Mangler produktbilde'), 'yes' => t('Ja'), 'no' => t('Nei'),
        'kinds' => [
            'sok' => ['title' => t('Søk'), 'sub' => t('De nøyaktige søkeordene kundene skrev, uten treff først.')],
            'samtaler' => ['title' => t('Spørsmål til Søren'), 'sub' => t('Samtalene slik de ble lagret, med svaret Søren ga.')],
            'byggelab' => ['title' => t('Byggelab'), 'sub' => t('Oppsettene kundene satte sammen, med produktene de valgte.')],
        ],
        'zero' => t('Søk uten treff'), 'withHit' => t('Søk med treff'), 'none' => t('Ingen i perioden'), 'noneZero' => t('Alle søk ga treff i perioden'),
        'searchMeta' => t('{c} økter, sist {t}'), 'hits' => t('{n} treff'), 'reviewed' => t('Vurdert'), 'notReviewed' => t('Ikke vurdert'),
        'task' => t('Oppgave #{id}'), 'mapped' => t('Koblet til produkt'), 'pickSearch' => t('Velg et søkeord for å se detaljene.'),
        'occ' => t('Ganger søkt'), 'customers' => t('Anonyme økter'), 'first' => t('Først søkt'), 'last' => t('Sist søkt'), 'before' => t('Søkt før perioden'),
        'match' => t('Produktkobling'), 'noMatch' => t('Ingen kobling ennå'), 'status' => t('Status'), 'hitsL' => t('Treff'), 'tasks' => t('Oppgaver'),
        'searchCat' => t('Søk i katalogen'), 'seeSetup' => t('Se oppsett'), 'noSetup' => t('Oppsettet i denne økten kan ikke åpnes: produktene er ikke lagret.'),
        'labNotDefect' => t('Dette gjelder oppsettet kunden valgte. Det betyr ikke at produktdataene er feil.'), 'markSeen' => t('Marker vurdert'), 'makeTask' => t('Opprett forbedringsoppgave'), 'ignore' => t('Ignorer'),
        'states' => ['ikke' => t('Ikke vurdert'), 'vurdert' => t('Vurdert'), 'ignorert' => t('Ignorert'), 'oppgave' => t('Oppgave opprettet')],
        'taskState' => t('Oppgave #{id} · {s}'), 'openTask' => t('Åpne oppgave #{id}'),
        'reviewFirst' => t('Se på søket først. Skrivefeil og engangssøk kan ignoreres; en oppgave lages bare når du har funnet en konkret feil eller mangel i katalogen.'),
        'taskWhen' => t('Opprett en oppgave bare hvis du har funnet hva som skal rettes: et produkt, et synonym eller en kategori.'), 'taskTitle' => t('Søk uten treff: «{q}»'),
        'noneChat' => t('Ingen samtaler med Søren i perioden'), 'noQuestion' => t('Ingen spørsmål lagret'), 'soren' => 'Søren', 'customer' => t('Kunde'),
        'turns' => t('{n} spørsmål'), 'escalated' => t('Sendt videre, sak #{id}'), 'notEscalated' => t('Ikke sendt videre'),
        'notEscalatedLong' => t('Ikke sendt videre til et menneske.'), 'read' => t('Lest'), 'markRead' => t('Merk som lest'),
        'pickChat' => t('Velg en samtale for å lese den.'), 'convo' => t('Samtale #{id}'), 'fromTicket' => t('Kopien saken tok vare på'),
        'viewProduct' => t('Vis produktet'), 'noProduct' => t('Ingen produkt knyttet til samtalen'), 'openTicket' => t('Åpne saken'),
        'noMessages' => t('Ingen meldinger er lagret for denne samtalen.'),
        'noneLab' => t('Ingen oppsett i Byggelab i perioden'), 'labMeta' => t('{n} produkter, {t}'), 'pickLab' => t('Velg et oppsett for å se produktene.'),
        'verdict' => ['klar' => t('Passer sammen'), 'mangler' => t('Mangler noe'), 'konflikt' => t('Passer ikke'), 'tom' => t('Tomt')],
        'saved' => t('Lagret'), 'listed' => t('Handleliste'), 'cart' => t('Lagt i kurven'), 'left' => t('Forlatt'),
        'total' => t('Sum'), 'totalNote' => t('ett av hvert, dagens pris'), 'conflicts' => t('Passer ikke'), 'unknownLinks' => t('Ukjente koblinger'),
        'timeline' => t('Tidslinje'), 'timelineV' => t('{a} til {b}, antall sjekker: {n}'), 'missing' => t('Mangler og advarsler'),
    ],
    'arrow' => icon('arrow-right'),
    'answer' => t('Svar'), 'send' => t('Send'), 'close' => t('Lukk sak'), 'sent' => t('Sendt'), 'draft' => t('Utkast fra Søren'), 'drafting' => t('Søren skriver …'), 'drafted' => t('Utkast satt inn. Les og rett før du sender.'), 'messages' => t('meldinger'), 'tstatus' => ['apen' => t('Åpen'), 'pagar' => t('Pågår'), 'lukket' => t('Lukket')],
    'tk' => [
        'title' => t('Saker'), 'none' => t('Ingen åpne saker'), 'noProduct' => t('Uten produkt'), 'guest' => t('Gjest'),
        'chat' => t('Samtalen med Søren'), 'customer' => t('Kunde'), 'staff' => t('Kundeservice'), 'thread' => t('Meldinger'),
    ],
    'ed' => [
        'actions' => t('Behandling'), 'review' => t('Merk som sett'), 'reviewed' => t('Sett av {who}, {at}'), 'staffer' => t('ansatt'),
        'related' => t('Relaterte søk'), 'relatedNone' => t('Ingen andre søk med samme ord.'), 'hits' => t('treff'),
        'mapping' => t('Produktkobling'), 'mappedTo' => t('Søket «{q}» viser først:'), 'mapNone' => t('Søket «{q}» er ikke koblet til et produkt.'),
        'mapFind' => t('Finn produkt'), 'map' => t('Koble'), 'mapOnly' => t('Bare katalogansatte kan koble søk til produkter.'),
        'task' => t('Forbedringsoppgave'), 'taskTitle' => t('Hva skal forbedres?'), 'taskProduct' => t('Produkt (valgfritt)'),
        'taskMake' => t('Opprett oppgave'), 'taskDefault' => t('Søk uten treff: «{q}»'), 'tasks' => t('Oppgaver'), 'taskClose' => t('Lukk'),
        'taskNone' => t('Ingen åpne oppgaver'), 'noTerm' => t('Hendelsen har ikke et søkeord.'),
    ],
    'fl' => [
        'hint' => t('Slipp på {kvalitet} for en forbedringsoppgave, på {sok} for produktkobling, eller på gulvet for å merke som sett.'),
        'askTask' => t('Opprette forbedringsoppgave for «{q}»?'), 'askSeen' => t('Merke hendelse #{id} som sett?'),
        'confirm' => t('Bekreft'), 'cancel' => t('Avbryt'), 'taskMade' => t('Oppgave #{id} opprettet'), 'seen' => t('Merket som sett'),
    ],
    'assess' => t('Vurdering fra Søren'), 'assessNote' => t('Bare en vurdering. Ingenting er endret.'),
    'combined' => t('Mangler samlet'),
    'tr' => [
        'cond' => [
            'title' => t('Forhold på ruten'), 'none' => t('Ingen kjente hindringer på ruten.'), 'delay' => t('Mulig forsinkelse ca. {n} min (anslag)'),
            'credit' => t('Værdata fra MET Norway'), 'demo' => t('Simulert (demo)'), 'source' => t('Kilde'), 'now' => t('Været nå'), 'range' => t('{a}° til {b}°'),
            'kinds' => ['weather' => t('Vær'), 'warning' => t('Farevarsel'), 'roadwork' => t('Vegarbeid'), 'closure' => t('Stengt veg'), 'ferry' => t('Ferje'), 'rail' => t('Jernbaneovergang'), 'traffic' => t('Trafikk')],
            'sev' => ['info' => t('Info'), 'slow' => t('Forsinker'), 'stop' => t('Stopper')],
            'ferry' => ['title' => t('Ferjer på ruten'), 'next' => t('Neste avganger'), 'from' => t('fra'), 'live' => t('sanntid'), 'planned' => t('rutetid'),
                'cancelled' => t('innstilt'), 'msg' => t('Melding fra ferjeselskapet'), 'vessels' => t('Ferjer med posisjon'), 'age' => t('nyeste for {n} min siden'), 'pos' => t('posisjon for {n} min siden'),
                'old' => t('gammel posisjon'), 'checked' => t('Sjekket {t}'), 'credit' => t('Data fra Entur'), 'none' => t('Ingen posisjon fra ferjene'),
                'status' => ['cancelled' => t('Avgang innstilt'), 'disrupted' => t('Melding om avvik'), 'delayed' => t('Forsinket {n} min'),
                    'normal' => t('Går etter planen (sanntid)'), 'scheduled' => t('Bare rutetider, ingen sanntid'), 'unknown' => t('Status ukjent')]],
            'sky' => ['sol' => t('Sol'), 'sky' => t('Skyet'), 'regn' => t('Regn'), 'sno' => t('Snø'), 'storm' => t('Storm'), 'natt' => t('Klar natt')],
            'icons' => array_map('icon', ['sol' => 'sun', 'natt' => 'moon', 'sky' => 'cloud', 'regn' => 'cloud-rain', 'sno' => 'cloud-snow', 'storm' => 'cloud-lightning',
                'warning' => 'triangle-alert', 'roadwork' => 'construction', 'closure' => 'ban', 'ferry' => 'ship', 'rail' => 'train-front', 'traffic' => 'users', 'wind' => 'wind']),
        ],
        'from' => t('Fra'), 'to' => t('Til'), 'me' => t('Du er her'), 'demoTag' => t('(demo)'),
        'meNone' => t('Nettleseren har ikke posisjon.'), 'meDenied' => t('Fikk ikke tilgang til posisjonen.'),
        'dirs' => [t('N'), t('NØ'), t('Ø'), t('SØ'), t('S'), t('SV'), t('V'), t('NV')],
        'demo' => 'DEMO DATA', 'shipments' => t('Sendinger'), 'find' => t('Søk på referanse, bil eller by'), 'noHit' => t('Ingen sendinger passer søket.'),
        'progressEst' => t('Estimert fremdrift'), 'demoGo' => t('Demolevering til meg'),
        // The simulated delivery (feature 032): one clock for movement, weather, ferries, the delay and the notifications
        'sim' => [
            'clock' => t('Simulert tid'), 'play' => t('Spill av'), 'pause' => t('Pause'), 'speed' => t('Fart'), 'replay' => t('Spill av fra start'), 'route' => t('Vis ruten'),
            'scenario' => t('Forhold'), 'scen' => ['ekte' => t('Ekte forhold'), 'storm' => t('Simulert storm')],
            'srcReal' => t('Vær fra MET Norway, veimeldinger fra Statens vegvesen og ferjeruter fra Entur, hentet av conditions.php. Lager, biler og fart er demo.'),
            'srcStorm' => t('Simulert storm på Vestlandet. Veimeldinger og ferjeruter er fortsatt ekte, været ved kysten er simulert.'),
            'source' => t('Kilde'), 'free' => t('ledig'), 'base' => t('Base {city}'),
            'phase' => ['vei' => t('Kjører fra {from} mot {to} på {road}'), 'venter' => t('Venter på ferjen ved {from}'), 'ferje' => t('Om bord på ferjen, {from} til {to}'),
                'laster' => t('Laster ved {from}'), 'start' => t('Klar til avgang fra {from}')],
            'planned' => t('Opprinnelig ankomst'), 'eta' => t('Ny ankomst'), 'etaOk' => t('Ankomst'), 'delay' => t('Forsinket {n} min'),
            'why' => ['works' => t('Vegarbeid på {road} ved {place}, {min} min'), 'storm' => t('Storm ved {place}, {min} min'), 'cancel' => t('Ferjeavgang innstilt ved {place}, {min} min')],
            'sky' => ['sol' => t('Klart'), 'natt' => t('Klar natt'), 'sky' => t('Skyet'), 'regn' => t('Regn'), 'sno' => t('Snø'), 'storm' => t('Storm')],
            'wind' => t('vind {n} m/s'), 'notes' => t('Varsler'), 'noNotes' => t('Ingen varsler ennå'),
            'note' => [
                'avgang' => t('Har kjørt fra {from}.'), 'laster' => t('Laster varer ved {place}.'), 'lastet' => t('Ferdig lastet ved {place}, kjører videre.'),
                'mot_ferje' => t('Nærmer seg ferjen {ferry}.'), 'ombord' => t('Om bord på ferjen {ferry}.'), 'i_land' => t('I land ved {place}, kjører videre.'),
                'regn' => t('Regn ved {place}, vind {wind} m/s.'), 'sno' => t('Snø ved {place}, vind {wind} m/s.'), 'storm' => t('Storm ved {place}, vind {wind} m/s.'),
                'opphold' => t('Opphold ved {place}, vind {wind} m/s.'), 'klarner' => t('Været klarner ved {place}.'),
                'innstilt' => t('Ferjeavgangen {ferry} er innstilt i stormen. Venter på neste.'), 'innstilt_entur' => t('Entur melder at avgangen {ferry} er innstilt. Venter på neste.'),
                'ferjemelding' => t('Melding om {ferry}: {text}'), 'veiarbeid' => t('Vegarbeid på {road} mellom {from} og {to}: {text}'),
                'veiarbeid_n' => t('{n} veimeldinger på {road} mellom {from} og {to}, blant annet: {text}'), 'stengt' => t('Stengt på {road} mellom {from} og {to}: {text}'),
                'farevarsel' => t('Farevarsel på strekningen {from} til {to}: {text}'), 'stormvarsel' => t('Stormvarsel ved {place}, vind {wind} m/s.'),
                'forsinket' => t('Forsinket {min} min. Ny ankomst er beregnet.'), 'naer' => t('Nærmer seg {place}.'), 'levert' => t('Levert i {place}.'),
            ],
            'demo' => t('Simulert'), 'ferry' => t('Ferjestrekning'),
            'batch' => t('Bestill alt ({n})'), 'batchTitle' => t('Bestill alt på listen'),
            'batchLead' => t('{n} varer venter. Hver vare går til den ledige bilen med nærmeste butikk innen {km} km som har den. Fjern haken for det som ikke skal med.'),
            'orderBatch' => t('Bestill {n} varer med {v} biler'), 'orderOne' => t('Bestill {n} varer med {v}'), 'units' => t('stk'),
            'from' => t('{store} har {have} · henter {take} av {want} stk'), 'short' => t('for få på lager'), 'uncovered' => t('{n} varer ingen ledig bil når'),
            'onlyAt' => t('Finnes i {list}'), 'nowhere' => t('Ingen butikk har varen på lager'),
            'reach' => t('Når butikker innen {km} km: {n}'), 'canFetch' => t('Kan hente fra bestillingslisten'), 'outOfReach' => t('{n} varer utenfor rekkevidde'),
            'noPicks' => t('Bestillingslisten er tom. Legg til varer fra Innsikt, Mangler i butikken.'), 'noneInReach' => t('Ingen butikk i nærheten har varene på listen.'),
            'missHere' => t('Har varer ingen ledig bil når'), 'inReach' => t('Innen rekkevidde'),
            'ordered' => t('{n} sendinger bestilt. Klokken går.'), 'skipped' => t('{n} varer kunne ikke bestilles.'), 'orderFail' => t('Bestillingen feilet.'),
            'tourTitle' => t('Demotur uten last'), 'tourText' => t('Kjør bilen til et punkt du klikker på kartet, med samme vær, ferjer og veimeldinger som en ekte tur.'),
            'tourGo' => t('Velg mål på kartet'), 'tourCancel' => t('Avbryt'), 'tourPick' => t('Klikk i kartet der turen skal gå.'), 'tourStarted' => t('Turen er i gang.'),
            'backBase' => t('Kjør tilbake til basen i {city}'), 'simHq' => t('simulert avdeling, demo'), 'detour' => t('Kjører om, {road} er stengt'), 'detourAny' => t('Kjører om en stengt vei'),
        ], 'demoStop' => t('Avslutt demo'), 'demoAsk' => t('Henter posisjonen din'), 'demoNoPos' => t('Fikk ikke posisjonen din. Klikk i kartet der leveringen skal komme.'), 'demoNote' => t('Simulert levering i hurtigtid. Posisjonen din avrundes til omtrent 100 m og lagres bare som demodata.'), 'demoLeft' => t('Ankomst om'), 'demoDone' => t('Levert'), 'demoYou' => t('Mottaker'), 'demoFail' => t('Demoleveringen kunne ikke startes.'), 'available' => t('Ledig last'), 'stale' => t('Gammel posisjon'), 'fresh' => t('Fersk'), 'noPos' => t('Ingen posisjon'),
        'estimate' => t('Estimert rute, ikke veien'), 'progress' => t('Fremdrift'), 'timeline' => t('Tider'), 'tz' => 'Europe/Oslo',
        'plannedDep' => t('Planlagt avgang'), 'actualDep' => t('Faktisk avgang'), 'lastPos' => t('Siste posisjon'), 'eta' => t('Beregnet ankomst'),
        'plannedArr' => t('Planlagt ankomst'), 'actualArr' => t('Faktisk ankomst'), 'variance' => t('Avvik'), 'min' => 'min', 'source' => t('Kilde'),
        'calculated' => t('beregnet'), 'delay' => t('Årsak'), 'vehicle' => t('Kjøretøy'), 'driver' => t('Sjåfør'), 'reg' => t('Reg.nr.'), 'phone' => t('Telefon'),
        'payload' => t('Nyttelast'), 'capacity' => t('Totalvekt'), 'cargo' => t('Last'), 'container' => t('Container'), 'qty' => t('Antall'),
        'weight' => t('Vekt'), 'declared' => t('Deklarert'), 'measured' => t('Målt'), 'itemsSum' => t('Sum varer'), 'remaining' => t('Ledig nyttelast'),
        'mismatch' => t('Vekter stemmer ikke'), 'history' => t('Historikk'), 'fitAll' => t('Alle'), 'fitRoute' => t('Rute'), 'fitNo' => t('Norge'),
        'none' => t('Ingen sendinger registrert'), 'inbound' => t('På vei inn'), 'tickets' => t('Saker'),
        'status' => ['ikke_startet' => t('Ikke startet'), 'lastes' => t('Lastes'), 'underveis' => t('Underveis'), 'stoppet' => t('Stoppet'), 'losses' => t('Losses'),
            'levert' => t('Levert'), 'forsinket' => t('Forsinket'), 'kansellert' => t('Kansellert'), 'ukjent' => t('Ukjent')],
        'types' => ['lastebil' => t('Lastebil'), 'trekkvogn' => t('Trekkvogn'), 'varebil' => t('Varebil'), 'tilhenger' => t('Tilhenger'), 'annet' => t('Annet')],
    ],
];
$types = tower_floor()->knownTypes();
$cfg = ['csrf' => csrf(), 'lang' => $en ? 'en' : 'no', 'role' => $u['role'] ?? '', 'routes' => Floor::ROUTES, 'stations' => ['mottak', 'kontroll', 'kvalitet', 'kunnskap', 'kobling', 'klar'],
    'types' => $types, 'L' => $labels];
?><!doctype html>
<html lang="<?= $en ? 'en' : 'nb' ?>" data-theme="<?= $dark ? 'dark' : 'light' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="<?= $dark ? 'dark' : 'light' ?>">
<title><?= h(t('Kontrolltårn')) ?> | Sørpedia</title>
<link rel="icon" type="image/png" href="/assets/brand/sorpedia-badge.png">
<link rel="stylesheet" href="/assets/tower.css?v=<?= $v('tower.css') ?>">
<link rel="stylesheet" href="/assets/tour.css?v=<?= $v('tour.css') ?>">
<script type="importmap"><?= json_encode(['imports' => ['three' => '/assets/vendor/three.module.min.js'] + array_combine(
    ['tower-lib', 'tower-insight', 'tower-desk', 'tower-soren', 'tower-overview', 'tower-sync', 'tower-drill'],
    array_map(fn($m) => "/assets/$m.js?v=" . $v("$m.js"), ['tower-lib', 'tower-insight', 'tower-desk', 'tower-soren', 'tower-overview', 'tower-sync', 'tower-drill']))], JSON_UNESCAPED_SLASHES) ?></script>
<script type="module" src="/assets/tower.js?v=<?= $v('tower.js') ?>"></script>
<script src="/assets/autodemo.js?v=<?= $v('autodemo.js') ?>" defer></script>
</head>
<body class="tw">
<script type="application/json" id="tw-cfg"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<header class="tw-bar">
  <a class="tw-back" href="/" aria-label="<?= h(t('Tilbake til butikken')) ?>"><?= icon('arrow-left') ?></a>
  <h1><?= h(t('Kontrolltårn')) ?></h1>
  <nav class="tw-nav" aria-label="<?= h(t('Visning')) ?>">
    <?php foreach (['oversikt' => ['gauge', t('Oversikt')], 'gulv' => ['warehouse', t('Gulv')], 'transport' => ['truck', t('Transport')], 'kundeservice' => ['headset', t('Kundeservice')], 'innsikt' => ['chart-column', t('Innsikt')]] as $k => [$ic, $l]): ?>
      <label><input type="radio" name="view" value="<?= $k ?>"<?= $k === 'oversikt' ? ' checked' : '' ?>><span><?= icon($ic) ?><?= h($l) ?><b class="tw-badge" data-badge="<?= $k ?>" hidden></b></span></label>
    <?php endforeach ?>
  </nav>
  <button type="button" class="tw-soren-btn" id="tw-tour-btn"><?= icon('circle-help') ?><span><?= h(t('Omvisning')) ?></span></button>
  <button type="button" class="tw-soren-btn" id="tw-soren-btn" aria-expanded="false" aria-controls="tw-soren"><?= icon('message-circle') ?><?= h(t('Spør Søren')) ?></button>
  <output class="tw-count" id="tw-count" aria-live="polite"></output>
  <span class="tw-live" id="tw-live"><i aria-hidden="true"></i><span></span></span>
</header>

<main class="tw-grid" hidden>
  <section class="tw-floor" aria-label="<?= h(t('Gulv')) ?>">
    <div class="tw-canvas" id="tw-canvas"></div>
    <div class="tw-labels" id="tw-labels" aria-hidden="true"></div>
    <div class="tw-drop" id="tw-drop" role="status" aria-live="polite" hidden></div>
    <div class="tw-sync" id="tw-sync" role="status" aria-live="polite" hidden></div>
    <div class="tw-legend" id="tw-legend" hidden></div>
    <div class="tw-tools" role="toolbar" aria-label="<?= h(t('Visning')) ?>">
      <button type="button" class="tw-btn" id="tw-legend-btn" aria-expanded="false" aria-controls="tw-legend"><?= icon('circle-help') ?><span><?= h(t('Forklaring')) ?></span></button>
      <span class="tw-hint"><?= h(t('Dra for å snu, rull for å zoome')) ?></span>
      <button type="button" class="tw-btn" data-cam="out" aria-label="<?= h(t('Zoom ut')) ?>"><?= icon('minus') ?></button>
      <button type="button" class="tw-btn" data-cam="in" aria-label="<?= h(t('Zoom inn')) ?>"><?= icon('plus') ?></button>
      <button type="button" class="tw-btn" data-cam="reset"><?= icon('scan') ?><span><?= h(t('Tilbakestill visning')) ?></span></button>
    </div>
    <p class="tw-idle" id="tw-idle" hidden></p>
    <div class="tw-table" id="tw-table" hidden></div>
    <div class="tw-sr" id="tw-counts" aria-live="polite"></div>
    <div class="tw-map" id="tw-map" hidden>
      <div class="tw-map-bar"><span class="tw-demo-big">DEMO DATA</span>
        <button type="button" class="tw-btn" id="tw-me" aria-pressed="false"><?= icon('locate') ?><?= h(t('Min posisjon')) ?></button>
        <span class="tw-map-fit"><button type="button" class="tw-btn" id="tw-follow" aria-pressed="false" hidden><?= h(t('Følg kjøretøy')) ?></button><button type="button" class="tw-btn" data-fit="all"><?= h(t('Tilbakestill visning')) ?></button></span>
      </div>
      <p class="tw-demo-msg" id="tw-demo-msg" role="status" hidden></p>
      <div class="tw-wx" aria-hidden="true"><i class="sun"></i><i class="fall"></i><i class="fall far"></i><i class="flash"></i></div>
      <p class="tw-wx-badge" id="tw-wx-badge" hidden></p>
      <svg id="tw-svg" role="img" aria-label="<?= h(t('Kart over Norge med sendinger')) ?>"></svg>
      <div class="tw-toasts" id="tw-toasts" aria-live="polite"></div>
      <section class="tw-sim" id="tw-sim" aria-label="<?= h(t('Simulert levering')) ?>" hidden></section>
    </div>
  </section>

  <aside class="tw-inspect" id="tw-inspect" aria-live="polite" aria-label="<?= h(t('Inspektør')) ?>" hidden></aside>

  <aside class="tw-ops" id="tw-ops" aria-label="<?= h(t('Varemottak og forbedringsoppgaver')) ?>">
    <details open data-part="inn"><summary><h2><?= h(t('Varemottak')) ?></h2></summary></details>
    <details open data-part="best"><summary><h2><?= h(t('Bestillingsliste')) ?></h2></summary></details>
    <details open data-part="opp"><summary><h2><?= h(t('Forbedringsoppgaver')) ?></h2></summary></details>
  </aside>

  <section class="tw-flow" aria-label="<?= h($labels['throughput']) ?>">
    <h2><?= h($labels['throughput']) ?> <small class="tw-muted"><?= h(t('14 dager')) ?></small></h2>
    <p class="tw-flow-leg" id="tw-flow-leg"></p>
    <div id="tw-chart" class="tw-chart"></div>
  </section>
</main>
<main class="tw-page ov" id="tw-ov" aria-label="<?= h(t('Oversikt')) ?>"></main>
<aside class="tw-drill" id="tw-drill" aria-label="<?= h(t('Detaljer bak tallet')) ?>" hidden></aside>
<main class="tw-page" id="tw-cs" aria-label="<?= h(t('Kundeservice')) ?>" hidden></main>
<main class="tw-page" id="tw-ins" aria-label="<?= h(t('Innsikt')) ?>" hidden></main>
<div class="tw-sweep" id="tw-sweep" aria-hidden="true"></div>
<aside class="tw-soren" id="tw-soren" aria-label="<?= h(t('Spør Søren')) ?>" hidden></aside>
</body>
</html>
