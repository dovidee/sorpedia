# Architecture diagrams

Seven Mermaid flowcharts of how Sørpedia works today. Every arrow was checked against the source. Dotted arrows are fallbacks. Boxes marked **Not implemented** describe something the code does not do yet; nothing else in these diagrams is planned work.

Component names are the same in every diagram: **Browser**, **PHP app** (`public/index.php` and `src/`), **MariaDB**, **Ollama**, **Rule-based answer**.

Update a diagram in the same change that changes the code it shows.

## 1. System architecture

```mermaid
flowchart LR
    B["Browser<br/>pages from templates/<br/>public/assets/app.js"]

    subgraph APP["PHP app: public/index.php + src/"]
        R["Router<br/>route(), check_csrf()"]
        RULES["Rule engine<br/>Catalog, Search, Guide,<br/>CatalogIntent"]
        CHAT["Chat<br/>Chat, ChatHistory,<br/>PageContext, ProductRef"]
        SSE["Live updates<br/>GET /api/stream"]
    end

    DB[("MariaDB<br/>products, spec_values, stock,<br/>chat_messages, events")]
    OL["Ollama<br/>deepseek-r1:8b<br/>OLLAMA_URL"]
    FB["Rule-based answer<br/>Chat::fallback, CatalogIntent"]
    MEDIA["public/media<br/>product images, stored locally"]

    B -->|"pages, forms, fetch"| R
    R --> RULES
    R --> CHAT
    RULES --> DB
    CHAT --> DB
    CHAT -->|"only src/Chat.php calls Ollama"| OL
    CHAT -.->|"Ollama down or too slow"| FB
    FB --> DB
    B -->|"EventSource"| SSE
    SSE -->|"reads events"| DB
    B -->|"static files"| MEDIA

    classDef client fill:#87CEEB,stroke:#333,stroke-width:2px,color:#00008B
    classDef app fill:#90EE90,stroke:#333,stroke-width:2px,color:#006400
    classDef data fill:#E6E6FA,stroke:#333,stroke-width:2px,color:#00008B
    classDef ext fill:#FFD700,stroke:#333,stroke-width:2px,color:#000000
    class B client
    class R,RULES,CHAT,SSE app
    class DB,MEDIA data
    class OL,FB ext
```

The browser talks only to the PHP app; it never reaches MariaDB or Ollama directly. Search, filters, the product guide and "goes well with" are rules over the database and never call the language model (enforced by `.claude/hooks/check_code.py`). Only `src/Chat.php` talks to Ollama, the one external service at runtime. If Ollama is down or too slow, the chat answers from the catalog with rules instead. In Docker (`docker-compose.yml`) the app runs on `php:8.4-apache`, the database on `mariadb:10.11`, and Ollama on the host machine.

Sources: `public/index.php`, `src/Chat.php`, `src/Catalog.php`, `docker-compose.yml`.

## 2. HTTP request lifecycle

```mermaid
flowchart TD
    A["Browser request"] --> W["Apache FallbackResource<br/>or php -S<br/>→ public/index.php"]
    W --> S["Errors::install()<br/>session_start: HttpOnly, SameSite=Lax<br/>*::ensure() schema upgrades<br/>Errors::cleanInput()"]
    S --> H["Security headers<br/>nosniff, X-Frame-Options DENY,<br/>Referrer-Policy same-origin"]
    H --> P{"POST?"}
    P -->|"yes"| C{"check_csrf()<br/>form field or X-CSRF header,<br/>hash_equals"}
    C -->|"invalid"| E400["400"]
    C -->|"valid"| RT
    P -->|"no"| RT{"route(): if chain,<br/>first match wins"}
    RT -->|"no match"| N404["404, templates/notfound.php"]
    RT -->|"match"| AU{"Access check in the handler<br/>current_user(), has_role(),<br/>is_verified()"}
    AU -->|"not allowed"| DEN["redirect /logg-inn<br/>or 403"]
    AU -->|"allowed"| L["Handler<br/>Catalog, Search, Guide, Auth, Chat"]
    L <--> D[("MariaDB<br/>db(): PDO")]
    L --> OUT{"Response"}
    OUT --> HT["render(): templates/*.php<br/>inside templates/layout.php"]
    OUT --> JS["json_out()"]
    OUT --> EV["text/event-stream"]
    OUT --> RD["redirect() + flash()"]

    classDef step fill:#90EE90,stroke:#333,stroke-width:2px,color:#006400
    classDef decision fill:#FFD700,stroke:#333,stroke-width:2px,color:#000000
    classDef stop fill:#FFB6C1,stroke:#DC143C,stroke-width:2px,color:#000000
    classDef data fill:#E6E6FA,stroke:#333,stroke-width:2px,color:#00008B
    class A,W,S,H,L,HT,JS,EV,RD step
    class P,C,RT,AU,OUT decision
    class E400,N404,DEN stop
    class D data
```

