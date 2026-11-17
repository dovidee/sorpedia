# Sørpedia: demo-nettbutikk for Sør-Tre (IS-219 gruppe 4)

Digital produktveileder og kunnskapsdatabase ("Wikipedia for produkter") bygget som en demo av Sør-Tre sin nettbutikk. Kunden skal få samme veiledning og trygghet på nett som i butikken.

## Arbeidsflyt (Context, Spec, Plan, Build, Verify)

1. Les relevante filer i `docs/context/` og `docs/governance/constitution.md`.
2. Les feature-spesifikasjonen i `specs/NNN-*/spec.md` før kode endres.
3. Lag plan før endringer på tvers av flere filer. Implementer en oppgave om gangen.
4. Prøv endringen i nettleseren og sammenhold resultatet med akseptansekriteriene.
5. Skriv kort i `specs/NNN-*/verification.md` hva som ble testet, hva som ble forkastet og kjente svakheter.

## Ufravikelige regler

1. Pris, lagerstatus, produktnavn og produkt-ID kommer alltid fra databasen. Språkmodellen skal aldri finne opp disse.
2. Spesifikasjoner har kilde og nivå: `sortre` (fra Sør-Tre), `utledet` (regel over produkttekst), `bruker` (verifisert kjøper), `ansatt` (godkjent). Ukjent er et gyldig svar. Utledet eller brukerdata vises aldri som bekreftet.
3. "Passer sammen" skal ha nivå: dokumentert, regelutledet eller antatt. Antatt vises aldri som sikkert.
4. Nye spesifikasjonstyper legges til som rader (`spec_keys`), aldri som nye kolonner. Ingen migrering per ny spesifikasjon.
5. Bare verifiserte brukere (bekreftet e-post OG minst ett betalt kjøp) kan redigere den åpne versjonen eller skrive anmeldelser.
6. Kundetekst på norsk bokmål. Ingen tankestreker i UI-tekst.
7. Demo-data (lager per varehus, anmeldelser, monteringsveiledninger, brukere) er tydelig merket som demo.
8. Ingen eksterne kall ved kjøretid unntatt Ollama (`OLLAMA_URL`). Bilder ligger lokalt.

## Sannhetskilder

| Tema | Fil |
|---|---|
| Virksomhet, brukere, problemer, mål | `docs/context/business-context.md` |
| Kategorier, filtre, "passer sammen" | `docs/context/product-taxonomy.md` |
| Risiko og guardrails | `docs/context/risks-and-guardrails.md` |
| Arkitektur og datamodell | `docs/architecture/architecture.md` |
| Prinsipper | `docs/governance/constitution.md` |
| Features og akseptansekriterier | `specs/README.md` |
| Arbeidsprosess (fra prototype-veikart) | `docs/process/arbeidsprosess.md` |

## Kommandoer

```
php scripts/scrape.php          # henter produkter fra sor-tre.no til data/raw/
php scripts/import.php          # bygger databasen fra data/raw + seed
php -S localhost:8080 -t public # utviklingsserver
```
