# Sørpedia

Demo web store for Sør-Tre, a Norwegian hardware store, made by group 4 in IS-219. The goal is to give customers the same guidance and confidence online as they get in the store. Sørpedia is a product guide and knowledge base, a kind of Wikipedia for products: every product has a verified version from Sør-Tre and an open version that verified buyers can fill in. The mascot Søren answers questions with a local language model and shows how he reasoned.

Price, stock, product name and product ID always come from the database, never from the language model. Every specification has a source (Sør-Tre, derived, buyer or staff), and "unknown" is a valid answer.

The store itself is in Norwegian, with an English toggle in the header.

The demo runs locally only. It contains Sør-Tre's products, demo reviews and test users, and should not be public on the internet (see `docs/architecture/architecture.md`, ADR-002).

## 1. Setup

You need Docker Desktop. Ollama is optional: without it, Søren answers with a rule engine instead of the language model.

```
ollama pull deepseek-r1:8b      # optional, about 5 GB
docker compose up -d --build
```

Open http://localhost:8080. The first start builds the database from `data/raw` and takes about half a minute.

The test users all have the password `admin`, and the login page lets you click one to fill it in.

| Email | Role |
|---|---|
| `admin@sorpedia.test` | Admin, sees the KPI dashboard at `/admin` |
| `ansatt@sorpedia.test` | Staff, approves contributions at `/ansatt` |
| `support@sorpedia.test` | Customer service, answers cases from Søren at `/support` |
| `kunde@sorpedia.test` | Verified buyer, can edit product info and write reviews |
| `ny@sorpedia.test` | New customer without a confirmed email and without purchases |

Email never leaves your machine. It goes to the demo inbox at `/innboks`.

Useful commands:

```
docker compose exec app php scripts/import.php   # rebuild the database
docker compose down -v                           # delete the database completely
```

On Linux, if the container can't reach Ollama, start Ollama with `OLLAMA_HOST=0.0.0.0`.

Without Docker you need PHP 8.4 with `pdo_mysql` and a MariaDB with the database and user `sorpedia` (password `sorpedia`). Then run `php scripts/import.php` followed by `PHP_CLI_SERVER_WORKERS=4 php -S localhost:8080 -t public public/index.php`. Several workers are needed because the chat and the live updates keep a connection open.

## 2. Settings

There is no config file, and nothing needs to be set: the defaults already run the demo with DeepSeek-R1 8B on port 8080. To change something, put the variable in a `.env` file next to `docker-compose.yml` (it is in `.gitignore`), for example `PORT=8090` if port 8080 is taken. The first three variables work this way.

| Variable | Default | What it does |
|---|---|---|
| `PORT` | `8080` | The port on your machine |
| `OLLAMA_MODEL` | `deepseek-r1:8b` | The language model Søren uses |
| `SOREN_NUM_CTX` | `8192` | The model's working memory in tokens. Lower it to `6144` if the GPU runs out of memory |
| `SOREN_FIRST_TOKEN_S` | `20` | Seconds Søren waits for the first word before the rule engine takes over |
| `SOREN_MAX_ANSWER_S` | `150` | Longest time a full answer may take |
| `SOREN_NATIVE_THINK` | empty | `1` or `0` says whether the model reasons on its own. Empty means it is guessed from the model name |
| `OLLAMA_URL` | `http://host.docker.internal:11434` | Where Ollama runs |
| `APP_DEBUG` | `0` | `1` shows error messages on the page. Development only |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | `db`, `3306`, `sorpedia`, `sorpedia`, `sorpedia` | The database connection |

The last five are changed under `environment` in `docker-compose.yml`.

The app only answers on your own machine. To show it to others on the same network, remove `127.0.0.1:` in front of the port in `docker-compose.yml`. Only do that on a network you trust, since the test users have the password `admin`.

## 3. Tech stack

| Part | Choice |
|---|---|
| Server | PHP 8.4 without a framework or Composer, on Apache (`php:8.4-apache`) |
| Database | MariaDB 10.11 |
| Browser | HTML, CSS and vanilla JavaScript, no build step. Lucide icons are inlined |
| Live updates | Server-Sent Events and an event table (MariaDB has no LISTEN/NOTIFY) |
| Language model | DeepSeek-R1 8B via Ollama on your own machine, with a rule-based fallback |
| Runtime | Docker Compose |

No external calls at runtime except Ollama. Images and assembly guides are stored locally in `public/media`.

Folders: `public/index.php` is the front controller with all routes, `src/` holds the logic, `templates/` the pages, `db/schema.sql` the database, `scripts/` scraping and importing products, and `docs/` and `specs/` describe the business, architecture and requirements.

## 4. Features

