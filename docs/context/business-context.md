# Business context

## Problem statement (from the DoT)

Customers don't get the same guidance and confidence online as in the store.

1. How can customers find the right product and accessories among thousands of items?
2. How can customers trust product information without talking to staff?
3. How can online customers get the same expertise as in the store?
4. How can customers' own experience become part of that expertise?

## Core problems the prototype must show solved

| No. | Problem | Source | Solution in the prototype |
|---|---|---|---|
| P1 | Missing technical specifications, e.g. beam range in meters for a headlamp | PK1 | Specification table with source and level, and an open version that verified buyers fill in |
| P2 | No reviews | PK2 | Reviews from verified buyers |
| P3 | Hard to find products, unclear categories, couldn't find a drill | PK1, K2 | Product guide, autocomplete, typo tolerance, search by use case, filters |
| P4 | No pictures of assembled products, no manuals | K1, K2, PK1, PK2 | Image gallery with zoom, customer photos of the finished result, downloadable assembly guide |
| P5 | No suggestions for things that go together (hammer and nails, headlamp and battery) | PK1, PK2 | "Goes well with" with level and reason, on the product page and in the cart |

Observation: Sør-Tre sells no power drills online (searching "drill" only gives drill pumps). This confirms PK1's experience and is used in the demo as an example of a zero result search handled with suggestions and "ask a staff member".

## Personas

| Persona | Based on | Needs |
|---|---|---|
| Arne Hansen, 54, craftsman, Søgne | K1, K2 | Advice from knowledgeable staff, quality at a fair price, long way to the store |
| Ingrid Lunde, 34, painter, Mandal | PK1, PK2 | Find the right product and accessories quickly online, confident the store is Norwegian, wants reviews |
| Thomas Gregersen, 38, store manager | Assumption | Quick access to product info, fewer repeated inquiries |

## Roles in the system

| Role | Can |
|---|---|
| Guest | Search, view products, use the guide and chat |
| User | Buy, have a cart and orders |
| Verified user | User with a confirmed email and at least one paid purchase. Can edit the open version, upload pictures, write reviews |
| Staff | Merge open changes into the verified version, reject them, edit stock |
| Support | Answer cases escalated from the chat, live |
| Admin | Everything above, plus users and the KPI dashboard |

## Success goals (benefits realization, chapter 5 of the report)

| Goal | Measured in the prototype by |
|---|---|
| Customers find the right product | Share of searches with zero results, time from first search to cart |
| More accessory sales | Share of orders with at least one "goes well with" product |
| Trust in product info | Number of specifications per product by source, number of reviews |
| Less load on staff | Share of chats resolved without escalation |
| Conversion | Visit to cart to purchase (funnel) |

The prototype logs these events in `analytics_events` and shows them on an admin dashboard.
