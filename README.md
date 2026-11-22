![sorpedia](images/sorpedia-full.png)
# Sørpedia

Demo web store for Sør-Tre, a Norwegian hardware store.

## Goal

Give customers the same guidance online as in the store. Every product has a verified version from Sør-Tre and an open version that verified buyers can edit. The mascot Søren answers questions with a local language model. Price, stock, product name and ID always come from the database.

The store is in Norwegian, with an English toggle. It runs locally only (ADR-002 in `docs/architecture/architecture.md`).

## Run with Docker Compose

```
ollama pull qwen2.5:7b-instruct # optional, without it Søren answers with rules
docker compose up -d --build
```

Open http://localhost:8080. The first start builds the database from `data/raw`.

```
docker compose exec app php scripts/import.php   # rebuild the database
docker compose exec app php scripts/transport-demo.php   # refresh only the demo shipments on the Control Tower map
docker compose exec app php scripts/conditions.php       # weather and route conditions (runs every 15 minutes in the container)
docker compose exec app php scripts/backup.php           # database backup to data/backups (also at start and every 24 hours)
docker compose down -v                           # delete the database
```

Restore a backup (replaces the current database contents): `gunzip -c data/backups/sorpedia-YYYYMMDD-HHMMSS.sql.gz | docker compose exec -T db mariadb -usorpedia -psorpedia sorpedia`. Backups hold customer data and stay out of git.

## Test users

All have the password `admin`. Email goes to the demo inbox at `/innboks`.

| Email | Role |
|---|---|
| `admin@sorpedia.test` | Admin, Control Tower at `/ansatt/tarn` with turnover and users |
| `ansatt@sorpedia.test` | Staff, approves edits in the Control Tower at `/ansatt/tarn` |
| `support@sorpedia.test` | Customer service, answers tickets in the Control Tower at `/ansatt/tarn` |
| `kunde@sorpedia.test` | Verified buyer, can edit and review |
| `ny@sorpedia.test` | New customer, no confirmed email or purchases |

## Settings

Put overrides in a `.env` file next to `docker-compose.yml`, for example:

```
PORT=8090
OLLAMA_MODEL=qwen2.5:7b-instruct
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

## Public API

REST API for reuse by another shop system, read-only except questions to the product assistant: `/api/v1` (entry point with links), interactive documentation at `/api/docs`, OpenAPI 3.1 at `/api/v1/openapi.json`. Product IDs are the web shop's own IDs.

```
curl -i http://localhost:8080/api/v1/products/291445/specifications
curl -X POST http://localhost:8080/api/v1/products/291445/questions -H 'Content-Type: application/json' -d '{"question":"Hvilke batterier passer?"}'
```

## WooCommerce

A WooCommerce shop keeps checkout, orders and stock and adds the knowledge layer: signed product webhooks to `/api/integrations/woocommerce/webhook` keep the catalogue in step (`WC_WEBHOOK_SECRET`), and the plugin in `integrations/woocommerce/sorpedia-connector` shows specifications with source, related products and the assistant on the shop's product pages. Setup: `docs/integration/woocommerce.md`.

## Docs

Architecture: `docs/architecture/architecture.md`. Diagrams: `docs/architecture/diagrams.md`. Security: `docs/architecture/security.md`. Features and acceptance criteria: `specs/README.md`. API contract: `docs/api/openapi.json` and `specs/024-public-api/spec.md`. WooCommerce: `docs/integration/woocommerce.md` and `specs/025-woocommerce-connector/spec.md`.