Every request goes through one front controller. CSRF is checked once, for every POST, before any route runs. There is no framework or middleware: each route is an `if (route(...))` block, and each handler checks its own access rules. PHP errors are logged with a reference, never shown to the customer.

Sources: `public/index.php` (top of file and every `route(` block), `src/helpers.php` (`check_csrf`, `current_user`, `has_role`, `is_verified`, `render`, `json_out`), `src/Errors.php`.

## 3. Søren chat lifecycle

```mermaid
flowchart TD
    U["Customer types a message"] --> JS["Browser: app.js<br/>POST /api/chat<br/>new message + page address"]
    JS --> CS{"check_csrf()"}
    CS -->|"valid"| IN["ChatHistory::input()<br/>new message only, max 1500 characters<br/>browser history is ignored"]
    IN --> HI[("chat_messages<br/>last 7 messages of this session's<br/>conversation, roles set by the server")]
    HI --> PC["PageContext::resolve()<br/>fixed list of public pages<br/>+ guide summary if it applies"]
    PC --> JOB["ChatJob::start()<br/>answer keeps running<br/>if the customer changes page"]
    JOB --> BL{"Chat::blocked()<br/>self harm, violence,<br/>profanity, override attempt"}
    BL -->|"match"| FX["Fixed reply shown to the customer<br/>the model is not asked"]
    FX --> SV1["ChatHistory::record()<br/>kept for support,<br/>never sent to the model"]
    BL -->|"no match"| FA["Facts from MariaDB<br/>CatalogIntent or Search, Chat::facts()<br/>price and stock as references"]
    FA --> CARDS["Product cards<br/>Catalog::cards(): name, price,<br/>stock straight from the database"]
    FA --> PR["Chat::prompt()<br/>persona + page + facts + rules<br/>earlier messages kept separate"]
    PR --> OL["Ollama<br/>Chat::streamOllama()"]
    OL -->|"stream"| VAL["Each sentence before it is sent<br/>loop check, ProductRef::clean,<br/>ProductRef::resolve, Moderation::censor,<br/>stop after 1200 characters"]
    OL -.->|"down or too slow"| FB["Rule-based answer<br/>CatalogIntent answer<br/>or Chat::fallback"]
    VAL --> OUTB["Browser shows thinking,<br/>answer and product cards"]
    FB --> OUTB
    CARDS --> OUTB
    OUTB --> SV2["ChatHistory::record()<br/>question + answer saved together<br/>with references, not numbers"]

    classDef client fill:#87CEEB,stroke:#333,stroke-width:2px,color:#00008B
    classDef step fill:#90EE90,stroke:#333,stroke-width:2px,color:#006400
    classDef decision fill:#FFD700,stroke:#333,stroke-width:2px,color:#000000
    classDef safety fill:#FFB6C1,stroke:#DC143C,stroke-width:2px,color:#000000
    classDef data fill:#E6E6FA,stroke:#333,stroke-width:2px,color:#00008B
    class U,JS,OUTB client
    class IN,PC,JOB,FA,CARDS,PR,OL,VAL,FB,SV2 step
    class CS,BL decision
    class FX,SV1 safety
    class HI data
```

