# SØRPEDIA: 5-MINUTTERS DEMOMANUS

The script for a five minute walkthrough. Structure and stage directions are in English; the lines to say are in Norwegian, as spoken. Every number said aloud is shown live on the Demo Hub (`/presentasjon`), counted from the database on that request, so read it from the screen rather than from this file.

## Roles and setup

Presenter 1 speaks and owns the timing. Presenter 2 drives the browser and never talks over presenter 1.

Before starting, presenter 2:

1. Starts the stack (`docker compose up`) and checks that Ollama answers (one short question to Søren). The first answer after a cold start loads the model.
2. Logs in as staff in one browser window. Tab A: `/presentasjon`, light view, full screen (F). Tab B: the store front page `/`. Tab C: `/ansatt/tarn`.
3. In tab B, opens `/byggelab/lys` once so the bench and its images are cached.
4. In tab C, Innsikt, adds one product from Mangler i butikken that Bergen has (Legg i bestilling), so Bestill alt has a line for the west coast. Checks that the simulation clock is paused and no trip is on the road.

Søren's answers take more than a minute on the test machine (specs/015 verification: 66 to 107 s). The script therefore asks Søren early and comes back to the answer later. Do not wait in silence for it.

## Timing

| Time | Presenter 1 says | Presenter 2 shows |
|---|---|---|
| 0:00 to 0:40 | «Når du står i butikken hos Sør-Tre, kan du spørre en ansatt om delene passer sammen. På nett må du gjette. Produktsidene mangler ofte målene som avgjør, så kunden ringer, gjetter eller returnerer. Sørpedia gir kunden den samme tryggheten på nett, med Sør-Tres egne produkter.» | Tab A, section 1. Point at the product and value counts at the end of the sentence. |
| 0:40 to 1:20 | «Her er en kunde som skal kjøpe hodelykt. Hver verdi på produktsiden har en kilde: Sør-Tre, en verifisert kjøper, eller utledet fra teksten. Ukjent står som ukjent.» | Tab B. Search «hodelykt», open Ledlenser H7R Core (#291483), scroll to the specifications and the source labels. |
| 1:20 to 2:00 | «I Byggelab legger kunden lykten, laderen og batteriet sammen før hun betaler. Sjekken sier ikke bare ja eller nei. Den sier at det ikke er oppgitt om ladekabelen følger med, og hvorfor.» | Tab B, `/byggelab/lys`. Add the H7R Core, the H5R Core, the charging cable and the battery. Point at the Mangler noe result and its reason. Click «Spør Søren». |
| 2:00 to 2:45 | «Søren svarer ut fra de samme dataene. Han kjører lokalt, og han kan ikke finne på en pris: modellen skriver en referanse, og serveren fyller inn pris og lager fra databasen. Mens han tenker, ser vi hva de ansatte ser.» | Leave the chat streaming. Switch to tab C. |
| 2:45 to 3:30 | «Kontrolltårnet viser hva kundene faktisk gjør. Sjekker som den vi nettopp gjorde havner her, med produktbildet. Et funn blir ikke en oppgave av seg selv: den ansatte vurderer det først, og bare da kan det lages en oppgave. Når en ansatt fletter inn verdien fra en kjøper, blir den verifisert, og oppgaven lukkes. Neste kunde får svaret.» | Tab C, Innsikt: point at the Byggelab opportunity with the H7R Core photo; click Marker vurdert, then Opprett forbedringsoppgave. Switch to Gulv: point at the task in Forbedringsoppgaver and at Produktkontroll. |
| 3:30 to 4:30 | «Det samme gjelder varene. Det som mangler i butikken, bestiller vi her. Systemet velger bilen med nærmeste butikk som har varen. Fra Bergen må bilen ta to ferjer. Vær, vei og ferjer hentes fra MET, Statens vegvesen og Entur, og kilden står ved hver melding. Bilene er en simulering merket som demo, men varene trekkes fra butikken de hentes i og kommer inn i varemottaket ved ankomst.» Then back to Søren: «Og her er svaret fra Søren, med pris og lager fra databasen.» | Tab C, Transport: click Bestill alt, point at the marked vehicles and their counts, click «Bestill N varer med N biler». Click V-02 (Bergen), turn on Følg kjøretøy, choose Simulert storm and x1800. When the ferry and storm notices appear, point at the source line and at the booked and new arrival. Then switch to tab B and show Søren's finished answer. |
| 4:30 to 5:00 | «Sørpedia er bygget på vanlig teknologi Sør-Tre kan drive selv. Gevinsten kan måles i tall de allerede har: hvor mange produkter kjøpere har beriket, salget på dem, og om returene for pakkekjøp går ned. Det som gjenstår før produksjon står ærlig på veikartet. Takk.» | Tab A, section 6 (Veikart) on «gjenstår». |

If Søren has not answered by 4:30, skip the Søren line and say: «Søren kjører på en vanlig bærbar her; i drift trenger han et skjermkort.» That is true and on the roadmap.

## Fallbacks

Ollama down: Søren answers with catalogue products instead. Say so; it is a feature (specs/015). Network down: Transport falls back to rows marked demo for weather and roads; ferries show as unknown, never simulated. Browser crash: tab A alone carries the whole story; use keys 1 to 7.

## Q&A cheat sheet

The Demo Hub's section 7 holds these answers with links to the evidence. Presenter 2 opens the section named in brackets while presenter 1 answers.

«Hvordan holdes dataene i takt?» Importen fra sor-tre.no kan kjøres på nytt uten duplikater, og WooCommerce sender signerte webhooks ved endringer. Live-endringer går via events-tabellen. (Arkitektur)

«Hvordan virker KI-en?» Spørsmålet tolkes først mot katalogen. Kandidatene sendes som data til en lokal DeepSeek-R1. Pris og lager fylles inn av serveren. (Teknikk)

«Hva skjer hvis KI-en går ned?» Søren svarer med produkter fra katalogsøket, og kunden kan alltid be om et menneske. (Funksjoner, Søren)

«Hva hindrer at kjøpere skriver feil?» Bare verifiserte kjøpere med bekreftet e-post og et betalt kjøp kan endre. Endringen havner i den åpne versjonen, ved siden av den verifiserte, og en ansatt fletter eller avviser. (Funksjoner, Produktintelligens)

«Hvordan er løsningen sikret?» Argon2id, låsing etter fem feil, CSRF-vern, roller, revisjonslogg uten IP-adresser, og vern mot instruksjonsangrep i chatten. (Teknikk)

«Hvordan unngår dere tap av data?» Sikkerhetskopi hver natt, de 14 nyeste beholdes. (Teknikk)

«Hva er ekte og hva er demo?» Katalogen og produktdataene er Sør-Tres egne. Lager per butikk, anmeldelser, brukere og leveransen er demodata og merket slik. (Veikart)

«Hvordan tjener dette penger?» Mer komplette produktsider gir tryggere kjøp, og sjekk før betaling gir færre returer. Begge måles i data Sør-Tre allerede har i WooCommerce. Vi har ikke salgstall fra Sør-Tre, så vi lover ingen prosent. (Veikart)

«Hva gjenstår?» Ekte betaling, lager og e-post, test mot Sør-Tres butikk, drift og en GPU for Søren. (Veikart)
