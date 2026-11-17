# Feature-backlog

Sporbarhet: kundeinnsikt (intervju) → problem (P1 til P5 i business-context) → Lotusblomst-idé → feature → akseptansekriterier → test.

| Nr | Feature | Lotus-idé | Løser | Akseptansekriterier (kort) |
|---|---|---|---|---|
| 001 | Produktkatalog og import | | grunnlag | Cirka 350 produkter i 7 temaer med lokale bilder. Fasetter utledet med kilde og evidens. Import kan kjøres på nytt uten duplikater |
| 002 | Varehusvelger og lager | (ny) | P3 | 12 varehus. Valgt varehus huskes. Hvert produkt viser fysisk lager, nettlager eller bestillingsvare med dato. Endring vises live |
| 003 | Produktguide ved oppstart | 1 Produktguide | P3 | 3 til 5 spørsmål. Gir maks tre forslag pluss komplett pakke med begrunnelse. Kan hoppes over |
| 004 | Bedre søk | 2 Bedre søkeforslag | P3 | Autocomplete under 200 ms. "hammmer" finner hammer. "henge opp bilde" finner Oppheng. Nullresultat gir forslag og knapp til ansatt. Logges |
| 005 | Filtrering | 3 Produktfiltrering | P3 | Filter på pris, materiale, størrelse og temaets tekniske fasetter. Utledede verdier merkes |
| 006 | Bilder, zoom, ferdig montert og manualer | 4 Bedre produktbilder | P4 | Galleri med zoom. Verifiserte kjøpere kan laste opp bilde av ferdig montert. Nedlastbar monteringsveiledning (PDF) med steg, merket demo |
| 007 | Maskot-chat med synlig resonnement og eskalering | 5 Online kundeservice, 8 AI-rådgiver | P3, P5 | Strømmer svar. Viser tenkning. Produktkort fra DB. "Snakk med et menneske" lager sak som Support ser live. Fungerer uten Ollama |
| 008 | Sammenlign produkter | 6 Sammenligne produkter | P1 | "Sammenlign" på produktsiden, søk etter produkt nummer to (opptil fire). Tabell med forskjeller uthevet og kilde per verdi |
| 009 | To-versjons produktinfo (Sørpedia) | 7 Bedre produktinfo | P1 | Verifisert og Åpen side om side. Bare verifiserte kjøpere redigerer. Historikk, flett, avvis, tilbakestill. Ny spesifikasjonstype uten migrering |
| 010 | Anmeldelser | | P2 | Bare verifiserte kjøpere av produktet. Snitt og antall vises i liste og produktside |
| 011 | Passer sammen og kaninhull | | P5 | Produktside og handlekurv viser "passer sammen" med nivå og begrunnelse. "Andre kjøpte også" holdes adskilt |
| 012 | Brukere, roller og verifisering | | P1, P2 | Fire roller pluss verifisert. Seedede testbrukere. E-postbekreftelse via demo-innboks. Verifisert først etter bekreftet e-post OG betalt ordre |
| 013 | KPI-dashboard | | rapport kap. 5 | Admin ser nullresultat-søk, tilbehørsandel, eskaleringsrate og trakt |
| 014 | Personvern og innstillinger | (ny) | P2 | Fullt navn vises aldri offentlig. Bidrag vises som «Kjøper #a1b2c3» som standard, fornavn bare hvis brukeren velger det. Alle kan melde fra om feil i åpen versjon. Innstillinger for navn, e-post, telefon, passord og profilbilde (EXIF fjernes, bare synlig for brukeren). Innsyn som JSON og sletting av konto |

Hver feature får egen mappe `specs/NNN-navn/` med `spec.md` og `verification.md` når den bygges.
