# Risiko og guardrails

| Risiko | Guardrail |
|---|---|
| Villedende spesifikasjoner (ulovlig) | Kilde og nivå på hver verdi. Utledet og brukerdata merkes. Bare ansatt kan flette til Verifisert |
| Trolling og spam i åpen versjon | Bare verifiserte kjøpere kan redigere. Full historikk med tilbakestilling. Ansatt kan låse felt |
| Modellen finner opp pris, lager eller produkter | Modellen får bare produktkandidater fra databasen (ID, navn, pris). Svaret rendres med pris og lager fra databasen, ikke fra modellteksten. Produkt-ID som ikke var i konteksten forkastes |
| Prompt injection via brukertekst i åpen versjon | Brukertekst sendes til modellen innpakket og merket som data, aldri som instruks |
| Resonnementet ser mer pålitelig ut enn det er | DeepSeek-R1 sin "tenking" er generert tekst og er ikke garantert å være den faktiske årsaken til svaret. Panelet merkes "Slik resonnerte maskoten (kan inneholde feil)" |
| Ollama er ikke tilgjengelig under demo | Regelbasert fallback med synlig beslutningsspor (hvilke regler og filtre som ble brukt) |
| Kunden står fast | Knapp "Snakk med et menneske" oppretter sak til Support, live-oppdatert |
| Falske anmeldelser | Bare verifiserte kjøpere av akkurat det produktet kan anmelde det. Demo-anmeldelser er merket "Demo" |
| Feil lagerstatus | Lager per varehus er syntetisk og merket demo |
| Personvern | Syntetiske brukere. Ingen e-post sendes ut, all e-post havner i demo-innboksen |
