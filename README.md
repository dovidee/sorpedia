# Sørpedia

Demo web store for Sør-Tre, a Norwegian hardware store.

## Goal

Give customers the same guidance online as in the store. Every product has a verified version from Sør-Tre and an open version that verified buyers can edit. The mascot Søren answers questions with a local language model. Price, stock, product name and ID always come from the database.

The store is in Norwegian, with an English toggle. It runs locally only (ADR-002 in `docs/architecture/architecture.md`).

## Run with Docker Compose

```
ollama pull deepseek-r1:8b      # optional, without it Søren answers with rules
docker compose up -d --build
```

Open http://localhost:8080. The first start builds the database from `data/raw`.

```
docker compose exec app php scripts/import.php   # rebuild the database
docker compose down -v                           # delete the database
```

## Test users

All have the password `admin`. Email goes to the demo inbox at `/innboks`.

| Email | Role |
|---|---|
| `admin@sorpedia.test` | Admin, KPI dashboard at `/admin` |
| `ansatt@sorpedia.test` | Staff, approves edits at `/ansatt` |
| `support@sorpedia.test` | Customer service at `/support` |
| `kunde@sorpedia.test` | Verified buyer, can edit and review |
| `ny@sorpedia.test` | New customer, no confirmed email or purchases |

## Settings

Put overrides in a `.env` file next to `docker-compose.yml`, for example:

```
PORT=8090
OLLAMA_MODEL=deepseek-r1:8b
SOREN_NUM_CTX=6144
```

`SOREN_NUM_CTX` defaults to 8192; lower it if the GPU runs out of memory. Timeouts and the database connection are set in `docker-compose.yml` and `src/bootstrap.php`.

## Run without Docker

Needs PHP 8.4 with `pdo_mysql`, and MariaDB with database and user `sorpedia` (password `sorpedia`).

```
php scripts/import.php
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8080 -t public public/index.php
```

Several workers are needed because the chat and live updates keep a connection open.

## Checks

```
php tests/run.php                                   # unit tests, needs the database
php evals/run.php                                   # golden dataset
php evals/soren.php                                 # chat cases against Ollama
sh scripts/tools.sh                                 # installs PHPStan into tools/
php -d memory_limit=1G tools/phpstan.phar analyse   # static analysis
```

## Docs

Architecture: `docs/architecture/architecture.md`. Diagrams: `docs/architecture/diagrams.md`. Security: `docs/architecture/security.md`. Features and acceptance criteria: `specs/README.md`.
