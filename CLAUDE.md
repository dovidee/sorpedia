# Sørpedia: demo web store for Sør-Tre

Digital product guide and knowledge base ("Wikipedia for products") built as a demo of Sør-Tre's web store. Customers should get the same guidance and confidence online as in the store.

## Workflow (Context, Spec, Plan, Build, Verify)

1. Read the relevant files in `docs/context/` and `docs/governance/constitution.md`.
2. Read the feature spec in `specs/NNN-*/spec.md` before changing code.
3. Make a plan before changes across several files. Implement one task at a time.
4. Try the change in the browser and compare the result with the acceptance criteria.
5. Write briefly in `specs/NNN-*/verification.md` what was tested, what was rejected and known weaknesses.
6. Report each change with how it was verified, and keep the levels apart: inspected, implemented, syntax checked, tested, run in the app, not verified. Never report a check that was not run.

## Non-negotiable rules

1. Price, stock status, product name and product ID always come from the database. The language model must never make these up.
2. Specifications have a source and level: `sortre` (from Sør-Tre), `utledet` (rule over product text), `bruker` (verified buyer), `ansatt` (approved by staff). Unknown is a valid answer. Derived or user data is never shown as confirmed.
3. "Goes well with" has a level: documented, rule based or assumed. Assumed is never shown as certain.
4. New specification types are added as rows (`spec_keys`), never as new columns. No migration per new specification.
5. Only verified users (confirmed email AND at least one paid purchase) can edit the open version or write reviews.
6. Customer-facing text is in Norwegian bokmål, with English through the language toggle. No em or en dashes in UI text.
7. Demo data (stock per store, reviews, assembly guides, users) is clearly marked as demo.
8. No external calls at runtime except Ollama (`OLLAMA_URL`). Images are stored locally.

## Sources of truth

| Topic | File |
|---|---|
| Business, users, problems, goals | `docs/context/business-context.md` |
| Categories, filters, "goes well with" | `docs/context/product-taxonomy.md` |
| Risks and guardrails | `docs/context/risks-and-guardrails.md` |
| Architecture and data model | `docs/architecture/architecture.md` |
| Principles | `docs/governance/constitution.md` |
| Features and acceptance criteria | `specs/README.md` |
| Work process | `docs/process/workflow.md` |

## Commands

```
php scripts/scrape.php          # fetches products from sor-tre.no into data/raw/
php scripts/import.php          # builds the database from data/raw + seed data
php scripts/transport-demo.php  # replaces only the demo shipments (Control Tower map)
php -S localhost:8080 -t public # development server
php tests/run.php               # unit tests (needs the database)
php evals/run.php               # golden dataset and the chat's fixed safety replies
php evals/soren.php             # conversation cases against Ollama, answers written out for review
sh scripts/tools.sh             # installs PHPStan into tools/ (not in git)
php -d memory_limit=1G tools/phpstan.phar analyse   # static analysis, level 3
```
