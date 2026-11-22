# 026 Connected journeys and product opportunities

## Goal

Product guide, Byggelab, Søren and Control Tower work on the same product IDs and the same `analytics_events`, so a customer can move from one to the next without starting again, and staff can see which needs the catalogue does not meet yet. Built on 022 (Control Tower) and 023 (Byggelab); nothing there is replaced.

## Scope

1. Guide to Byggelab: the guide result asks "Vil du bare sammenligne disse produktene, eller bygge et komplett oppsett?" and offers the comparison of the suggestions (`/sammenlign?ids=`) and the theme's Byggelab with the suggestions and their "goes well with" products loaded (`/byggelab/{theme}?c=id:1,...`, at most 8). The deterministic ranking is unchanged.
2. Søren to the guide: when an answer has no product cards and no product facts, the reply ends with "Start produktguiden". Not on guide pages, not on fixed safety replies. Category pages always show the category's popular products, so the offer does not appear there.
3. Events, all through `track()` (no user ID, session only for counting):
   `guide_step` (question shown, answers so far), `guide_feedback` also when the customer's own words found no product (`product` null),
   `lab_check` and `lab_test` (builder, product IDs, verdict, number of conflicts and unknown links, up to 6 missing or warning texts),
   `lab_save` and `lab_list` (saved or exported in the browser; builder and product IDs). `lab_cart` is unchanged.
4. Control Tower floor: a Byggelab station receives `lab_check`, `lab_test`, `lab_save` and `lab_list`; `guide_step` goes to Guider.
5. Innsikt, last 30 days (`Insight::journeys`):
   Muligheter: searches without results (by term), the guide's "Nei, jeg lette etter" (by text) and missing components in Byggelab (by text and builder), counted in customers (sessions) and times, most customers first, at most 12. Each row says what to look into and offers "Merk som vurdert" and "Lag oppgave" on its newest event, through the existing `EventDesk` actions.
   Byggelab: configurations (one per session and builder, last checked state), how many were left without saving, export or cart, verdicts, most used products and products most often used together.
   Produktguiden: sessions started and finished, and the question where unfinished sessions stopped.

## Rules

1. An opportunity is a suggestion for review. Nothing creates a product, a specification or a relation; staff act through the existing review, mapping and task actions.
2. Sessions are counted, never shown. No customer text other than the search term or the need the customer typed.
3. Product names come from the database by ID.

## Acceptance

1. A finished guide shows both choices; the Byggelab link opens the builder with the suggested products in it.
2. Every Byggelab check with products stores one `lab_check`; a test run one `lab_test`; save and CSV export one row each.
3. "Nei, jeg lette etter" with text that finds nothing still stores `guide_feedback` with `wanted`, and the text appears under Muligheter.
4. "Merk som vurdert" and "Lag oppgave" on an opportunity change its state after reload; support staff get 403 on the task as before.
5. A chat answer without products offers the guide.
