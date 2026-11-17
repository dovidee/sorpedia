# Feature backlog

Traceability: customer insight (interviews) → problem (P1 to P5 in business-context) → Lotus blossom idea → feature → acceptance criteria → test.

| No. | Feature | Lotus idea | Solves | Acceptance criteria (short) |
|---|---|---|---|---|
| 001 | Product catalog and import | | foundation | 496 products in 7 themes with local images. Facets derived with source and evidence. Import can be rerun without duplicates |
| 002 | Store picker and stock | (new) | P3 | 12 stores. The chosen store is remembered. Every product shows physical stock, online stock or backorder with a date. Changes show live |
| 003 | Product guide on arrival | 1 Product guide | P3 | 3 to 5 questions. Gives at most three suggestions plus a complete kit with reasons. Can be skipped |
| 004 | Better search | 2 Better search suggestions | P3 | Autocomplete under 200 ms. "hammmer" finds hammer. "henge opp bilde" (hang a picture) finds hangers. Zero results give suggestions and a button to ask staff. Logged |
| 005 | Filtering | 3 Product filtering | P3 | Filters on price, material, size and the theme's technical facets. Derived values are marked |
| 006 | Images, zoom, assembled photos and manuals | 4 Better product images | P4 | Gallery with zoom. Verified buyers can upload a photo of the finished result. Downloadable assembly guide (PDF) with steps, marked demo |
| 007 | Mascot chat with visible reasoning and escalation | 5 Online customer service, 8 AI advisor | P3, P5 | Streams answers. Shows its thinking. Product cards from the DB. "Talk to a human" creates a case that Support sees live. Works without Ollama |
| 008 | Compare products | 6 Compare products | P1 | "Compare" on the product page, search for a second product (up to four). Table with differences highlighted and a source for each value |
| 009 | Two-version product info (Sørpedia) | 7 Better product info | P1 | Verified and Open side by side. Only verified buyers edit. History, merge, reject, roll back. New specification type without migration |
| 010 | Reviews | | P2 | Only verified buyers of the product. Average and count shown in lists and on the product page |
| 011 | Goes well with and rabbit holes | | P5 | Product page and cart show "goes well with" with level and reason. "Others also bought" is kept separate |
| 012 | Users, roles and verification | | P1, P2 | Four roles plus verified. Seeded test users. Email confirmation through a demo inbox. Verified only after a confirmed email AND a paid order |
| 013 | KPI dashboard | | report ch. 5 | Admin sees zero result searches, accessory share, escalation rate and funnel |
| 014 | Privacy and settings | (new) | P2 | Full names are never shown publicly. Contributions show as "Kjøper #a1b2c3" by default, first name only if the user chooses it. Anyone can report errors in the open version. Settings for name, email, phone, password and profile picture (EXIF removed, only visible to the user). Data export as JSON and account deletion |
| 015 | Chat trust and personality | 5 Online customer service, 8 AI advisor | P3, P5 | The server owns the chat history. The browser can't add or change the mascot's messages. A conversation is only reachable from its own session. Every new message is checked before the model. Full spec in `specs/015-chat-trust-and-personality/spec.md` |

Each feature gets its own folder `specs/NNN-name/` with `spec.md` and `verification.md` when it is built.