The browser sends only the new message and the address of the page it is on. The server adds the earlier messages from its own table and decides what Søren may see: only public product pages, never accounts, carts or orders. Messages about self harm, violence, profanity or attempts to override him get a fixed reply without asking the model. The model never gets exact prices or stock counts. It gets references such as `[[pris:298381]]`, and the server fills in the current database value while the answer streams, after removing references to products that were not in the facts. Product cards are built from the database by search, never from the model's text. The question and answer are saved together only once the answer is finished.

**Not implemented:** an amount the model writes in its own words, instead of a reference, is not checked at runtime. `evals/soren.php` flags it in the evaluation.

Sources: `public/index.php` (route `POST /api/chat`), `src/ChatHistory.php`, `src/PageContext.php`, `src/ChatJob.php`, `src/Chat.php` (`blocked`, `facts`, `prompt`, `answer`, `streamOllama`, `fallback`), `src/ProductRef.php`, `src/CatalogIntent.php`, `public/assets/app.js`.

## 4. Product data flow

```mermaid
flowchart LR
    ST["sor-tre.no"] -->|"scripts/scrape.php<br/>development only"| RAW["data/raw/*.json<br/>public/media/products"]
    RAW -->|"scripts/import.php"| PROD[("products, stock,<br/>product_images, relations")]
    RAW -->|"import: Sør-Tre's own data"| V1[("spec_values<br/>verifisert · source sortre")]
    RAW -->|"import: Extract::specs()<br/>rule over product text"| O1[("spec_values<br/>apen · source utledet")]

    BUY["Verified buyer<br/>POST /produkt/{slug}/rediger<br/>Guard + Moderation"] --> REV[("spec_revisions")]
    BUY --> O2[("spec_values<br/>apen · source bruker")]
    STAFF["Staff<br/>POST /ansatt/revisjon/{id}"] -->|"approve: copy Open to Verified"| V2[("spec_values<br/>verifisert · source ansatt")]
    REV --> STAFF

    PROD --> CARDS["Catalog::cards()<br/>name, price, stock<br/>for pages and chat cards"]
    V1 --> SPECS["Catalog::specs()<br/>Verified and Open<br/>side by side, with source"]
    V2 --> SPECS
    O1 --> SPECS
    O2 --> SPECS
    SPECS --> FACTS["Chat::facts()<br/>VERIFISERT and UKONTROLLERT"]

    classDef ext fill:#FFD700,stroke:#333,stroke-width:2px,color:#000000
    classDef data fill:#E6E6FA,stroke:#333,stroke-width:2px,color:#00008B
    classDef person fill:#87CEEB,stroke:#333,stroke-width:2px,color:#00008B
    classDef step fill:#90EE90,stroke:#333,stroke-width:2px,color:#006400
    class ST,RAW ext
    class PROD,V1,V2,O1,O2,REV data
    class BUY,STAFF person
    class CARDS,SPECS,FACTS step
```

The database is the only source for product name, ID, price and stock. Every specification is a row in `spec_values` with a version (`verifisert` or `apen`) and a source: `sortre` from Sør-Tre, `utledet` derived by a rule from the product text, `bruker` from a verified buyer, `ansatt` approved by staff. Derived and buyer values are always in the Open version; only Sør-Tre's data and staff approvals are Verified. A new kind of specification is a new row in `spec_keys`, never a new column. Price and stock on every card come from `Catalog::cards()`.

Sources: `scripts/scrape.php`, `scripts/import.php`, `src/Extract.php`, `public/index.php` (routes `/produkt/{slug}/rediger` and `/ansatt/revisjon/{id}`), `src/Catalog.php`, `db/schema.sql`.

## 5. Authentication and authorization