| Feature | What it does |
|---|---|
| Product catalog | About 350 products from sor-tre.no in seven themes, with local images |
| Stores and stock | Twelve stores. Stock per store updates live when someone buys |
| Product guide | Three to five questions give at most three suggestions and a complete kit with reasons. The guide learns from what customers pick |
| Search | Suggestions as you type, typo correction ("hammmer" finds hammer) and use cases ("henge opp bilde", hang a picture) |
| Filters | Price, material, size and technical properties per theme. Derived values are marked |
| Compare | Up to four products in a table with the differences highlighted and a source for each value |
| Søren | Chat with visible reasoning. Product cards with price and stock come from the database. "Talk to a human" creates a case that customer service sees live |
| Two versions of product info | Verified and open version side by side. Verified buyers edit, staff approve or reject, and the history can be rolled back |
| New specification types | Added as rows, without changing the database structure |
| Reviews | Only from verified buyers of that product |
| Goes well with | Accessories with a level (documented, rule based or assumed) and a reason. "Others also bought" is kept separate |
| Assembly guides | Step by step, with a PDF and "buy everything you need" |
| Users and roles | Customer, staff, customer service and admin. A verified buyer has a confirmed email and at least one paid purchase |
| KPI dashboard | Searches with no results, accessory share, escalations and funnel |
| Privacy | Contributions show as "Kjøper #a1b2c3" unless the user chooses their first name. Data export as JSON and account deletion |
| Language and appearance | Norwegian and English, light and dark mode |

## 5. Security

Each point refers to its category in the OWASP Top 10:2025.

**CSRF.** Every form has a token (`csrf_field()`). All POST requests are checked in one place in `public/index.php` with `check_csrf()`, which compares using `hash_equals()`. The chat sends the token in the `X-CSRF` header. Without a valid token the server answers 400. OWASP A01:2025 Broken Access Control.

**Access control.** The staff, customer service and admin pages check the role with `has_role()` on every request. Editing and reviews require `is_verified()`. Orders and profile pictures are always fetched together with the logged in user, and a support case only by the session that created it, so nobody can see someone else's by changing a number in the URL. OWASP A01:2025 Broken Access Control.

**SQL injection.** Prepared statements with PDO and emulation turned off (`PDO::ATTR_EMULATE_PREPARES => false`). The few queries built as text only use numbers cast with `(int)` or values passed through `quote()`. OWASP A05:2025 Injection.

**XSS.** All output is escaped with `h()`. Text from users goes through `hc()`, which also removes profanity. Data passed to JavaScript is encoded with `JSON_HEX_TAG`. OWASP A05:2025 Injection.

**Prompt injection.** Søren is protected in several layers (`src/Guard.php`). A specification from a buyer must be a value, not an instruction: text with words like "ignore", "system" or "you are", links, code characters or talk about price and stock is rejected before it is saved. Tricks like invisible characters, Cyrillic letters that look Latin, `ign0re` and s p a c e d letters are normalized before the check. Before reaching the model, everything from buyers is checked again, shortened and put in quotes so the model sees it as data. Chat messages that try to override Søren, or that are about self harm, violence or profanity, get a fixed answer without asking the model. Søren only gets public product info, built on the server from a fixed list of pages, and never sees passwords, accounts, carts or orders. Product cards with name, price and stock are built from the database, not from the text the model writes. OWASP A05:2025 Injection and OWASP LLM01:2025 Prompt Injection.

**Passwords.** Argon2id (`password_hash()` with `PASSWORD_ARGON2ID`) and at least 8 characters. OWASP A04:2025 Cryptographic Failures.

**Login.** `session_regenerate_id(true)` on login, logout and password change, against session fixation. The session cookie is `HttpOnly` and `SameSite=Lax`, `Secure` under HTTPS, and the server only accepts session IDs it created itself (`use_strict_mode`). Five wrong passwords in a row lock the account for 15 minutes (`src/Auth.php`). Unknown email, wrong password and locked account all give the same message. OWASP A07:2025 Authentication Failures.

**Redirects.** After a POST, `redirect()` sends 303 See Other (Post/Redirect/Get). Addresses from forms (`back`) go through `safe_back()`, which only accepts paths on this site, so `//evil.no` can't send the customer elsewhere. OWASP A01:2025 Broken Access Control.

**Configuration.** Every page sends `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` and `Referrer-Policy: same-origin`. Apache and PHP hide their version numbers (`ServerTokens Prod`, `ServerSignature Off`, `expose_php = Off` in `docker/Dockerfile`). The app only listens on `127.0.0.1`, and the database is not reachable outside Docker. No secrets are needed: the database password only applies inside Docker. OWASP A02:2025 Security Misconfiguration.

**Uploads and privacy.** Profile pictures must be JPEG or PNG under 3 MB, and location and other metadata are removed. They are stored in `data/avatars` outside `public/`, kept out of git, and only shown to the user themselves. Full names are never shown publicly, and analytics are stored without user IDs.

**Errors.** Warnings become exceptions, and the customer only sees a friendly page with a short reference that also appears in the log (`docker compose logs app`). Arrays where text is expected (`?q[]=`) give 400 instead of a crash. OWASP A10:2025 Mishandling of Exceptional Conditions.

**Deliberate demo choices.** Registration says when an email is already in use, all test users have the password `admin`, and the chat has no limit on the number of questions. This keeps the demo easy to show.
