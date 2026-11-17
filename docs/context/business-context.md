# Forretningskontekst

## Problemdefinisjon (fra DoT)

Kunden får ikke den samme veiledningen og tryggheten på nett som i butikken.

1. Hvordan kan kunder finne riktig produkt og tilbehør når det finnes tusenvis av varer?
2. Hvordan kan kunder stole på produktinformasjon uten å snakke med en ansatt?
3. Hvordan kan nettkunder få samme fagkunnskap som i butikken?
4. Hvordan kan kundenes egen erfaring bli en del av fagkunnskapen?

## Kjerneproblemer som prototypen må vise løst

| Nr | Problem | Kilde | Løsning i prototypen |
|---|---|---|---|
| P1 | Mangler tekniske spesifikasjoner, f.eks. rekkevidde i meter på hodelykt | PK1 | Spesifikasjonstabell med kilde og nivå, åpen versjon som verifiserte kjøpere fyller inn |
| P2 | Ingen anmeldelser | PK2 | Anmeldelser fra verifiserte kjøpere |
| P3 | Vanskelig å finne produkter, uklare kategorier, fant ikke drill | PK1, K2 | Produktguide, autocomplete, skrivefeil-toleranse, søk etter bruksområde, filtre |
| P4 | Ingen bilder av ferdig montert, ingen manualer | K1, K2, PK1, PK2 | Bildegalleri med zoom, kundebilder "ferdig montert", nedlastbar monteringsveiledning |
| P5 | Ingen forslag til ting som passer sammen (hammer og spiker, hodelykt og batteri) | PK1, PK2 | "Passer sammen" med nivå og begrunnelse, i produktside og handlekurv |

Observasjon: Sør-Tre selger ingen bormaskiner på nett (søk på "drill" gir bare drillpumper). Det bekrefter PK1 sin opplevelse og brukes i demoen som eksempel på nullresultat-søk som håndteres med forslag og "spør en ansatt".

## Personas

| Persona | Basert på | Behov |
|---|---|---|
| Arne Hansen, 54, håndverker, Søgne | K1, K2 | Råd fra ansatte med fagkunnskap, kvalitet til rimelig pris, lang vei til butikken |
| Ingrid Lunde, 34, maler, Mandal | PK1, PK2 | Finne riktig produkt og tilbehør raskt på nett, trygg på at butikken er norsk, vil ha anmeldelser |
| Thomas Gregersen, 38, butikksjef | Antakelse | Rask tilgang til produktinfo, færre gjentatte henvendelser |

## Roller i systemet

| Rolle | Kan |
|---|---|
| Gjest | Søke, se produkter, bruke guide og chat |
| Bruker | Handle, ha handlekurv og ordrer |
| Verifisert bruker | Bruker med bekreftet e-post og minst ett betalt kjøp. Kan redigere åpen versjon, laste opp bilder, skrive anmeldelser |
| Ansatt | Flette åpne endringer inn i verifisert versjon, avvise, redigere lager |
| Support | Svare på saker eskalert fra chatten, i sanntid |
| Admin | Alt over, pluss brukere og KPI-dashboard |

## Suksessmål (gevinstrealisering, kap. 5 i rapporten)

| Mål | Måling i prototypen |
|---|---|
| Kunden finner riktig produkt | Andel søk med null treff, tid fra første søk til handlekurv |
| Mer salg av tilbehør | Andel ordrer med minst ett "passer sammen"-produkt |
| Tillit til produktinfo | Antall spesifikasjoner per produkt fordelt på kilde, antall anmeldelser |
| Avlastning av ansatte | Andel chatsamtaler løst uten eskalering |
| Konvertering | Besøk til handlekurv til kjøp (trakt) |

Prototypen logger disse hendelsene i `analytics_events` og viser dem på et admin-dashboard.