```mermaid
flowchart TD
    LG["POST /logg-inn"] --> AT{"Auth::attempt()<br/>password_verify, Argon2id hash"}
    AT -->|"wrong 5 times"| LOCK["Locked 15 minutes<br/>same message as a wrong password"]
    AT -->|"wrong"| BACK["redirect /logg-inn"]
    AT -->|"right"| SES["session_regenerate_id()<br/>$_SESSION uid<br/>another account's chat is forgotten"]
    SES --> CU["current_user()<br/>users row, not deleted"]

    REG["POST /registrer<br/>password at least 8 characters"] --> MAIL["Confirmation email<br/>in mail_outbox, demo inbox /innboks"]
    MAIL --> CONF["GET /bekreft/{token}<br/>email_verified_at set"]

    CU --> ROLE{"has_role()"}
    ROLE -->|"ansatt, support, admin"| R1["/ansatt/tarn, /api/tarn/*"]
    ROLE -->|"ansatt, admin"| R2["POST /ansatt/revisjon, /ansatt/rapport"]
    ROLE -->|"support, ansatt, admin"| R3["POST /support/{id}/lukk, ticket channels"]
    CU --> VER{"is_verified()<br/>confirmed email AND<br/>a paid order"}
    VER -->|"yes"| R4["Edit the Open version<br/>write a review"]
    CU -->|"logged in"| R5["/kasse, /ordre/{id},<br/>/innstillinger/*"]
    OUTL["POST /logg-ut"] --> FG["uid removed, chat forgotten,<br/>session_regenerate_id()"]

    classDef step fill:#90EE90,stroke:#333,stroke-width:2px,color:#006400
    classDef decision fill:#FFD700,stroke:#333,stroke-width:2px,color:#000000
    classDef stop fill:#FFB6C1,stroke:#DC143C,stroke-width:2px,color:#000000
    classDef route fill:#87CEEB,stroke:#333,stroke-width:2px,color:#00008B
    class LG,SES,CU,REG,MAIL,CONF,OUTL,FG step
    class AT,ROLE,VER decision
    class LOCK,BACK stop
    class R1,R2,R3,R4,R5 route
```

Login is a session cookie, renewed with `session_regenerate_id()` at login, logout, password change and account deletion. The four roles are `bruker`, `ansatt`, `support` and `admin`, stored on the user row. "Verified" is not a role: it is computed on each request as a confirmed email plus at least one paid order. The Control Tower redirects to login when access is missing; it is the only staff page. The staff POST actions `/ansatt/revisjon/{id}` and `/ansatt/rapport/{id}` answer 403; closing a support case without the role does nothing and redirects. The demo users are listed in the README.

Sources: `src/Auth.php`, `src/helpers.php` (`current_user`, `has_role`, `is_verified`), `public/index.php` (routes `/logg-inn`, `/logg-ut`, `/registrer`, `/bekreft/{token}`, `/ansatt/tarn`, `/ansatt/*`, `/support/{id}/lukk`, `settings_user()`).

## 6. Live updates

```mermaid
flowchart LR
    subgraph WRITE["Writers: emit() in src/helpers.php"]
        W1["Buyer edit<br/>product:{id}, staff"]
        W2["Spec report<br/>staff"]
        W3["Staff merge, reject<br/>or remove a value<br/>product:{id}"]
        W4["Checkout<br/>stock"]
        W5["New support case<br/>support"]
        W6["Support message<br/>or case closed<br/>ticket:{id}"]
    end
    W1 --> EV[("events<br/>id, channel, type, payload")]
    W2 --> EV
    W3 --> EV
    W4 --> EV
    W5 --> EV
    W6 --> EV
    EV -->|"every 0.7 s:<br/>id > last id AND channel IN (...)"| API["GET /api/stream?ch=...<br/>checks access per channel<br/>closes after 25 s"]
    API -->|"SSE with id:"| R1["Chat panel, app.js<br/>ticket:{id}"]
    API -->|"SSE with id:"| R2["Control Tower, tower.js<br/>ticket:{id}"]
    NS["Not implemented:<br/>no page listens to<br/>product:{id} or stock"]
    EV -.- NS

    classDef step fill:#90EE90,stroke:#333,stroke-width:2px,color:#006400
    classDef data fill:#E6E6FA,stroke:#333,stroke-width:2px,color:#00008B
    classDef client fill:#87CEEB,stroke:#333,stroke-width:2px,color:#00008B
    classDef gap fill:#FFB6C1,stroke:#DC143C,stroke-width:2px,color:#000000
    class W1,W2,W3,W4,W5,W6,API step
    class EV data
    class R1,R2 client
    class NS gap
```

