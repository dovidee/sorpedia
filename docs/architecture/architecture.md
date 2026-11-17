# Architecture

Diagrams of the system, a request, the chat, product data, login, live updates and security boundaries: `docs/architecture/diagrams.md`.

## Decisions (short ADRs)

| No. | Decision | Rationale |
|---|---|---|
| ADR-001 | Custom PHP 8.4 + MariaDB, no framework and no build step | Similar to Sør-Tre (WordPress and WooCommerce on MySQL), easy to understand and explain, no npm build |
| ADR-002 | Local only, with Docker Compose | A public copy with Sør-Tre's products and demo reviews could mislead customers. Open editing and uploads invite abuse |
| ADR-003 | Specifications as rows (EAV), not columns | New specification types require no migration |
| ADR-004 | Live updates with an event table and Server-Sent Events instead of LISTEN/NOTIFY | MariaDB has no LISTEN/NOTIFY. Works on every MySQL variant without extensions |
| ADR-005 | DeepSeek-R1 via Ollama on the host, with a rule-based fallback | Visible reasoning (white box), no data to external AI services, the demo works without a model |
| ADR-006 | Email is written to a table and shown in a demo inbox | No email leaves the machine. No extra service |

## Components

```mermaid
flowchart LR
  B["Browser (HTML, CSS, vanilla JS)"] --> W["PHP app (public/index.php, routes)"]
  W --> DB[("MariaDB")]
  W -->|"/api/stream (SSE)"| B
  W -->|"HTTP /api/chat"| O["Ollama: deepseek-r1:8b"]
  W --> R["Rule engine: facets, goes well with, guide"]
  R --> DB
```

## Data model (main tables)

| Table | Contents |
|---|---|
| `products` | id, sku, name, slug, price in øre, short and long description from Sør-Tre, source link |
| `categories`, `product_categories` | Sør-Tre's category tree |
| `product_images` | image, order, type (sortre, customer photo of the finished result), uploaded by |
| `spec_keys` | key, label, unit, data type, facet (yes/no). New specification type = new row |
| `spec_values` | product, key, value, version (verified or open), source (sortre, utledet, bruker, ansatt), evidence, revision |
| `spec_revisions` | Git-like change sets: who, what (JSON diff), comment, status (active, merged, rejected, rolled back) |
| `warehouses`, `stock` | 12 stores. Per product and store: physical stock, online stock, backorder with expected date |
| `users`, `roles` | user, verified status is computed: email confirmed AND at least one paid order |
| `orders`, `order_items` | Demo checkout (Vipps and Klarna simulated) |
| `reviews` | Only from verified buyers of that exact product |
| `relations` | product A, product B, type (goes well with, alternative), level (documented, rule based, assumed), reason |
| `manuals` | assembly guide per product or product group: steps, images, PDF |
| `chat_conversations`, `chat_messages` | The chat history, owned by the server. Owner is a hash of a random token in the session. Expires 24 hours after the last message, at most 40 messages per conversation |
| `support_tickets`, `support_messages` | Escalation from the chat to Support. The chat log in the case is copied from `chat_messages` |
| `events` | Event log for live updates (id, channel, JSON payload) |
| `mail_outbox` | Outgoing email shown in the demo inbox |
| `analytics_events` | Searches, zero results, guide, cart, purchase, escalation. Used by the KPI dashboard |

## The two-version system

Every specification exists in up to two versions. Verified is Sør-Tre's data plus what staff have approved. Open is what verified buyers have added or changed, like on Wikipedia. The product page shows both side by side, with a source on every value. An edit creates a `spec_revision` and updates the open version immediately. Staff see the change in their queue and can merge it into Verified, reject it or roll it back. Derived values (from product text) sit in Open with the source `utledet` until staff confirm them.

## Live updates without LISTEN/NOTIFY

When something happens (new revision, staff decision, spec report, checkout, support case or message), a row is written to `events`. Buyer edits, staff merges and rejects, and checkout write it in the same transaction as the change; the others write it right after their own statement. The `/api/stream` endpoint keeps a Server-Sent Events connection open for 25 seconds. It queries `events WHERE id > last_id AND channel IN (...)` every 0.7 seconds and sends new rows. The browser's EventSource reconnects automatically and sends `Last-Event-ID`, so no events are lost. The chat panel, `/support` and `/ansatt` listen. Events on `product:{id}` and `stock` are written, but no page listens to them yet. This is a "transactional outbox" pattern. It has the same effect as LISTEN/NOTIFY for a demo, on any MySQL or MariaDB.

Note: it is the EAV model (rows instead of columns) that means new descriptions require no migration. LISTEN/NOTIFY only solves the notification, not the migration problem.

## The mascot and the AI chat

1. The browser sends only the new message and the page address. Earlier messages and their roles come from `chat_messages` (`src/ChatHistory.php`), so the browser cannot add answers in the mascot's name. A question and its answer are saved together after the answer is finished.
2. The customer's message is first interpreted deterministically (keywords, synonyms, use cases), which gives a list of candidate products from the database.
3. The candidates (ID, name, specifications with source, and references such as `[[pris:ID]]` instead of price and stock) and rules are sent to DeepSeek-R1 as data. The server fills in the references from the database while the answer streams (`src/ProductRef.php`).
4. The answer is streamed to the browser. The reasoning is shown in the panel «Sørens resonnement (generert, kan være feil)».
5. Product cards next to the answer come from the database by search (`Catalog::cards`), never from the model's text.
6. "Talk to a human" creates a support case with the log from the server. The Support role sees it live via SSE.
7. If Ollama is unavailable or too slow, a rule-based answer is used instead: the catalog overview's own answer, or what goes well with the first product (`Chat::fallback`), with a note that the language model is not answering.

## Running

| Environment | How |
|---|---|
| Development (cloud server) | `PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:8080 -t public` against a local MariaDB. Several workers are needed because SSE keeps a connection open |
| Demo (own PC) | `docker compose up` (php:8.4-apache and mariadb). Ollama runs on the host. The app reaches it via `http://host.docker.internal:11434` |
