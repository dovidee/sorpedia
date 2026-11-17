# Sørpedia

Demo av en nettbutikk for Sør-Tre, laget av gruppe 4 i IS-219. Kunden skal få samme veiledning og trygghet på nett som i butikken. Sørpedia er en produktveileder og kunnskapsdatabase, en slags Wikipedia for produkter: hvert produkt har en kontrollert versjon fra Sør-Tre og en åpen versjon som verifiserte kjøpere kan fylle ut. Maskoten Søren svarer på spørsmål med en lokal språkmodell og viser hvordan han tenkte.

Pris, lagerstatus, produktnavn og produkt-ID kommer alltid fra databasen, aldri fra språkmodellen. Hver spesifikasjon har en kilde (Sør-Tre, utledet, kjøper eller ansatt), og «ukjent» er et gyldig svar.

Demoen kjører bare lokalt. Den inneholder Sør-Tre sine produkter, demo-anmeldelser og testbrukere, og skal ikke ligge åpent på nett (se `docs/architecture/architecture.md`, ADR-002).

## 1. Oppsett

Du trenger Docker Desktop. Ollama er valgfritt: uten Ollama svarer Søren med en regelmotor i stedet for språkmodellen.

```
ollama pull deepseek-r1:8b      # valgfritt, cirka 5 GB
docker compose up -d --build
```

Åpne http://localhost:8080. Første oppstart bygger databasen fra `data/raw` og tar et halvt minutt.

Testbrukerne har passordet `admin`, og innloggingssiden lar deg klikke for å fylle dem inn.

| E-post | Rolle |
|---|---|
| `admin@sorpedia.test` | Admin, ser KPI-dashbordet på `/admin` |
| `ansatt@sorpedia.test` | Ansatt, godkjenner bidrag på `/ansatt` |
| `support@sorpedia.test` | Kundeservice, svarer på saker fra Søren på `/support` |
| `kunde@sorpedia.test` | Verifisert kjøper, kan redigere og skrive anmeldelser |
| `ny@sorpedia.test` | Ny kunde uten bekreftet e-post og uten kjøp |

E-post forlater aldri maskinen. Den havner i demo-innboksen på `/innboks`.

Nyttige kommandoer:

```
docker compose exec app php scripts/import.php   # bygg databasen på nytt
docker compose down -v                           # slett databasen helt
```

Bruker du Linux og Ollama ikke svarer fra containeren, start Ollama med `OLLAMA_HOST=0.0.0.0`.

Uten Docker trengs PHP 8.4 med `pdo_mysql` og en MariaDB med databasen og brukeren `sorpedia` (passord `sorpedia`). Da kjøres `php scripts/import.php` og så `PHP_CLI_SERVER_WORKERS=4 php -S localhost:8080 -t public public/index.php`. Flere arbeidere trengs fordi chatten og sanntidsoppdateringene holder en tilkobling åpen.

## 2. Innstillinger

Det finnes ingen konfigurasjonsfil. Alt styres med miljøvariabler som har fornuftige standardverdier. De tre første kan settes i en fil `.env` ved siden av `docker-compose.yml` (den er i `.gitignore`), eller rett på kommandolinjen:

```
OLLAMA_MODEL=gemma3:4b PORT=8090 docker compose up -d
```

| Variabel | Standard | Hva den gjør |
|---|---|---|
| `PORT` | `8080` | Porten på din maskin |
| `OLLAMA_MODEL` | `deepseek-r1:8b` | Språkmodellen Søren bruker. `gemma3:4b` er raskere, men svakere |
| `SOREN_NUM_CTX` | `8192` | Modellens arbeidsminne i tokens. Sett ned til `6144` hvis grafikkortet går tom for minne |
| `SOREN_FIRST_TOKEN_S` | `20` | Sekunder Søren venter på første ord før regelmotoren tar over |
| `SOREN_MAX_ANSWER_S` | `150` | Lengste tid et helt svar får bruke |
| `SOREN_NATIVE_THINK` | tom | `1` eller `0` sier om modellen tenker selv. Tom betyr at det gjettes fra modellnavnet |
| `OLLAMA_URL` | `http://host.docker.internal:11434` | Hvor Ollama kjører |
| `APP_DEBUG` | `0` | `1` viser feilmeldinger på siden. Bare for utvikling |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | `db`, `3306`, `sorpedia`, `sorpedia`, `sorpedia` | Databasetilkoblingen |

