# 033 Demo Hub

## Goal

An overview page inside Sørpedia. It shows what exists and how the parts connect, with the live store one click away. It never invents a feature or a number.

## Scope

`GET /presentasjon` renders `templates/presentation.php` for the roles ansatt, support and admin; everyone else is redirected to log in. It is not linked from the store or the admin page. The page is Norwegian only: it is staff material, not customer text, so it has no English strings.

Seven sections, reachable by keys 1 to 7, arrow and Page keys, and F for full screen:

1. Overview: problem, users, what is different, and counts from the database on the request (products, categories, verified values, relations, builders, routes, verified features, unit tests).
2. Feature inventory by area from `Presentation::features()`. Each feature has what it does, where it lives in the code, a status (Verifisert only when a `specs/NNN/verification.md` records a run; Implementert; Demodata; Gjenstår), and whether it can be shown live. Filter chips by status.
3. Architecture: an SVG diagram of the parts with clickable, keyboard reachable connections. Each connection names what flows and the mechanism and file (`Presentation::links()`). Below it, one customer journey across the platform with real IDs (products 291483, 291482, 291385, 291485 and analytics row 255).
4. Technical depth in expandable details: database, API, login, AI and retrieval, sync, backup, testing, responsive, operations.
5. Søren: how a question is answered.
6. Roadmap: status counts and what remains before production (`Presentation::REMAINING`).
7. Questions and answers, each linking to the section with the evidence.

The five minute script with timing, roles and a Q&A cheat sheet is `docs/presentation/demo-script.md`.

## Acceptance criteria

Every figure on the page is counted on the request or is the recorded gate result with its date. Every cited file and table exists. Light view by default for a projector; dark on request. No horizontal scroll at 390 px. Anonymous requests get a redirect to login.