A change writes a row to `events`. Buyer edits, staff merges and rejects, and checkout do it inside the same transaction as the change. The other writers (spec reports, removing a reported value, support cases and messages) write it right after their own statement, without a transaction. `/api/stream` reads new rows for the requested channels every 0.7 seconds and sends them as Server-Sent Events. It closes after 25 seconds and the browser reconnects with `Last-Event-ID`, so no event is lost. Ticket channels are open to the session that created the case and to support staff; `support` and `staff` only to staff.

**Not implemented:** events on `product:{id}` and `stock` are written, but no page subscribes to them, so product pages and stock do not update live.

Sources: `src/helpers.php` (`emit`), `public/index.php` (route `GET /api/stream` and every `emit(` call), `public/assets/app.js`, `public/assets/tower.js`.

## 7. Security boundaries

```mermaid
flowchart LR
    subgraph IN["Untrusted input"]
        BR["Browser input<br/>forms, query strings,<br/>chat message, page address"]
        BUY["Buyer contributions<br/>Open values, reviews, reports"]
    end

    subgraph GIN["Checks on the way in"]
        G1["check_csrf(),<br/>Errors::cleanInput()"]
        G2["is_verified(), Guard,<br/>Moderation before saving"]
        G3["Chat::blocked()<br/>before the model"]
    end

    subgraph TRUSTED["Trusted server data"]
        DB[("MariaDB<br/>price, stock, Verified specs,<br/>chat roles")]
        PCX["PageContext<br/>fixed list of public pages"]
        G4["Guard::forPrompt, forProse<br/>buyer text quoted as data"]
        PROMPT["Chat::prompt()"]
    end

    OL["Ollama"]

    subgraph OUTU["Untrusted output"]
        GEN["Model output<br/>thinking and answer text"]
    end

    G5["ProductRef::clean and resolve,<br/>Moderation::censor, loop stop"]
    OUT["Browser<br/>h() escaping in templates"]

    BR --> G1 --> DB
    BR -->|"chat message"| G3 --> PROMPT
    BUY --> G2 --> DB
    DB -->|"Open values, reviews"| G4 --> PROMPT
    DB -->|"facts with references"| PROMPT
    PCX --> PROMPT
    PROMPT --> OL --> GEN --> G5 --> OUT
    DB -->|"cards: name, price, stock"| OUT

    classDef bad fill:#FFB6C1,stroke:#DC143C,stroke-width:2px,color:#000000
    classDef gate fill:#FFD700,stroke:#333,stroke-width:2px,color:#000000
    classDef good fill:#90EE90,stroke:#333,stroke-width:2px,color:#006400
    classDef client fill:#87CEEB,stroke:#333,stroke-width:2px,color:#00008B
    class BR,BUY,GEN bad
    class G1,G2,G3,G4,G5 gate
    class DB,PCX,PROMPT,OL good
    class OUT client
```

Three kinds of text are never trusted: what the browser sends, what buyers write, and what the model generates. Browser input passes the CSRF check and is escaped in templates. Buyer text is checked before it is saved, kept in the Open version, and quoted as data when it reaches the prompt. Chat messages pass the fixed safety checks first. Model output is checked sentence by sentence before the customer sees it. Price, stock and product identity on the cards go straight from the database to the browser and never pass through the model. Earlier chat messages come from the server, so the browser cannot forge Søren's side of the conversation.

**Not implemented:** amounts the model writes in its own words are not checked at runtime, and the pre-model checks are regex patterns, so rephrased messages can get through.

Sources: `src/helpers.php`, `src/Guard.php`, `src/Moderation.php`, `src/Chat.php`, `src/ProductRef.php`, `src/PageContext.php`, `src/ChatHistory.php`.