De fem siste endres under `environment` i `docker-compose.yml`.

Appen svarer bare på denne maskinen. For å vise den til andre på samme nettverk, fjern `127.0.0.1:` foran porten i `docker-compose.yml`. Gjør det bare på et nettverk du stoler på, siden testbrukerne har passordet `admin`.

## 3. Teknologi

| Del | Valg |
|---|---|
| Server | PHP 8.4 uten rammeverk og uten Composer, på Apache (`php:8.4-apache`) |
| Database | MariaDB 10.11 |
| Nettleser | HTML, CSS og vanilla JavaScript, uten byggesteg. Ikoner fra Lucide ligger inne i koden |
| Sanntid | Server-Sent Events og en hendelsestabell (MariaDB har ikke LISTEN/NOTIFY) |
| Språkmodell | DeepSeek-R1 8B via Ollama på din egen maskin, med regelbasert reserve |
| Kjøring | Docker Compose |

Ingen eksterne kall ved kjøretid unntatt Ollama. Bilder og monteringsveiledninger ligger lokalt i `public/media`.

Mappene: `public/index.php` er frontkontrolleren med alle ruter, `src/` har logikken, `templates/` sidene, `db/schema.sql` databasen, `scripts/` henting og import av produkter, og `docs/` og `specs/` beskriver virksomheten, arkitekturen og kravene.

## 4. Funksjoner

| Funksjon | Hva den gjør |
|---|---|
| Produktkatalog | Cirka 350 produkter fra sor-tre.no i sju temaer, med lokale bilder |
| Varehus og lager | Tolv varehus. Lager per varehus oppdateres live når noen handler |
| Produktguide | Tre til fem spørsmål gir maks tre forslag og en komplett pakke med begrunnelse. Guiden lærer av hva kundene velger |
| Søk | Forslag mens du skriver, retting av skrivefeil («hammmer» finner hammer) og bruksområder («henge opp bilde») |
| Filtre | Pris, materiale, størrelse og tekniske egenskaper per tema. Utledede verdier er merket |
| Sammenligning | Opptil fire produkter i en tabell med forskjellene uthevet og kilde per verdi |
| Søren | Chat med synlig resonnement. Produktkort med pris og lager kommer fra databasen. «Snakk med et menneske» lager en sak som kundeservice ser live |
| To versjoner av produktinfo | Kontrollert og åpen versjon side om side. Verifiserte kjøpere redigerer, ansatte godkjenner eller avviser, og historikken kan tilbakestilles |
| Nye spesifikasjonstyper | Legges til som rader, uten endringer i databasestrukturen |
| Anmeldelser | Bare fra verifiserte kjøpere av produktet |
| Passer sammen | Tilbehør med nivå (dokumentert, regelutledet eller antatt) og begrunnelse. «Andre kjøpte også» holdes adskilt |
| Monteringsveiledninger | Steg for steg, med PDF og «kjøp alt du trenger» |
| Brukere og roller | Kunde, ansatt, kundeservice og admin. Verifisert kjøper betyr bekreftet e-post og minst ett betalt kjøp |
| KPI-dashbord | Søk uten treff, andel tilbehør, eskaleringer og trakt |
| Personvern | Bidrag vises som «Kjøper #a1b2c3» med mindre brukeren velger fornavn. Innsyn som JSON og sletting av konto |
| Språk og utseende | Norsk og engelsk, lys og mørk modus |

## 5. Sikkerhet

Hvert punkt viser til kategorien i OWASP Top 10:2025.

**CSRF.** Alle skjemaer har en engangskode (`csrf_field()`). Alle POST-forespørsler sjekkes på ett sted i `public/index.php` med `check_csrf()`, som sammenligner med `hash_equals()`. Chatten sender koden i headeren `X-CSRF`. Mangler koden, svarer serveren 400. OWASP A01:2025 Broken Access Control.

**Tilgangskontroll.** Sidene for ansatte, kundeservice og admin sjekker rollen med `has_role()` på hver forespørsel. Redigering og anmeldelser krever `is_verified()`. Ordrer og profilbilder hentes alltid sammen med innlogget bruker, og en supportsak bare av økten som opprettet den, så ingen kan se andres ved å endre et tall i adressen. OWASP A01:2025 Broken Access Control.

**SQL-injection.** Forberedte spørringer med PDO uten emulering (`PDO::ATTR_EMULATE_PREPARES => false`). De få spørringene som settes sammen som tekst, bruker bare tall som er gjort om med `(int)` eller verdier gjennom `quote()`. OWASP A05:2025 Injection.

**XSS.** All tekst skrives ut med `h()`. Tekst fra brukere går gjennom `hc()`, som også fjerner stygge ord. Data til JavaScript skrives med `JSON_HEX_TAG`. OWASP A05:2025 Injection.

**Prompt injection.** Søren har vern i flere lag (`src/Guard.php`). En spesifikasjon fra en kjøper skal være en verdi og ikke en ordre: tekst med ord som «ignorer», «system» eller «du er», lenker, kodetegn eller snakk om pris og lager avvises før den lagres. Triks som usynlige tegn, kyrilliske bokstaver som ligner latinske, `ign0re` og s p r e d t e bokstaver slås sammen før sjekken. Før modellen sjekkes alt fra kjøpere på nytt, kuttes og settes i anførselstegn så modellen ser at det er data. Meldinger i chatten som prøver å overstyre Søren, eller som handler om selvskading, vold eller banning, får et fast svar uten at modellen spørres. Søren får bare offentlig produktinfo, bygget på serveren fra en fast liste med sider, og ser aldri passord, konto, handlekurv eller ordrer. Produktkort med navn, pris og lager lages fra databasen, ikke fra teksten modellen skriver. OWASP A05:2025 Injection og OWASP LLM01:2025 Prompt Injection.

**Passord.** Argon2id (`password_hash()` med `PASSWORD_ARGON2ID`) og minst 8 tegn. OWASP A04:2025 Cryptographic Failures.

**Innlogging.** `session_regenerate_id(true)` ved innlogging, utlogging og bytte av passord, mot sesjonsfiksering. Sesjonscookien er `HttpOnly` og `SameSite=Lax`, `Secure` under HTTPS, og serveren godtar bare sesjons-ID-er den selv har laget (`use_strict_mode`). Fem feil passord på rad låser kontoen i 15 minutter (`src/Auth.php`). Ukjent e-post, feil passord og låst konto gir samme melding. OWASP A07:2025 Authentication Failures.

**Videresending.** Etter POST sender `redirect()` 303 See Other (Post/Redirect/Get). Adresser fra skjemaer (`back`) går gjennom `safe_back()`, som bare godtar stier på dette nettstedet, så `//evil.no` ikke kan sende kunden videre. OWASP A01:2025 Broken Access Control.

**Oppsett.** Hver side sender `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` og `Referrer-Policy: same-origin`. Apache og PHP viser ikke versjonsnummer (`ServerTokens Prod`, `ServerSignature Off`, `expose_php = Off` i `docker/Dockerfile`). Appen lytter bare på `127.0.0.1`, og databasen er ikke åpen utenfor Docker. Ingen hemmeligheter trengs: databasepassordet gjelder bare inne i Docker. OWASP A02:2025 Security Misconfiguration.

**Opplasting og personvern.** Profilbilder må være JPEG eller PNG under 3 MB, og posisjon og annen metadata fjernes. De lagres i `data/avatars` utenfor `public/`, holdes utenfor git, og vises bare for brukeren selv. Fullt navn vises aldri offentlig, og måltallene lagres uten bruker-ID.

**Feil.** Advarsler blir unntak, og kunden ser bare en vennlig side med en kort referanse som også står i loggen (`docker compose logs app`). Lister der det skal være tekst (`?q[]=`) gir 400 i stedet for krasj. OWASP A10:2025 Mishandling of Exceptional Conditions.

**Bevisste valg for demoen.** Registreringen sier fra hvis e-posten allerede er i bruk, alle testbrukere har passordet `admin`, og chatten har ingen grense for antall spørsmål. Det gjør demoen enkel å vise frem.
